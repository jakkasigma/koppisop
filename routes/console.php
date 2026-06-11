<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Announcement;
use App\Models\Diskon;
use App\Models\Karyawan;
use App\Models\PromoBundling;
use App\Models\Absensi;
use App\Models\JadwalKaryawan;
use App\Models\StrukSetting;
use App\Services\DemoWorkforceSeeder;
use App\Services\StaffNotificationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('jadwal:fake {--month= : Format YYYY-MM (default: bulan ini)} {--overwrite : Hapus jadwal bulan tsb dulu} {--full : Jadwalkan semua karyawan aktif setiap hari} {--seed= : Seed angka biar hasil repeatable}', function () {
    $monthOpt = trim((string) ($this->option('month') ?? ''));
    $seedOpt = trim((string) ($this->option('seed') ?? ''));
    $overwrite = (bool) $this->option('overwrite');
    $full = (bool) $this->option('full');

    $month = $monthOpt !== '' ? $monthOpt : now()->format('Y-m');
    if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
        $this->error("Format --month harus YYYY-MM. Contoh: 2026-03");
        return 1;
    }

    try {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
    } catch (\Throwable $e) {
        $this->error("Month tidak valid: {$month}");
        return 1;
    }
    $end = $start->copy()->endOfMonth();

    $activeShiftCount = 2;
    try {
        $setting = StrukSetting::current();
        $activeShiftCount = (int) ($setting->active_shift_count ?? 2);
    } catch (\Throwable $e) {
        $activeShiftCount = 2;
    }
    if ($activeShiftCount < 1 || $activeShiftCount > 3) {
        $activeShiftCount = 2;
    }

    $kq = Karyawan::query();
    if (Schema::hasColumn('karyawan', 'is_active')) {
        $kq->where('is_active', true);
    }
    $karyawan = $kq->orderBy('id_karyawan')->get(['id_karyawan', 'nama_karyawan', 'jabatan']);
    if ($karyawan->isEmpty()) {
        $this->error('Tidak ada karyawan aktif untuk dijadwalkan.');
        return 1;
    }

    $ids = $karyawan->pluck('id_karyawan')->map(fn ($v) => (int) $v)->values()->all();
    $jabatanById = $karyawan->mapWithKeys(function ($k): array {
        return [(int) $k->id_karyawan => strtolower((string) ($k->jabatan ?? ''))];
    })->all();

    if ($overwrite) {
        DB::table('jadwal_karyawan')
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->delete();
    }

    $seedBase = $seedOpt !== '' && ctype_digit($seedOpt) ? (int) $seedOpt : 20260311;

    $inserted = 0;
    $rows = [];

    // Simple weights: shift 2 usually busier than shift 1.
    $weights = $activeShiftCount === 3 ? [1 => 0.30, 2 => 0.40, 3 => 0.30] : [1 => 0.45, 2 => 0.55];

    for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
        $dateStr = $d->toDateString();

        // Deterministic shuffle per date.
        $seed = (int) (abs(crc32($month . '|' . $dateStr)) + $seedBase);
        mt_srand($seed);
        $pool = $ids;
        shuffle($pool);

        $n = count($pool);

        if ($full) {
            // Assign every active staff to exactly one shift per day.
            $cuts = [];
            $acc = 0;
            for ($s = 1; $s <= $activeShiftCount; $s++) {
                $acc += (float) ($weights[$s] ?? (1.0 / $activeShiftCount));
                $cuts[$s] = (int) round($n * $acc);
            }

            foreach ($pool as $idx => $id) {
                $shift = 1;
                for ($s = 1; $s <= $activeShiftCount; $s++) {
                    if ($idx < ($cuts[$s] ?? $n)) {
                        $shift = $s;
                        break;
                    }
                }
                $rows[] = ['tanggal' => $dateStr, 'shift_ke' => $shift, 'id_karyawan' => (int) $id];
            }

            if (count($rows) >= 500) {
                $before = count($rows);
                DB::table('jadwal_karyawan')->insertOrIgnore($rows);
                $inserted += $before;
                $rows = [];
            }

            continue;
        }

        // Daily total scheduled: between 40% and 75% of active staff, at least 1.
        $minTotal = max(1, (int) floor($n * 0.40));
        $maxTotal = max($minTotal, (int) floor($n * 0.75));
        $targetTotal = $n > 1 ? mt_rand($minTotal, $maxTotal) : 1;
        $targetTotal = min($targetTotal, $n);

        // Calculate per-shift counts (at least 1 each).
        $counts = [];
        $sum = 0;
        for ($s = 1; $s <= $activeShiftCount; $s++) {
            $w = (float) ($weights[$s] ?? (1.0 / $activeShiftCount));
            $c = (int) max(1, round($targetTotal * $w));
            $counts[$s] = $c;
            $sum += $c;
        }
        // Adjust to not exceed targetTotal.
        while ($sum > $targetTotal) {
            for ($s = 1; $s <= $activeShiftCount && $sum > $targetTotal; $s++) {
                if ($counts[$s] > 1) {
                    $counts[$s]--;
                    $sum--;
                }
            }
            // Safety: if all are 1 and still over, break.
            if ($sum > $targetTotal && array_sum(array_map(fn ($v) => $v === 1 ? 1 : 0, $counts)) === $activeShiftCount) {
                break;
            }
        }
        // If under targetTotal, add extras to shift 2 then others.
        while ($sum < $targetTotal) {
            $pref = $activeShiftCount >= 2 ? [2, 1, 3] : [1];
            foreach ($pref as $s) {
                if ($sum >= $targetTotal) break;
                if (isset($counts[$s])) {
                    $counts[$s]++;
                    $sum++;
                }
            }
        }

        // Try to ensure at least one "kasir" exists each day (assigned to one shift only).
        $kasirId = null;
        foreach ($pool as $pid) {
            $j = $jabatanById[(int) $pid] ?? '';
            if ($j !== '' && str_contains($j, 'kasir')) {
                $kasirId = (int) $pid;
                break;
            }
        }
        if ($kasirId !== null) {
            // Put kasir into shift 1 on odd days, shift 2 on even days (or shift 1 if only 1 shift).
            $targetShift = $activeShiftCount >= 2 ? (($d->day % 2 === 0) ? 2 : 1) : 1;
            // Remove from pool and reserve.
            $pool = array_values(array_filter($pool, fn ($x) => (int) $x !== $kasirId));
            $counts[$targetShift] = max(1, (int) ($counts[$targetShift] ?? 1));
            $rows[] = ['tanggal' => $dateStr, 'shift_ke' => $targetShift, 'id_karyawan' => $kasirId];
        }

        for ($s = 1; $s <= $activeShiftCount; $s++) {
            $take = (int) ($counts[$s] ?? 0);
            if ($take <= 0) continue;

            // If kasir was already inserted to this shift, reduce one slot.
            if ($kasirId !== null) {
                $targetShift = $activeShiftCount >= 2 ? (($d->day % 2 === 0) ? 2 : 1) : 1;
                if ($s === $targetShift) {
                    $take = max(0, $take - 1);
                }
            }

            for ($i = 0; $i < $take && !empty($pool); $i++) {
                $id = (int) array_shift($pool);
                $rows[] = ['tanggal' => $dateStr, 'shift_ke' => $s, 'id_karyawan' => $id];
            }
        }

        // Insert in chunks to keep memory stable.
        if (count($rows) >= 500) {
            $before = count($rows);
            DB::table('jadwal_karyawan')->insertOrIgnore($rows);
            $inserted += $before;
            $rows = [];
        }
    }

    if (!empty($rows)) {
        $before = count($rows);
        DB::table('jadwal_karyawan')->insertOrIgnore($rows);
        $inserted += $before;
    }

    $this->info("Jadwal fake dibuat untuk {$month} (shift aktif: {$activeShiftCount}).");
    $this->info("Insert attempt: {$inserted} baris" . ($overwrite ? " (overwrite ON)" : " (overwrite OFF)") . ".");
    $this->line("Cek: /dashboard/jadwal lalu pilih bulan {$month}.");

    return 0;
})->purpose('Generate jadwal karyawan fake untuk 1 bulan (untuk testing absensi).');

