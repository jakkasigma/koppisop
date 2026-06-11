<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\AnnouncementAdminRead;
use App\Models\Karyawan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $allowedStatuses = ['all', 'active', 'inactive', 'aktif', 'berjalan', 'berakhir', 'terjadwal'];
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = Announcement::query()->orderByDesc('published_at')->orderByDesc('id');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $announcementNow = now();
        $items = $query->limit(50)->get()
            ->map(function (Announcement $announcement) use ($announcementNow) {
                $promoInfo = $announcement->resolvePromoStatus($announcementNow);
            $announcement->promo_status = $promoInfo['status'] === 'Akan Mulai' ? 'Terjadwal' : $promoInfo['status'];
            $announcement->promo_end_at = $promoInfo['end_at'];
            return $announcement;
        });
        if (in_array($status, ['aktif', 'berjalan', 'berakhir', 'terjadwal'], true)) {
            $map = [
                'aktif' => 'Aktif',
                'berjalan' => 'Aktif',
                'berakhir' => 'Berakhir',
                'terjadwal' => 'Terjadwal',
            ];
            $target = $map[$status] ?? null;
            if ($target) {
                $items = $items->filter(fn (Announcement $announcement): bool => ($announcement->promo_status ?? null) === $target)->values();
            }
        }

        $readCounts = [];
        $adminReads = [];
        $readersByAnnouncement = [];
        if ($items->count() > 0 && Schema::hasTable('announcement_reads')) {
            $ids = $items->pluck('id')->all();
            $readCounts = AnnouncementRead::query()
                ->select(['announcement_id', DB::raw('COUNT(*) as total')])
                ->whereIn('announcement_id', $ids)
                ->groupBy('announcement_id')
                ->pluck('total', 'announcement_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $reads = AnnouncementRead::query()
                ->whereIn('announcement_id', $ids)
                ->leftJoin('karyawan', 'announcement_reads.karyawan_id', '=', 'karyawan.id_karyawan')
                ->orderByDesc('announcement_reads.read_at')
                ->get([
                    'announcement_reads.announcement_id',
                    'announcement_reads.read_at',
                    'karyawan.nama_karyawan',
                    'karyawan.jabatan',
                ]);
            foreach ($reads as $read) {
                $announcementId = (int) $read->announcement_id;
                if (! array_key_exists($announcementId, $readersByAnnouncement)) {
                    $readersByAnnouncement[$announcementId] = [];
                }
                $readersByAnnouncement[$announcementId][] = $read;
            }
        }
        if ($items->count() > 0 && Schema::hasTable('announcement_admin_reads')) {
            $userId = (int) (auth()->id() ?? 0);
            if ($userId > 0) {
                $ids = $items->pluck('id')->all();
                $adminReads = AnnouncementAdminRead::query()
                    ->where('user_id', $userId)
                    ->whereIn('announcement_id', $ids)
                    ->pluck('read_at', 'announcement_id')
                    ->map(fn ($v) => $v ? (string) $v : null)
                    ->all();
            }
        }

        return view('dashboard.announcements.index', [
            'items' => $items,
            'status' => $status,
            'readCounts' => $readCounts,
            'adminReads' => $adminReads,
            'readersByAnnouncement' => $readersByAnnouncement,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function create(): View
    {
        $roles = $this->roleOptions();

        return view('dashboard.announcements.create', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
            'target_role' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('announcements', 'public');
        }

        Announcement::query()->create([
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'target_role' => isset($data['target_role']) && trim((string) $data['target_role']) !== '' ? trim((string) $data['target_role']) : null,
            'is_active' => $request->boolean('is_active', true),
            'published_at' => isset($data['published_at']) ? Carbon::parse((string) $data['published_at']) : now(),
            'image_path' => $path,
        ]);

        return redirect()->route('dashboard.announcements.index')->with('success', 'Pengumuman berhasil dibuat.');
    }

    public function show(Announcement $announcement): View
    {
        $readers = collect();
        $adminReadAt = null;
        if (Schema::hasTable('announcement_reads')) {
            $readers = AnnouncementRead::query()
                ->where('announcement_id', (int) $announcement->id)
                ->leftJoin('karyawan', 'announcement_reads.karyawan_id', '=', 'karyawan.id_karyawan')
                ->orderByDesc('announcement_reads.read_at')
                ->get([
                    'announcement_reads.read_at',
                    'karyawan.nama_karyawan',
                    'karyawan.jabatan',
                ]);
        }
        if (Schema::hasTable('announcement_admin_reads')) {
            $userId = (int) (auth()->id() ?? 0);
            if ($userId > 0) {
                $adminReadAt = AnnouncementAdminRead::query()
                    ->where('announcement_id', (int) $announcement->id)
                    ->where('user_id', $userId)
                    ->value('read_at');
            }
        }

        return view('dashboard.announcements.show', [
            'announcement' => $announcement,
            'readers' => $readers,
            'adminReadAt' => $adminReadAt,
        ]);
    }

    public function edit(Announcement $announcement): View
    {
        $roles = $this->roleOptions();

        return view('dashboard.announcements.edit', [
            'announcement' => $announcement,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string'],
            'target_role' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $path = $announcement->image_path;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('announcements', 'public');
        }

        $announcement->update([
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'target_role' => isset($data['target_role']) && trim((string) $data['target_role']) !== '' ? trim((string) $data['target_role']) : null,
            'is_active' => $request->boolean('is_active', true),
            'published_at' => isset($data['published_at']) ? Carbon::parse((string) $data['published_at']) : $announcement->published_at,
            'image_path' => $path,
        ]);

        return redirect()->route('dashboard.announcements.index')->with('success', 'Pengumuman berhasil disimpan.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();
        return redirect()->route('dashboard.announcements.index')->with('success', 'Pengumuman dihapus.');
    }

    public function readByAdmin(Request $request, Announcement $announcement): RedirectResponse
    {
        if (! Schema::hasTable('announcement_admin_reads')) {
            return back();
        }

        $userId = (int) (auth()->id() ?? 0);
        if ($userId <= 0) {
            return back();
        }

        AnnouncementAdminRead::query()->firstOrCreate(
            [
                'announcement_id' => (int) $announcement->id,
                'user_id' => $userId,
            ],
            [
                'read_at' => now(),
            ]
        );

        return back()->with('success', 'Pengumuman ditandai sudah dibaca.');
    }

    private function roleOptions(): array
    {
        if (! Schema::hasTable('karyawan')) {
            return [];
        }

        return Karyawan::query()
            ->select('jabatan')
            ->whereNotNull('jabatan')
            ->where('jabatan', '!=', '')
            ->distinct()
            ->orderBy('jabatan')
            ->pluck('jabatan')
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}
