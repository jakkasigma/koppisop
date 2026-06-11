<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\StaffMessage;
use App\Models\StaffNotification;
use App\Services\StaffNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardLeaveController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->get('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'pending';
        }

        $rows = LeaveRequest::query()
            ->with('karyawan')
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('dashboard.leave.index', [
            'rows' => $rows,
            'status' => $status,
        ]);
    }

    public function show(LeaveRequest $leave): View
    {
        $leave->load('karyawan');

        $messages = StaffMessage::query()
            ->where('thread_type', 'leave')
            ->where('thread_id', (int) $leave->id)
            ->orderBy('created_at')
            ->get();

        return view('dashboard.leave.show', [
            'leave' => $leave,
            'messages' => $messages,
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave): RedirectResponse
    {
        if ($leave->status !== 'pending') {
            return back()->withErrors(['leave' => 'Permintaan ini sudah diproses.']);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $leave->status = 'approved';
        $leave->note = $data['note'] ?? null;
        $leave->approved_by = (int) auth()->id();
        $leave->approved_at = now();
        $leave->save();

        $note = trim((string) ($data['note'] ?? ''));
        $message = $note !== '' ? ('Disetujui: ' . $note) : 'Permintaan disetujui.';
        StaffMessage::query()->create([
            'thread_type' => 'leave',
            'thread_id' => (int) $leave->id,
            'sender_role' => 'admin',
            'sender_user_id' => (int) auth()->id(),
            'message' => $message,
        ]);
        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $leave->id_karyawan,
            'sender_role' => 'admin',
            'sender_user_id' => (int) auth()->id(),
            'message' => 'Izin/Sakit disetujui. ' . ($note !== '' ? $note : ''),
            'meta' => [
                'action' => [
                    'type' => 'leave',
                    'id' => (int) $leave->id,
                ],
            ],
        ]);

        app(StaffNotificationService::class)->notify(
            (int) $leave->id_karyawan,
            StaffNotification::CATEGORY_LEAVE,
            ucfirst((string) ($leave->jenis ?? 'izin')) . ' disetujui',
            'Pengajuan ' . (string) ($leave->jenis ?? 'izin') . ' kamu sudah disetujui admin.' . ($note !== '' ? ' Catatan: ' . $note : ''),
            route('staff.leave.index'),
            'Lihat izin',
            'leave-status:' . (int) $leave->id . ':approved',
            [
                'type' => 'leave',
                'leave_id' => (int) $leave->id,
                'status' => 'approved',
            ]
        );

        return redirect()->route('dashboard.leave.show', $leave)->with('success', 'Permintaan disetujui.');
    }

    public function reject(Request $request, LeaveRequest $leave): RedirectResponse
    {
        if ($leave->status !== 'pending') {
            return back()->withErrors(['leave' => 'Permintaan ini sudah diproses.']);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $leave->status = 'rejected';
        $leave->note = $data['note'] ?? null;
        $leave->approved_by = (int) auth()->id();
        $leave->approved_at = now();
        $leave->save();

        $note = trim((string) ($data['note'] ?? ''));
        $message = $note !== '' ? ('Ditolak: ' . $note) : 'Permintaan ditolak.';
        StaffMessage::query()->create([
            'thread_type' => 'leave',
            'thread_id' => (int) $leave->id,
            'sender_role' => 'admin',
            'sender_user_id' => (int) auth()->id(),
            'message' => $message,
        ]);
        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $leave->id_karyawan,
            'sender_role' => 'admin',
            'sender_user_id' => (int) auth()->id(),
            'message' => 'Izin/Sakit ditolak. ' . ($note !== '' ? $note : ''),
            'meta' => [
                'action' => [
                    'type' => 'leave',
                    'id' => (int) $leave->id,
                ],
            ],
        ]);

        app(StaffNotificationService::class)->notify(
            (int) $leave->id_karyawan,
            StaffNotification::CATEGORY_LEAVE,
            ucfirst((string) ($leave->jenis ?? 'izin')) . ' ditolak',
            'Pengajuan ' . (string) ($leave->jenis ?? 'izin') . ' kamu ditolak admin.' . ($note !== '' ? ' Catatan: ' . $note : ''),
            route('staff.leave.index'),
            'Lihat izin',
            'leave-status:' . (int) $leave->id . ':rejected',
            [
                'type' => 'leave',
                'leave_id' => (int) $leave->id,
                'status' => 'rejected',
            ]
        );

        return redirect()->route('dashboard.leave.show', $leave)->with('success', 'Permintaan ditolak.');
    }

    public function message(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        StaffMessage::query()->create([
            'thread_type' => 'leave',
            'thread_id' => (int) $leave->id,
            'sender_role' => 'admin',
            'sender_user_id' => (int) auth()->id(),
            'message' => (string) $data['message'],
        ]);

        return back()->with('success', 'Pesan terkirim.');
    }
}
