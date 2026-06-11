<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Models\JadwalTukarRequest;
use App\Models\LeaveRequest;
use App\Models\StaffMessage;
use App\Models\StaffMessageRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminChatController extends Controller
{
    public function index(Request $request): View
    {
        $query = Karyawan::query()->orderBy('nama_karyawan');
        if (Schema::hasColumn('karyawan', 'is_active')) {
            $query->where('is_active', true);
        }

        $karyawan = $query->get(['id_karyawan', 'nama_karyawan', 'jabatan']);

        $karyawanIds = $karyawan->pluck('id_karyawan')->map(fn ($id) => (int) $id)->all();
        $lastMessages = $this->resolveLastMessagesForChat($karyawanIds);
        $unreadCounts = $this->resolveUnreadCountsForChat($karyawanIds, (int) auth()->id());

        return view('dashboard.chat.index', [
            'karyawan' => $karyawan,
            'lastMessages' => $lastMessages,
            'unreadCounts' => $unreadCounts,
        ]);
    }

    public function show(Karyawan $karyawan): View
    {
        $listQuery = Karyawan::query()->orderBy('nama_karyawan');
        if (Schema::hasColumn('karyawan', 'is_active')) {
            $listQuery->where('is_active', true);
        }
        $karyawanList = $listQuery->get(['id_karyawan', 'nama_karyawan', 'jabatan']);

        $messages = StaffMessage::query()
            ->where('thread_type', 'admin_chat')
            ->where('thread_id', (int) $karyawan->id_karyawan)
            ->orderBy('created_at')
            ->get();

        $this->markThreadRead(
            'admin_chat',
            (int) $karyawan->id_karyawan,
            'admin',
            null,
            (int) auth()->id()
        );

        $karyawanIds = $karyawanList->pluck('id_karyawan')->map(fn ($id) => (int) $id)->all();
        $lastMessages = $this->resolveLastMessagesForChat($karyawanIds);
        $unreadCounts = $this->resolveUnreadCountsForChat($karyawanIds, (int) auth()->id());

        $swapIds = [];
        $leaveIds = [];
        foreach ($messages as $msg) {
            $action = $msg->meta['action'] ?? null;
            if (! is_array($action)) {
                continue;
            }
            $type = (string) ($action['type'] ?? '');
            $id = (int) ($action['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($type === 'swap') {
                $swapIds[] = $id;
            } elseif ($type === 'leave') {
                $leaveIds[] = $id;
            }
        }
        $swapStatusMap = [];
        if (! empty($swapIds)) {
            $swapStatusMap = JadwalTukarRequest::query()
                ->whereIn('id', array_unique($swapIds))
                ->pluck('status', 'id')
                ->map(fn ($status) => (string) $status)
                ->all();
        }
        $leaveStatusMap = [];
        if (! empty($leaveIds)) {
            $leaveStatusMap = LeaveRequest::query()
                ->whereIn('id', array_unique($leaveIds))
                ->pluck('status', 'id')
                ->map(fn ($status) => (string) $status)
                ->all();
        }

        return view('dashboard.chat.show', [
            'karyawan' => $karyawan,
            'karyawanList' => $karyawanList,
            'messages' => $messages,
            'lastMessages' => $lastMessages,
            'unreadCounts' => $unreadCounts,
            'swapStatusMap' => $swapStatusMap,
            'leaveStatusMap' => $leaveStatusMap,
        ]);
    }

    public function store(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $karyawan->id_karyawan,
            'sender_role' => 'admin',
            'sender_user_id' => (int) auth()->id(),
            'message' => (string) $data['message'],
        ]);

        return back()->with('success', 'Pesan terkirim.');
    }

    private function resolveLastMessagesForChat(array $karyawanIds): array
    {
        if (empty($karyawanIds)) {
            return [];
        }

        $messages = StaffMessage::query()
            ->where('thread_type', 'admin_chat')
            ->whereIn('thread_id', $karyawanIds)
            ->orderByDesc('created_at')
            ->get();

        $lastMessages = [];
        foreach ($messages as $msg) {
            $key = 'admin_chat:' . (int) $msg->thread_id;
            if (! isset($lastMessages[$key])) {
                $lastMessages[$key] = $msg;
            }
        }

        return $lastMessages;
    }

    private function resolveUnreadCountsForChat(array $karyawanIds, int $adminUserId): array
    {
        if (empty($karyawanIds)) {
            return [];
        }

        $reads = StaffMessageRead::query()
            ->where('reader_role', 'admin')
            ->where('reader_user_id', $adminUserId)
            ->get()
            ->keyBy(function ($row) {
                return $row->thread_type . ':' . (int) $row->thread_id;
            });

        $messages = StaffMessage::query()
            ->where('thread_type', 'admin_chat')
            ->whereIn('thread_id', $karyawanIds)
            ->where('sender_role', 'staff')
            ->orderBy('created_at')
            ->get(['thread_id', 'created_at']);

        $unreadCounts = [];
        foreach ($messages as $msg) {
            $key = 'admin_chat:' . (int) $msg->thread_id;
            $lastRead = $reads[$key]->last_read_at ?? null;
            if ($lastRead instanceof Carbon && $msg->created_at <= $lastRead) {
                continue;
            }
            $unreadCounts[$key] = ($unreadCounts[$key] ?? 0) + 1;
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
}
