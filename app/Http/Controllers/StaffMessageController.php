<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\JadwalTukarRequest;
use App\Models\LeaveRequest;
use App\Models\StaffMessage;
use App\Models\StaffMessageRead;
use App\Services\StaffActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StaffMessageController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');

        [$threads, $lastMessages, $unreadCounts] = $this->buildThreadsForStaff((int) $karyawan->id_karyawan, true);

        return view('staff.messages.index', [
            'threads' => $threads,
            'lastMessages' => $lastMessages,
            'unreadCounts' => $unreadCounts,
        ]);
    }

    public function show(Request $request, string $type, int $id): View
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $this->assertThreadAccess($type, $id, (int) $karyawan->id_karyawan);

        $includeHidden = $type !== 'admin_chat';
        [$threads, $lastMessages, $unreadCounts] = $this->buildThreadsForStaff((int) $karyawan->id_karyawan, $includeHidden);

        $messages = StaffMessage::query()
            ->where('thread_type', $type)
            ->where('thread_id', $id)
            ->orderBy('created_at')
            ->get();

        $this->markThreadRead(
            $type,
            $id,
            'staff',
            (int) $karyawan->id_karyawan,
            null
        );

        return view('staff.messages.show', [
            'type' => $type,
            'threadId' => $id,
            'messages' => $messages,
            'threads' => $threads,
            'lastMessages' => $lastMessages,
            'unreadCounts' => $unreadCounts,
        ]);
    }

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        /** @var \App\Models\Karyawan|null $karyawan */
        $karyawan = $request->attributes->get('staff_karyawan');
        $this->assertThreadAccess($type, $id, (int) $karyawan->id_karyawan);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        StaffMessage::query()->create([
            'thread_type' => $type,
            'thread_id' => $id,
            'sender_role' => 'staff',
            'sender_karyawan_id' => (int) $karyawan->id_karyawan,
            'message' => (string) $data['message'],
        ]);

        $threadLabel = match ($type) {
            'admin_chat' => 'chat admin',
            'absensi' => 'room absensi',
            'leave' => 'room izin/sakit',
            'swap' => 'room tukar jadwal',
            default => 'percakapan staff',
        };

        app(StaffActivityLogger::class)->log(
            $request,
            $karyawan,
            'staff.message.send',
            'Kirim Pesan',
            'Mengirim pesan ke ' . $threadLabel . '.',
            [
                'thread_type' => $type,
                'message_preview' => \Illuminate\Support\Str::limit((string) $data['message'], 80),
            ],
            $type,
            $id,
            ucfirst(str_replace('_', ' ', $type)),
        );

        return back()->with('success', 'Pesan terkirim.');
    }

    private function resolveLastMessages(Collection $threads): array
    {
        $groupedIds = $threads->groupBy('type')->map(fn ($items) => $items->pluck('id')->all())->all();
        $lastMessages = [];

        foreach ($groupedIds as $type => $ids) {
            if (empty($ids)) {
                continue;
            }
            $messages = StaffMessage::query()
                ->where('thread_type', $type)
                ->whereIn('thread_id', $ids)
                ->orderByDesc('created_at')
                ->get();

            foreach ($messages as $msg) {
                $key = $type . ':' . (int) $msg->thread_id;
                if (! isset($lastMessages[$key])) {
                    $lastMessages[$key] = $msg;
                }
            }
        }

        return $lastMessages;
    }

    private function buildThreadsForStaff(int $karyawanId, bool $includeHidden): array
    {
        $leaveRows = collect();
        $swapRows = collect();
        $absensiRows = collect();
        if ($includeHidden) {
            $leaveRows = LeaveRequest::query()
                ->where('id_karyawan', $karyawanId)
                ->get();

            if (class_exists(JadwalTukarRequest::class)) {
                $swapRows = JadwalTukarRequest::query()
                    ->where('from_karyawan_id', $karyawanId)
                    ->orWhere('to_karyawan_id', $karyawanId)
                    ->get();
            }
        }

        if ($includeHidden) {
            $absensiRows = Absensi::query()
                ->where('id_karyawan', $karyawanId)
                ->orderByDesc('tanggal')
                ->limit(30)
                ->get([
                    'id_absensi',
                    'tanggal',
                    'waktu_masuk',
                    'waktu_pulang',
                    'verification_status',
                    'checkout_correction_status',
                ]);
        } else {
            $absensiThreadIds = StaffMessage::query()
                ->where('thread_type', 'absensi')
                ->orderByDesc('created_at')
                ->pluck('thread_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($absensiThreadIds->isNotEmpty()) {
                $absensiRows = Absensi::query()
                    ->where('id_karyawan', $karyawanId)
                    ->whereIn('id_absensi', $absensiThreadIds)
                    ->orderByDesc('tanggal')
                    ->get([
                        'id_absensi',
                        'tanggal',
                        'waktu_masuk',
                        'waktu_pulang',
                        'verification_status',
                        'checkout_correction_status',
                    ]);
            }
        }

        $threads = collect();

        if ($includeHidden) {
            foreach ($leaveRows as $leave) {
                $jenis = strtolower((string) ($leave->jenis ?? ''));
                $jenisLabel = $jenis === 'sakit' ? 'Sakit' : 'Izin';
                $threads->push([
                'type' => 'leave',
                'id' => (int) $leave->id,
                'title' => 'Pengajuan ' . $jenisLabel,
                'subtitle' => ($leave->tanggal_awal?->format('Y-m-d') ?? '-') . ' s/d ' . ($leave->tanggal_akhir?->format('Y-m-d') ?? '-'),
                'status' => (string) $leave->status,
                ]);
            }

            foreach ($swapRows as $swap) {
                $threads->push([
                'type' => 'swap',
                'id' => (int) $swap->id,
                'title' => 'Tukar Shift',
                'subtitle' => ($swap->from_tanggal?->format('Y-m-d') ?? '-') . ' s/d ' . ($swap->to_tanggal?->format('Y-m-d') ?? '-'),
                'status' => (string) ($swap->status ?? 'pending'),
                ]);
            }
        }

        foreach ($absensiRows as $absensi) {
            $status = 'pending';
            $subtitle = 'Absensi menunggu tindak lanjut admin.';

            if ((string) ($absensi->verification_status ?? '') === 'verified') {
                $status = 'approved';
                $subtitle = 'Absensi tanggal ' . ($absensi->tanggal?->translatedFormat('d M Y') ?? '-') . ' sudah diverifikasi admin.';
            } elseif ((string) ($absensi->verification_status ?? '') === 'rejected') {
                $status = 'rejected';
                $subtitle = 'Absensi tanggal ' . ($absensi->tanggal?->translatedFormat('d M Y') ?? '-') . ' perlu diperbaiki.';
            } elseif ((string) ($absensi->checkout_correction_status ?? '') === Absensi::CHECKOUT_CORRECTION_REQUESTED) {
                $subtitle = 'Koreksi jam pulang sedang menunggu keputusan admin.';
            } elseif ((string) ($absensi->checkout_correction_status ?? '') === Absensi::CHECKOUT_CORRECTION_REJECTED) {
                $status = 'rejected';
                $subtitle = 'Koreksi jam pulang ditolak. Cek catatan admin di percakapan ini.';
            } elseif ($absensi->waktu_masuk && ! $absensi->waktu_pulang) {
                $subtitle = 'Jam pulang belum lengkap dan perlu dikoreksi.';
            } else {
                $subtitle = 'Absensi tanggal ' . ($absensi->tanggal?->translatedFormat('d M Y') ?? '-') . ' siap diverifikasi.';
            }

            $threads->push([
                'type' => 'absensi',
                'id' => (int) $absensi->id_absensi,
                'title' => 'Absensi ' . ($absensi->tanggal?->translatedFormat('d M Y') ?? '-'),
                'subtitle' => $subtitle,
                'status' => $status,
            ]);
        }

        $threads->push([
            'type' => 'admin_chat',
            'id' => $karyawanId,
            'title' => 'Chat Admin',
            'subtitle' => 'Balasan admin, izin, dan tukar shift ada di sini.',
            'status' => '',
        ]);

        $lastMessages = $this->resolveLastMessages($threads);
        $unreadCounts = $this->resolveUnreadCounts($threads, 'staff', $karyawanId, null);

        return [$threads, $lastMessages, $unreadCounts];
    }

    private function resolveUnreadCounts(Collection $threads, string $role, ?int $karyawanId, ?int $userId): array
    {
        if ($threads->isEmpty()) {
            return [];
        }

        $readsQuery = StaffMessageRead::query()->where('reader_role', $role);
        if ($role === 'staff') {
            $readsQuery->where('reader_karyawan_id', $karyawanId);
        } elseif ($role === 'admin') {
            $readsQuery->where('reader_user_id', $userId);
        }

        $reads = $readsQuery->get()->keyBy(function ($row) {
            return $row->thread_type . ':' . (int) $row->thread_id;
        });

        $groupedIds = $threads->groupBy('type')->map(fn ($items) => $items->pluck('id')->all())->all();
        $unreadCounts = [];

        foreach ($groupedIds as $type => $ids) {
            if (empty($ids)) {
                continue;
            }

            $messages = StaffMessage::query()
                ->where('thread_type', $type)
                ->whereIn('thread_id', $ids)
                ->orderBy('created_at')
                ->get(['thread_id', 'created_at', 'sender_role', 'sender_karyawan_id']);

            foreach ($messages as $msg) {
                if ($role === 'admin') {
                    if ((string) $msg->sender_role !== 'staff') {
                        continue;
                    }
                } else {
                    if ((string) $msg->sender_role === 'staff' && (int) $msg->sender_karyawan_id === (int) $karyawanId) {
                        continue;
                    }
                }

                $key = $type . ':' . (int) $msg->thread_id;
                $lastRead = $reads[$key]->last_read_at ?? null;
                if ($lastRead instanceof Carbon && $msg->created_at <= $lastRead) {
                    continue;
                }

                $unreadCounts[$key] = ($unreadCounts[$key] ?? 0) + 1;
            }
        }

        return $unreadCounts;
    }

    private function markThreadRead(string $type, int $id, string $role, ?int $karyawanId, ?int $userId): void
    {
        StaffMessageRead::query()->updateOrCreate(
            [
                'thread_type' => $type,
                'thread_id' => $id,
                'reader_role' => $role,
                'reader_karyawan_id' => $karyawanId,
                'reader_user_id' => $userId,
            ],
            [
                'last_read_at' => now(),
            ]
        );
    }

    private function assertThreadAccess(string $type, int $id, int $karyawanId): void
    {
        if ($type === 'leave') {
            $exists = LeaveRequest::query()
                ->where('id', $id)
                ->where('id_karyawan', $karyawanId)
                ->exists();
            abort_unless($exists, 404);
            return;
        }

        if ($type === 'swap' && class_exists(JadwalTukarRequest::class)) {
            $exists = JadwalTukarRequest::query()
                ->where('id', $id)
                ->where(function ($q) use ($karyawanId): void {
                    $q->where('from_karyawan_id', $karyawanId)->orWhere('to_karyawan_id', $karyawanId);
                })
                ->exists();
            abort_unless($exists, 404);
            return;
        }

        if ($type === 'absensi') {
            $exists = Absensi::query()
                ->where('id_absensi', $id)
                ->where('id_karyawan', $karyawanId)
                ->exists();
            abort_unless($exists, 404);
            return;
        }

        if ($type === 'admin_chat') {
            abort_unless($id === $karyawanId, 404);
            return;
        }

        abort(404);
    }
}