Artisan::command('promo:expire', function () {
    $today = now()->toDateString();
    $todayObj = Carbon::parse($today)->startOfDay();
    $h3 = $todayObj->copy()->addDays(3)->toDateString();

    $makeAnnouncement = function (string $title, string $body) use ($today): void {
        $exists = Announcement::query()
            ->where('title', $title)
            ->where('body', $body)
            ->whereDate('published_at', $today)
            ->exists();
        if (! $exists) {
            Announcement::create([
                'title' => $title,
                'body' => $body,
                'target_role' => null,
                'is_active' => true,
                'published_at' => now(),
            ]);
        }
    };

    $upcomingDiskon = Diskon::query()
        ->where('status_aktif', true)
        ->whereNotNull('tanggal_mulai')
        ->whereDate('tanggal_mulai', '=', $h3)
        ->get();

    $startingDiskon = Diskon::query()
        ->where('status_aktif', true)
        ->whereNotNull('tanggal_mulai')
        ->whereDate('tanggal_mulai', '=', $today)
        ->get();

    $endingTodayDiskon = Diskon::query()
        ->where('status_aktif', true)
        ->whereNotNull('tanggal_selesai')
        ->whereDate('tanggal_selesai', '=', $today)
        ->get();

    $expiredDiskon = Diskon::query()
        ->where('status_aktif', true)
        ->whereNotNull('tanggal_selesai')
        ->whereDate('tanggal_selesai', '<', $today)
        ->get();

    $upcomingBundling = PromoBundling::query()
        ->where('status_aktif', true)
        ->whereNotNull('tanggal_mulai')
        ->whereDate('tanggal_mulai', '=', $h3)
        ->get();

    $startingBundling = PromoBundling::query()
        ->where('status_aktif', true)
        ->whereNotNull('tanggal_mulai')
        ->whereDate('tanggal_mulai', '=', $today)
        ->get();

    $endingTodayBundling = PromoBundling::query()
        ->where('status_aktif', true)
        ->whereNotNull('tanggal_selesai')
        ->whereDate('tanggal_selesai', '=', $today)
        ->get();

    $expiredBundling = PromoBundling::query()
        ->where('status_aktif', true)
        ->whereNotNull('tanggal_selesai')
        ->whereDate('tanggal_selesai', '<', $today)
        ->get();

    $count = 0;

    foreach ($expiredDiskon as $diskon) {
        $diskon->status_aktif = false;
        $diskon->save();

        $tipe = (string) ($diskon->tipe_diskon ?? '');
        $nama = trim((string) ($diskon->nama_diskon ?? 'Promo'));
        $tipeLabel = match ($tipe) {
            'persen' => 'Diskon Persen',
            'nominal' => 'Diskon Nominal',
            'harga_kategori' => 'Harga Spesial Kategori',
            default => 'Promo',
        };
        $periodeSelesai = $diskon->tanggal_selesai?->format('Y-m-d');

        $bodyParts = array_filter([
            "{$tipeLabel} {$nama}",
            $periodeSelesai ? "Berakhir pada: {$periodeSelesai}" : null,
            'Status: Nonaktif',
        ]);
        $body = implode("\n", $bodyParts);
        $makeAnnouncement('Promo Berakhir', $body);
        $count++;
    }

    foreach ($expiredBundling as $promo) {
        $promo->status_aktif = false;
        $promo->save();

        $nama = trim((string) ($promo->nama_promo ?? 'Bundling'));
        $periodeSelesai = $promo->tanggal_selesai?->format('Y-m-d');

        $bodyParts = array_filter([
            "Bundling {$nama}",
            $periodeSelesai ? "Berakhir pada: {$periodeSelesai}" : null,
            'Status: Nonaktif',
        ]);
        $body = implode("\n", $bodyParts);
        $makeAnnouncement('Promo Bundling Berakhir', $body);
        $count++;
    }

    foreach ($upcomingDiskon as $diskon) {
        $tipe = (string) ($diskon->tipe_diskon ?? '');
        $nama = trim((string) ($diskon->nama_diskon ?? 'Promo'));
        $tipeLabel = match ($tipe) {
            'persen' => 'Diskon Persen',
            'nominal' => 'Diskon Nominal',
            'harga_kategori' => 'Harga Spesial Kategori',
            default => 'Promo',
        };
        $mulai = $diskon->tanggal_mulai?->format('Y-m-d');
        $bodyParts = array_filter([
            "{$tipeLabel} {$nama}",
            $mulai ? "Mulai pada: {$mulai}" : null,
            'H-3 hari sebelum dimulai',
        ]);
        $makeAnnouncement('Promo Segera Hadir', implode("\n", $bodyParts));
    }

    foreach ($startingDiskon as $diskon) {
        $tipe = (string) ($diskon->tipe_diskon ?? '');
        $nama = trim((string) ($diskon->nama_diskon ?? 'Promo'));
        $tipeLabel = match ($tipe) {
            'persen' => 'Diskon Persen',
            'nominal' => 'Diskon Nominal',
            'harga_kategori' => 'Harga Spesial Kategori',
            default => 'Promo',
        };
        $mulai = $diskon->tanggal_mulai?->format('Y-m-d');
        $bodyParts = array_filter([
            "{$tipeLabel} {$nama}",
            $mulai ? "Mulai pada: {$mulai}" : null,
            'Status: Aktif',
        ]);
        $makeAnnouncement('Promo Dimulai', implode("\n", $bodyParts));
    }

    foreach ($endingTodayDiskon as $diskon) {
        $tipe = (string) ($diskon->tipe_diskon ?? '');
        $nama = trim((string) ($diskon->nama_diskon ?? 'Promo'));
        $tipeLabel = match ($tipe) {
            'persen' => 'Diskon Persen',
            'nominal' => 'Diskon Nominal',
            'harga_kategori' => 'Harga Spesial Kategori',
            default => 'Promo',
        };
        $selesai = $diskon->tanggal_selesai?->format('Y-m-d');
        $bodyParts = array_filter([
            "{$tipeLabel} {$nama}",
            $selesai ? "Berakhir hari ini: {$selesai}" : null,
        ]);
        $makeAnnouncement('Promo Berakhir Hari Ini', implode("\n", $bodyParts));
    }

    foreach ($upcomingBundling as $promo) {
        $nama = trim((string) ($promo->nama_promo ?? 'Bundling'));
        $mulai = $promo->tanggal_mulai?->format('Y-m-d');
        $bodyParts = array_filter([
            "Bundling {$nama}",
            $mulai ? "Mulai pada: {$mulai}" : null,
            'H-3 hari sebelum dimulai',
        ]);
        $makeAnnouncement('Promo Bundling Segera Hadir', implode("\n", $bodyParts));
    }

    foreach ($startingBundling as $promo) {
        $nama = trim((string) ($promo->nama_promo ?? 'Bundling'));
        $mulai = $promo->tanggal_mulai?->format('Y-m-d');
        $bodyParts = array_filter([
            "Bundling {$nama}",
            $mulai ? "Mulai pada: {$mulai}" : null,
            'Status: Aktif',
        ]);
        $makeAnnouncement('Promo Bundling Dimulai', implode("\n", $bodyParts));
    }

    foreach ($endingTodayBundling as $promo) {
        $nama = trim((string) ($promo->nama_promo ?? 'Bundling'));
        $selesai = $promo->tanggal_selesai?->format('Y-m-d');
        $bodyParts = array_filter([
            "Bundling {$nama}",
            $selesai ? "Berakhir hari ini: {$selesai}" : null,
        ]);
        $makeAnnouncement('Promo Bundling Berakhir Hari Ini', implode("\n", $bodyParts));
    }

    $this->info("Promo expire check selesai. Total dinonaktifkan: {$count}.");
})->purpose('Nonaktifkan promo yang sudah lewat tanggal selesai dan buat pengumuman otomatis.');

