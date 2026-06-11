@extends('layouts.app')

@section('title', 'Dashboard Admin')

@php
    $salesTrendChartData = collect($salesTrend ?? [])->map(fn ($row) => [
        'date'      => $row['date'] ?? $row['label'] ?? '-',
        'omzet'     => (float) ($row['omzet'] ?? 0),
        'transaksi' => (int) ($row['transaksi'] ?? 0),
    ])->values();
@endphp

@section('scripts')
@vite(['resources/js/dashboard-chart.jsx'])
<script>
    (function () {
        const toggleBtn = document.querySelector('[data-toggle="sales-mode"]');
        const panel = document.querySelector('[data-panel="sales-mode"]');
        if (!toggleBtn || !panel) return;

        const closePanel = () => panel.setAttribute('hidden', 'hidden');

        toggleBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            if (panel.hasAttribute('hidden')) {
                panel.removeAttribute('hidden');
            } else {
                closePanel();
            }
        });

        document.addEventListener('click', (event) => {
            if (!panel.hasAttribute('hidden') && !panel.contains(event.target) && event.target !== toggleBtn) {
                    closePanel();
            }
        });
    })();
</script>
@endsection

@section('content')
@php
    $paymentSlices = $paymentSlices ?? [];
    $paymentTotal = (float) ($paymentTotal ?? 0);
    $cursor = 0;
    $stops = [];
    foreach ($paymentSlices as $slice) {
        $deg = $paymentTotal > 0 ? round(((float) $slice['total'] / $paymentTotal) * 360, 2) : 0;
        $start = $cursor;
        $cursor += $deg;
        $stops[] = "{$slice['color']} {$start}deg {$cursor}deg";
    }
    $paymentGradient = count($stops) ? 'conic-gradient(' . implode(',', $stops) . ')' : 'conic-gradient(var(--muted) 0deg 360deg)';
    $hasOmzetMetode = $paymentTotal > 0;

    $pendingTotal = (int) ($pendingSwapCount ?? 0) + (int) ($pendingLeaveCount ?? 0) + (int) ($absensiAttentionCount ?? 0);
    $activeShift = (bool) ($jadwalShiftSnapshot['hasActiveShift'] ?? false);
    $shiftLabel = $activeShift && (int) ($jadwalShiftSnapshot['shiftKe'] ?? 0) > 0
        ? 'Shift ' . (int) $jadwalShiftSnapshot['shiftKe']
        : 'Belum Aktif';

    $staffToday = collect((array) ($jadwalShiftSnapshot['staff'] ?? []));
    $announcementPreview = collect($announcements ?? [])->take(5);
    $transactionPreview = collect($transaksiTerbaru ?? []);
    $salesTrend = collect($salesTrend ?? []);
    $salesTrendMax = (float) max(1, (float) ($salesTrendMax ?? $salesTrend->max('omzet') ?? 1));

    $setoranState = 'Aman';
    $setoranStateClass = 'ok';
    if (! ($keuanganMenuEnabled ?? true)) {
        $setoranState = 'Menu Off';
        $setoranStateClass = 'warn';
    } elseif($nextSetoranDueAt === null) {
        $setoranState = 'Belum Ada Data';
        $setoranStateClass = 'warn';
    } elseif((int) $setoranDueDays < 0) {
        $setoranState = 'Terlambat';
        $setoranStateClass = 'err';
    } elseif((int) $setoranDueDays === 0) {
        $setoranState = 'Jatuh Tempo';
        $setoranStateClass = 'warn';
    }

    $absensiAttentionStart = (string) ($absensiAttentionStart ?? now()->subDays(30)->toDateString());
@endphp

