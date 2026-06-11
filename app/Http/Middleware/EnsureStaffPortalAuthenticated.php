<?php

namespace App\Http\Middleware;

use App\Models\Absensi;
use App\Models\Karyawan;
use App\Models\StaffMessage;
use App\Models\StaffMessageRead;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffPortalAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (int) ($request->session()->get('staff_karyawan_id') ?? 0);
        if ($id <= 0) {
            // Preserve intended URL so after login staff returns to the exact page (absen/jadwal/etc).
            try {
                $request->session()->put('staff_redirect_after_login', url()->full());
            } catch (\Throwable $e) {
                // ignore
            }
            return redirect()->route('staff.login');
        }

        $idleMinutes = 10;
        $now = time();
        $last = (int) ($request->session()->get('staff_last_activity') ?? 0);
        if ($last > 0 && ($now - $last) > ($idleMinutes * 60)) {
            $request->session()->forget(['staff_karyawan_id', 'staff_karyawan_name', 'staff_last_activity']);
            $request->session()->regenerate();
            return redirect()
                ->route('staff.login')
                ->withErrors(['login' => 'Sesi kamu berakhir karena tidak ada aktivitas. Silakan login lagi.']);
        }

        $karyawan = Karyawan::query()->where('id_karyawan', $id)->first();
        if (! $karyawan || (property_exists($karyawan, 'is_active') && $karyawan->is_active === false)) {
            $request->session()->forget(['staff_karyawan_id', 'staff_karyawan_name', 'staff_last_activity']);
            $request->session()->regenerate();
            return redirect()->route('staff.login');
        }

        $request->session()->put('staff_last_activity', $now);
        // Make it available to controllers/views if needed.
        $request->attributes->set('staff_karyawan', $karyawan);
        $request->attributes->set('staff_unread_messages', $this->resolveUnreadMessages((int) $karyawan->id_karyawan));

        return $next($request);
    }

    private function resolveUnreadMessages(int $karyawanId): int
    {
        if ($karyawanId <= 0) {
            return 0;
        }

        if (! Schema::hasTable('staff_messages') || ! Schema::hasTable('staff_message_reads')) {
            return 0;
        }

        $threads = collect();

        $threads->push(['type' => 'admin_chat', 'id' => $karyawanId]);
        $absensiThreadIds = StaffMessage::query()
            ->where('thread_type', 'absensi')
            ->pluck('thread_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($absensiThreadIds->isNotEmpty()) {
            $absensiRows = Absensi::query()
                ->where('id_karyawan', $karyawanId)
                ->whereIn('id_absensi', $absensiThreadIds)
                ->get(['id_absensi']);

            foreach ($absensiRows as $absensi) {
                $threads->push(['type' => 'absensi', 'id' => (int) $absensi->id_absensi]);
            }
        }
        $threads = $threads->unique(fn ($t) => $t['type'] . ':' . (int) $t['id']);

        $reads = StaffMessageRead::query()
            ->where('reader_role', 'staff')
            ->where('reader_karyawan_id', $karyawanId)
            ->get()
            ->keyBy(fn ($row) => $row->thread_type . ':' . (int) $row->thread_id);

        if ($threads->isEmpty()) {
            return 0;
        }

        $grouped = $threads->groupBy('type')->map(fn ($items) => $items->pluck('id')->all())->all();

        $count = 0;
        foreach ($grouped as $type => $ids) {
            if (empty($ids)) {
                continue;
            }
            $messages = StaffMessage::query()
                ->where('thread_type', $type)
                ->whereIn('thread_id', $ids)
                ->orderBy('created_at')
                ->get(['thread_type', 'thread_id', 'created_at', 'sender_role', 'sender_karyawan_id']);

            foreach ($messages as $msg) {
                if ((string) $msg->sender_role === 'staff' && (int) $msg->sender_karyawan_id === $karyawanId) {
                    continue;
                }
                $key = $msg->thread_type . ':' . (int) $msg->thread_id;
                $lastRead = $reads[$key]->last_read_at ?? null;
                if (! $lastRead || $msg->created_at->gt($lastRead)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