Schedule::command('promo:expire')->dailyAt('00:05');

Artisan::command('absensi:purge-selfies {--days=7}', function () {
    if (! Schema::hasTable('absensi')) {
        $this->error('Tabel absensi belum tersedia.');
        return 1;
    }
    if (! Schema::hasColumn('absensi', 'selfie_path')) {
        $this->error('Kolom selfie_path tidak ditemukan di tabel absensi.');
        return 1;
    }

    $days = (int) ($this->option('days') ?? 7);
    $days = max(1, min(90, $days));
    $cutoff = now()->subDays($days);

    $rows = Absensi::query()
        ->whereNotNull('selfie_path')
        ->whereDate('tanggal', '<', $cutoff->toDateString())
        ->get(['id', 'selfie_path']);

    $deletedFiles = 0;
    $updated = 0;

    foreach ($rows as $row) {
        $path = trim((string) ($row->selfie_path ?? ''));
        if ($path !== '') {
            try {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    $deletedFiles++;
                }
            } catch (\Throwable $e) {
                // Continue; we still want to clean DB to avoid broken links.
            }
        }

        $row->selfie_path = null;
        $row->save();
        $updated++;
    }

    $this->info("Purge selfie selesai. Records: {$updated}, files deleted: {$deletedFiles}, cutoff < {$cutoff->toDateString()}.");
})->purpose('Hapus file selfie absensi yang sudah lewat (default 7 hari), data absensi tetap ada.');

