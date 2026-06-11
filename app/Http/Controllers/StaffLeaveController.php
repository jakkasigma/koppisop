<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\JadwalKaryawan;
use App\Models\StaffMessage;
use App\Services\StaffActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StaffLeaveController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        $rows = LeaveRequest::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->orderByDesc('created_at')
            ->get();

        $leaveIds = $rows->pluck('id')->all();
        $lastMessages = [];
        if (! empty($leaveIds)) {
            $messages = StaffMessage::query()
                ->where('thread_type', 'leave')
                ->whereIn('thread_id', $leaveIds)
                ->orderByDesc('created_at')
                ->get();

            foreach ($messages as $msg) {
                $threadId = (int) $msg->thread_id;
                if (! isset($lastMessages[$threadId])) {
                    $lastMessages[$threadId] = $msg;
                }
            }
        }

        $today = now()->toDateString();
        $rangeEnd = now()->addDays(60)->toDateString();
        $jadwalOptions = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', '>=', $today)
            ->whereDate('tanggal', '<=', $rangeEnd)
            ->orderBy('tanggal')
            ->get(['tanggal', 'shift_ke']);

        return view('staff.leave.index', [
            'karyawan' => $karyawan,
            'rows' => $rows,
            'lastMessages' => $lastMessages,
            'jadwalOptions' => $jadwalOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        $data = $request->validate([
            'jenis' => ['required', 'in:izin,sakit'],
            'tanggal_pilihan' => ['required', 'array', 'min:1'],
            'tanggal_pilihan.*' => ['required', 'date'],
            'alasan' => ['nullable', 'string', 'max:1000'],
            'bukti' => ['nullable', 'file', 'max:2048'],
            'pesan' => ['nullable', 'string', 'max:1000'],
        ]);

        $selectedDates = array_values(array_unique(array_filter($data['tanggal_pilihan'] ?? [])));
        if (count($selectedDates) === 0) {
            return back()->withErrors(['tanggal_pilihan' => 'Pilih minimal satu tanggal jadwal.'])->withInput();
        }

        $today = now()->toDateString();
        foreach ($selectedDates as $d) {
            $dateStr = Carbon::parse((string) $d)->toDateString();
            if ($dateStr < $today) {
                return back()->withErrors(['tanggal_pilihan' => 'Tidak bisa memilih tanggal yang sudah lewat.'])->withInput();
            }
        }

        $jadwalRows = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereIn('tanggal', $selectedDates)
            ->get(['tanggal', 'shift_ke']);
        $jadwalByDate = $jadwalRows->keyBy(fn ($r) => $r->tanggal?->format('Y-m-d') ?? '');
        if ($jadwalByDate->count() !== count($selectedDates)) {
            return back()->withErrors(['tanggal_pilihan' => 'Tanggal yang dipilih harus sesuai jadwal masuk kamu.'])->withInput();
        }

        $path = null;
        if ($request->hasFile('bukti')) {
            $path = $request->file('bukti')->storePublicly('leave-bukti', 'public');
        }

        sort($selectedDates);
        $ranges = [];
        $rangeStart = null;
        $prev = null;
        foreach ($selectedDates as $d) {
            $cur = Carbon::parse((string) $d)->toDateString();
            if ($rangeStart === null) {
                $rangeStart = $cur;
                $prev = $cur;
                continue;
            }
            $prevDate = Carbon::parse($prev)->addDay()->toDateString();
            if ($cur === $prevDate) {
                $prev = $cur;
                continue;
            }
            $ranges[] = [$rangeStart, $prev];
            $rangeStart = $cur;
            $prev = $cur;
        }
        if ($rangeStart !== null) {
            $ranges[] = [$rangeStart, $prev];
        }

        $leaves = [];
        foreach ($ranges as [$start, $end]) {
            $leaves[] = LeaveRequest::query()->create([
                'id_karyawan' => (int) $karyawan->id_karyawan,
                'jenis' => (string) $data['jenis'],
                'tanggal_awal' => $start,
                'tanggal_akhir' => $end,
                'alasan' => $data['alasan'] ?? null,
                'bukti_path' => $path,
                'status' => 'pending',
            ]);
        }

        $pesan = trim((string) ($data['pesan'] ?? ''));
        $jenisLabel = strtoupper((string) $data['jenis']);
        $listInfo = [];
        foreach ($selectedDates as $d) {
            $row = $jadwalByDate[Carbon::parse($d)->toDateString()] ?? null;
            $shiftNo = $row?->shift_ke ? (int) $row->shift_ke : null;
            $listInfo[] = $d . ($shiftNo ? ' (S' . $shiftNo . ')' : '');
        }
        $summary = $jenisLabel . ': ' . implode(', ', $listInfo);
        $messageText = 'Pengajuan izin/sakit: ' . $summary;
        if ($pesan !== '') {
            $messageText .= ' | Pesan: ' . $pesan;
        }
        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $karyawan->id_karyawan,
            'sender_role' => 'staff',
            'sender_karyawan_id' => (int) $karyawan->id_karyawan,
            'message' => $messageText,
            'meta' => [
                'action' => [
                    'type' => 'leave',
                'id' => (int) ($leaves[0]->id ?? 0),
            ],
        ],
        ]);

        if ($pesan !== '') {
            foreach ($leaves as $leave) {
                StaffMessage::query()->create([
                    'thread_type' => 'leave',
                    'thread_id' => (int) $leave->id,
                    'sender_role' => 'staff',
                    'sender_karyawan_id' => (int) $karyawan->id_karyawan,
                    'message' => $pesan,
                ]);
            }
        }

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.leave.request',
            'Ajukan Izin/Sakit',
            'Mengajukan ' . strtolower((string) $data['jenis']) . ' untuk ' . count($selectedDates) . ' tanggal jadwal.',
            [
                'jenis' => (string) $data['jenis'],
                'jumlah_tanggal' => count($selectedDates),
                'tanggal' => implode(', ', $selectedDates),
            ],
            'leave_request',
            (int) ($leaves[0]->id ?? 0),
            'Pengajuan ' . strtoupper((string) $data['jenis']),
        );

        return back()->with('success', 'Pengajuan izin/sakit berhasil dikirim. Menunggu persetujuan admin.');
    }
}