<div class="container admin-dashboard admin-dashboard-v2">
    <div class="admin-page-head admin-dashboard-head">
        <div>
            <div class="admin-page-label">Dashboard</div>
            <h1>Ringkasan Operasional</h1>
            <p>Per {{ $today }} dengan mode {{ $salesModeLabel ?? 'Penjualan Biasa' }}.</p>
            <p class="admin-page-sub">{{ $operasionalInfo }}</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-neutral" href="{{ route('dashboard.statistik', array_merge(request()->query(), ['sales_mode' => $salesMode ?? 'regular'])) }}">Statistik Detail</a>
            <a class="btn-neutral" href="{{ route('transaksi.index') }}">Transaksi</a>
            <div class="admin-chip">Periode {{ $monthLabel ?? '-' }}</div>
            <div class="admin-chip soft">Reset {{ str_pad((string) ($operasionalResetHour ?? 0), 2, '0', STR_PAD_LEFT) }}:00</div>
        </div>
    </div>

    <div class="admin-dashboard-toolbar">
        <div class="modebar admin-modebar">
            <button type="button" class="mode-btn" data-toggle="sales-mode">
                Mode Penjualan
                <strong>{{ $salesModeLabel ?? 'Penjualan Biasa' }}</strong>
            </button>
            <div class="mode-panel" data-panel="sales-mode" hidden>
                <a class="{{ ($salesMode ?? 'regular') === 'regular' ? 'active' : '' }}" href="{{ route('dashboard.index', array_merge(request()->query(), ['sales_mode' => 'regular'])) }}">Penjualan Biasa</a>
                <a class="{{ ($salesMode ?? '') === 'app' ? 'active' : '' }}" href="{{ route('dashboard.index', array_merge(request()->query(), ['sales_mode' => 'app'])) }}">Penjualan Aplikasi</a>
                <a class="{{ ($salesMode ?? '') === 'all' ? 'active' : '' }}" href="{{ route('dashboard.index', array_merge(request()->query(), ['sales_mode' => 'all'])) }}">Semua Penjualan</a>
            </div>
        </div>
        <div class="admin-dashboard-toolbar-meta">
            <span class="sync-pill {{ $cashSyncOk ? 'ok' : 'warn' }}">Cash {{ $cashSyncOk ? 'sinkron' : 'cek ulang' }}</span>
            <span class="sync-pill {{ $setoranStateClass }}">{{ $setoranState }}</span>
        </div>
    </div>

    <section class="admin-section-cards">
        <a class="admin-section-card" href="{{ route('dashboard.statistik', array_merge(request()->query(), ['sales_mode' => $salesMode ?? 'regular'])) }}">
            <div class="admin-section-card-head">
                <span>Omzet Hari Ini</span>
                <span class="admin-card-badge ok">Lunas</span>
            </div>
            <strong>Rp {{ number_format((float) $omzetHariIni, 0, ',', '.') }}</strong>
            <p>{{ number_format((int) $jumlahTransaksiHariIni, 0, ',', '.') }} transaksi lunas dalam periode operasional.</p>
        </a>

        <a class="admin-section-card" href="{{ route('dashboard.leave.index', ['status' => 'pending']) }}">
            <div class="admin-section-card-head">
                <span>Approval Pending</span>
                <span class="admin-card-badge {{ $pendingTotal > 0 ? 'warn' : 'ok' }}">{{ $pendingTotal > 0 ? 'Perlu cek' : 'Beres' }}</span>
            </div>
            <strong>{{ number_format($pendingTotal, 0, ',', '.') }}</strong>
            <p>{{ (int) ($pendingLeaveCount ?? 0) }} izin, {{ (int) ($pendingSwapCount ?? 0) }} swap, {{ (int) ($absensiAttentionCount ?? 0) }} koreksi absensi.</p>
        </a>

        <a class="admin-section-card" href="{{ route('dashboard.chat.index') }}">
            <div class="admin-section-card-head">
                <span>Inbox Staff</span>
                <span class="admin-card-badge {{ (int) ($unreadChatCount ?? 0) > 0 ? 'warn' : 'ok' }}">Pesan</span>
            </div>
            <strong>{{ number_format((int) ($unreadChatCount ?? 0), 0, ',', '.') }}</strong>
            <p>Pesan belum dibaca dari thread karyawan dan operasional.</p>
        </a>

        <a class="admin-section-card" href="{{ route('dashboard.jadwal.index', ['bulan' => substr((string) $today, 0, 7)]) }}">
            <div class="admin-section-card-head">
                <span>Shift Hari Ini</span>
                <span class="admin-card-badge {{ $activeShift ? 'ok' : 'warn' }}">{{ $activeShift ? 'Aktif' : 'Standby' }}</span>
            </div>
            <strong>{{ $shiftLabel }}</strong>
            <p>{{ $staffToday->count() }} staff terjadwal untuk tanggal {{ (string) ($jadwalShiftSnapshot['today'] ?? $today) }}.</p>
        </a>
    </section>

    <section class="admin-dashboard-main-grid">
        <div
            id="react-dashboard-chart"
            data-chart-data="{{ json_encode($salesTrendChartData) }}"
            data-title="Area Chart - Pendapatan"
            data-description="Omzet dan transaksi operasional terakhir"
            data-stats-url="{{ route('dashboard.statistik', array_merge(request()->query(), ['sales_mode' => $salesMode ?? 'regular'])) }}"
        ></div>

        <div class="admin-payment-card">
            <div class="admin-card-header">
                <div>
                    <h2>Komposisi Pembayaran</h2>
                    <p>{{ $paymentCenterLabel ?? 'Omzet Operasional' }}</p>
                </div>
                <div class="mini-nav">
                    <a class="btn-neutral {{ $paymentPeriod === 'today' ? 'active' : '' }}" href="{{ route('dashboard.index', array_merge(request()->query(), ['payment_period' => 'today'])) }}">Hari Ini</a>
                    <a class="btn-neutral {{ $paymentPeriod === 'month' ? 'active' : '' }}" href="{{ route('dashboard.index', array_merge(request()->query(), ['payment_period' => 'month'])) }}">Bulan Ini</a>
                </div>
            </div>
            <div class="admin-payment-content">
                <div class="pay-donut" style="--donut-bg: {{ $paymentGradient }};">
                    <div class="pay-center">
                        <div>Total</div>
                        <strong>Rp {{ number_format($paymentTotal, 0, ',', '.') }}</strong>
                    </div>
                </div>
                <div class="pay-legend">
                    @foreach($paymentSlices as $slice)
                        <div class="pay-item">
                            <span class="pay-dot" style="background:{{ $slice['color'] }}"></span>
                            <span class="pay-label">{{ $slice['label'] }}</span>
                            <span class="pay-value">Rp {{ number_format((float) $slice['total'], 0, ',', '.') }}</span>
                            <span class="pay-trx">{{ (int) $slice['trx'] }} trx</span>
                            <span class="pay-pct">{{ number_format((float) ($slice['pct'] ?? 0), 2, ',', '.') }}%</span>
                        </div>
                    @endforeach
                    @if(! $hasOmzetMetode)
                        <div class="muted">Belum ada transaksi lunas pada periode ini.</div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="admin-dashboard-action-grid">
        <div class="admin-dashboard-card">
            <div class="admin-card-header">
                <div>
                    <h2>Action Queue</h2>
                    <p>Tindakan yang butuh perhatian admin.</p>
                </div>
            </div>
            <div class="admin-action-list">
                <a class="admin-action-row" href="{{ route('dashboard.leave.index', ['status' => 'pending']) }}">
                    <span class="admin-action-icon">IZ</span>
                    <div>
                        <strong>Izin dan sakit</strong>
                        <small>Pengajuan yang menunggu keputusan.</small>
                    </div>
                    <em>{{ (int) ($pendingLeaveCount ?? 0) }}</em>
                </a>
                <a class="admin-action-row" href="{{ route('dashboard.jadwal.swap_requests', ['status' => 'pending']) }}">
                    <span class="admin-action-icon">SW</span>
                    <div>
                        <strong>Tukar shift</strong>
                        <small>Permintaan pertukaran jadwal staff.</small>
                    </div>
                    <em>{{ (int) ($pendingSwapCount ?? 0) }}</em>
                </a>
                <a class="admin-action-row" href="{{ route('dashboard.absensi', ['tanggal_awal' => $absensiAttentionStart, 'tanggal_akhir' => $today, 'correction_state' => 'needs_attention']) }}">
                    <span class="admin-action-icon">AB</span>
                    <div>
                        <strong>Koreksi Absensi</strong>
                        <small>{{ (int) ($absensiRequestedCount ?? 0) }} menunggu admin, {{ (int) ($absensiForgotCount ?? 0) }} lupa pulang.</small>
                    </div>
                    <em>{{ (int) ($absensiAttentionCount ?? 0) }}</em>
                </a>
            </div>
        </div>

        <div class="admin-dashboard-card">
            <div class="admin-card-header">
                <div>
                    <h2>Shift Aktif</h2>
                    <p>{{ $activeShift ? 'Shift sedang berjalan.' : 'Belum ada shift aktif saat ini.' }}</p>
                </div>
                <span class="sync-pill {{ $activeShift ? 'ok' : 'warn' }}">{{ $shiftLabel }}</span>
            </div>
            <div class="admin-staff-list">
                @forelse($staffToday->take(6) as $s)
                    <div class="admin-staff-row">
                        <span>{{ strtoupper(\Illuminate\Support\Str::substr((string) ($s['nama'] ?? 'S'), 0, 1)) }}</span>
                        <strong>{{ $s['nama'] ?? '-' }}</strong>
                        <em class="{{ !empty($s['absen']) ? 'ok' : 'warn' }}">{{ !empty($s['absen']) ? 'Sudah absen' : 'Belum absen' }}</em>
                    </div>
                @empty
                    <div class="muted">Belum ada staff terjadwal untuk shift aktif.</div>
                @endforelse
            </div>
        </div>

        <div class="admin-dashboard-card">
            <div class="admin-card-header">
                <div>
                    <h2>Kas dan Setoran</h2>
                    <p>Status cash operasional dan jadwal setoran.</p>
                </div>
                <span class="sync-pill {{ $setoranStateClass }}">{{ $setoranState }}</span>
            </div>
            <div class="admin-finance-list">
                <div>
                    <span>Omzet Bulan Ini</span>
                    <strong>Rp {{ number_format((float) $omzetBulanIni, 0, ',', '.') }}</strong>
                </div>
                <div>
                    <span>Saldo Belum Disetor</span>
                    <strong>{{ ! ($keuanganMenuEnabled ?? true) ? 'Menu Keuangan Off' : 'Rp ' . number_format((float) $saldoBelumDisetor, 0, ',', '.') }}</strong>
                </div>
                <div>
                    <span>Estimasi Uang di Laci</span>
                    <strong>Rp {{ number_format((float) $estimasiKasSaatIni, 0, ',', '.') }}</strong>
                </div>
                <div>
                    <span>Jatuh Tempo</span>
                    <strong>
                        @if(! ($keuanganMenuEnabled ?? true))
                            Nonaktif
                        @elseif($nextSetoranDueAt)
                            {{ $nextSetoranDueAt->format('d/m/Y') }}
                        @else
                            Belum ada setoran
                        @endif
                    </strong>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-dashboard-split">
        <div class="admin-dashboard-card">
            <div class="admin-card-header">
                <div>
                    <h2>Produk Terlaris</h2>
                    <p>Produk paling banyak terjual hari ini.</p>
                </div>
            </div>
            <div class="admin-ranking-list">
                @forelse($produkTerlarisHariIni as $item)
                    <div class="admin-ranking-row">
                        <span>{{ $loop->iteration }}</span>
                        <strong>{{ $item->nama_produk }}</strong>
                        <em>{{ (int) $item->total_terjual }} terjual</em>
                    </div>
                @empty
                    <div class="muted">Belum ada penjualan hari ini.</div>
                @endforelse
            </div>
        </div>

        <div class="admin-dashboard-card">
            <div class="admin-card-header">
                <div>
                    <h2>Pengumuman Terbaru</h2>
                    <p>Promo, info SOP, dan update toko.</p>
                </div>
                <a class="btn-neutral" href="{{ route('dashboard.announcements.index') }}">Kelola</a>
            </div>
            <div class="admin-news-list">
                @forelse($announcementPreview as $ann)
                    <a class="admin-news-row" href="{{ route('dashboard.announcements.show', $ann) }}">
                        <strong>{{ $ann->title }}</strong>
                        <span>{{ \Illuminate\Support\Str::limit(strip_tags((string) $ann->body), 86) }}</span>
                    </a>
                @empty
                    <div class="muted">Belum ada pengumuman terbaru.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="admin-dashboard-card admin-dashboard-table-card">
        <div class="admin-card-header">
            <div>
                <h2>Transaksi Terbaru</h2>
                <p>Data realtime transaksi terakhir dari POS kasir dan mode admin.</p>
            </div>
            <div class="admin-card-actions">
                <a class="btn-neutral" href="{{ route('dashboard.statistik', array_merge(request()->query(), ['sales_mode' => $salesMode ?? 'regular'])) }}">Statistik Detail</a>
                <a class="btn-neutral" href="{{ route('transaksi.index') }}">Lihat Semua</a>
            </div>
        </div>
        <div class="table-wrap admin-data-table">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Waktu</th>
                    <th>Pelanggan</th>
                    <th>Kasir</th>
                    <th>Status</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                @forelse($transactionPreview as $item)
                    <tr>
                        <td><a class="trx-link" href="{{ route('transaksi.show', $item) }}">#{{ $item->id_pesanan }}</a></td>
                        <td>{{ $item->waktu_pembayaran }}</td>
                        <td>{{ $item->pelanggan?->nama ?? ($item->kasir_label ? 'Admin' : 'Umum') }}</td>
                        <td>{{ $item->kasir_label ?: ($item->karyawan?->nama_karyawan ?? '-') }}</td>
                        <td>
                            <span class="status-pill {{ $item->status_pembayaran === 'dibatalkan' ? 'cancel' : 'ok' }}">
                                {{ strtoupper((string) $item->status_pembayaran) }}
                            </span>
                        </td>
                        <td>Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Belum ada data transaksi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