Schedule::command('absensi:purge-selfies')->dailyAt('00:10');

Artisan::command('absensi:mark-alpa', function () {
    if (! Schema::hasTable('absensi') || ! Schema::hasTable('jadwal_karyawan')) {
        $this->error('Tabel absensi atau jadwal_karyawan belum tersedia.');
        return 1;
    }

    $setting = StrukSetting::current();
    $shiftStarts = [
        1 => (string) ($setting->shift1_start_time ?? '07:00'),
        2 => (string) ($setting->shift2_start_time ?? '15:00'),
        3 => (string) ($setting->shift3_start_time ?? '23:00'),
    ];
    $after = max(0, (int) ($setting->absensi_checkin_after_minutes ?? 60));

    $now = now();
    $dates = [
        $now->copy()->subDay()->toDateString(),
        $now->toDateString(),
    ];

    $inserted = 0;
    foreach ($dates as $dateStr) {
        $jadwal = JadwalKaryawan::query()
            ->whereDate('tanggal', $dateStr)
            ->get(['id_karyawan', 'shift_ke']);

        foreach ($jadwal as $j) {
            $shiftNo = (int) ($j->shift_ke ?? 0);
            if ($shiftNo <= 0) continue;

            $startAt = Carbon::parse($dateStr . ' ' . ($shiftStarts[$shiftNo] ?? '07:00') . ':00');
            $closeAt = $startAt->copy()->addMinutes($after);
            if ($now->lte($closeAt)) {
                continue;
            }

            $exists = Absensi::query()
                ->where('id_karyawan', (int) $j->id_karyawan)
                ->whereDate('tanggal', $dateStr)
                ->exists();
            if ($exists) continue;

            Absensi::query()->create([
                'id_karyawan' => (int) $j->id_karyawan,
                'tanggal' => $dateStr,
                'waktu_masuk' => null,
                'waktu_pulang' => null,
                'catatan' => 'Alpa otomatis (tidak absen).',
                'status' => 'alpa',
                'absensi_source' => null,
                'shift_no' => $shiftNo,
                'selfie_path' => null,
                'geo_lat' => null,
                'geo_lng' => null,
                'geo_accuracy_m' => null,
            ]);
            $inserted++;
        }
    }

    $this->info("Absensi alpa otomatis dibuat: {$inserted} baris.");
    return 0;
})->purpose('Buat otomatis absensi alpa untuk karyawan yang terjadwal namun tidak absen sampai batas waktu.');

