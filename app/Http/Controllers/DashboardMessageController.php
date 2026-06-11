<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\JadwalTukarRequest;
use App\Models\LeaveRequest;
use App\Models\StaffMessage;
use App\Models\StaffMessageRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardMessageController extends Controller
{
    public function show(string $type, int $id): View
    {
        $this->assertThreadExists($type, $id);

        $messages = StaffMessage::query()
            ->where('thread_type', $type)
            ->where('thread_id', $id)
            ->orderBy('created_at')
            ->get();

        StaffMessageRead::query()->updateOrCreate(
            [
                'thread_type' => $type,
                'thread_id' => $id,
                'reader_role' => 'admin',
                'reader_karyawan_id' => null,
                'reader_user_id' => (int) auth()->id(),
            ],
            [
                'last_read_at' => now(),
            ]
        );

        return view('dashboard.messages.show', [
            'type' => $type,
            'threadId' => $id,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $this->assertThreadExists($type, $id);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        StaffMessage::query()->create([
            'thread_type' => $type,
            'thread_id' => $id,
            'sender_role' => 'admin',
            'sender_user_id' => (int) auth()->id(),
            'message' => (string) $data['message'],
        ]);

        return back()->with('success', 'Pesan terkirim.');
    }

    private function assertThreadExists(string $type, int $id): void
    {
        if ($type === 'leave') {
            abort_unless(LeaveRequest::query()->where('id', $id)->exists(), 404);
            return;
        }
        if ($type === 'swap') {
            abort_unless(JadwalTukarRequest::query()->where('id', $id)->exists(), 404);
            return;
        }
        if ($type === 'absensi') {
            abort_unless(Absensi::query()->where('id_absensi', $id)->exists(), 404);
            return;
        }

        abort(404);
    }
}
