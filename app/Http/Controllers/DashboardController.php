<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Kategori;
use App\Models\Diskon;
use App\Models\PromoBundling;
use App\Models\MasterOpsiKasir;
use App\Models\Pelanggan;
use App\Models\Karyawan;
use App\Models\JadwalKaryawan;
use App\Models\JadwalTukarRequest;
use App\Models\LeaveRequest;
use App\Models\StrukSetting;
use App\Models\Announcement;
use App\Models\StaffMessage;
use App\Models\StaffMessageRead;
use App\Models\Absensi;
use App\Services\AbsensiCorrectionService;
use App\Services\PromoAutoExpire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function shareQr(): View
    {
        // Single entry point for staff: portal karyawan -> menu absen/jadwal.
        // `/staff` is protected by middleware; guest will be redirected to `/staff/login`.
        $portalUrl = url('/staff');

        return view('dashboard.share-qr', [
            'portalUrl' => $portalUrl,
        ]);
    }

    public function previewShareQr(Request $request, string $kind)
    {
        $kind = strtolower(trim($kind));
        // Backward compatible aliases: `absen`/`jadwal` now point to the same staff portal QR.
        if (! in_array($kind, ['portal', 'absen', 'jadwal'], true)) {
            abort(404);
        }

        $targetUrl = url('/staff');
        $size = (int) $request->query('size', 420);
        $size = max(180, min(1200, $size));

        // Same-origin PNG preview so browser canvas can export labeled PNG without CORS issues.
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?format=png&size=' . $size . 'x' . $size . '&data=' . rawurlencode($targetUrl);

        try {
            $res = Http::timeout(10)->get($qrUrl);
            if (! $res->ok()) {
                abort(502, 'QR preview gagal dimuat.');
            }

            return response($res->body(), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="qr-' . $kind . '.png"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        } catch (\Throwable $e) {
            abort(502, 'QR preview gagal dimuat (koneksi).');
        }
    }

    public function downloadShareQr(Request $request, string $kind)
    {
        $kind = strtolower(trim($kind));
        // Backward compatible aliases: `absen`/`jadwal` now point to the same staff portal QR.
        if (! in_array($kind, ['portal', 'absen', 'jadwal'], true)) {
            abort(404);
        }

        $targetUrl = url('/staff');

        // Note: We fetch the PNG from a QR service and stream it as a file download.
        // This keeps the feature working without extra composer dependencies.
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?format=png&size=800x800&data=' . rawurlencode($targetUrl);

        try {
            $res = Http::timeout(10)->get($qrUrl);
            if (! $res->ok()) {
                return back()->withErrors([
                    'qr' => 'Gagal mengunduh gambar QR. Coba lagi saat koneksi internet stabil.',
                ]);
            }

            $filename = 'qr-portal-' . now()->format('Ymd-His') . '.png';

            return response($res->body(), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'qr' => 'Gagal mengunduh gambar QR (koneksi). Coba lagi.',
            ]);
        }
    }

    public function masterData(): View
    {
        $today = now()->toDateString();
        $lowStockThreshold = 5;

        $totalProduk = (int) Produk::query()->count();
        $totalKategori = (int) Kategori::query()->count();
        $totalPelanggan = (int) Pelanggan::query()->count();
        $totalKaryawan = (int) Karyawan::query()->count();

        $opsiKasirAktif = (int) MasterOpsiKasir::query()->where('is_active', true)->count();
        $diskonAktif = (int) Diskon::query()->activeForDate($today)->count();
        $bundlingAktif = (int) PromoBundling::query()->activeForDate($today)->count();

        $stokMenipisCount = (int) Produk::query()
            ->whereNotNull('stok')
            ->where('stok', '<=', $lowStockThreshold)
            ->count();

        $stokMenipis = Produk::query()
            ->with('kategori')
            ->whereNotNull('stok')
            ->where('stok', '<=', $lowStockThreshold)
            ->orderBy('stok')
            ->orderBy('nama_produk')
            ->limit(10)
            ->get();

        return view('master.index', [
            'today' => $today,
            'lowStockThreshold' => $lowStockThreshold,
            'totalProduk' => $totalProduk,
            'totalKategori' => $totalKategori,
            'totalPelanggan' => $totalPelanggan,
            'totalKaryawan' => $totalKaryawan,
            'opsiKasirAktif' => $opsiKasirAktif,
            'diskonAktif' => $diskonAktif,
            'bundlingAktif' => $bundlingAktif,
            'stokMenipisCount' => $stokMenipisCount,
            'stokMenipis' => $stokMenipis,
        ]);
    }

    public function index(Request $request): View
    {
        PromoAutoExpire::run();
        $setting = StrukSetting::current();
        $resetHour = $this->resolveOperationalResetHour();
        $deliveryMethods = ['shopeefood', 'gofood', 'grabfood'];
        $salesMode = $this->resolveSalesMode($request);
        $salesModeLabel = match ($salesMode) {
            'app' => 'Penjualan Aplikasi',
            'all' => 'Semua Penjualan',
            default => 'Penjualan Biasa',
        };

        $operasional = now()->subHours($resetHour);
        $today = $operasional->toDateString();

        // Batas harian operasional: jam reset hari ini s/d sebelum jam reset hari berikutnya.
        $startOfOperationalDay = $operasional->copy()->startOfDay()->addHours($resetHour);
        $endOfOperationalDay = $startOfOperationalDay->copy()->addDay();

        // Batas bulanan operasional: awal bulan jam reset s/d awal bulan berikutnya jam reset.
        $startOfOperationalMonth = $operasional->copy()->startOfMonth()->addHours($resetHour);
        $endOfOperationalMonth = $startOfOperationalMonth->copy()->addMonth();

        $endHour = ($resetHour + 23) % 24;
        $operasionalInfo = sprintf(
            'Periode operasional harian: %02d:00 - %02d:59',
            $resetHour,
            $endHour
        );

        // 1. Omzet Hari Ini (Berdasarkan jam operasional)
        $omzetHariIniQuery = Pesanan::query()
            ->where('waktu_pembayaran', '>=', $startOfOperationalDay)
            ->where('waktu_pembayaran', '<', $endOfOperationalDay)
            ->where('status_pembayaran', 'lunas');
        $this->applySalesModeFilter($omzetHariIniQuery, 'metode_pembayaran', $salesMode, $deliveryMethods);
        $omzetHariIni = (float) $omzetHariIniQuery->sum('total_harga');

        // 2. Omzet Bulan Ini
        $omzetBulanIniQuery = Pesanan::query()
            ->where('waktu_pembayaran', '>=', $startOfOperationalMonth)
            ->where('waktu_pembayaran', '<', $endOfOperationalMonth)
            ->where('status_pembayaran', 'lunas');
        $this->applySalesModeFilter($omzetBulanIniQuery, 'metode_pembayaran', $salesMode, $deliveryMethods);
        $omzetBulanIni = (float) $omzetBulanIniQuery->sum('total_harga');

        // 3. Jumlah Transaksi Lunas Hari Ini
        $jumlahTransaksiHariIniQuery = Pesanan::query()
            ->where('waktu_pembayaran', '>=', $startOfOperationalDay)
            ->where('waktu_pembayaran', '<', $endOfOperationalDay)
            ->where('status_pembayaran', 'lunas');
        $this->applySalesModeFilter($jumlahTransaksiHariIniQuery, 'metode_pembayaran', $salesMode, $deliveryMethods);
        $jumlahTransaksiHariIni = (int) $jumlahTransaksiHariIniQuery->count();

        // 4. Jumlah Transaksi Dibatalkan Hari Ini
        $jumlahDibatalkanHariIniQuery = Pesanan::query()
            ->where('waktu_pembayaran', '>=', $startOfOperationalDay)
            ->where('waktu_pembayaran', '<', $endOfOperationalDay)
            ->where('status_pembayaran', 'dibatalkan');
        $this->applySalesModeFilter($jumlahDibatalkanHariIniQuery, 'metode_pembayaran', $salesMode, $deliveryMethods);
        $jumlahDibatalkanHariIni = (int) $jumlahDibatalkanHariIniQuery->count();

        $modalKasSistem = (float) ($setting->default_cash_float ?? 0);
        if ($modalKasSistem <= 0) {
            $lastEstimasiKasAkhir = (float) (DB::table('kasir_shift_sessions')
                ->whereNotNull('ended_at')
                ->whereNotNull('estimasi_kas_akhir')
                ->orderByDesc('ended_at')
                ->value('estimasi_kas_akhir') ?? 0);
            $modalKasSistem = max(0.0, $lastEstimasiKasAkhir);
        }

        $totalCashHariIni = (float) Pesanan::query()
            ->where('waktu_pembayaran', '>=', $startOfOperationalDay)
            ->where('waktu_pembayaran', '<', $endOfOperationalDay)
            ->where('status_pembayaran', 'lunas')
            ->where('metode_pembayaran', 'cash')
            ->sum('total_harga');

        $totalPengeluaranHariIni = (float) DB::table('kasir_shift_pengeluaran')
            ->where('pengeluaran_at', '>=', $startOfOperationalDay)
            ->where('pengeluaran_at', '<', $endOfOperationalDay)
            ->sum('nominal');

        $estimasiKasSaatIni = $modalKasSistem + $totalCashHariIni - $totalPengeluaranHariIni;
        $setoranHariIni = $totalCashHariIni - $totalPengeluaranHariIni;

        $setoranIntervalDays = max(1, (int) ($setting->setoran_interval_days ?? 7));
        $lastSetoranRow = DB::table('kas_setoran')
            ->select('tanggal_setor', 'nominal', 'catatan')
            ->orderByDesc('tanggal_setor')
            ->first();
        $lastSetoranAt = $lastSetoranRow?->tanggal_setor ? Carbon::parse((string) $lastSetoranRow->tanggal_setor) : null;
        $nextSetoranDueAt = $lastSetoranAt?->copy()->addDays($setoranIntervalDays);
        $setoranDueDays = $nextSetoranDueAt
            ? now()->startOfDay()->diffInDays($nextSetoranDueAt->copy()->startOfDay(), false)
            : null;
        $isSetoranOverdue = $nextSetoranDueAt ? now()->greaterThanOrEqualTo($nextSetoranDueAt) : true;

        $totalCashAll = (float) Pesanan::query()
            ->where('status_pembayaran', 'lunas')
            ->whereRaw("LOWER(TRIM(COALESCE(metode_pembayaran, ''))) = 'cash'")
            ->sum('total_harga');
        $totalPengeluaranAll = (float) DB::table('kasir_shift_pengeluaran')->sum('nominal');
        $totalSetoranAll = (float) DB::table('kas_setoran')->sum('nominal');
        $saldoBelumDisetor = $totalCashAll - $totalPengeluaranAll - $totalSetoranAll;

        $paymentPeriod = $request->query('payment_period', 'today');
        if (! in_array($paymentPeriod, ['today', 'month'], true)) {
            $paymentPeriod = 'today';
        }

        $paymentStart = $paymentPeriod === 'month' ? $startOfOperationalMonth : $startOfOperationalDay;
        $paymentEnd = $paymentPeriod === 'month' ? $endOfOperationalMonth : $endOfOperationalDay;

        // 5. Komposisi metode pembayaran (sesuai periode terpilih, hanya transaksi lunas)
        $metodeRows = Pesanan::query()
            ->select('metode_pembayaran', DB::raw('SUM(total_harga) as total'), DB::raw('COUNT(*) as trx_count'))
            ->where('waktu_pembayaran', '>=', $paymentStart)
            ->where('waktu_pembayaran', '<', $paymentEnd)
            ->where('status_pembayaran', 'lunas')
            ->groupBy('metode_pembayaran')
            ->get()
            ->keyBy('metode_pembayaran');
        $methodTotals = [
            'cash' => (float) ($metodeRows->get('cash')->total ?? 0),
            'qris' => (float) ($metodeRows->get('qris')->total ?? 0),
            'debit' => (float) ($metodeRows->get('debit')->total ?? 0),
            'shopeefood' => (float) ($metodeRows->get('shopeefood')->total ?? 0),
            'gofood' => (float) ($metodeRows->get('gofood')->total ?? 0),
            'grabfood' => (float) ($metodeRows->get('grabfood')->total ?? 0),
        ];
        $methodTrx = [
            'cash' => (int) ($metodeRows->get('cash')->trx_count ?? 0),
            'qris' => (int) ($metodeRows->get('qris')->trx_count ?? 0),
            'debit' => (int) ($metodeRows->get('debit')->trx_count ?? 0),
            'shopeefood' => (int) ($metodeRows->get('shopeefood')->trx_count ?? 0),
            'gofood' => (int) ($metodeRows->get('gofood')->trx_count ?? 0),
            'grabfood' => (int) ($metodeRows->get('grabfood')->trx_count ?? 0),
        ];

        $regularTotal = $methodTotals['cash'] + $methodTotals['qris'] + $methodTotals['debit'];
        $appTotal = $methodTotals['shopeefood'] + $methodTotals['gofood'] + $methodTotals['grabfood'];
        $deliveryTotalToday = $appTotal;
        $deliveryTrxToday = $methodTrx['shopeefood'] + $methodTrx['gofood'] + $methodTrx['grabfood'];
        $totalMetodePembayaranHariIni = $regularTotal + $appTotal;
        $deliveryPctHariIni = $totalMetodePembayaranHariIni > 0 ? round(($deliveryTotalToday / $totalMetodePembayaranHariIni) * 100, 2) : 0.0;

        $paymentCenterLabel = $salesMode === 'app'
            ? 'Omzet Aplikasi'
            : ($salesMode === 'all' ? 'Omzet Total' : 'Omzet Operasional');

        if ($salesMode === 'app') {
            $paymentSlices = [
                ['key' => 'shopeefood', 'label' => 'ShopeeFood', 'total' => $methodTotals['shopeefood'], 'trx' => $methodTrx['shopeefood'], 'color' => '#f97316'],
                ['key' => 'gofood', 'label' => 'GoFood', 'total' => $methodTotals['gofood'], 'trx' => $methodTrx['gofood'], 'color' => '#22c55e'],
                ['key' => 'grabfood', 'label' => 'GrabFood', 'total' => $methodTotals['grabfood'], 'trx' => $methodTrx['grabfood'], 'color' => '#3b82f6'],
            ];
            $paymentTotal = $appTotal;
        } elseif ($salesMode === 'all') {
            $paymentSlices = [
                ['key' => 'cash', 'label' => 'Cash', 'total' => $methodTotals['cash'], 'trx' => $methodTrx['cash'], 'color' => 'var(--accent)'],
                ['key' => 'qris', 'label' => 'QRIS', 'total' => $methodTotals['qris'], 'trx' => $methodTrx['qris'], 'color' => 'var(--accent-2)'],
                ['key' => 'debit', 'label' => 'Debit', 'total' => $methodTotals['debit'], 'trx' => $methodTrx['debit'], 'color' => '#d97706'],
                ['key' => 'delivery', 'label' => 'Aplikasi', 'total' => $appTotal, 'trx' => $deliveryTrxToday, 'color' => '#7c3aed'],
            ];
            $paymentTotal = $totalMetodePembayaranHariIni;
        } else {
            $paymentSlices = [
                ['key' => 'cash', 'label' => 'Cash', 'total' => $methodTotals['cash'], 'trx' => $methodTrx['cash'], 'color' => 'var(--accent)'],
                ['key' => 'qris', 'label' => 'QRIS', 'total' => $methodTotals['qris'], 'trx' => $methodTrx['qris'], 'color' => 'var(--accent-2)'],
                ['key' => 'debit', 'label' => 'Debit', 'total' => $methodTotals['debit'], 'trx' => $methodTrx['debit'], 'color' => '#d97706'],
            ];
            $paymentTotal = $regularTotal;
        }

        foreach ($paymentSlices as $index => $slice) {
            $paymentSlices[$index]['pct'] = $paymentTotal > 0 ? round(($slice['total'] / $paymentTotal) * 100, 2) : 0.0;
        }

        $cashSyncDelta = abs($totalCashHariIni - (float) ($methodTotals['cash'] ?? 0));
        $cashSyncOk = $cashSyncDelta < 0.5;

        // 6. Grafik tren penjualan 90 hari operasional terakhir (untuk filter 90d/30d/7d)
        $trendDays  = 89; // 90 hari termasuk hari ini
        $trendStart = $operasional->copy()->subDays($trendDays)->startOfDay()->addHours($resetHour);
        $trendEnd   = $operasional->copy()->startOfDay()->addHours($resetHour)->addDay();
        $driver = DB::getDriverName();
        $adjustedDateExpr = $driver === 'sqlite'
            ? "DATE(datetime(waktu_pembayaran, '-{$resetHour} hours'))"
            : "DATE(DATE_SUB(waktu_pembayaran, INTERVAL {$resetHour} HOUR))";

        $trendRowsQuery = DB::table('pesanan')
            ->selectRaw("{$adjustedDateExpr} as operational_date")
            ->selectRaw('SUM(total_harga) as omzet')
            ->selectRaw('COUNT(*) as transaksi')
            ->where('status_pembayaran', 'lunas')
            ->where('waktu_pembayaran', '>=', $trendStart)
            ->where('waktu_pembayaran', '<', $trendEnd);
        $this->applySalesModeFilter($trendRowsQuery, 'metode_pembayaran', $salesMode, $deliveryMethods);
        $trendRows = $trendRowsQuery->groupBy('operational_date')->get()
            ->keyBy('operational_date');

        $salesTrend = collect(range($trendDays, 0))
            ->map(function (int $offset) use ($operasional, $trendRows) {
                $operationalDay = $operasional->copy()->subDays($offset)->startOfDay();
                $key = $operationalDay->toDateString();
                $row = $trendRows->get($key);

                return [
                    'date'      => $key,
                    'label'     => $operationalDay->format('d/m'),
                    'omzet'     => (float) ($row->omzet ?? 0),
                    'transaksi' => (int) ($row->transaksi ?? 0),
                ];
            })
            ->values();

        $salesTrendMax = (float) max(1, (float) $salesTrend->max('omzet'));

        // 7. Produk Terlaris Hari Ini
        $produkTerlarisQuery = DB::table('detail_pesanan as dp')
            ->join('pesanan as p', 'p.id_pesanan', '=', 'dp.id_pesanan')
            ->join('produk as pr', 'pr.id_produk', '=', 'dp.id_produk')
            ->select('pr.nama_produk', DB::raw('SUM(dp.jumlah) as total_terjual'))
            ->where('p.waktu_pembayaran', '>=', $startOfOperationalDay)
            ->where('p.waktu_pembayaran', '<', $endOfOperationalDay)
            ->where('p.status_pembayaran', 'lunas');
        $this->applySalesModeFilter($produkTerlarisQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $produkTerlarisHariIni = $produkTerlarisQuery->groupBy('pr.id_produk', 'pr.nama_produk')
            ->orderByDesc('total_terjual')
            ->limit(5)
            ->get();

        // 8. Transaksi Terbaru (Tetap ambil yang paling baru secara realtime)
        $transaksiTerbaruQuery = Pesanan::with(['pelanggan', 'karyawan'])
            ->orderByDesc('id_pesanan')
            ->limit(8);
        $this->applySalesModeFilter($transaksiTerbaruQuery, 'metode_pembayaran', $salesMode, $deliveryMethods);
        $transaksiTerbaru = $transaksiTerbaruQuery->get();

        $jadwalShiftSnapshot = Schema::hasTable('jadwal_karyawan')
            ? JadwalKaryawan::dashboardShiftSnapshot()
            : ['hasActiveShift' => false, 'shiftKe' => null, 'today' => $today, 'staff' => []];

        $announcements = collect();
        if (Schema::hasTable('announcements')) {
            $announcementNow = now();
            $announcementCutoff = $announcementNow->copy()->subDays(3);
            $announcements = Announcement::query()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(function (Announcement $announcement) use ($announcementNow) {
                    $promoInfo = $announcement->resolvePromoStatus($announcementNow);
                    $announcement->promo_status = $promoInfo['status'] === 'Akan Mulai' ? 'Terjadwal' : $promoInfo['status'];
                    $announcement->promo_end_at = $promoInfo['end_at'];
                    return $announcement;
                })
                ->filter(function (Announcement $announcement) use ($announcementCutoff) {
                    if (! empty($announcement->promo_end_at) && $announcement->promo_end_at instanceof Carbon) {
                        return $announcement->promo_end_at->greaterThanOrEqualTo($announcementCutoff);
                    }
                    return true;
                })
                ->values()
                ->take(6);
        }

        $pendingSwapCount = 0;
        if (Schema::hasTable('jadwal_tukar_requests')) {
            $pendingSwapCount = (int) JadwalTukarRequest::query()
                ->where('status', 'pending')
                ->where('staff_status', 'approved')
                ->count();
        }

        $pendingLeaveCount = 0;
        if (Schema::hasTable('leave_requests')) {
            $pendingLeaveCount = (int) LeaveRequest::query()
                ->where('status', 'pending')
                ->count();
        }

        $unreadChatCount = 0;
        if (Schema::hasTable('staff_messages') && Schema::hasTable('staff_message_reads')) {
            $userId = (int) (auth()->id() ?? 0);
            if ($userId > 0) {
                $reads = StaffMessageRead::query()
                    ->where('reader_role', 'admin')
                    ->where('reader_user_id', $userId)
                    ->get()
                    ->keyBy(fn ($row) => $row->thread_type . ':' . (int) $row->thread_id);

                $messages = StaffMessage::query()
                    ->where('thread_type', 'admin_chat')
                    ->where('sender_role', 'staff')
                    ->get(['thread_id', 'created_at']);

                foreach ($messages as $msg) {
                    $key = 'admin_chat:' . (int) $msg->thread_id;
                    $lastRead = $reads[$key]->last_read_at ?? null;
                    if (! $lastRead || $msg->created_at->gt($lastRead)) {
                        $unreadChatCount++;
                    }
                }
            }
        }

        $absensiAttentionCount = 0;
        $absensiRequestedCount = 0;
        $absensiForgotCount = 0;
        $absensiAttentionStart = now()->subDays(30)->toDateString();
        if (Schema::hasTable('absensi')) {
            $correctionService = app(AbsensiCorrectionService::class);
            $openAttendance = Absensi::query()
                ->with('karyawan')
                ->whereNotNull('waktu_masuk')
                ->whereNull('waktu_pulang')
                ->orderBy('tanggal')
                ->get();

            $attentionRows = $openAttendance
                ->map(function (Absensi $row) use ($correctionService, $setting) {
                    $state = $correctionService->state($row, $setting, $row->karyawan);
                    $row->setAttribute('correction_meta', $state);

                    return $row;
                })
                ->filter(fn (Absensi $row) => $correctionService->matchesFilter((array) ($row->correction_meta ?? []), 'needs_attention'))
                ->values();

            $absensiAttentionCount = (int) $attentionRows->count();
            $absensiRequestedCount = (int) $attentionRows
                ->filter(fn (Absensi $row) => $correctionService->matchesFilter((array) ($row->correction_meta ?? []), 'requested'))
                ->count();
            $absensiForgotCount = (int) $attentionRows
                ->filter(fn (Absensi $row) => $correctionService->matchesFilter((array) ($row->correction_meta ?? []), 'forgot'))
                ->count();

            $earliestAttentionDate = $attentionRows
                ->pluck('tanggal')
                ->filter()
                ->map(fn ($date) => Carbon::parse((string) $date)->toDateString())
                ->sort()
                ->first();

            if (is_string($earliestAttentionDate) && $earliestAttentionDate !== '') {
                $absensiAttentionStart = $earliestAttentionDate;
            }
        }

        return view('dashboard.index', [
            'today' => $today, // Menampilkan tanggal operasional di dashboard
            'monthLabel' => $operasional->format('m/Y'),
            'operasionalInfo' => $operasionalInfo,
            'operasionalResetHour' => $resetHour,
            'modalKasSistem' => $modalKasSistem,
            'totalCashHariIni' => $totalCashHariIni,
            'totalPengeluaranHariIni' => $totalPengeluaranHariIni,
            'estimasiKasSaatIni' => $estimasiKasSaatIni,
            'setoranHariIni' => $setoranHariIni,
            'omzetHariIni' => $omzetHariIni,
            'omzetBulanIni' => $omzetBulanIni,
            'jumlahTransaksiHariIni' => $jumlahTransaksiHariIni,
            'jumlahDibatalkanHariIni' => $jumlahDibatalkanHariIni,
            'deliveryTotalHariIni' => $deliveryTotalToday,
            'deliveryTrxHariIni' => $deliveryTrxToday,
            'deliveryPctHariIni' => $deliveryPctHariIni,
            'paymentSlices' => $paymentSlices,
            'paymentTotal' => $paymentTotal,
            'paymentCenterLabel' => $paymentCenterLabel,
            'cashSyncOk' => $cashSyncOk,
            'cashSyncDelta' => $cashSyncDelta,
            'salesMode' => $salesMode,
            'salesModeLabel' => $salesModeLabel,
            'setoranIntervalDays' => $setoranIntervalDays,
            'lastSetoranAt' => $lastSetoranAt,
            'nextSetoranDueAt' => $nextSetoranDueAt,
            'setoranDueDays' => $setoranDueDays,
            'isSetoranOverdue' => $isSetoranOverdue,
            'saldoBelumDisetor' => $saldoBelumDisetor,
            'keuanganMenuEnabled' => (bool) ($setting->enable_keuangan_menu ?? true),
            'taxEnabled' => (bool) ($setting->enable_tax ?? false),
            'taxPercent' => (float) ($setting->tax_percent ?? 0),
            'taxMode' => (string) ($setting->tax_mode ?? 'transaksi'),
            'paymentPeriod' => $paymentPeriod,
            'salesTrend' => $salesTrend,
            'salesTrendMax' => $salesTrendMax,
            'produkTerlarisHariIni' => $produkTerlarisHariIni,
            'transaksiTerbaru' => $transaksiTerbaru,
            'jadwalShiftSnapshot' => $jadwalShiftSnapshot,
            'announcements' => $announcements,
            'pendingSwapCount' => $pendingSwapCount,
            'pendingLeaveCount' => $pendingLeaveCount,
            'unreadChatCount' => $unreadChatCount,
            'absensiAttentionCount' => $absensiAttentionCount,
            'absensiRequestedCount' => $absensiRequestedCount,
            'absensiForgotCount' => $absensiForgotCount,
            'absensiAttentionStart' => $absensiAttentionStart,
        ]);
    }

    public function storeSetoran(Request $request): RedirectResponse
    {
        if (! $this->isKeuanganMenuEnabled()) {
            return redirect()->route('dashboard.workspace')->withErrors([
                'keuangan' => 'Menu Keuangan sedang dinonaktifkan dari Ruang Kerja.',
            ]);
        }

        $data = $request->validate([
            'nominal' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $now = now();
        $nominal = isset($data['nominal']) ? (float) $data['nominal'] : null;
        $catatan = trim((string) ($data['catatan'] ?? '')) ?: null;
        $userId = (int) (auth()->id() ?? 0) ?: null;

        $setoranId = DB::table('kas_setoran')->insertGetId([
            'tanggal_setor' => now(),
            'nominal' => $nominal,
            'catatan' => $catatan,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'id');

        $this->logSetoranAudit(
            setoranId: (int) $setoranId,
            aksi: 'buat_setoran',
            nominalLama: null,
            nominalBaru: $nominal,
            catatanLama: null,
            catatanBaru: $catatan,
            meta: ['sumber' => 'form_keuangan']
        );

        return redirect()
            ->back()
            ->with('success', 'Setoran kas berhasil ditandai.');
    }

    public function updateSetoranCatatan(Request $request, int $setoran): RedirectResponse
    {
        if (! $this->isKeuanganMenuEnabled()) {
            return redirect()->route('dashboard.workspace')->withErrors([
                'keuangan' => 'Menu Keuangan sedang dinonaktifkan dari Ruang Kerja.',
            ]);
        }

        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $target = DB::table('kas_setoran')
            ->select('id', 'nominal', 'catatan')
            ->where('id', $setoran)
            ->first();

        if (! $target) {
            return redirect()->back()->withErrors(['setoran' => 'Data setoran tidak ditemukan.']);
        }

        $catatanBaru = trim((string) ($data['catatan'] ?? '')) ?: null;
        $catatanLama = $target->catatan ? (string) $target->catatan : null;

        DB::table('kas_setoran')
            ->where('id', $setoran)
            ->update([
                'catatan' => $catatanBaru,
                'updated_at' => now(),
            ]);

        $this->logSetoranAudit(
            setoranId: (int) $setoran,
            aksi: 'ubah_catatan',
            nominalLama: isset($target->nominal) ? (float) $target->nominal : null,
            nominalBaru: isset($target->nominal) ? (float) $target->nominal : null,
            catatanLama: $catatanLama,
            catatanBaru: $catatanBaru,
            meta: ['sumber' => 'form_keuangan']
        );

        return redirect()->back()->with('success', 'Catatan setoran berhasil diperbarui.');
    }

    public function koreksiSetoranNominal(Request $request, int $setoran): RedirectResponse
    {
        if (! $this->isKeuanganMenuEnabled()) {
            return redirect()->route('dashboard.workspace')->withErrors([
                'keuangan' => 'Menu Keuangan sedang dinonaktifkan dari Ruang Kerja.',
            ]);
        }

        $data = $request->validate([
            'nominal_baru' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $target = DB::table('kas_setoran')
            ->select('id', 'nominal', 'tanggal_setor', 'catatan')
            ->where('id', $setoran)
            ->first();

        if (! $target) {
            return redirect()->back()->withErrors(['setoran' => 'Data setoran tidak ditemukan.']);
        }

        $nominalLama = (float) ($target->nominal ?? 0);
        $nominalBaru = (float) $data['nominal_baru'];
        $delta = $nominalBaru - $nominalLama;

        if (abs($delta) < 0.01) {
            return redirect()->back()->withErrors(['setoran' => 'Nominal baru sama dengan nominal lama.']);
        }

        $catatanUser = trim((string) ($data['catatan'] ?? ''));
        $catatanAudit = 'Koreksi setoran #' . (int) $target->id
            . ' (' . number_format($nominalLama, 0, ',', '.') . ' -> ' . number_format($nominalBaru, 0, ',', '.') . ')';
        if ($catatanUser !== '') {
            $catatanAudit .= ' | ' . $catatanUser;
        }

        DB::table('kas_setoran')
            ->where('id', $setoran)
            ->update([
                'nominal' => $nominalBaru,
                'updated_at' => now(),
            ]);

        $this->logSetoranAudit(
            setoranId: (int) $target->id,
            aksi: 'koreksi_nominal',
            nominalLama: $nominalLama,
            nominalBaru: $nominalBaru,
            catatanLama: $target->catatan ?? null,
            catatanBaru: $catatanAudit,
            meta: [
                'setoran_target_id' => (int) $target->id,
                'delta' => $delta,
                'sumber' => 'form_koreksi',
            ]
        );

        return redirect()->back()->with('success', 'Koreksi nominal setoran berhasil disimpan.');
    }

    public function keuangan(Request $request): View
    {
        $setting = StrukSetting::current();
        if (! (bool) ($setting->enable_keuangan_menu ?? true)) {
            abort(404);
        }

        $resetHour = $this->resolveOperationalResetHour();
        $operasional = now()->subHours($resetHour);
        $startOfOperationalDay = $operasional->copy()->startOfDay()->addHours($resetHour);
        $endOfOperationalDay = $startOfOperationalDay->copy()->addDay();

        $setoranIntervalDays = max(1, (int) ($setting->setoran_interval_days ?? 7));
        $lastSetoranRow = DB::table('kas_setoran')
            ->select('tanggal_setor', 'nominal', 'catatan')
            ->orderByDesc('tanggal_setor')
            ->first();
        $lastSetoranAt = $lastSetoranRow?->tanggal_setor ? Carbon::parse((string) $lastSetoranRow->tanggal_setor) : null;
        $nextSetoranDueAt = $lastSetoranAt?->copy()->addDays($setoranIntervalDays);
        $setoranDueDays = $nextSetoranDueAt
            ? now()->startOfDay()->diffInDays($nextSetoranDueAt->copy()->startOfDay(), false)
            : null;
        $isSetoranOverdue = $nextSetoranDueAt ? now()->greaterThanOrEqualTo($nextSetoranDueAt) : true;

        $totalCashHariIni = (float) Pesanan::query()
            ->where('waktu_pembayaran', '>=', $startOfOperationalDay)
            ->where('waktu_pembayaran', '<', $endOfOperationalDay)
            ->where('status_pembayaran', 'lunas')
            ->whereRaw("LOWER(TRIM(COALESCE(metode_pembayaran, ''))) = 'cash'")
            ->sum('total_harga');
        $totalPengeluaranHariIni = (float) DB::table('kasir_shift_pengeluaran')
            ->where('pengeluaran_at', '>=', $startOfOperationalDay)
            ->where('pengeluaran_at', '<', $endOfOperationalDay)
            ->sum('nominal');
        $setoranHariIni = $totalCashHariIni - $totalPengeluaranHariIni;

        $totalCashAll = (float) Pesanan::query()
            ->where('status_pembayaran', 'lunas')
            ->whereRaw("LOWER(TRIM(COALESCE(metode_pembayaran, ''))) = 'cash'")
            ->sum('total_harga');
        $totalPengeluaranAll = (float) DB::table('kasir_shift_pengeluaran')->sum('nominal');
        $totalSetoranAll = (float) DB::table('kas_setoran')->sum('nominal');
        $saldoBelumDisetor = $totalCashAll - $totalPengeluaranAll - $totalSetoranAll;

        $filters = $request->validate([
            'tanggal_awal' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date'],
        ]);
        $query = DB::table('kas_setoran as ks')
            ->leftJoin('users as u', 'u.id', '=', 'ks.user_id')
            ->select('ks.id', 'ks.tanggal_setor', 'ks.nominal', 'ks.catatan', 'u.name as user_name')
            ->orderByDesc('ks.tanggal_setor');
        if (! empty($filters['tanggal_awal']) || ! empty($filters['tanggal_akhir'])) {
            $awal = (string) ($filters['tanggal_awal'] ?? $filters['tanggal_akhir']);
            $akhir = (string) ($filters['tanggal_akhir'] ?? $filters['tanggal_awal']);
            $start = Carbon::parse($awal)->startOfDay();
            $end = Carbon::parse($akhir)->endOfDay();
            if ($end->lt($start)) {
                [$start, $end] = [$end, $start];
            }
            $query->where('ks.tanggal_setor', '>=', $start)->where('ks.tanggal_setor', '<=', $end);
        }
        $setoranRows = $query->paginate(20, ['*'], 'setoran_page')->onEachSide(1)->withQueryString();

        if (Schema::hasTable('kas_setoran_audits')) {
            $auditQuery = DB::table('kas_setoran_audits as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->select(
                    'a.id',
                    'a.setoran_id',
                    'a.aksi',
                    'a.nominal_lama',
                    'a.nominal_baru',
                    'a.catatan_lama',
                    'a.catatan_baru',
                    'a.meta',
                    'a.dibuat_pada',
                    'u.name as user_name'
                )
                ->orderByDesc('a.dibuat_pada');

            if (! empty($filters['tanggal_awal']) || ! empty($filters['tanggal_akhir'])) {
                $awal = (string) ($filters['tanggal_awal'] ?? $filters['tanggal_akhir']);
                $akhir = (string) ($filters['tanggal_akhir'] ?? $filters['tanggal_awal']);
                $start = Carbon::parse($awal)->startOfDay();
                $end = Carbon::parse($akhir)->endOfDay();
                if ($end->lt($start)) {
                    [$start, $end] = [$end, $start];
                }
                $auditQuery->where('a.dibuat_pada', '>=', $start)->where('a.dibuat_pada', '<=', $end);
            }
            $auditRows = $auditQuery->paginate(15, ['*'], 'audit_page')->onEachSide(1)->withQueryString();
        } else {
            $auditRows = new LengthAwarePaginator(
                items: collect(),
                total: 0,
                perPage: 15,
                currentPage: (int) request('audit_page', 1),
                options: [
                    'path' => request()->url(),
                    'query' => request()->query(),
                    'pageName' => 'audit_page',
                ]
            );
        }

        return view('dashboard.keuangan', [
            'setoranIntervalDays' => $setoranIntervalDays,
            'lastSetoranAt' => $lastSetoranAt,
            'nextSetoranDueAt' => $nextSetoranDueAt,
            'setoranDueDays' => $setoranDueDays,
            'isSetoranOverdue' => $isSetoranOverdue,
            'totalCashHariIni' => $totalCashHariIni,
            'totalPengeluaranHariIni' => $totalPengeluaranHariIni,
            'setoranHariIni' => $setoranHariIni,
            'saldoBelumDisetor' => $saldoBelumDisetor,
            'setoranRows' => $setoranRows,
            'auditRows' => $auditRows,
            'filters' => $filters,
        ]);
    }

    public function exportKeuanganExcel(Request $request): StreamedResponse
    {
        if (! $this->isKeuanganMenuEnabled()) {
            abort(404);
        }

        $filters = $request->validate([
            'tanggal_awal' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date'],
        ]);

        $query = DB::table('kas_setoran as ks')
            ->leftJoin('users as u', 'u.id', '=', 'ks.user_id')
            ->select('ks.id', 'ks.tanggal_setor', 'ks.nominal', 'ks.catatan', 'u.name as user_name')
            ->orderByDesc('ks.tanggal_setor');

        $start = null;
        $end = null;
        if (! empty($filters['tanggal_awal']) || ! empty($filters['tanggal_akhir'])) {
            $awal = (string) ($filters['tanggal_awal'] ?? $filters['tanggal_akhir']);
            $akhir = (string) ($filters['tanggal_akhir'] ?? $filters['tanggal_awal']);
            $start = Carbon::parse($awal)->startOfDay();
            $end = Carbon::parse($akhir)->endOfDay();
            if ($end->lt($start)) {
                [$start, $end] = [$end, $start];
            }
            $query->where('ks.tanggal_setor', '>=', $start)->where('ks.tanggal_setor', '<=', $end);
        }

        $rows = $query->get();
        $totalNominal = (float) $rows->sum(fn (object $row): float => (float) ($row->nominal ?? 0));

        $filename = 'keuangan-setoran-' . now()->format('Ymd-His') . '.xls';

        return response()->streamDownload(function () use ($rows, $start, $end, $totalNominal): void {
            echo '<html><head><meta charset="UTF-8">';
            echo '<style>';
            echo 'body{font-family:Arial,sans-serif;font-size:12px;}';
            echo 'h2{margin:0 0 8px 0;}';
            echo 'table{border-collapse:collapse;width:100%;margin-top:8px;}';
            echo 'th,td{border:1px solid #333;padding:6px;}';
            echo 'th{background:#efefef;text-align:left;}';
            echo '.num{text-align:right;}';
            echo '</style></head><body>';

            echo '<h2>Laporan Keuangan - Histori Setoran</h2>';
            if ($start && $end) {
                echo '<p>Periode: ' . $this->e($start->toDateString()) . ' s/d ' . $this->e($end->toDateString()) . '</p>';
            } else {
                echo '<p>Periode: Semua tanggal</p>';
            }

            echo '<table>';
            echo '<thead><tr><th>ID</th><th>Tanggal Setor</th><th>Jenis</th><th class="num">Nominal</th><th>Catatan</th><th>User</th></tr></thead>';
            echo '<tbody>';

            foreach ($rows as $row) {
                $catatan = (string) ($row->catatan ?? '');
                $isKoreksi = str_starts_with($catatan, 'Koreksi setoran #');
                echo '<tr>';
                echo '<td>' . $this->e((int) ($row->id ?? 0)) . '</td>';
                echo '<td>' . $this->e($row->tanggal_setor ?? '-') . '</td>';
                echo '<td>' . $this->e($isKoreksi ? 'Koreksi' : 'Normal') . '</td>';
                echo '<td class="num">' . $this->e(number_format((float) ($row->nominal ?? 0), 0, ',', '.')) . '</td>';
                echo '<td>' . $this->e($catatan !== '' ? $catatan : '-') . '</td>';
                echo '<td>' . $this->e($row->user_name ?? '-') . '</td>';
                echo '</tr>';
            }

            if ($rows->isEmpty()) {
                echo '<tr><td colspan="6">Belum ada histori setoran.</td></tr>';
            }

            echo '</tbody>';
            echo '<tfoot><tr><th colspan="3">Total Nominal</th><th class="num">' . $this->e(number_format($totalNominal, 0, ',', '.')) . '</th><th colspan="2"></th></tr></tfoot>';
            echo '</table>';

            echo '</body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function updateOperationalResetHour(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'operasional_reset_hour' => ['required', 'integer', 'between:0,23'],
        ]);

        $setting = StrukSetting::current();
        $setting->operasional_reset_hour = (int) $data['operasional_reset_hour'];
        $setting->save();

        return redirect()
            ->route('dashboard.index')
            ->with('success', 'Jam reset operasional berhasil diperbarui.');
    }

    public function workspace(): View
    {
        return view('dashboard.workspace', [
            'setting' => StrukSetting::current(),
        ]);
    }

    public function updateWorkspace(Request $request): RedirectResponse
    {
        $normalizedTaxPercent = str_replace(',', '.', trim((string) $request->input('tax_percent', '')));
        $normalizedDefaultCash = str_replace(',', '.', trim((string) $request->input('default_cash_float', '')));
        $request->merge([
            'tax_percent' => $normalizedTaxPercent === '' ? null : $normalizedTaxPercent,
            'default_cash_float' => $normalizedDefaultCash === '' ? null : $normalizedDefaultCash,
        ]);

        $data = $request->validate([
            'operasional_reset_hour' => ['required', 'integer', 'between:0,23'],
            'active_shift_count' => ['required', 'integer', 'between:1,3'],
            'default_cash_float' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'setoran_interval_days' => ['nullable', 'integer', 'between:1,30'],
            'enable_keuangan_menu' => ['nullable', 'boolean'],
            'enable_tax' => ['nullable', 'boolean'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_mode' => ['nullable', 'in:transaksi,produk'],
            'enable_payment_cash' => ['nullable', 'boolean'],
            'enable_payment_qris' => ['nullable', 'boolean'],
            'enable_payment_debit' => ['nullable', 'boolean'],
            'enable_payment_shopeefood' => ['nullable', 'boolean'],
            'enable_payment_gofood' => ['nullable', 'boolean'],
            'enable_payment_grabfood' => ['nullable', 'boolean'],
            'shift1_start_time' => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'shift2_start_time' => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'shift3_start_time' => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
        ]);

        $setting = StrukSetting::current();

        $enableCash = $request->boolean('enable_payment_cash');
        $enableQris = $request->boolean('enable_payment_qris');
        $enableDebit = $request->boolean('enable_payment_debit');
        $enableShopeefood = $request->boolean('enable_payment_shopeefood');
        $enableGofood = $request->boolean('enable_payment_gofood');
        $enableGrabfood = $request->boolean('enable_payment_grabfood');
        $enableKeuanganMenu = $request->boolean('enable_keuangan_menu');
        $enableTax = $request->boolean('enable_tax');
        $taxPercentInput = isset($data['tax_percent'])
            ? (float) $data['tax_percent']
            : (float) ($setting->tax_percent ?? 0);
        $taxMode = (string) ($data['tax_mode'] ?? $setting->tax_mode ?? 'transaksi');

        if (! $enableCash && ! $enableQris && ! $enableDebit && ! $enableShopeefood && ! $enableGofood && ! $enableGrabfood) {
            return back()
                ->withErrors(['enable_payment_cash' => 'Minimal satu metode pembayaran harus aktif.'])
                ->withInput();
        }
        if ($enableTax && $taxPercentInput <= 0) {
            return back()
                ->withErrors(['tax_percent' => 'Persen pajak harus lebih dari 0 saat pajak diaktifkan.'])
                ->withInput();
        }

        $setting->operasional_reset_hour = (int) $data['operasional_reset_hour'];
        $setting->active_shift_count = (int) $data['active_shift_count'];
        $setting->default_cash_float = isset($data['default_cash_float'])
            ? (float) $data['default_cash_float']
            : null;
        $setting->setoran_interval_days = (int) ($data['setoran_interval_days'] ?? $setting->setoran_interval_days ?? 7);
        $setting->enable_keuangan_menu = $enableKeuanganMenu;
        $setting->enable_tax = $enableTax;
        // Keep last configured tax percent even when tax is temporarily disabled.
        $setting->tax_percent = max(0, $taxPercentInput);
        $setting->tax_mode = in_array($taxMode, ['transaksi', 'produk'], true) ? $taxMode : 'transaksi';
        $setting->enable_payment_cash = $enableCash;
        $setting->enable_payment_qris = $enableQris;
        $setting->enable_payment_debit = $enableDebit;
        $setting->enable_payment_shopeefood = $enableShopeefood;
        $setting->enable_payment_gofood = $enableGofood;
        $setting->enable_payment_grabfood = $enableGrabfood;
        $setting->shift1_start_time = (string) ($data['shift1_start_time'] ?? ($setting->shift1_start_time ?? '07:00'));
        $setting->shift2_start_time = (string) ($data['shift2_start_time'] ?? ($setting->shift2_start_time ?? '15:00'));
        $setting->shift3_start_time = (string) ($data['shift3_start_time'] ?? ($setting->shift3_start_time ?? '23:00'));

        $setting->save();

        return redirect()
            ->route('dashboard.workspace')
            ->with('success', 'Ruang kerja admin berhasil diperbarui.');
    }

    public function statistik(Request $request): View
    {
        $resetHour = $this->resolveOperationalResetHour();
        $deliveryMethods = ['shopeefood', 'gofood', 'grabfood'];
        $salesMode = $this->resolveSalesMode($request);
        $salesModeLabel = match ($salesMode) {
            'app' => 'Penjualan Aplikasi',
            'all' => 'Semua Penjualan',
            default => 'Penjualan Biasa',
        };
        $operasionalNow = now()->subHours($resetHour);
        $endDateDefault = $operasionalNow->toDateString();
        $startDateDefault = $operasionalNow->copy()->subDays(29)->toDateString();

        $filters = $request->validate([
            'tanggal_awal' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date'],
        ]);

        $tanggalAwal = (string) ($filters['tanggal_awal'] ?? $startDateDefault);
        $tanggalAkhir = (string) ($filters['tanggal_akhir'] ?? $endDateDefault);
        $startOperationalDate = Carbon::parse($tanggalAwal)->startOfDay();
        $endOperationalDate = Carbon::parse($tanggalAkhir)->startOfDay();
        if ($endOperationalDate->lt($startOperationalDate)) {
            [$startOperationalDate, $endOperationalDate] = [$endOperationalDate, $startOperationalDate];
        }

        $rangeDays = max(1, $startOperationalDate->diffInDays($endOperationalDate) + 1);
        $startTs = $startOperationalDate->copy()->addHours($resetHour);
        $endTs = $endOperationalDate->copy()->addDay()->addHours($resetHour);

        $prevStartTs = $startTs->copy()->subDays($rangeDays);
        $prevEndTs = $startTs->copy();

        $driver = DB::getDriverName();
        $adjustedDateExpr = $driver === 'sqlite'
            ? "DATE(datetime(p.waktu_pembayaran, '-{$resetHour} hours'))"
            : "DATE(DATE_SUB(p.waktu_pembayaran, INTERVAL {$resetHour} HOUR))";
        $adjustedMonthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', datetime(p.waktu_pembayaran, '-{$resetHour} hours'))"
            : "DATE_FORMAT(DATE_SUB(p.waktu_pembayaran, INTERVAL {$resetHour} HOUR), '%Y-%m')";
        $hourExpr = $driver === 'sqlite'
            ? "CAST(strftime('%H', p.waktu_pembayaran) as INTEGER)"
            : 'HOUR(p.waktu_pembayaran)';

        $summaryCurrentQuery = DB::table('pesanan as p')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as omzet')
            ->selectRaw('COUNT(*) as transaksi')
            ->selectRaw('COALESCE(SUM(COALESCE(p.diskon_nominal,0)),0) as total_diskon')
            ->selectRaw('COALESCE(SUM(COALESCE(p.pajak_nominal,0)),0) as total_pajak')
            ->selectRaw('COALESCE(SUM(COALESCE(p.subtotal_harga, p.total_harga + COALESCE(p.diskon_nominal,0) - COALESCE(p.pajak_nominal,0))),0) as omzet_bruto')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        $this->applySalesModeFilter($summaryCurrentQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $summaryCurrent = $summaryCurrentQuery->first();
        $summaryPrevQuery = DB::table('pesanan as p')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as omzet')
            ->selectRaw('COUNT(*) as transaksi')
            ->selectRaw('COALESCE(SUM(COALESCE(p.diskon_nominal,0)),0) as total_diskon')
            ->selectRaw('COALESCE(SUM(COALESCE(p.pajak_nominal,0)),0) as total_pajak')
            ->selectRaw('COALESCE(SUM(COALESCE(p.subtotal_harga, p.total_harga + COALESCE(p.diskon_nominal,0) - COALESCE(p.pajak_nominal,0))),0) as omzet_bruto')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $prevStartTs)
            ->where('p.waktu_pembayaran', '<', $prevEndTs);
        $this->applySalesModeFilter($summaryPrevQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $summaryPrev = $summaryPrevQuery->first();

        $omzetTotal = (float) ($summaryCurrent->omzet ?? 0); // netto
        $omzetBruto = (float) ($summaryCurrent->omzet_bruto ?? 0);
        $totalDiskon = (float) ($summaryCurrent->total_diskon ?? 0);
        $totalPajak = (float) ($summaryCurrent->total_pajak ?? 0);
        $transaksiTotal = (int) ($summaryCurrent->transaksi ?? 0);
        $avgTicket = $transaksiTotal > 0 ? ($omzetTotal / $transaksiTotal) : 0.0;
        $omzetPrev = (float) ($summaryPrev->omzet ?? 0);
        $omzetBrutoPrev = (float) ($summaryPrev->omzet_bruto ?? 0);
        $totalDiskonPrev = (float) ($summaryPrev->total_diskon ?? 0);
        $transaksiPrev = (int) ($summaryPrev->transaksi ?? 0);
        $omzetGrowth = $omzetPrev > 0 ? (($omzetTotal - $omzetPrev) / $omzetPrev) * 100 : ($omzetTotal > 0 ? 100.0 : 0.0);
        $omzetBrutoGrowth = $omzetBrutoPrev > 0 ? (($omzetBruto - $omzetBrutoPrev) / $omzetBrutoPrev) * 100 : ($omzetBruto > 0 ? 100.0 : 0.0);
        $diskonGrowth = $totalDiskonPrev > 0 ? (($totalDiskon - $totalDiskonPrev) / $totalDiskonPrev) * 100 : ($totalDiskon > 0 ? 100.0 : 0.0);
        $trxGrowth = $transaksiPrev > 0 ? (($transaksiTotal - $transaksiPrev) / $transaksiPrev) * 100 : ($transaksiTotal > 0 ? 100.0 : 0.0);

        $dailyRowsQuery = DB::table('pesanan as p')
            ->selectRaw("{$adjustedDateExpr} as operational_date")
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as omzet')
            ->selectRaw('COUNT(*) as transaksi')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        $this->applySalesModeFilter($dailyRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $dailyRows = $dailyRowsQuery->groupBy('operational_date')->get()
            ->keyBy('operational_date');

        $dailyTrend = collect(range(0, $rangeDays - 1))
            ->map(function (int $offset) use ($startOperationalDate, $dailyRows) {
                $day = $startOperationalDate->copy()->addDays($offset);
                $key = $day->toDateString();
                $row = $dailyRows->get($key);

                return [
                    'date' => $key,
                    'label' => $day->format('d/m'),
                    'omzet' => (float) ($row->omzet ?? 0),
                    'transaksi' => (int) ($row->transaksi ?? 0),
                ];
            })
            ->values();
        $dailyTrendMax = (float) max(1, (float) $dailyTrend->max('omzet'));

        $methodCaseExpr = $driver === 'sqlite'
            ? "LOWER(TRIM(COALESCE(p.metode_pembayaran, '')))"
            : "LOWER(TRIM(IFNULL(p.metode_pembayaran, '')))";

        $methodLabels = [];
        $methodColors = [];
        if ($salesMode === 'app') {
            $methodKeys = ['shopeefood', 'gofood', 'grabfood'];
            $methodLabels = ['shopeefood' => 'ShopeeFood', 'gofood' => 'GoFood', 'grabfood' => 'GrabFood'];
            $methodColors = ['shopeefood' => '#f97316', 'gofood' => '#22c55e', 'grabfood' => '#3b82f6'];
        } elseif ($salesMode === 'all') {
            $methodKeys = ['cash', 'qris', 'debit', 'delivery'];
            $methodLabels = ['cash' => 'Cash', 'qris' => 'QRIS', 'debit' => 'Debit', 'delivery' => 'Aplikasi'];
            $methodColors = ['cash' => 'var(--accent-2)', 'qris' => '#38bdf8', 'debit' => '#d97706', 'delivery' => '#7c3aed'];
        } else {
            $methodKeys = ['cash', 'qris', 'debit'];
            $methodLabels = ['cash' => 'Cash', 'qris' => 'QRIS', 'debit' => 'Debit'];
            $methodColors = ['cash' => 'var(--accent-2)', 'qris' => '#38bdf8', 'debit' => '#d97706'];
        }

        $dailyMethodRowsQuery = DB::table('pesanan as p')
            ->selectRaw("{$adjustedDateExpr} as operational_date")
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        foreach ($methodKeys as $key) {
            if ($key === 'delivery') {
                $dailyMethodRowsQuery->selectRaw("COALESCE(SUM(CASE WHEN {$methodCaseExpr} IN ('shopeefood','gofood','grabfood') THEN p.total_harga ELSE 0 END),0) as delivery_omzet");
            } else {
                $dailyMethodRowsQuery->selectRaw("COALESCE(SUM(CASE WHEN {$methodCaseExpr} = '{$key}' THEN p.total_harga ELSE 0 END),0) as {$key}_omzet");
            }
        }
        $this->applySalesModeFilter($dailyMethodRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $dailyMethodRows = $dailyMethodRowsQuery->groupByRaw($adjustedDateExpr)->get()
            ->keyBy('operational_date');

        $dailySeries = [
            'total' => $dailyTrend->map(fn (array $row): array => ['date' => $row['date'], 'label' => $row['label'], 'omzet' => (float) $row['omzet'], 'transaksi' => (int) $row['transaksi']])->values(),
        ];
        foreach ($methodKeys as $key) {
            $dailySeries[$key] = $dailyTrend->map(function (array $row) use ($dailyMethodRows, $key): array {
                $dateKey = (string) ($row['date'] ?? '');
                $methodRow = $dailyMethodRows->get($dateKey);
                $prop = $key . '_omzet';
                return ['date' => $row['date'], 'label' => $row['label'], 'omzet' => (float) ($methodRow->{$prop} ?? 0)];
            })->values();
        }

        $monthStart = $operasionalNow->copy()->startOfMonth()->subMonths(11)->startOfDay()->addHours($resetHour);
        $monthEnd = $operasionalNow->copy()->startOfMonth()->addMonth()->startOfDay()->addHours($resetHour);
        $monthlyRowsQuery = DB::table('pesanan as p')
            ->selectRaw("{$adjustedMonthExpr} as operational_month")
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as omzet')
            ->selectRaw('COUNT(*) as transaksi')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $monthStart)
            ->where('p.waktu_pembayaran', '<', $monthEnd);
        $this->applySalesModeFilter($monthlyRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $monthlyRows = $monthlyRowsQuery->groupBy('operational_month')->get()
            ->keyBy('operational_month');

        // Per-metode bulanan — same method keys as daily
        $monthlyMethodRowsQuery = DB::table('pesanan as p')
            ->selectRaw("{$adjustedMonthExpr} as operational_month")
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $monthStart)
            ->where('p.waktu_pembayaran', '<', $monthEnd);
        foreach ($methodKeys as $key) {
            if ($key === 'delivery') {
                $monthlyMethodRowsQuery->selectRaw("COALESCE(SUM(CASE WHEN {$methodCaseExpr} IN ('shopeefood','gofood','grabfood') THEN p.total_harga ELSE 0 END),0) as delivery_omzet");
            } else {
                $monthlyMethodRowsQuery->selectRaw("COALESCE(SUM(CASE WHEN {$methodCaseExpr} = '{$key}' THEN p.total_harga ELSE 0 END),0) as {$key}_omzet");
            }
        }
        $this->applySalesModeFilter($monthlyMethodRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $monthlyMethodRows = $monthlyMethodRowsQuery->groupByRaw($adjustedMonthExpr)->get()
            ->keyBy('operational_month');

        $monthlyTrend = collect(range(11, 0))
            ->map(function (int $offset) use ($operasionalNow, $monthlyRows, $monthlyMethodRows, $methodKeys) {
                $month = $operasionalNow->copy()->subMonths($offset)->startOfMonth();
                $key = $month->format('Y-m');
                $row = $monthlyRows->get($key);
                $methodRow = $monthlyMethodRows->get($key);

                $point = [
                    'month'     => $key,
                    'label'     => $month->format('M y'),
                    'omzet'     => (float) ($row->omzet ?? 0),
                    'transaksi' => (int) ($row->transaksi ?? 0),
                ];
                foreach ($methodKeys as $mKey) {
                    $prop = $mKey . '_omzet';
                    $point[$mKey] = (float) ($methodRow->{$prop} ?? 0);
                }

                return $point;
            })
            ->values();
        $monthlyTrendMax = (float) max(1, (float) $monthlyTrend->max('omzet'));

        $metodeRowsQuery = DB::table('pesanan as p')
            ->select('p.metode_pembayaran')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as total')
            ->selectRaw('COUNT(*) as transaksi')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        $this->applySalesModeFilter($metodeRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $metodeRows = $metodeRowsQuery->groupBy('p.metode_pembayaran')->get()
            ->keyBy('metode_pembayaran');
        $paymentBreakdown = collect($methodKeys)->mapWithKeys(function (string $metode) use ($metodeRows, $omzetTotal, $deliveryMethods) {
            if ($metode === 'delivery') {
                $total = (float) collect($deliveryMethods)->sum(fn (string $m): float => (float) ($metodeRows->get($m)->total ?? 0));
                $trx = (int) collect($deliveryMethods)->sum(fn (string $m): int => (int) ($metodeRows->get($m)->transaksi ?? 0));
            } else {
                $total = (float) ($metodeRows->get($metode)->total ?? 0);
                $trx = (int) ($metodeRows->get($metode)->transaksi ?? 0);
            }
            $pct = $omzetTotal > 0 ? round(($total / $omzetTotal) * 100, 2) : 0.0;
            $avg = $trx > 0 ? ($total / $trx) : 0.0;
            return [$metode => ['total' => $total, 'trx' => $trx, 'pct' => $pct, 'avg' => $avg]];
        });

        $categoryRowsQuery = DB::table('detail_pesanan as dp')
            ->join('pesanan as p', 'p.id_pesanan', '=', 'dp.id_pesanan')
            ->join('produk as pr', 'pr.id_produk', '=', 'dp.id_produk')
            ->join('kategori as k', 'k.id_kategori', '=', 'pr.id_kategori')
            ->select('k.id_kategori', 'k.nama_kategori')
            ->selectRaw('SUM(dp.jumlah) as qty')
            ->selectRaw('SUM(dp.jumlah * dp.harga_satuan) as omzet')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        $this->applySalesModeFilter($categoryRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $categoryRows = $categoryRowsQuery->groupBy('k.id_kategori', 'k.nama_kategori')
            ->orderByDesc('omzet')
            ->get();
        $categoryTotal = (float) $categoryRows->sum('omzet');
        $categoryBreakdown = $categoryRows->map(function (object $row) use ($categoryTotal): array {
            $omzet = (float) ($row->omzet ?? 0);
            return [
                'id_kategori' => (int) ($row->id_kategori ?? 0),
                'nama_kategori' => (string) ($row->nama_kategori ?? '-'),
                'qty' => (int) ($row->qty ?? 0),
                'omzet' => $omzet,
                'pct' => $categoryTotal > 0 ? round(($omzet / $categoryTotal) * 100, 2) : 0.0,
            ];
        })->values();

        $promoPerformanceQuery = DB::table('pesanan as p')
            ->selectRaw("COALESCE(NULLIF(TRIM(p.diskon_nama), ''), 'Tanpa Nama Promo') as promo_nama")
            ->selectRaw("COALESCE(NULLIF(TRIM(p.diskon_tipe), ''), '-') as promo_tipe")
            ->selectRaw('COUNT(*) as trx')
            ->selectRaw('COALESCE(SUM(COALESCE(p.diskon_nominal,0)),0) as total_potongan')
            ->selectRaw('COALESCE(SUM(COALESCE(p.subtotal_harga, p.total_harga + COALESCE(p.diskon_nominal,0) - COALESCE(p.pajak_nominal,0))),0) as omzet_bruto')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as omzet_netto')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs)
            ->whereRaw('COALESCE(p.diskon_nominal, 0) > 0');
        $this->applySalesModeFilter($promoPerformanceQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $promoPerformance = $promoPerformanceQuery->groupByRaw("COALESCE(NULLIF(TRIM(p.diskon_nama), ''), 'Tanpa Nama Promo'), COALESCE(NULLIF(TRIM(p.diskon_tipe), ''), '-')")
            ->orderByDesc('total_potongan')
            ->limit(15)
            ->get();

        $topProductsQuery = DB::table('detail_pesanan as dp')
            ->join('pesanan as p', 'p.id_pesanan', '=', 'dp.id_pesanan')
            ->join('produk as pr', 'pr.id_produk', '=', 'dp.id_produk')
            ->select('pr.nama_produk')
            ->selectRaw('SUM(dp.jumlah) as qty')
            ->selectRaw('SUM(dp.jumlah * dp.harga_satuan) as omzet')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        $this->applySalesModeFilter($topProductsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $topProducts = $topProductsQuery->groupBy('pr.id_produk', 'pr.nama_produk')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $hourRowsQuery = DB::table('pesanan as p')
            ->selectRaw("{$hourExpr} as jam")
            ->selectRaw('COUNT(*) as transaksi')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as omzet')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        $this->applySalesModeFilter($hourRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $hourRows = $hourRowsQuery->groupBy('jam')->get()
            ->keyBy('jam');
        $hourlyStats = collect(range(0, 23))
            ->map(function (int $hour) use ($hourRows) {
                $row = $hourRows->get($hour);
                return [
                    'jam' => $hour,
                    'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00',
                    'trx' => (int) ($row->transaksi ?? 0),
                    'omzet' => (float) ($row->omzet ?? 0),
                ];
            })
            ->values();
        $hourlyMaxTrx = (float) max(1, (float) $hourlyStats->max('trx'));

        return view('dashboard.statistik', [
            'resetHour' => $resetHour,
            'tanggalAwal' => $startOperationalDate->toDateString(),
            'tanggalAkhir' => $endOperationalDate->toDateString(),
            'rangeDays' => $rangeDays,
            'omzetTotal' => $omzetTotal,
            'omzetBruto' => $omzetBruto,
            'totalDiskon' => $totalDiskon,
            'totalPajak' => $totalPajak,
            'transaksiTotal' => $transaksiTotal,
            'avgTicket' => $avgTicket,
            'omzetGrowth' => $omzetGrowth,
            'omzetBrutoGrowth' => $omzetBrutoGrowth,
            'diskonGrowth' => $diskonGrowth,
            'trxGrowth' => $trxGrowth,
            'dailyTrend' => $dailyTrend,
            'dailyTrendMax' => $dailyTrendMax,
            'dailySeries' => $dailySeries,
            'monthlyTrend' => $monthlyTrend,
            'monthlyTrendMax' => $monthlyTrendMax,
            'paymentBreakdown' => $paymentBreakdown,
            'salesMode' => $salesMode,
            'salesModeLabel' => $salesModeLabel,
            'methodKeys' => $methodKeys,
            'methodLabels' => $methodLabels,
            'methodColors' => $methodColors,
            'categoryBreakdown' => $categoryBreakdown,
            'promoPerformance' => $promoPerformance,
            'topProducts' => $topProducts,
            'hourlyStats' => $hourlyStats,
            'hourlyMaxTrx' => $hourlyMaxTrx,
        ]);
    }

    public function exportStatistikExcel(Request $request): StreamedResponse
    {
        $resetHour = $this->resolveOperationalResetHour();
        $deliveryMethods = ['shopeefood', 'gofood', 'grabfood'];
        $salesMode = $this->resolveSalesMode($request);
        $salesModeLabel = match ($salesMode) {
            'app' => 'Penjualan Aplikasi',
            'all' => 'Semua Penjualan',
            default => 'Penjualan Biasa',
        };
        $operasionalNow = now()->subHours($resetHour);
        $endDateDefault = $operasionalNow->toDateString();
        $startDateDefault = $operasionalNow->copy()->subDays(29)->toDateString();

        $filters = $request->validate([
            'tanggal_awal' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date'],
        ]);

        $tanggalAwal = (string) ($filters['tanggal_awal'] ?? $startDateDefault);
        $tanggalAkhir = (string) ($filters['tanggal_akhir'] ?? $endDateDefault);
        $startOperationalDate = Carbon::parse($tanggalAwal)->startOfDay();
        $endOperationalDate = Carbon::parse($tanggalAkhir)->startOfDay();
        if ($endOperationalDate->lt($startOperationalDate)) {
            [$startOperationalDate, $endOperationalDate] = [$endOperationalDate, $startOperationalDate];
        }

        $startTs = $startOperationalDate->copy()->addHours($resetHour);
        $endTs = $endOperationalDate->copy()->addDay()->addHours($resetHour);

        $driver = DB::getDriverName();
        $adjustedDateExpr = $driver === 'sqlite'
            ? "DATE(datetime(p.waktu_pembayaran, '-{$resetHour} hours'))"
            : "DATE(DATE_SUB(p.waktu_pembayaran, INTERVAL {$resetHour} HOUR))";

        $summaryQuery = DB::table('pesanan as p')
            ->selectRaw('COUNT(*) as transaksi')
            ->selectRaw('COALESCE(SUM(COALESCE(p.subtotal_harga, p.total_harga + COALESCE(p.diskon_nominal,0) - COALESCE(p.pajak_nominal,0))),0) as omzet_bruto')
            ->selectRaw('COALESCE(SUM(COALESCE(p.diskon_nominal,0)),0) as total_diskon')
            ->selectRaw('COALESCE(SUM(COALESCE(p.pajak_nominal,0)),0) as total_pajak')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as omzet_netto')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        $this->applySalesModeFilter($summaryQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $summary = $summaryQuery->first();

        $methodLabels = [];
        if ($salesMode === 'app') {
            $methodKeys = ['shopeefood', 'gofood', 'grabfood'];
            $methodLabels = ['shopeefood' => 'ShopeeFood', 'gofood' => 'GoFood', 'grabfood' => 'GrabFood'];
        } elseif ($salesMode === 'all') {
            $methodKeys = ['cash', 'qris', 'debit', 'delivery'];
            $methodLabels = ['cash' => 'CASH', 'qris' => 'QRIS', 'debit' => 'DEBIT', 'delivery' => 'APLIKASI'];
        } else {
            $methodKeys = ['cash', 'qris', 'debit'];
            $methodLabels = ['cash' => 'CASH', 'qris' => 'QRIS', 'debit' => 'DEBIT'];
        }

        $dailyRowsQuery = DB::table('pesanan as p')
            ->selectRaw("{$adjustedDateExpr} as tanggal_operasional")
            ->selectRaw('COUNT(*) as transaksi')
            ->selectRaw('COALESCE(SUM(COALESCE(p.subtotal_harga, p.total_harga + COALESCE(p.diskon_nominal,0) - COALESCE(p.pajak_nominal,0))),0) as omzet_bruto')
            ->selectRaw('COALESCE(SUM(COALESCE(p.diskon_nominal,0)),0) as total_diskon')
            ->selectRaw('COALESCE(SUM(COALESCE(p.pajak_nominal,0)),0) as total_pajak')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as omzet_netto')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        foreach ($methodKeys as $key) {
            if ($key === 'delivery') {
                $dailyRowsQuery->selectRaw("COALESCE(SUM(CASE WHEN p.metode_pembayaran IN ('shopeefood','gofood','grabfood') THEN p.total_harga ELSE 0 END),0) as omzet_delivery");
            } else {
                $dailyRowsQuery->selectRaw("COALESCE(SUM(CASE WHEN p.metode_pembayaran = '{$key}' THEN p.total_harga ELSE 0 END),0) as omzet_{$key}");
            }
        }
        $this->applySalesModeFilter($dailyRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $dailyRows = $dailyRowsQuery->groupByRaw($adjustedDateExpr)
            ->orderBy('tanggal_operasional')
            ->get();

        $paymentRowsQuery = DB::table('pesanan as p')
            ->select('p.metode_pembayaran')
            ->selectRaw('COUNT(*) as transaksi')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as total')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        $this->applySalesModeFilter($paymentRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $paymentRows = $paymentRowsQuery->groupBy('p.metode_pembayaran')->get()
            ->keyBy('metode_pembayaran');

        $categoryRowsQuery = DB::table('detail_pesanan as dp')
            ->join('pesanan as p', 'p.id_pesanan', '=', 'dp.id_pesanan')
            ->join('produk as pr', 'pr.id_produk', '=', 'dp.id_produk')
            ->join('kategori as k', 'k.id_kategori', '=', 'pr.id_kategori')
            ->select('k.nama_kategori')
            ->selectRaw('SUM(dp.jumlah) as qty')
            ->selectRaw('SUM(dp.jumlah * dp.harga_satuan) as omzet')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs);
        $this->applySalesModeFilter($categoryRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $categoryRows = $categoryRowsQuery->groupBy('k.id_kategori', 'k.nama_kategori')
            ->orderByDesc('omzet')
            ->get();

        $promoRowsQuery = DB::table('pesanan as p')
            ->selectRaw("COALESCE(NULLIF(TRIM(p.diskon_nama), ''), 'Tanpa Nama Promo') as promo_nama")
            ->selectRaw("COALESCE(NULLIF(TRIM(p.diskon_tipe), ''), '-') as promo_tipe")
            ->selectRaw('COUNT(*) as trx')
            ->selectRaw('COALESCE(SUM(COALESCE(p.diskon_nominal,0)),0) as total_potongan')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as omzet_netto')
            ->where('p.status_pembayaran', 'lunas')
            ->where('p.waktu_pembayaran', '>=', $startTs)
            ->where('p.waktu_pembayaran', '<', $endTs)
            ->whereRaw('COALESCE(p.diskon_nominal,0) > 0');
        $this->applySalesModeFilter($promoRowsQuery, 'p.metode_pembayaran', $salesMode, $deliveryMethods);
        $promoRows = $promoRowsQuery->groupByRaw("COALESCE(NULLIF(TRIM(p.diskon_nama), ''), 'Tanpa Nama Promo'), COALESCE(NULLIF(TRIM(p.diskon_tipe), ''), '-')")
            ->orderByDesc('total_potongan')
            ->get();

        $filename = 'statistik-cafe-' . now()->format('Ymd-His') . '.xls';

        return response()->streamDownload(function () use (
            $summary,
            $dailyRows,
            $paymentRows,
            $categoryRows,
            $promoRows,
            $startOperationalDate,
            $endOperationalDate,
            $resetHour,
            $methodKeys,
            $methodLabels,
            $deliveryMethods,
            $salesModeLabel
        ): void {
            $omzetNetto = (float) ($summary->omzet_netto ?? 0);
            $omzetBruto = (float) ($summary->omzet_bruto ?? 0);
            $totalDiskon = (float) ($summary->total_diskon ?? 0);
            $totalPajak = (float) ($summary->total_pajak ?? 0);
            $totalTrx = (int) ($summary->transaksi ?? 0);
            $avgTicket = $totalTrx > 0 ? ($omzetNetto / $totalTrx) : 0.0;

            echo '<html><head><meta charset="UTF-8">';
            echo '<style>';
            echo 'body{font-family:Arial,sans-serif;font-size:12px;}';
            echo 'h2{margin:0 0 8px 0;}';
            echo 'h3{margin:16px 0 8px 0;}';
            echo 'table{border-collapse:collapse;width:100%;margin-bottom:12px;}';
            echo 'th,td{border:1px solid #333;padding:6px;}';
            echo 'th{background:#efefef;text-align:left;}';
            echo '.num{text-align:right;}';
            echo '</style></head><body>';

            echo '<h2>Laporan Statistik Cafe</h2>';
            echo '<p>Periode: ' . $this->e($startOperationalDate->toDateString()) . ' s/d ' . $this->e($endOperationalDate->toDateString()) . '</p>';
            echo '<p>Reset operasional: ' . $this->e(str_pad((string) $resetHour, 2, '0', STR_PAD_LEFT) . ':00') . '</p>';
            echo '<p>Mode Penjualan: ' . $this->e($salesModeLabel) . '</p>';

            echo '<h3>KPI Ringkasan</h3>';
            echo '<table><tbody>';
            echo '<tr><th>Total Transaksi</th><td class="num">' . $this->e($totalTrx) . '</td></tr>';
            echo '<tr><th>Omzet Bruto</th><td class="num">Rp ' . $this->e(number_format($omzetBruto, 0, ',', '.')) . '</td></tr>';
            echo '<tr><th>Total Diskon</th><td class="num">Rp ' . $this->e(number_format($totalDiskon, 0, ',', '.')) . '</td></tr>';
            echo '<tr><th>Total Pajak</th><td class="num">Rp ' . $this->e(number_format($totalPajak, 0, ',', '.')) . '</td></tr>';
            echo '<tr><th>Omzet Netto</th><td class="num">Rp ' . $this->e(number_format($omzetNetto, 0, ',', '.')) . '</td></tr>';
            echo '<tr><th>Average Ticket</th><td class="num">Rp ' . $this->e(number_format($avgTicket, 0, ',', '.')) . '</td></tr>';
            echo '</tbody></table>';

            echo '<h3>Tren Harian</h3>';
            echo '<table><thead><tr><th>Tanggal Operasional</th><th class="num">Trx</th><th class="num">Bruto</th><th class="num">Diskon</th><th class="num">Pajak</th><th class="num">Netto</th>';
            foreach ($methodKeys as $key) {
                echo '<th class="num">' . $this->e($methodLabels[$key] ?? strtoupper($key)) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($dailyRows as $row) {
                echo '<tr>';
                echo '<td>' . $this->e($row->tanggal_operasional ?? '-') . '</td>';
                echo '<td class="num">' . $this->e((int) ($row->transaksi ?? 0)) . '</td>';
                echo '<td class="num">' . $this->e(number_format((float) ($row->omzet_bruto ?? 0), 0, ',', '.')) . '</td>';
                echo '<td class="num">' . $this->e(number_format((float) ($row->total_diskon ?? 0), 0, ',', '.')) . '</td>';
                echo '<td class="num">' . $this->e(number_format((float) ($row->total_pajak ?? 0), 0, ',', '.')) . '</td>';
                echo '<td class="num">' . $this->e(number_format((float) ($row->omzet_netto ?? 0), 0, ',', '.')) . '</td>';
                foreach ($methodKeys as $key) {
                    $prop = $key === 'delivery' ? 'omzet_delivery' : 'omzet_' . $key;
                    echo '<td class="num">' . $this->e(number_format((float) ($row->{$prop} ?? 0), 0, ',', '.')) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';

            $totMetode = (float) collect($methodKeys)->sum(function (string $k) use ($paymentRows, $deliveryMethods): float {
                if ($k === 'delivery') {
                    return (float) collect($deliveryMethods)->sum(fn (string $m): float => (float) ($paymentRows->get($m)->total ?? 0));
                }
                return (float) ($paymentRows->get($k)->total ?? 0);
            });
            echo '<h3>Metode Pembayaran</h3>';
            echo '<table><thead><tr><th>Metode</th><th class="num">Trx</th><th class="num">Total</th><th class="num">Kontribusi</th></tr></thead><tbody>';
            foreach ($methodKeys as $key) {
                if ($key === 'delivery') {
                    $total = (float) collect($deliveryMethods)->sum(fn (string $m): float => (float) ($paymentRows->get($m)->total ?? 0));
                    $trx = (int) collect($deliveryMethods)->sum(fn (string $m): int => (int) ($paymentRows->get($m)->transaksi ?? 0));
                } else {
                    $total = (float) ($paymentRows->get($key)->total ?? 0);
                    $trx = (int) ($paymentRows->get($key)->transaksi ?? 0);
                }
                $pct = $totMetode > 0 ? (($total / $totMetode) * 100) : 0;
                echo '<tr>';
                echo '<td>' . $this->e($methodLabels[$key] ?? strtoupper($key)) . '</td>';
                echo '<td class="num">' . $this->e($trx) . '</td>';
                echo '<td class="num">' . $this->e(number_format($total, 0, ',', '.')) . '</td>';
                echo '<td class="num">' . $this->e(number_format($pct, 2, ',', '.')) . '%</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            $catTotal = (float) $categoryRows->sum('omzet');
            echo '<h3>Kontribusi Kategori</h3>';
            echo '<table><thead><tr><th>Kategori</th><th class="num">Qty</th><th class="num">Omzet</th><th class="num">Kontribusi</th></tr></thead><tbody>';
            foreach ($categoryRows as $row) {
                $omzet = (float) ($row->omzet ?? 0);
                $pct = $catTotal > 0 ? (($omzet / $catTotal) * 100) : 0;
                echo '<tr>';
                echo '<td>' . $this->e($row->nama_kategori ?? '-') . '</td>';
                echo '<td class="num">' . $this->e((int) ($row->qty ?? 0)) . '</td>';
                echo '<td class="num">' . $this->e(number_format($omzet, 0, ',', '.')) . '</td>';
                echo '<td class="num">' . $this->e(number_format($pct, 2, ',', '.')) . '%</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            echo '<h3>Performa Promo</h3>';
            echo '<table><thead><tr><th>Promo</th><th>Tipe</th><th class="num">Trx</th><th class="num">Total Potongan</th><th class="num">Omzet Netto</th></tr></thead><tbody>';
            foreach ($promoRows as $row) {
                echo '<tr>';
                echo '<td>' . $this->e($row->promo_nama ?? '-') . '</td>';
                echo '<td>' . $this->e($row->promo_tipe ?? '-') . '</td>';
                echo '<td class="num">' . $this->e((int) ($row->trx ?? 0)) . '</td>';
                echo '<td class="num">' . $this->e(number_format((float) ($row->total_potongan ?? 0), 0, ',', '.')) . '</td>';
                echo '<td class="num">' . $this->e(number_format((float) ($row->omzet_netto ?? 0), 0, ',', '.')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';

            echo '</body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function resolveOperationalResetHour(): int
    {
        $configHour = (int) config('app.operasional_reset_hour', 3);
        if (app()->environment('testing') && $configHour !== 3) {
            return max(0, min(23, $configHour));
        }

        $settingHour = (int) (StrukSetting::query()->value('operasional_reset_hour') ?? -1);
        if ($settingHour >= 0 && $settingHour <= 23) {
            return $settingHour;
        }

        return max(0, min(23, $configHour));
    }

    private function resolveSalesMode(Request $request): string
    {
        $mode = (string) $request->query('sales_mode', 'regular');
        return in_array($mode, ['regular', 'app', 'all'], true) ? $mode : 'regular';
    }

    private function applySalesModeFilter(\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query, string $column, string $mode, array $deliveryMethods): void
    {
        if ($mode === 'app') {
            $query->whereIn($column, $deliveryMethods);
        } elseif ($mode === 'regular') {
            $query->whereNotIn($column, $deliveryMethods);
        }
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function isKeuanganMenuEnabled(): bool
    {
        return (bool) (StrukSetting::current()->enable_keuangan_menu ?? true);
    }

    private function logSetoranAudit(
        int $setoranId,
        string $aksi,
        ?float $nominalLama,
        ?float $nominalBaru,
        ?string $catatanLama,
        ?string $catatanBaru,
        ?array $meta = null
    ): void {
        if (! Schema::hasTable('kas_setoran_audits')) {
            return;
        }

        DB::table('kas_setoran_audits')->insert([
            'setoran_id' => $setoranId,
            'aksi' => $aksi,
            'nominal_lama' => $nominalLama,
            'nominal_baru' => $nominalBaru,
            'catatan_lama' => $catatanLama,
            'catatan_baru' => $catatanBaru,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'user_id' => (int) (auth()->id() ?? 0) ?: null,
            'dibuat_pada' => now(),
        ]);
    }
}
 