Schedule::command('absensi:mark-alpa')->everyThirtyMinutes();

Artisan::command('absensi:reset-week {--seed=}', function () {
    if (! Schema::hasTable('absensi') || ! Schema::hasTable('jadwal_karyawan')) {
        $this->error('Tabel absensi atau jadwal_karyawan belum tersedia.');
        return 1;
    }

    $setting = StrukSetting::current();
    $shiftStarts = [
        1 => (string) ($setting->shift1_start_time ?? '07:00'),
        2 => (string) ($setting->shift2_start_time ?? '15:00'),
        3 => (string) ($setting->shift3_start_time ?? '23:00'),
    ];
    $tolerance = max(0, (int) ($setting->absensi_late_tolerance_minutes ?? 10));
    $before = max(0, (int) ($setting->absensi_checkin_before_minutes ?? 30));
    $after = max(0, (int) ($setting->absensi_checkin_after_minutes ?? 60));

    $seedOpt = trim((string) ($this->option('seed') ?? ''));
    $seed = $seedOpt !== '' && ctype_digit($seedOpt) ? (int) $seedOpt : (int) (abs(crc32(now()->format('Y-m-d'))));
    mt_srand($seed);

    DB::table('absensi')->delete();

    $start = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
    $end = now()->endOfWeek(Carbon::SUNDAY)->startOfDay();

    $activeKaryawanIds = Karyawan::query()
        ->when(Schema::hasColumn('karyawan', 'is_active'), fn ($q) => $q->where('is_active', true))
        ->pluck('id_karyawan')
        ->map(fn ($v) => (int) $v)
        ->values()
        ->all();

    $rows = [];
    $total = 0;

    for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
        $dateStr = $d->toDateString();

        $jadwal = JadwalKaryawan::query()
            ->whereDate('tanggal', $dateStr)
            ->get(['id_karyawan', 'shift_ke']);

        $scheduledIds = $jadwal->pluck('id_karyawan')->map(fn ($v) => (int) $v)->all();
        $scheduledSet = array_fill_keys($scheduledIds, true);

        foreach ($jadwal as $j) {
            $karyawanId = (int) $j->id_karyawan;
            $shiftNo = (int) ($j->shift_ke ?? 0);
            if ($shiftNo <= 0) {
                continue;
            }

            // 15% skip (belum absen)
            if (mt_rand(1, 100) <= 15) {
                continue;
            }

            $startAt = Carbon::parse($dateStr . ' ' . ($shiftStarts[$shiftNo] ?? '07:00') . ':00');
            $status = 'hadir';

            $roll = mt_rand(1, 100);
            if ($roll <= 70) {
                // On time / early
                $delta = mt_rand(-min(10, $before), min(5, max(1, $tolerance)));
            } elseif ($roll <= 85) {
                // Slightly late (keep status telat for legacy display)
                $delta = mt_rand($tolerance + 1, min($after, $tolerance + 25));
                $status = 'telat';
            } else {
                // Alpa (late beyond tolerance but still within window)
                $delta = mt_rand($tolerance + 10, min($after, $tolerance + 45));
                $status = 'alpa';
            }

            $masuk = $startAt->copy()->addMinutes($delta);
            $source = mt_rand(1, 100) <= 70 ? 'portal' : 'kasir';

            $geoLat = $setting->absensi_geo_lat ? (float) $setting->absensi_geo_lat : null;
            $geoLng = $setting->absensi_geo_lng ? (float) $setting->absensi_geo_lng : null;
            $geoAcc = null;
            if ($geoLat !== null && $geoLng !== null) {
                $geoLat += (mt_rand(-20, 20) / 100000.0);
                $geoLng += (mt_rand(-20, 20) / 100000.0);
                $geoAcc = mt_rand(20, 140);
            }

            $rows[] = [
                'id_karyawan' => $karyawanId,
                'tanggal' => $dateStr,
                'waktu_masuk' => $masuk,
                'waktu_pulang' => null,
                'catatan' => mt_rand(1, 100) <= 12 ? 'Catatan otomatis' : null,
                'status' => $status,
                'absensi_source' => $source,
                'shift_no' => $shiftNo,
                'selfie_path' => null,
                'geo_lat' => $geoLat,
                'geo_lng' => $geoLng,
                'geo_accuracy_m' => $geoAcc,
            ];

            if (count($rows) >= 500) {
                DB::table('absensi')->insert($rows);
                $total += count($rows);
                $rows = [];
            }
        }

        // Add a tiny chance of "tidak dijadwalkan"
        if (!empty($activeKaryawanIds) && mt_rand(1, 100) <= 12) {
            $randId = (int) $activeKaryawanIds[array_rand($activeKaryawanIds)];
            if (! isset($scheduledSet[$randId])) {
                $masuk = Carbon::parse($dateStr . ' 10:00:00')->addMinutes(mt_rand(-15, 30));
                $rows[] = [
                    'id_karyawan' => $randId,
                    'tanggal' => $dateStr,
                    'waktu_masuk' => $masuk,
                    'waktu_pulang' => null,
                    'catatan' => 'Tidak dijadwalkan',
                    'status' => 'tidak_dijadwalkan',
                    'absensi_source' => 'portal',
                    'shift_no' => null,
                    'selfie_path' => null,
                    'geo_lat' => null,
                    'geo_lng' => null,
                    'geo_accuracy_m' => null,
                ];
            }
        }
    }

    if (!empty($rows)) {
        DB::table('absensi')->insert($rows);
        $total += count($rows);
    }

    $this->info("Absensi di-reset dan diisi fake untuk minggu ini. Total baris: {$total}.");
    $this->line("Minggu: {$start->toDateString()} s/d {$end->toDateString()}");

    return 0;
})->purpose('Hapus semua data absensi lalu buat fake absensi untuk minggu ini.');

