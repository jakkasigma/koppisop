<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\JadwalKaryawan;
use App\Models\JadwalTukarRequest;
use App\Models\Karyawan;
use App\Models\StaffMessage;
use App\Models\StaffNotification;
use App\Models\StrukSetting;
use App\Services\StaffNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class JadwalController extends Controller
{
    private function ensureTableExistsOrFail(): void
    {
        if (! Schema::hasTable('jadwal_karyawan')) {
            abort(500, 'Tabel jadwal belum ada. Jalankan migrasi jadwal_karyawan terlebih dulu.');
        }
    }

    private function activeKaryawanQuery()
    {
        $query = Karyawan::query();
        if (Schema::hasColumn('karyawan', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query;
    }

    private function karyawanListColumns(): array
    {
        $columns = ['id_karyawan', 'nama_karyawan', 'jabatan'];

        if (Schema::hasColumn('karyawan', 'employment_type')) {
            $columns[] = 'employment_type';
        }

        return $columns;
    }

    private function parseMonth(string $bulan): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', $bulan)->startOfMonth();
        } catch (\Throwable $e) {
            return now()->startOfMonth();
        }
    }

    private function formatPersonLabel(?Karyawan $person): string
    {
        if (! $person) {
            return '-';
        }
        $name = trim((string) ($person->nama_karyawan ?? ''));
        if ($name === '') {
            $name = 'Karyawan #' . (int) ($person->id_karyawan ?? 0);
        }
        $role = trim((string) ($person->jabatan ?? ''));
        return $role !== '' ? ($name . ' (' . $role . ')') : $name;
    }

    private function formatSwapMessage(
        string $title,
        string $fromDate,
        int $fromShift,
        string $toDate,
        int $toShift,
        ?Karyawan $fromPerson = null,
        ?Karyawan $toPerson = null,
        ?string $note = null
    ): string {
        $lines = [
            $title,
            "Dari: {$fromDate} (S{$fromShift})",
            "Ke: {$toDate} (S{$toShift})",
        ];

        $fromLabel = $this->formatPersonLabel($fromPerson);
        $toLabel = $this->formatPersonLabel($toPerson);
        if ($fromLabel !== '-') {
            $lines[] = 'Pemohon: ' . $fromLabel;
        }
        if ($toLabel !== '-') {
            $lines[] = 'Target: ' . $toLabel;
        }
        $note = trim((string) ($note ?? ''));
        if ($note !== '') {
            $lines[] = 'Catatan: ' . $note;
        }

        return implode("\n", $lines);
    }

    public function index(Request $request): View
    {
        $this->ensureTableExistsOrFail();
        $bulan = (string) $request->get('bulan', now()->format('Y-m'));
        $mode = (string) $request->get('mode', 'calendar');
        if (! in_array($mode, ['calendar', 'list'], true)) {
            $mode = 'calendar';
        }
        $start = $this->parseMonth($bulan);
        $end = (clone $start)->endOfMonth();

        $setting = StrukSetting::current();
        $activeShiftCount = (int) ($setting->active_shift_count ?? 2);
        $activeShiftCount = max(1, min(3, $activeShiftCount));

        $karyawan = $this->activeKaryawanQuery()
            ->orderBy('nama_karyawan')
            ->get($this->karyawanListColumns());

        $rows = JadwalKaryawan::query()
            ->with('karyawan')
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->orderBy('tanggal')
            ->orderBy('shift_ke')
            ->orderBy('id_karyawan')
            ->get();

        $byTanggal = $rows
            ->groupBy(fn ($r) => $r->tanggal?->format('Y-m-d') ?? '-')
            ->map(fn ($items) => $items->groupBy('shift_ke'));

        // Rekap per karyawan untuk bulan ini: jadwal vs absen (hanya menghitung hari yang dijadwalkan).
        $rekap = collect();
        if ($rows->isNotEmpty()) {
            $rekap = DB::table('jadwal_karyawan as j')
                ->leftJoin('absensi as a', function ($join): void {
                    $join->on('a.id_karyawan', '=', 'j.id_karyawan');
                    $join->on('a.tanggal', '=', 'j.tanggal');
                })
                ->whereDate('j.tanggal', '>=', $start->toDateString())
                ->whereDate('j.tanggal', '<=', $end->toDateString())
                ->selectRaw('j.id_karyawan as id_karyawan')
                ->selectRaw('count(*) as total_jadwal')
                ->selectRaw('sum(case when a.waktu_masuk is not null then 1 else 0 end) as total_absen')
                ->groupBy('j.id_karyawan')
                ->get()
                ->keyBy(fn ($r) => (int) $r->id_karyawan);
        }

        // Kalender hari dalam bulan.
        $days = [];
        $cursor = (clone $start)->startOfDay();
        while ($cursor->lte($end)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return view('dashboard.jadwal.index', [
            'bulan' => $start->format('Y-m'),
            'bulanLabel' => $start->format('m/Y'),
            'mode' => $mode,
            'start' => $start,
            'end' => $end,
            'activeShiftCount' => $activeShiftCount,
            'setting' => $setting,
            'karyawan' => $karyawan,
            'byTanggal' => $byTanggal,
            'rekap' => $rekap,
            'days' => $days,
        ]);
    }

    public function edit(Request $request, string $tanggal): View
    {
        $this->ensureTableExistsOrFail();
        try {
            $date = Carbon::parse($tanggal)->toDateString();
        } catch (\Throwable $e) {
            abort(404);
        }

        $activeShiftCount = (int) (StrukSetting::current()?->active_shift_count ?? 2);
        $activeShiftCount = max(1, min(3, $activeShiftCount));

        $karyawan = $this->activeKaryawanQuery()
            ->orderBy('nama_karyawan')
            ->get($this->karyawanListColumns());

        $existing = JadwalKaryawan::query()
            ->whereDate('tanggal', $date)
            ->orderBy('shift_ke')
            ->orderBy('id_karyawan')
            ->get()
            ->groupBy('shift_ke')
            ->map(fn ($items) => $items->pluck('id_karyawan')->map(fn ($v) => (int) $v)->values()->all())
            ->all();

        $alreadyAbsen = Absensi::query()
            ->whereDate('tanggal', $date)
            ->whereNotNull('waktu_masuk')
            ->pluck('id_karyawan')
            ->map(fn ($v) => (int) $v)
            ->all();

        return view('dashboard.jadwal.edit', [
            'tanggal' => $date,
            'activeShiftCount' => $activeShiftCount,
            'setting' => StrukSetting::current(),
            'karyawan' => $karyawan,
            'existing' => $existing,
            'alreadyAbsen' => $alreadyAbsen,
        ]);
    }

    public function update(Request $request, string $tanggal): RedirectResponse
    {
        $this->ensureTableExistsOrFail();
        try {
            $date = Carbon::parse($tanggal)->toDateString();
        } catch (\Throwable $e) {
            abort(404);
        }

        $activeShiftCount = (int) (StrukSetting::current()?->active_shift_count ?? 2);
        $activeShiftCount = max(1, min(3, $activeShiftCount));

        $rules = [];
        for ($shift = 1; $shift <= $activeShiftCount; $shift++) {
            $rules["shift_$shift"] = ['nullable', 'array'];
            $rules["shift_$shift.*"] = ['integer', 'exists:karyawan,id_karyawan'];
        }

        $data = $request->validate($rules);

        $perShift = [];
        $all = [];
        for ($shift = 1; $shift <= $activeShiftCount; $shift++) {
            $ids = array_values(array_unique(array_map('intval', (array) ($data["shift_$shift"] ?? []))));
            $perShift[$shift] = $ids;
            $all = array_merge($all, $ids);
        }

        // Karyawan boleh dijadwalkan di lebih dari 1 shift pada tanggal yang sama.

        // Pastikan hanya karyawan aktif yang bisa dijadwalkan (jika kolom ada).
        if (! empty($all) && Schema::hasColumn('karyawan', 'is_active')) {
            $activeIds = $this->activeKaryawanQuery()
                ->whereIn('id_karyawan', $all)
                ->pluck('id_karyawan')
                ->map(fn ($v) => (int) $v)
                ->all();
            $inactiveSelected = array_values(array_diff($all, $activeIds));
            if (! empty($inactiveSelected)) {
                return back()->withErrors([
                    'jadwal' => 'Ada karyawan nonaktif yang dipilih. Aktifkan dulu atau hapus dari jadwal.',
                ])->withInput();
            }
        }

        DB::transaction(function () use ($date, $perShift, $activeShiftCount): void {
            JadwalKaryawan::query()->whereDate('tanggal', $date)->delete();

            $payload = [];
            for ($shift = 1; $shift <= $activeShiftCount; $shift++) {
                foreach ($perShift[$shift] as $idKaryawan) {
                    $payload[] = [
                        'tanggal' => $date,
                        'shift_ke' => $shift,
                        'id_karyawan' => (int) $idKaryawan,
                    ];
                }
            }

            if (! empty($payload)) {
                DB::table('jadwal_karyawan')->insert($payload);
            }
        });

        return redirect()
            ->route('dashboard.jadwal.index', ['bulan' => substr($date, 0, 7)])
            ->with('success', 'Jadwal berhasil disimpan.');
    }

    public function selfSchedule(Request $request): View
    {
        $setting = StrukSetting::current();

        $enabled = (bool) ($setting->self_schedule_enabled ?? false);
        $open = $enabled && (bool) ($setting->self_schedule_is_open ?? false);
        $today = Carbon::today()->toDateString();

        $returnBulan = (string) $request->query('return_bulan', '');
        if (! preg_match('/^\\d{4}-\\d{2}$/', $returnBulan)) {
            $returnBulan = '';
        }
        $returnMode = (string) $request->query('return_mode', '');
        if (! in_array($returnMode, ['calendar', 'list'], true)) {
            $returnMode = '';
        }

        $buildSlotCards = function (string $employmentType) use ($setting, $today) {
            return collect(range(1, 3))->map(function (int $shiftNo) use ($employmentType, $setting, $today) {
                return [
                    'code' => $setting->shiftCodeFor($shiftNo, $employmentType),
                    'title' => $setting->shiftTitleFor($shiftNo, $employmentType),
                    'range' => $setting->shiftRangeLabel($shiftNo, $employmentType, $today),
                    'duration' => $setting->shiftDurationLabelFor($employmentType),
                    'weekday_capacity' => $setting->selfScheduleCapacityForShift($shiftNo, $employmentType, false),
                    'weekend_capacity' => $setting->selfScheduleCapacityForShift($shiftNo, $employmentType, true),
                ];
            })->all();
        };

        return view('dashboard.jadwal.self-schedule', [
            'setting' => $setting,
            'enabled' => $enabled,
            'open' => $open,
            'fullTimeSlotCards' => $buildSlotCards(Karyawan::EMPLOYMENT_FULL_TIME),
            'partTimeSlotCards' => $buildSlotCards(Karyawan::EMPLOYMENT_PART_TIME),
            'returnBulan' => $returnBulan !== '' ? $returnBulan : null,
            'returnMode' => $returnMode !== '' ? $returnMode : null,
        ]);
    }

    public function swapRequests(Request $request): View
    {
        $this->ensureTableExistsOrFail();
        $status = (string) $request->query('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $requests = collect();
        if (Schema::hasTable('jadwal_tukar_requests')) {
            $query = JadwalTukarRequest::query()
                ->with(['fromKaryawan', 'toKaryawan'])
                ->where('status', $status)
                ->orderByDesc('id')
                ->limit(50);
            if ($status === 'pending') {
                $query->where('staff_status', 'approved');
            }
            $requests = $query->get();
        }

        return view('dashboard.jadwal.swap-requests', [
            'status' => $status,
            'requests' => $requests,
        ]);
    }

    public function approveSwap(Request $request, JadwalTukarRequest $swap): RedirectResponse
    {
        if (! Schema::hasTable('jadwal_tukar_requests')) {
            return back()->withErrors(['swap' => 'Tabel tukar jadwal belum tersedia. Jalankan migrasi dulu.']);
        }
        if ($swap->status !== 'pending') {
            return back()->withErrors(['swap' => 'Permintaan ini sudah diproses.']);
        }

        $note = trim((string) $request->input('note', ''));

        DB::transaction(function () use ($swap, $note): void {
            $swapRow = JadwalTukarRequest::query()
                ->where('id', (int) $swap->id)
                ->lockForUpdate()
                ->first();

            if (! $swapRow || $swapRow->status !== 'pending') {
                throw new \RuntimeException('Permintaan ini sudah diproses.');
            }

            $tanggal = $swapRow->from_tanggal?->format('Y-m-d') ?? ($swapRow->tanggal?->format('Y-m-d') ?? null);
            $tanggalTarget = $swapRow->to_tanggal?->format('Y-m-d') ?? ($swapRow->tanggal?->format('Y-m-d') ?? null);
            if (! $tanggal || ! $tanggalTarget) {
                throw new \RuntimeException('Tanggal permintaan tidak valid.');
            }

            $fromRow = JadwalKaryawan::query()
                ->where('id_karyawan', (int) $swapRow->from_karyawan_id)
                ->whereDate('tanggal', $tanggal)
                ->lockForUpdate()
                ->first();

            $toRow = JadwalKaryawan::query()
                ->where('id_karyawan', (int) $swapRow->to_karyawan_id)
                ->whereDate('tanggal', $tanggalTarget)
                ->lockForUpdate()
                ->first();

            if (! $fromRow || ! $toRow) {
                throw new \RuntimeException('Jadwal salah satu staff sudah berubah atau tidak ditemukan.');
            }

            $fromDup = JadwalKaryawan::query()
                ->where('id_karyawan', (int) $swapRow->from_karyawan_id)
                ->whereDate('tanggal', $tanggalTarget)
                ->where('shift_ke', (int) $toRow->shift_ke)
                ->where('id', '<>', (int) $fromRow->id)
                ->exists();
            if ($fromDup) {
                throw new \RuntimeException('Staff pemohon sudah punya shift ini di tanggal target.');
            }

            $toDup = JadwalKaryawan::query()
                ->where('id_karyawan', (int) $swapRow->to_karyawan_id)
                ->whereDate('tanggal', $tanggal)
                ->where('shift_ke', (int) $fromRow->shift_ke)
                ->where('id', '<>', (int) $toRow->id)
                ->exists();
            if ($toDup) {
                throw new \RuntimeException('Staff target sudah punya shift ini di tanggal pemohon.');
            }

            // Swap schedules across dates (same date also supported).
            $tmpTanggal = $fromRow->tanggal;
            $tmpShift = (int) $fromRow->shift_ke;
            $fromRow->tanggal = $toRow->tanggal;
            $fromRow->shift_ke = (int) $toRow->shift_ke;
            $toRow->tanggal = $tmpTanggal;
            $toRow->shift_ke = $tmpShift;
            $fromRow->save();
            $toRow->save();

            $swapRow->status = 'approved';
            $swapRow->note = $note !== '' ? $note : null;
            $swapRow->approved_by = (int) auth()->id();
            $swapRow->approved_at = now();
            $swapRow->save();

            $fromStaff = Karyawan::query()
                ->where('id_karyawan', (int) $swapRow->from_karyawan_id)
                ->first(['id_karyawan', 'nama_karyawan', 'jabatan']);
            $toStaff = Karyawan::query()
                ->where('id_karyawan', (int) $swapRow->to_karyawan_id)
                ->first(['id_karyawan', 'nama_karyawan', 'jabatan']);
            $fromDate = $swapRow->from_tanggal?->format('Y-m-d') ?? ($swapRow->tanggal?->format('Y-m-d') ?? '-');
            $toDate = $swapRow->to_tanggal?->format('Y-m-d') ?? ($swapRow->tanggal?->format('Y-m-d') ?? '-');
            $msg = $this->formatSwapMessage(
                'Keputusan Admin: DISETUJUI',
                $fromDate,
                (int) ($swapRow->from_shift ?? 0),
                $toDate,
                (int) ($swapRow->to_shift ?? 0),
                $fromStaff,
                $toStaff,
                $note !== '' ? $note : null
            );
            StaffMessage::query()->create([
                'thread_type' => 'swap',
                'thread_id' => (int) $swapRow->id,
                'sender_role' => 'admin',
                'sender_user_id' => (int) auth()->id(),
                'message' => $msg,
            ]);
            StaffMessage::query()->create([
                'thread_type' => 'admin_chat',
                'thread_id' => (int) $swapRow->from_karyawan_id,
                'sender_role' => 'admin',
                'sender_user_id' => (int) auth()->id(),
                'message' => 'Tukar shift disetujui admin.',
                'meta' => [
                    'action' => [
                        'type' => 'swap',
                        'id' => (int) $swapRow->id,
                    ],
                ],
            ]);

            foreach (array_unique([(int) $swapRow->from_karyawan_id, (int) $swapRow->to_karyawan_id]) as $staffId) {
                if ($staffId <= 0) {
                    continue;
                }

                app(StaffNotificationService::class)->notify(
                    $staffId,
                    StaffNotification::CATEGORY_SWAP,
                    'Tukar shift disetujui',
                    'Permintaan tukar shift untuk ' . $fromDate . ' dan ' . $toDate . ' sudah disetujui admin.' . ($note !== '' ? ' Catatan: ' . $note : ''),
                    route('staff.swap.index'),
                    'Lihat swap',
                    'swap-status:' . (int) $swapRow->id . ':' . $staffId . ':approved',
                    [
                        'type' => 'swap',
                        'swap_id' => (int) $swapRow->id,
                        'status' => 'approved',
                    ]
                );
            }
        }, 3);

        return back()->with('success', 'Permintaan tukar jadwal sudah disetujui.');
    }

    public function rejectSwap(Request $request, JadwalTukarRequest $swap): RedirectResponse
    {
        if (! Schema::hasTable('jadwal_tukar_requests')) {
            return back()->withErrors(['swap' => 'Tabel tukar jadwal belum tersedia. Jalankan migrasi dulu.']);
        }
        if ($swap->status !== 'pending') {
            return back()->withErrors(['swap' => 'Permintaan ini sudah diproses.']);
        }

        $note = trim((string) $request->input('note', ''));
        $swap->status = 'rejected';
        $swap->note = $note !== '' ? $note : null;
        $swap->approved_by = (int) auth()->id();
        $swap->approved_at = now();
        $swap->save();

        $fromStaff = Karyawan::query()
            ->where('id_karyawan', (int) $swap->from_karyawan_id)
            ->first(['id_karyawan', 'nama_karyawan', 'jabatan']);
        $toStaff = Karyawan::query()
            ->where('id_karyawan', (int) $swap->to_karyawan_id)
            ->first(['id_karyawan', 'nama_karyawan', 'jabatan']);
        $fromDate = $swap->from_tanggal?->format('Y-m-d') ?? ($swap->tanggal?->format('Y-m-d') ?? '-');
        $toDate = $swap->to_tanggal?->format('Y-m-d') ?? ($swap->tanggal?->format('Y-m-d') ?? '-');
        $msg = $this->formatSwapMessage(
            'Keputusan Admin: DITOLAK',
            $fromDate,
            (int) ($swap->from_shift ?? 0),
            $toDate,
            (int) ($swap->to_shift ?? 0),
            $fromStaff,
            $toStaff,
            $note !== '' ? $note : null
        );
        StaffMessage::query()->create([
            'thread_type' => 'swap',
            'thread_id' => (int) $swap->id,
            'sender_role' => 'admin',
            'sender_user_id' => (int) auth()->id(),
            'message' => $msg,
        ]);
        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $swap->from_karyawan_id,
            'sender_role' => 'admin',
            'sender_user_id' => (int) auth()->id(),
            'message' => 'Tukar shift ditolak admin.',
            'meta' => [
                'action' => [
                    'type' => 'swap',
                    'id' => (int) $swap->id,
                ],
            ],
        ]);

        foreach (array_unique([(int) $swap->from_karyawan_id, (int) $swap->to_karyawan_id]) as $staffId) {
            if ($staffId <= 0) {
                continue;
            }

            app(StaffNotificationService::class)->notify(
                $staffId,
                StaffNotification::CATEGORY_SWAP,
                'Tukar shift ditolak',
                'Permintaan tukar shift untuk ' . $fromDate . ' dan ' . $toDate . ' ditolak admin.' . ($note !== '' ? ' Catatan: ' . $note : ''),
                route('staff.swap.index'),
                'Lihat swap',
                'swap-status:' . (int) $swap->id . ':' . $staffId . ':rejected',
                [
                    'type' => 'swap',
                    'swap_id' => (int) $swap->id,
                    'status' => 'rejected',
                ]
            );
        }

        return back()->with('success', 'Permintaan tukar jadwal ditolak.');
    }

    public function updateSelfSchedule(Request $request): RedirectResponse
    {
        $setting = StrukSetting::current();

        $data = $request->validate([
            'self_schedule_enabled' => ['nullable', 'boolean'],
            'self_schedule_is_open' => ['nullable', 'boolean'],
            'self_schedule_pick_preset' => ['nullable', 'in:next_7_days,next_14_days'],
            'self_schedule_pick_start_date' => ['nullable', 'date'],
            'self_schedule_pick_end_date' => ['nullable', 'date'],
            'self_schedule_open_start_date' => ['nullable', 'date'],
            'self_schedule_open_end_date' => ['nullable', 'date'],
            'part_time_shift1_start_time' => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'part_time_shift2_start_time' => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'part_time_shift3_start_time' => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'self_schedule_capacity_shift1' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_capacity_shift2' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_capacity_shift3' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_part_time_capacity_shift1' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_part_time_capacity_shift2' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_part_time_capacity_shift3' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_min_per_week' => ['nullable', 'integer', 'between:0,50'],
            'self_schedule_max_per_week' => ['nullable', 'integer', 'between:0,50'],
            'self_schedule_min_per_month' => ['nullable', 'integer', 'between:0,200'],
            'self_schedule_max_per_month' => ['nullable', 'integer', 'between:0,200'],
            'self_schedule_part_time_min_per_week' => ['nullable', 'integer', 'between:0,50'],
            'self_schedule_part_time_max_per_week' => ['nullable', 'integer', 'between:0,50'],
            'self_schedule_part_time_min_per_month' => ['nullable', 'integer', 'between:0,200'],
            'self_schedule_part_time_max_per_month' => ['nullable', 'integer', 'between:0,200'],
            'self_schedule_allow_cancel' => ['nullable', 'boolean'],
            'self_schedule_cancel_min_days_before' => ['nullable', 'integer', 'between:0,45'],
            'self_schedule_capacity_weekend_shift1' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_capacity_weekend_shift2' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_capacity_weekend_shift3' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_part_time_capacity_weekend_shift1' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_part_time_capacity_weekend_shift2' => ['nullable', 'integer', 'between:1,50'],
            'self_schedule_part_time_capacity_weekend_shift3' => ['nullable', 'integer', 'between:1,50'],
            'return_bulan' => ['nullable', 'regex:/^\\d{4}-\\d{2}$/'],
            'return_mode' => ['nullable', 'in:calendar,list'],
        ]);

        $enabled = $request->boolean('self_schedule_enabled');
        $open = $request->boolean('self_schedule_is_open');

        $pickPreset = isset($data['self_schedule_pick_preset']) ? (string) $data['self_schedule_pick_preset'] : '';

        $pickStart = isset($data['self_schedule_pick_start_date']) ? (string) $data['self_schedule_pick_start_date'] : null;
        $pickEnd = isset($data['self_schedule_pick_end_date']) ? (string) $data['self_schedule_pick_end_date'] : null;
        $openStart = isset($data['self_schedule_open_start_date']) ? (string) $data['self_schedule_open_start_date'] : null;
        $openEnd = isset($data['self_schedule_open_end_date']) ? (string) $data['self_schedule_open_end_date'] : null;

        if ($enabled && $pickPreset !== '') {
            $today = now()->startOfDay();
            if ($pickPreset === 'next_7_days') {
                $pickStart = $today->toDateString();
                $pickEnd = $today->copy()->addDays(6)->toDateString();
            } elseif ($pickPreset === 'next_14_days') {
                $pickStart = $today->toDateString();
                $pickEnd = $today->copy()->addDays(13)->toDateString();
            }
        }

        if ($enabled && $open) {
            if (! $pickStart || ! $pickEnd) {
                return back()
                    ->withErrors(['self_schedule_pick_start_date' => 'Saat pendaftaran dibuka, periode ambil jadwal wajib diisi (tanggal mulai dan selesai).'])
                    ->withInput();
            }
            if (strtotime($pickEnd) < strtotime($pickStart)) {
                return back()
                    ->withErrors(['self_schedule_pick_end_date' => 'Tanggal selesai harus >= tanggal mulai.'])
                    ->withInput();
            }
            if ($openStart || $openEnd) {
                if (! $openStart || ! $openEnd) {
                    return back()
                        ->withErrors(['self_schedule_open_start_date' => 'Jika periode pendaftaran diisi, tanggal mulai dan selesai wajib lengkap.'])
                        ->withInput();
                }
                if (strtotime($openEnd) < strtotime($openStart)) {
                    return back()
                        ->withErrors(['self_schedule_open_end_date' => 'Tanggal selesai pendaftaran harus >= tanggal mulai.'])
                        ->withInput();
                }
            }
        }

        $setting->self_schedule_enabled = $enabled;
        $setting->self_schedule_is_open = $enabled ? $open : false;
        $setting->self_schedule_pick_start_date = $enabled ? $pickStart : null;
        $setting->self_schedule_pick_end_date = $enabled ? $pickEnd : null;
        $setting->self_schedule_open_start_date = $enabled ? $openStart : null;
        $setting->self_schedule_open_end_date = $enabled ? $openEnd : null;
        $setting->self_schedule_capacity_shift1 = (int) ($data['self_schedule_capacity_shift1'] ?? ($setting->self_schedule_capacity_shift1 ?? 1));
        $setting->self_schedule_capacity_shift2 = (int) ($data['self_schedule_capacity_shift2'] ?? ($setting->self_schedule_capacity_shift2 ?? 1));
        $setting->self_schedule_capacity_shift3 = (int) ($data['self_schedule_capacity_shift3'] ?? ($setting->self_schedule_capacity_shift3 ?? 1));

        if (Schema::hasColumn('struk_settings', 'part_time_shift1_start_time')) {
            $setting->part_time_shift1_start_time = (string) ($data['part_time_shift1_start_time'] ?? ($setting->part_time_shift1_start_time ?? '07:00'));
            $setting->part_time_shift2_start_time = (string) ($data['part_time_shift2_start_time'] ?? ($setting->part_time_shift2_start_time ?? '11:30'));
            $setting->part_time_shift3_start_time = (string) ($data['part_time_shift3_start_time'] ?? ($setting->part_time_shift3_start_time ?? '16:00'));
        }

        // Optional advanced rules (added by a later migration). Guard to avoid breaking when DB isn't migrated yet.
        if (Schema::hasColumn('struk_settings', 'self_schedule_min_per_week')) {
            $minWeek = array_key_exists('self_schedule_min_per_week', $data) ? (int) $data['self_schedule_min_per_week'] : null;
            $maxWeek = array_key_exists('self_schedule_max_per_week', $data) ? (int) $data['self_schedule_max_per_week'] : null;
            $minMonth = array_key_exists('self_schedule_min_per_month', $data) ? (int) $data['self_schedule_min_per_month'] : null;
            $maxMonth = array_key_exists('self_schedule_max_per_month', $data) ? (int) $data['self_schedule_max_per_month'] : null;

            if ($minWeek !== null && $maxWeek !== null && $maxWeek > 0 && $minWeek > $maxWeek) {
                return back()->withErrors(['self_schedule_min_per_week' => 'Minimal per minggu tidak boleh melebihi maksimal per minggu.'])->withInput();
            }
            if ($minMonth !== null && $maxMonth !== null && $maxMonth > 0 && $minMonth > $maxMonth) {
                return back()->withErrors(['self_schedule_min_per_month' => 'Minimal per bulan tidak boleh melebihi maksimal per bulan.'])->withInput();
            }

            $setting->self_schedule_min_per_week = $minWeek !== null && $minWeek > 0 ? $minWeek : null;
            $setting->self_schedule_max_per_week = $maxWeek !== null && $maxWeek > 0 ? $maxWeek : null;
            $setting->self_schedule_min_per_month = $minMonth !== null && $minMonth > 0 ? $minMonth : null;
            $setting->self_schedule_max_per_month = $maxMonth !== null && $maxMonth > 0 ? $maxMonth : null;

            if (Schema::hasColumn('struk_settings', 'self_schedule_part_time_min_per_week')) {
                $ptMinWeek = array_key_exists('self_schedule_part_time_min_per_week', $data) ? (int) $data['self_schedule_part_time_min_per_week'] : null;
                $ptMaxWeek = array_key_exists('self_schedule_part_time_max_per_week', $data) ? (int) $data['self_schedule_part_time_max_per_week'] : null;
                $ptMinMonth = array_key_exists('self_schedule_part_time_min_per_month', $data) ? (int) $data['self_schedule_part_time_min_per_month'] : null;
                $ptMaxMonth = array_key_exists('self_schedule_part_time_max_per_month', $data) ? (int) $data['self_schedule_part_time_max_per_month'] : null;

                if ($ptMinWeek !== null && $ptMaxWeek !== null && $ptMaxWeek > 0 && $ptMinWeek > $ptMaxWeek) {
                    return back()->withErrors(['self_schedule_part_time_min_per_week' => 'Minimal jadwal part time per minggu tidak boleh melebihi maksimalnya.'])->withInput();
                }
                if ($ptMinMonth !== null && $ptMaxMonth !== null && $ptMaxMonth > 0 && $ptMinMonth > $ptMaxMonth) {
                    return back()->withErrors(['self_schedule_part_time_min_per_month' => 'Minimal jadwal part time per bulan tidak boleh melebihi maksimalnya.'])->withInput();
                }

                $setting->self_schedule_part_time_min_per_week = $ptMinWeek !== null && $ptMinWeek > 0 ? $ptMinWeek : null;
                $setting->self_schedule_part_time_max_per_week = $ptMaxWeek !== null && $ptMaxWeek > 0 ? $ptMaxWeek : null;
                $setting->self_schedule_part_time_min_per_month = $ptMinMonth !== null && $ptMinMonth > 0 ? $ptMinMonth : null;
                $setting->self_schedule_part_time_max_per_month = $ptMaxMonth !== null && $ptMaxMonth > 0 ? $ptMaxMonth : null;
            }

            $setting->self_schedule_allow_cancel = $enabled ? $request->boolean('self_schedule_allow_cancel') : false;
            if ($setting->self_schedule_allow_cancel) {
                $cancelMin = array_key_exists('self_schedule_cancel_min_days_before', $data)
                    ? (int) ($data['self_schedule_cancel_min_days_before'] ?? 0)
                    : (int) ($setting->self_schedule_cancel_min_days_before ?? 0);
                $setting->self_schedule_cancel_min_days_before = max(0, $cancelMin);
            } else {
                $setting->self_schedule_cancel_min_days_before = 0;
            }

            $setting->self_schedule_capacity_weekend_shift1 = array_key_exists('self_schedule_capacity_weekend_shift1', $data)
                ? ($data['self_schedule_capacity_weekend_shift1'] === null ? null : (int) $data['self_schedule_capacity_weekend_shift1'])
                : ($setting->self_schedule_capacity_weekend_shift1 ?? null);
            $setting->self_schedule_capacity_weekend_shift2 = array_key_exists('self_schedule_capacity_weekend_shift2', $data)
                ? ($data['self_schedule_capacity_weekend_shift2'] === null ? null : (int) $data['self_schedule_capacity_weekend_shift2'])
                : ($setting->self_schedule_capacity_weekend_shift2 ?? null);
            $setting->self_schedule_capacity_weekend_shift3 = array_key_exists('self_schedule_capacity_weekend_shift3', $data)
                ? ($data['self_schedule_capacity_weekend_shift3'] === null ? null : (int) $data['self_schedule_capacity_weekend_shift3'])
                : ($setting->self_schedule_capacity_weekend_shift3 ?? null);
            if (! $enabled) {
                $setting->self_schedule_capacity_weekend_shift1 = null;
                $setting->self_schedule_capacity_weekend_shift2 = null;
                $setting->self_schedule_capacity_weekend_shift3 = null;
            }

            if (Schema::hasColumn('struk_settings', 'self_schedule_part_time_capacity_shift1')) {
                $setting->self_schedule_part_time_capacity_shift1 = array_key_exists('self_schedule_part_time_capacity_shift1', $data)
                    ? ($data['self_schedule_part_time_capacity_shift1'] === null ? null : (int) $data['self_schedule_part_time_capacity_shift1'])
                    : ($setting->self_schedule_part_time_capacity_shift1 ?? null);
                $setting->self_schedule_part_time_capacity_shift2 = array_key_exists('self_schedule_part_time_capacity_shift2', $data)
                    ? ($data['self_schedule_part_time_capacity_shift2'] === null ? null : (int) $data['self_schedule_part_time_capacity_shift2'])
                    : ($setting->self_schedule_part_time_capacity_shift2 ?? null);
                $setting->self_schedule_part_time_capacity_shift3 = array_key_exists('self_schedule_part_time_capacity_shift3', $data)
                    ? ($data['self_schedule_part_time_capacity_shift3'] === null ? null : (int) $data['self_schedule_part_time_capacity_shift3'])
                    : ($setting->self_schedule_part_time_capacity_shift3 ?? null);

                $setting->self_schedule_part_time_capacity_weekend_shift1 = array_key_exists('self_schedule_part_time_capacity_weekend_shift1', $data)
                    ? ($data['self_schedule_part_time_capacity_weekend_shift1'] === null ? null : (int) $data['self_schedule_part_time_capacity_weekend_shift1'])
                    : ($setting->self_schedule_part_time_capacity_weekend_shift1 ?? null);
                $setting->self_schedule_part_time_capacity_weekend_shift2 = array_key_exists('self_schedule_part_time_capacity_weekend_shift2', $data)
                    ? ($data['self_schedule_part_time_capacity_weekend_shift2'] === null ? null : (int) $data['self_schedule_part_time_capacity_weekend_shift2'])
                    : ($setting->self_schedule_part_time_capacity_weekend_shift2 ?? null);
                $setting->self_schedule_part_time_capacity_weekend_shift3 = array_key_exists('self_schedule_part_time_capacity_weekend_shift3', $data)
                    ? ($data['self_schedule_part_time_capacity_weekend_shift3'] === null ? null : (int) $data['self_schedule_part_time_capacity_weekend_shift3'])
                    : ($setting->self_schedule_part_time_capacity_weekend_shift3 ?? null);

                if (! $enabled) {
                    $setting->self_schedule_part_time_capacity_shift1 = null;
                    $setting->self_schedule_part_time_capacity_shift2 = null;
                    $setting->self_schedule_part_time_capacity_shift3 = null;
                    $setting->self_schedule_part_time_capacity_weekend_shift1 = null;
                    $setting->self_schedule_part_time_capacity_weekend_shift2 = null;
                    $setting->self_schedule_part_time_capacity_weekend_shift3 = null;
                }
            }
        }

        $setting->save();

        $returnBulan = isset($data['return_bulan']) ? (string) $data['return_bulan'] : '';
        $returnMode = isset($data['return_mode']) ? (string) $data['return_mode'] : '';
        if ($returnBulan !== '') {
            $params = ['bulan' => $returnBulan];
            if ($returnMode !== '') {
                $params['mode'] = $returnMode;
            }
            return redirect()
                ->route('dashboard.jadwal.index', $params)
                ->with('success', 'Pengaturan Ambil Jadwal Mandiri berhasil disimpan.');
        }

        return redirect()
            ->route('dashboard.jadwal.index')
            ->with('success', 'Pengaturan Ambil Jadwal Mandiri berhasil disimpan.');
    }
}
