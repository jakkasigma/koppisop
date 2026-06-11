<?php

namespace App\Http\Controllers;

use App\Models\JadwalKaryawan;
use App\Models\JadwalTukarRequest;
use App\Models\Karyawan;
use App\Models\StaffMessage;
use App\Models\StrukSetting;
use App\Services\StaffActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StaffSelfScheduleController extends Controller
{
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

    private function employmentTypeFor(?Karyawan $karyawan): string
    {
        if ($karyawan && method_exists($karyawan, 'employmentTypeValue')) {
            return $karyawan->employmentTypeValue();
        }

        return Karyawan::normalizeEmploymentType($karyawan?->employment_type ?? null);
    }

    public function index(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $setting = StrukSetting::current();
        $employmentType = $this->employmentTypeFor($karyawan);
        $isPartTimeSchedule = $setting->usesPartTimeSlots($employmentType);

        $enabled = (bool) ($setting->self_schedule_enabled ?? false);
        $open = $enabled && (bool) ($setting->self_schedule_is_open ?? false);

        $start = $setting->self_schedule_pick_start_date
            ? Carbon::parse((string) $setting->self_schedule_pick_start_date)->startOfDay()
            : null;
        $end = $setting->self_schedule_pick_end_date
            ? Carbon::parse((string) $setting->self_schedule_pick_end_date)->startOfDay()
            : null;
        $openStart = $setting->self_schedule_open_start_date
            ? Carbon::parse((string) $setting->self_schedule_open_start_date)->startOfDay()
            : null;
        $openEnd = $setting->self_schedule_open_end_date
            ? Carbon::parse((string) $setting->self_schedule_open_end_date)->startOfDay()
            : null;

        $activeShiftCount = max(1, (int) ($setting->active_shift_count ?? 2));
        $capacityByShift = [
            1 => $setting->selfScheduleCapacityForShift(1, $employmentType),
            2 => $setting->selfScheduleCapacityForShift(2, $employmentType),
            3 => $setting->selfScheduleCapacityForShift(3, $employmentType),
        ];
        $weekendCapacityByShift = [
            1 => $setting->selfScheduleCapacityForShift(1, $employmentType, true),
            2 => $setting->selfScheduleCapacityForShift(2, $employmentType, true),
            3 => $setting->selfScheduleCapacityForShift(3, $employmentType, true),
        ];

        $minPerWeek = $setting->selfScheduleMinPerWeekFor($employmentType);
        $maxPerWeek = $setting->selfScheduleMaxPerWeekFor($employmentType);
        $minPerMonth = $setting->selfScheduleMinPerMonthFor($employmentType);
        $maxPerMonth = $setting->selfScheduleMaxPerMonthFor($employmentType);

        $allowCancel = (bool) ($setting->self_schedule_allow_cancel ?? false);
        $cancelMinDaysBefore = max(0, (int) ($setting->self_schedule_cancel_min_days_before ?? 0));

        $days = [];
        $counts = [];
        $mine = [];
        $isWeekendByDay = [];
        $capacityByDayShift = [];
        $weekCountByDay = [];
        $monthCountByDay = [];

        if ($enabled && $start && $end && $end->gte($start)) {
            // Hard limit to keep UI manageable.
            $maxDays = 45;
            $cursor = $start->copy();
            $i = 0;
            while ($cursor->lte($end) && $i < $maxDays) {
                $days[] = $cursor->toDateString();
                $cursor->addDay();
                $i++;
            }

            if (count($days) > 0) {
                foreach ($days as $d) {
                    try {
                        $isWeekend = Carbon::parse($d)->isWeekend();
                    } catch (\Throwable $e) {
                        $isWeekend = false;
                    }
                    $isWeekendByDay[$d] = $isWeekend;
                    for ($s = 1; $s <= 3; $s++) {
                        $cap = $isWeekend && $weekendCapacityByShift[$s] !== null
                            ? (int) $weekendCapacityByShift[$s]
                            : (int) ($capacityByShift[$s] ?? 1);
                        $capacityByDayShift[$d][$s] = max(1, (int) $cap);
                    }
                }

                $rows = JadwalKaryawan::query()
                    ->when(Schema::hasColumn('karyawan', 'employment_type'), function ($query) use ($employmentType) {
                        $query->join('karyawan', 'karyawan.id_karyawan', '=', 'jadwal_karyawan.id_karyawan')
                            ->where('karyawan.employment_type', $employmentType);
                    })
                    ->whereDate('jadwal_karyawan.tanggal', '>=', $days[0])
                    ->whereDate('jadwal_karyawan.tanggal', '<=', $days[count($days) - 1])
                    ->get(['jadwal_karyawan.tanggal', 'jadwal_karyawan.shift_ke', 'jadwal_karyawan.id_karyawan']);

                foreach ($rows as $r) {
                    $d = $r->tanggal?->format('Y-m-d') ?? null;
                    $s = (int) ($r->shift_ke ?? 0);
                    if (!is_string($d) || $d === '' || $s < 1 || $s > 3) continue;
                    $counts[$d][$s] = (int) ($counts[$d][$s] ?? 0) + 1;
                    if ((int) $r->id_karyawan === (int) $karyawan->id_karyawan) {
                        $mine[$d] ??= [];
                        $mine[$d][] = $s;
                    }
                }
            }

            // Weekly/monthly counters for the current staff (for UI hints + disabling buttons when max is reached).
            $expandedStart = $start->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $expandedEnd = $end->copy()->endOfWeek(Carbon::MONDAY)->toDateString();

            $myRows = JadwalKaryawan::query()
                ->where('id_karyawan', (int) $karyawan->id_karyawan)
                ->whereDate('tanggal', '>=', $expandedStart)
                ->whereDate('tanggal', '<=', $expandedEnd)
                ->get(['tanggal']);

            $weekCounts = [];
            $monthCounts = [];
            foreach ($myRows as $r) {
                $dt = $r->tanggal;
                if (! $dt) continue;
                $weekKey = $dt->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
                $monthKey = $dt->format('Y-m');
                $weekCounts[$weekKey] = (int) ($weekCounts[$weekKey] ?? 0) + 1;
                $monthCounts[$monthKey] = (int) ($monthCounts[$monthKey] ?? 0) + 1;
            }

            foreach ($days as $d) {
                try {
                    $dt = Carbon::parse($d)->startOfDay();
                } catch (\Throwable $e) {
                    continue;
                }
                $weekKey = $dt->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
                $monthKey = $dt->format('Y-m');
                $weekCountByDay[$d] = (int) ($weekCounts[$weekKey] ?? 0);
                $monthCountByDay[$d] = (int) ($monthCounts[$monthKey] ?? 0);
            }
        }

        return view('staff.ambil-jadwal', [
            'setting' => $setting,
            'karyawan' => $karyawan,
            'enabled' => $enabled,
            'open' => $open,
            'rangeStart' => $start ? $start->toDateString() : null,
            'rangeEnd' => $end ? $end->toDateString() : null,
            'openStart' => $openStart ? $openStart->toDateString() : null,
            'openEnd' => $openEnd ? $openEnd->toDateString() : null,
            'activeShiftCount' => $activeShiftCount,
            'capacityByShift' => $capacityByShift,
            'capacityByDayShift' => $capacityByDayShift,
            'isWeekendByDay' => $isWeekendByDay,
            'days' => $days,
            'counts' => $counts,
            'mine' => $mine,
            'minPerWeek' => $minPerWeek,
            'maxPerWeek' => $maxPerWeek,
            'minPerMonth' => $minPerMonth,
            'maxPerMonth' => $maxPerMonth,
            'weekCountByDay' => $weekCountByDay,
            'monthCountByDay' => $monthCountByDay,
            'allowCancel' => $allowCancel,
            'cancelMinDaysBefore' => $cancelMinDaysBefore,
            'today' => now()->toDateString(),
            'employmentType' => $employmentType,
            'isPartTimeSchedule' => $isPartTimeSchedule,
        ]);
    }

    public function swapIndex(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $setting = StrukSetting::current();
        $employmentType = $this->employmentTypeFor($karyawan);

        $enabled = (bool) ($setting->self_schedule_enabled ?? false);
        $rangeStart = $setting->self_schedule_pick_start_date
            ? Carbon::parse((string) $setting->self_schedule_pick_start_date)->toDateString()
            : null;
        $rangeEnd = $setting->self_schedule_pick_end_date
            ? Carbon::parse((string) $setting->self_schedule_pick_end_date)->toDateString()
            : null;
        $today = now()->toDateString();
        $activeShiftCount = max(1, (int) ($setting->active_shift_count ?? 2));

        $fromDate = $today;
        $toDate = now()->addDays(90)->toDateString();

        $mySchedules = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', '>=', $fromDate)
            ->whereDate('tanggal', '<=', $toDate)
            ->orderBy('tanggal')
            ->get(['tanggal', 'shift_ke']);

        $staffList = collect();
        if (Schema::hasTable('karyawan')) {
            $staffQuery = Karyawan::query()->orderBy('nama_karyawan');
            if (Schema::hasColumn('karyawan', 'is_active')) {
                $staffQuery->where('is_active', true);
            }
            if (Schema::hasColumn('karyawan', 'employment_type')) {
                $staffQuery->where('employment_type', $employmentType);
            }
            $staffList = $staffQuery->get(['id_karyawan', 'nama_karyawan', 'jabatan']);
        }

        $swapRequests = collect();
        if (Schema::hasTable('jadwal_tukar_requests')) {
            $swapRequests = JadwalTukarRequest::query()
                ->where(function ($q) use ($karyawan): void {
                    $q->where('from_karyawan_id', (int) $karyawan->id_karyawan)
                        ->orWhere('to_karyawan_id', (int) $karyawan->id_karyawan);
                })
                ->orderByDesc('id')
                ->limit(30)
                ->get();
        }

        $minDays = max(0, (int) ($setting->self_schedule_cancel_min_days_before ?? 0));
        $minTarget = now()->addDays(max(1, $minDays))->toDateString();

        return view('staff.tukar-jadwal', [
            'setting' => $setting,
            'karyawan' => $karyawan,
            'enabled' => $enabled,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'today' => $today,
            'activeShiftCount' => $activeShiftCount,
            'minDays' => $minDays,
            'minTarget' => $minTarget,
            'mySchedules' => $mySchedules,
            'staffList' => $staffList,
            'swapRequests' => $swapRequests,
            'employmentType' => $employmentType,
        ]);
    }

    public function availableForSwap(Request $request)
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $data = $request->validate([
            'date' => ['required', 'date'],
            'shift' => ['nullable', 'integer', 'between:1,3'],
        ]);

        $date = Carbon::parse((string) $data['date'])->toDateString();
        $shift = isset($data['shift']) ? (int) $data['shift'] : null;
        $today = now()->toDateString();
        if ($date <= $today) {
            return response()->json([
                'ok' => true,
                'items' => [],
                'message' => 'Tanggal target harus di masa depan.',
            ]);
        }

        $query = JadwalKaryawan::query()
            ->leftJoin('karyawan', 'karyawan.id_karyawan', '=', 'jadwal_karyawan.id_karyawan')
            ->whereDate('jadwal_karyawan.tanggal', $date);
        if (Schema::hasColumn('karyawan', 'employment_type')) {
            $query->where('karyawan.employment_type', $this->employmentTypeFor($karyawan));
        }
        if ($shift !== null) {
            $query->where('jadwal_karyawan.shift_ke', $shift);
        }

        // Do not filter by active status here; show all scheduled staff.
        if ($karyawan) {
            $query->where('karyawan.id_karyawan', '<>', (int) $karyawan->id_karyawan);
        }

        $items = $query
            ->orderBy('karyawan.nama_karyawan')
            ->get([
                'jadwal_karyawan.id_karyawan as id',
                'karyawan.nama_karyawan as nama',
                'karyawan.jabatan as jabatan',
                'jadwal_karyawan.shift_ke as shift',
            ])
            ->map(function ($row) {
                $nama = trim((string) ($row->nama ?? ''));
                if ($nama === '') {
                    $nama = 'Karyawan #' . (int) $row->id;
                }
                return [
                    'id' => (int) $row->id,
                    'nama' => $nama,
                    'jabatan' => (string) ($row->jabatan ?? ''),
                    'shift' => (int) ($row->shift ?? 0),
                ];
            })
            ->values()
            ->all();

        $myShifts = [];
        if ($karyawan) {
            $myShifts = JadwalKaryawan::query()
                ->where('id_karyawan', (int) $karyawan->id_karyawan)
                ->whereDate('tanggal', $date)
                ->pluck('shift_ke')
                ->map(fn ($s) => (int) $s)
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        $shifts = collect($items)->pluck('shift')->filter()->unique()->sort()->values()->all();

        return response()->json([
            'ok' => true,
            'items' => $items,
            'shifts' => $shifts,
            'my_shifts' => $myShifts,
        ]);
    }

    public function pick(Request $request): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $setting = StrukSetting::current();
        $employmentType = $this->employmentTypeFor($karyawan);

        if (! (bool) ($setting->self_schedule_enabled ?? false)) {
            return back()->withErrors(['pick' => 'Fitur ambil jadwal belum diaktifkan admin.']);
        }
        if (! (bool) ($setting->self_schedule_is_open ?? false)) {
            return back()->withErrors(['pick' => 'Pendaftaran ambil jadwal belum dibuka admin.']);
        }

        $openStart = $setting->self_schedule_open_start_date ? Carbon::parse((string) $setting->self_schedule_open_start_date)->toDateString() : null;
        $openEnd = $setting->self_schedule_open_end_date ? Carbon::parse((string) $setting->self_schedule_open_end_date)->toDateString() : null;
        $today = now()->toDateString();
        if ($openStart && $openEnd && ($today < $openStart || $today > $openEnd)) {
            return back()->withErrors(['pick' => 'Saat ini di luar periode pendaftaran yang dibuka admin.']);
        }

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'shift_ke' => ['required', 'integer', 'between:1,3'],
        ]);

        $tgl = Carbon::parse((string) $data['tanggal'])->toDateString();
        $shift = (int) $data['shift_ke'];

        $start = $setting->self_schedule_pick_start_date ? Carbon::parse((string) $setting->self_schedule_pick_start_date)->toDateString() : null;
        $end = $setting->self_schedule_pick_end_date ? Carbon::parse((string) $setting->self_schedule_pick_end_date)->toDateString() : null;
        if (! $start || ! $end || $tgl < $start || $tgl > $end) {
            return back()->withErrors(['pick' => 'Tanggal ini tidak termasuk periode ambil jadwal yang dibuka admin.']);
        }
        if ($tgl < now()->toDateString()) {
            return back()->withErrors(['pick' => 'Tidak bisa ambil jadwal untuk tanggal yang sudah lewat.']);
        }

        $activeShiftCount = max(1, (int) ($setting->active_shift_count ?? 2));
        if ($shift > $activeShiftCount) {
            return back()->withErrors(['pick' => 'Shift ini tidak aktif.']);
        }

        $capacity = $setting->selfScheduleCapacityForDateAndShift($tgl, $shift, $employmentType);

        $maxPerWeek = $setting->selfScheduleMaxPerWeekFor($employmentType);
        $maxPerMonth = $setting->selfScheduleMaxPerMonthFor($employmentType);

        try {
            DB::transaction(function () use ($setting, $karyawan, $tgl, $shift, $capacity, $maxPerWeek, $maxPerMonth, $employmentType): void {
                // Serialize picks to make weekly/monthly max checks race-safe (esp. when staff opens multiple tabs).
                if ((int) ($setting->id ?? 0) > 0) {
                    DB::table('struk_settings')->where('id', (int) $setting->id)->lockForUpdate()->first();
                }

                $target = Carbon::parse($tgl)->startOfDay();
                $weekStart = $target->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                $weekEnd = $target->copy()->endOfWeek(Carbon::MONDAY)->toDateString();
                $monthStart = $target->copy()->startOfMonth()->toDateString();
                $monthEnd = $target->copy()->endOfMonth()->toDateString();

                if ($maxPerWeek !== null && $maxPerWeek > 0) {
                    $weekCount = (int) JadwalKaryawan::query()
                        ->where('id_karyawan', (int) $karyawan->id_karyawan)
                        ->whereDate('tanggal', '>=', $weekStart)
                        ->whereDate('tanggal', '<=', $weekEnd)
                        ->lockForUpdate()
                        ->count();
                    if ($weekCount >= $maxPerWeek) {
                        throw new \RuntimeException("Batas maksimal jadwal per minggu adalah {$maxPerWeek} shift.");
                    }
                }

                if ($maxPerMonth !== null && $maxPerMonth > 0) {
                    $monthCount = (int) JadwalKaryawan::query()
                        ->where('id_karyawan', (int) $karyawan->id_karyawan)
                        ->whereDate('tanggal', '>=', $monthStart)
                        ->whereDate('tanggal', '<=', $monthEnd)
                        ->lockForUpdate()
                        ->count();
                    if ($monthCount >= $maxPerMonth) {
                        throw new \RuntimeException("Batas maksimal jadwal per bulan adalah {$maxPerMonth} shift.");
                    }
                }

                // Lock rows for this day to avoid racey overbooking.
                $existingForDay = JadwalKaryawan::query()
                    ->whereDate('tanggal', $tgl)
                    ->lockForUpdate()
                    ->get(['id', 'id_karyawan', 'shift_ke']);

                $employmentTypeMap = [];
                if ($existingForDay->isNotEmpty() && Schema::hasColumn('karyawan', 'employment_type')) {
                    $employmentTypeMap = Karyawan::query()
                        ->whereIn('id_karyawan', $existingForDay->pluck('id_karyawan')->unique()->values()->all())
                        ->pluck('employment_type', 'id_karyawan')
                        ->map(fn ($type) => Karyawan::normalizeEmploymentType($type))
                        ->all();
                }

                foreach ($existingForDay as $r) {
                    if ((int) $r->id_karyawan === (int) $karyawan->id_karyawan && (int) $r->shift_ke === (int) $shift) {
                        throw new \RuntimeException('Kamu sudah mengambil shift ini.');
                    }
                }

                $countShift = 0;
                foreach ($existingForDay as $r) {
                    $rowEmploymentType = array_key_exists((int) $r->id_karyawan, $employmentTypeMap)
                        ? (string) $employmentTypeMap[(int) $r->id_karyawan]
                        : Karyawan::EMPLOYMENT_FULL_TIME;
                    if ((int) $r->shift_ke === (int) $shift && $rowEmploymentType === $employmentType) {
                        $countShift++;
                    }
                }
                if ($countShift >= $capacity) {
                    throw new \RuntimeException('Kuota shift ini sudah penuh.');
                }

                JadwalKaryawan::query()->create([
                    'tanggal' => $tgl,
                    'shift_ke' => $shift,
                    'id_karyawan' => (int) $karyawan->id_karyawan,
                ]);
            }, 3);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['pick' => $e->getMessage()]);
        } catch (QueryException $e) {
            // Unique violation (tanggal,id_karyawan,shift_ke).
            return back()->withErrors(['pick' => 'Kamu sudah mengambil shift ini.']);
        }

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.self_schedule.pick',
            'Ambil Jadwal',
            'Mengambil jadwal ' . $tgl . ' ' . $setting->shiftCodeFor($shift, $employmentType) . '.',
            [
                'tanggal' => $tgl,
                'shift' => $setting->shiftCodeFor($shift, $employmentType),
            ],
            'jadwal_karyawan',
            null,
            $tgl . ' ' . $setting->shiftCodeFor($shift, $employmentType),
        );

        return back()->with('success', 'Jadwal berhasil diambil: ' . $tgl . ' ' . $setting->shiftCodeFor($shift, $employmentType) . '.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $setting = StrukSetting::current();
        $employmentType = $this->employmentTypeFor($karyawan);

        if (! (bool) ($setting->self_schedule_enabled ?? false)) {
            return back()->withErrors(['cancel' => 'Fitur ambil jadwal belum diaktifkan admin.']);
        }
        if (! (bool) ($setting->self_schedule_allow_cancel ?? false)) {
            return back()->withErrors(['cancel' => 'Pembatalan jadwal tidak diizinkan admin.']);
        }

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'shift_ke' => ['nullable', 'integer', 'between:1,3'],
        ]);

        $tgl = Carbon::parse((string) $data['tanggal'])->toDateString();

        $start = $setting->self_schedule_pick_start_date ? Carbon::parse((string) $setting->self_schedule_pick_start_date)->toDateString() : null;
        $end = $setting->self_schedule_pick_end_date ? Carbon::parse((string) $setting->self_schedule_pick_end_date)->toDateString() : null;
        if ($start && $end && ($tgl < $start || $tgl > $end)) {
            return back()->withErrors(['cancel' => 'Tanggal ini di luar periode ambil jadwal.']);
        }

        $today = now()->startOfDay();
        $target = Carbon::parse($tgl)->startOfDay();
        if ($target->lte($today)) {
            return back()->withErrors(['cancel' => 'Tidak bisa membatalkan jadwal untuk hari ini atau tanggal yang sudah lewat.']);
        }

        $minDays = max(0, (int) ($setting->self_schedule_cancel_min_days_before ?? 0));
        if ($minDays > 0) {
            $diff = (int) $today->diffInDays($target, false);
            if ($diff < $minDays) {
                return back()->withErrors(['cancel' => "Pembatalan minimal {$minDays} hari sebelum tanggal jadwal."]);
            }
        }

        $query = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', $tgl);
        if (isset($data['shift_ke'])) {
            $query->where('shift_ke', (int) $data['shift_ke']);
        }
        $deleted = (int) $query->delete();

        if ($deleted <= 0) {
            return back()->withErrors(['cancel' => 'Tidak ada jadwal yang bisa dibatalkan pada tanggal ini.']);
        }

        $shiftLabel = isset($data['shift_ke'])
            ? $setting->shiftCodeFor((int) $data['shift_ke'], $employmentType)
            : 'Semua shift';

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.self_schedule.cancel',
            'Batalkan Jadwal',
            'Membatalkan jadwal ' . $tgl . ' ' . $shiftLabel . '.',
            [
                'tanggal' => $tgl,
                'shift' => $shiftLabel,
            ],
            'jadwal_karyawan',
            null,
            $tgl . ' ' . $shiftLabel,
        );

        if (isset($data['shift_ke'])) {
            return back()->with('success', 'Jadwal dibatalkan: ' . $tgl . ' ' . $setting->shiftCodeFor((int) $data['shift_ke'], $employmentType) . '.');
        }

        return back()->with('success', "Jadwal dibatalkan: {$tgl}.");
    }

    public function requestSwap(Request $request): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $setting = StrukSetting::current();
        $employmentType = $this->employmentTypeFor($karyawan);

        if (! Schema::hasTable('jadwal_tukar_requests')) {
            return back()->withErrors(['swap' => 'Fitur tukar jadwal belum tersedia. Jalankan migrasi dulu.']);
        }

        if (! (bool) ($setting->self_schedule_enabled ?? false)) {
            return back()->withErrors(['swap' => 'Fitur ambil jadwal belum diaktifkan admin.']);
        }

        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'target_tanggal' => ['nullable', 'date'],
            'target_shift' => ['required', 'integer', 'between:1,3'],
            'target_karyawan_id' => ['required', 'integer', 'exists:karyawan,id_karyawan'],
            'pesan' => ['nullable', 'string', 'max:1000'],
        ]);

        $tgl = Carbon::parse((string) $data['tanggal'])->toDateString();
        $targetTanggal = isset($data['target_tanggal']) && (string) $data['target_tanggal'] !== ''
            ? Carbon::parse((string) $data['target_tanggal'])->toDateString()
            : $tgl;
        $targetShift = (int) $data['target_shift'];

        if (Schema::hasColumn('karyawan', 'employment_type')) {
            $targetEmploymentType = Karyawan::query()
                ->where('id_karyawan', (int) $data['target_karyawan_id'])
                ->value('employment_type');
            if (Karyawan::normalizeEmploymentType($targetEmploymentType) !== $employmentType) {
                return back()->withErrors(['swap' => 'Tukar jadwal saat ini hanya bisa diajukan ke staff dengan tipe kerja yang sama.'])->withInput();
            }
        }
        $targetId = (int) $data['target_karyawan_id'];
        if ($targetId === (int) $karyawan->id_karyawan) {
            return back()->withErrors(['swap' => 'Tidak bisa menukar jadwal dengan diri sendiri.']);
        }

        $today = now()->startOfDay();
        $targetDate = Carbon::parse($tgl)->startOfDay();
        if ($targetDate->lte($today)) {
            return back()->withErrors(['swap' => 'Tidak bisa menukar jadwal untuk hari ini atau tanggal yang sudah lewat.']);
        }
        $targetDate2 = Carbon::parse($targetTanggal)->startOfDay();
        if ($targetDate2->lte($today)) {
            return back()->withErrors(['swap' => 'Tanggal target harus di masa depan.']);
        }

        $hasSameShift = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', $targetTanggal)
            ->where('shift_ke', $targetShift)
            ->exists();
        if ($hasSameShift) {
            return back()->withErrors(['swap' => 'Kamu sudah punya shift ini di tanggal target.']);
        }

        $minDays = max(0, (int) ($setting->self_schedule_cancel_min_days_before ?? 0));
        if ($minDays > 0) {
            $diff = (int) $today->diffInDays($targetDate, false);
            if ($diff < $minDays) {
                return back()->withErrors(['swap' => "Tukar jadwal minimal {$minDays} hari sebelum tanggal jadwal."]);
            }
            $diff2 = (int) $today->diffInDays($targetDate2, false);
            if ($diff2 < $minDays) {
                return back()->withErrors(['swap' => "Tukar jadwal minimal {$minDays} hari sebelum tanggal target."]);
            }
        }

        $myRow = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', $tgl)
            ->first();
        if (! $myRow) {
            return back()->withErrors(['swap' => 'Kamu tidak punya jadwal pada tanggal ini.']);
        }

        $targetRow = JadwalKaryawan::query()
            ->where('id_karyawan', $targetId)
            ->whereDate('tanggal', $targetTanggal)
            ->where('shift_ke', $targetShift)
            ->first();
        if (! $targetRow) {
            return back()->withErrors(['swap' => 'Staff yang dipilih tidak punya jadwal pada shift tersebut.']);
        }
        $targetHasSameShift = JadwalKaryawan::query()
            ->where('id_karyawan', $targetId)
            ->whereDate('tanggal', $tgl)
            ->where('shift_ke', (int) ($myRow->shift_ke ?? 0))
            ->exists();
        if ($targetHasSameShift) {
            return back()->withErrors(['swap' => 'Staff target sudah punya shift ini di tanggal kamu.']);
        }

        $swap = JadwalTukarRequest::query()->create([
            'tanggal' => $tgl,
            'from_tanggal' => $tgl,
            'to_tanggal' => $targetTanggal,
            'from_shift' => (int) ($myRow->shift_ke ?? 0),
            'to_shift' => (int) ($targetRow->shift_ke ?? 0),
            'from_karyawan_id' => (int) $karyawan->id_karyawan,
            'to_karyawan_id' => $targetId,
            'status' => 'pending',
            'staff_status' => 'pending',
        ]);

        $pesan = trim((string) ($data['pesan'] ?? ''));
        $targetStaff = Karyawan::query()
            ->where('id_karyawan', $targetId)
            ->first(['id_karyawan', 'nama_karyawan', 'jabatan']);
        $defaultMsg = $this->formatSwapMessage(
            'Permintaan Tukar Shift',
            $tgl,
            (int) ($myRow->shift_ke ?? 0),
            $targetTanggal,
            $targetShift,
            $karyawan,
            $targetStaff,
            $pesan !== '' ? $pesan : null
        );
        StaffMessage::query()->create([
            'thread_type' => 'swap',
            'thread_id' => (int) $swap->id,
            'sender_role' => 'staff',
            'sender_karyawan_id' => (int) $karyawan->id_karyawan,
            'message' => $defaultMsg,
        ]);
        $adminChatBase = [
            'thread_type' => 'admin_chat',
            'sender_role' => 'staff',
            'sender_karyawan_id' => (int) $karyawan->id_karyawan,
            'message' => $defaultMsg,
            'meta' => [
                'action' => [
                    'type' => 'swap',
                    'id' => (int) $swap->id,
                ],
            ],
        ];
        StaffMessage::query()->create(array_merge($adminChatBase, [
            'thread_id' => (int) $karyawan->id_karyawan,
        ]));
        StaffMessage::query()->create(array_merge($adminChatBase, [
            'thread_id' => (int) $targetId,
        ]));

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.swap.request',
            'Ajukan Tukar Jadwal',
            'Mengajukan tukar jadwal ' . $tgl . ' ke ' . $targetTanggal . '.',
            [
                'tanggal' => $tgl,
                'target_tanggal' => $targetTanggal,
                'shift' => $setting->shiftCodeFor((int) ($myRow->shift_ke ?? 0), $employmentType),
                'target_shift' => $setting->shiftCodeFor($targetShift, $employmentType),
            ],
            'jadwal_tukar_request',
            (int) $swap->id,
            'Permintaan tukar jadwal',
        );

        return back()->with('success', 'Permintaan tukar jadwal berhasil dikirim dan menunggu persetujuan admin.');
    }

    public function approveSwapByStaff(Request $request, JadwalTukarRequest $swap): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        if ((int) $swap->to_karyawan_id !== (int) $karyawan->id_karyawan) {
            return back()->withErrors(['swap' => 'Kamu tidak berhak menyetujui permintaan ini.']);
        }
        if ((string) $swap->status !== 'pending' || (string) $swap->staff_status !== 'pending') {
            return back()->withErrors(['swap' => 'Permintaan ini sudah diproses.']);
        }

        $note = trim((string) $request->input('note', ''));
        $swap->staff_status = 'approved';
        $swap->staff_note = $note !== '' ? $note : null;
        $swap->staff_responded_by = (int) $karyawan->id_karyawan;
        $swap->staff_responded_at = now();
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
            'Respon Staff Target: DISETUJUI',
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
            'sender_role' => 'staff',
            'sender_karyawan_id' => (int) $karyawan->id_karyawan,
            'message' => $msg,
        ]);
        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $swap->from_karyawan_id,
            'sender_role' => 'staff',
            'sender_karyawan_id' => (int) $karyawan->id_karyawan,
            'message' => 'Permintaan tukar disetujui oleh staff target. Menunggu admin.',
            'meta' => [
                'action' => [
                    'type' => 'swap',
                    'id' => (int) $swap->id,
                ],
            ],
        ]);

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.swap.approve',
            'Setujui Tukar Jadwal',
            'Menyetujui permintaan tukar jadwal staff lain.',
            [
                'tanggal' => $fromDate,
                'target_tanggal' => $toDate,
            ],
            'jadwal_tukar_request',
            (int) $swap->id,
            'Persetujuan tukar jadwal',
        );

        return back()->with('success', 'Permintaan tukar kamu setujui. Menunggu admin.');
    }

    public function rejectSwapByStaff(Request $request, JadwalTukarRequest $swap): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        if ((int) $swap->to_karyawan_id !== (int) $karyawan->id_karyawan) {
            return back()->withErrors(['swap' => 'Kamu tidak berhak menolak permintaan ini.']);
        }
        if ((string) $swap->status !== 'pending' || (string) $swap->staff_status !== 'pending') {
            return back()->withErrors(['swap' => 'Permintaan ini sudah diproses.']);
        }

        $note = trim((string) $request->input('note', ''));
        $swap->staff_status = 'rejected';
        $swap->status = 'rejected';
        $swap->staff_note = $note !== '' ? $note : null;
        $swap->staff_responded_by = (int) $karyawan->id_karyawan;
        $swap->staff_responded_at = now();
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
            'Respon Staff Target: DITOLAK',
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
            'sender_role' => 'staff',
            'sender_karyawan_id' => (int) $karyawan->id_karyawan,
            'message' => $msg,
        ]);
        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $swap->from_karyawan_id,
            'sender_role' => 'staff',
            'sender_karyawan_id' => (int) $karyawan->id_karyawan,
            'message' => 'Permintaan tukar ditolak oleh staff target.',
            'meta' => [
                'action' => [
                    'type' => 'swap',
                    'id' => (int) $swap->id,
                ],
            ],
        ]);

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.swap.reject',
            'Tolak Tukar Jadwal',
            'Menolak permintaan tukar jadwal staff lain.',
            [
                'tanggal' => $fromDate,
                'target_tanggal' => $toDate,
            ],
            'jadwal_tukar_request',
            (int) $swap->id,
            'Penolakan tukar jadwal',
        );

        return back()->with('success', 'Permintaan tukar kamu tolak.');
    }
}