Artisan::command('demo:seed-workforce {--reset-demo : Hapus data demo FT/PT sebelumnya dulu}', function () {
    /** @var DemoWorkforceSeeder $seeder */
    $seeder = app(DemoWorkforceSeeder::class);
    $result = $seeder->seed((bool) $this->option('reset-demo'));

    $this->info('Data demo FT/PT berhasil dibuat.');
    $this->line('Periode aktif: ' . (string) ($result['periods']['current'] ?? '-'));
    $this->line('Periode slip gaji: ' . (string) ($result['periods']['previous'] ?? '-'));
    $this->newLine();

    $this->info('Akun staff demo:');
    foreach ((array) ($result['employees'] ?? []) as $employee) {
        $this->line(sprintf(
            '- %s | %s | %s | PIN %s | %s',
            (string) ($employee['name'] ?? '-'),
            (string) ($employee['employment'] ?? '-'),
            (string) ($employee['phone'] ?? '-'),
            (string) ($employee['pin'] ?? '-'),
            (string) ($employee['salary_label'] ?? '-'),
        ));
    }

    $this->newLine();
    $this->info('Ringkasan data:');
    foreach ((array) ($result['counts'] ?? []) as $label => $value) {
        $this->line('- ' . $label . ': ' . $value);
    }

    $this->newLine();
    $this->info('Skenario siap tes:');
    foreach ((array) ($result['scenarios'] ?? []) as $value) {
        $this->line('- ' . (string) $value);
    }

    return 0;
})->purpose('Buat data demo FT/PT untuk testing jadwal, absensi, payroll, dan koreksi pulang.');

Artisan::command('staff-notifications:backfill {--demo : Tambahkan notif demo operasional staff} {--staff-id= : Target staff tertentu untuk notif demo}', function () {
    /** @var StaffNotificationService $service */
    $service = app(StaffNotificationService::class);

    $backfilled = $service->backfillHistorical();
    $demoCount = 0;

    if ((bool) $this->option('demo')) {
        $staffId = $this->option('staff-id');
        $demoCount = $service->seedDemoNotifications(is_numeric($staffId) ? (int) $staffId : null);
    }

    $this->info('Sinkronisasi notifikasi staff selesai.');
    $this->line('- Backfill riwayat: ' . $backfilled);
    $this->line('- Data demo: ' . $demoCount);

    return 0;
})->purpose('Buat riwayat notifikasi staff dari data operasional lama dan tambah notif demo untuk testing.');
