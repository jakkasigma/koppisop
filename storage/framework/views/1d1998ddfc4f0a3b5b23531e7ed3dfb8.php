

<?php $__env->startSection('title', 'Statistik Detail Cafe'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Analitik</div>
            <h1>Statistik Detail Cafe</h1>
            <p>Analisis performa penjualan, metode bayar, produk, promo, dan jam ramai.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip">Reset <?php echo e(str_pad((string) $resetHour, 2, '0', STR_PAD_LEFT)); ?>:00</span>
            <span class="admin-chip soft"><?php echo e($tanggalAwal); ?> s/d <?php echo e($tanggalAkhir); ?></span>
        </div>
    </div>

    <div class="filters">
        <form method="get" action="<?php echo e(route('dashboard.statistik')); ?>" id="statistik-filter-form">
            <input type="hidden" name="sales_mode" value="<?php echo e($salesMode ?? 'regular'); ?>">
            <input type="hidden" id="statistik_awal"  name="tanggal_awal"  value="<?php echo e($tanggalAwal); ?>">
            <input type="hidden" id="statistik_akhir" name="tanggal_akhir" value="<?php echo e($tanggalAkhir); ?>">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <button
                    type="button"
                    class="btn-daterange-trigger <?php echo e(($tanggalAwal || $tanggalAkhir) ? 'has-value' : ''); ?>"
                    data-daterange-trigger
                    data-start="#statistik_awal"
                    data-end="#statistik_akhir"
                >
                    <span class="dp-trigger-icon">&#128197;</span>
                    <?php if($tanggalAwal && $tanggalAkhir): ?>
                        <span class="dp-trigger-range"><?php echo e(\Carbon\Carbon::parse($tanggalAwal)->translatedFormat('d M Y')); ?> &ndash; <?php echo e(\Carbon\Carbon::parse($tanggalAkhir)->translatedFormat('d M Y')); ?></span>
                    <?php else: ?>
                        <span class="dp-trigger-label">Pilih Periode</span>
                    <?php endif; ?>
                </button>
                <button class="btn-primary" type="submit">Terapkan</button>
                <a class="btn-neutral" href="<?php echo e(route('dashboard.statistik', ['sales_mode' => ($salesMode ?? 'regular')])); ?>">Reset</a>
                <a class="btn-primary" href="<?php echo e(route('dashboard.statistik.export_excel', ['tanggal_awal' => $tanggalAwal, 'tanggal_akhir' => $tanggalAkhir, 'sales_mode' => ($salesMode ?? 'regular')])); ?>">Export</a>
                <a class="btn-neutral" href="<?php echo e(route('dashboard.index', array_merge(request()->query(), ['sales_mode' => $salesMode ?? 'regular']))); ?>">Kembali Dashboard</a>
            </div>
        </form>
    </div>

    <div class="modebar">
        <button type="button" class="mode-btn" data-toggle="sales-mode">Mode Penjualan: <strong><?php echo e($salesModeLabel ?? 'Penjualan Biasa'); ?></strong></button>
        <div class="mode-panel" data-panel="sales-mode" hidden>
            <a class="<?php echo e(($salesMode ?? 'regular') === 'regular' ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.statistik', array_merge(request()->query(), ['sales_mode' => 'regular']))); ?>">Penjualan Biasa</a>
            <a class="<?php echo e(($salesMode ?? '') === 'app' ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.statistik', array_merge(request()->query(), ['sales_mode' => 'app']))); ?>">Penjualan Aplikasi</a>
            <a class="<?php echo e(($salesMode ?? '') === 'all' ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.statistik', array_merge(request()->query(), ['sales_mode' => 'all']))); ?>">Semua Penjualan</a>
        </div>
    </div>

    <div class="admin-kpi-grid">
        <div class="admin-kpi-card"><div class="label">Periode</div><div class="value"><?php echo e($rangeDays); ?> hari</div></div>
        <div class="admin-kpi-card"><div class="label">Omzet Bruto</div><div class="value">Rp <?php echo e(number_format((float) $omzetBruto, 0, ',', '.')); ?></div></div>
        <div class="admin-kpi-card"><div class="label">Total Diskon</div><div class="value">Rp <?php echo e(number_format((float) $totalDiskon, 0, ',', '.')); ?></div></div>
        <div class="admin-kpi-card"><div class="label">Total Pajak</div><div class="value">Rp <?php echo e(number_format((float) $totalPajak, 0, ',', '.')); ?></div></div>
        <div class="admin-kpi-card"><div class="label">Omzet Netto</div><div class="value">Rp <?php echo e(number_format((float) $omzetTotal, 0, ',', '.')); ?></div></div>
        <div class="admin-kpi-card"><div class="label">Total Transaksi</div><div class="value"><?php echo e((int) $transaksiTotal); ?></div></div>
        <div class="admin-kpi-card"><div class="label">Average Ticket</div><div class="value">Rp <?php echo e(number_format((float) $avgTicket, 0, ',', '.')); ?></div></div>
        <div class="admin-kpi-card">
            <div class="label">Growth vs Periode Sebelumnya</div>
            <div class="growth-list">
                <div class="growth <?php echo e($omzetBrutoGrowth >= 0 ? 'up' : 'down'); ?>"><span>Bruto</span><span><?php echo e(number_format((float) $omzetBrutoGrowth, 2, ',', '.')); ?>%</span></div>
                <div class="growth <?php echo e($omzetGrowth >= 0 ? 'up' : 'down'); ?>"><span>Omzet</span><span><?php echo e(number_format((float) $omzetGrowth, 2, ',', '.')); ?>%</span></div>
                <div class="growth <?php echo e($diskonGrowth >= 0 ? 'up' : 'down'); ?>"><span>Diskon</span><span><?php echo e(number_format((float) $diskonGrowth, 2, ',', '.')); ?>%</span></div>
                <div class="growth <?php echo e($trxGrowth >= 0 ? 'up' : 'down'); ?>"><span>Trx</span><span><?php echo e(number_format((float) $trxGrowth, 2, ',', '.')); ?>%</span></div>
            </div>
        </div>
    </div>

    
    <div class="stat-chart-wrap">
        <?php
            /* Normalise daily data — backend sends { date, label, omzet, transaksi } */
            $dailyChartData = collect($dailySeries['total'] ?? [])
                ->map(fn ($row) => [
                    'date'      => $row['date']  ?? $row['label'] ?? '',
                    'omzet'     => (float) ($row['omzet']     ?? 0),
                    'transaksi' => (int)   ($row['transaksi'] ?? 0),
                ])->values();

            /* Normalise monthly data — include per-method keys */
            $monthlyChartData = collect($monthlyTrend ?? [])
                ->map(function ($row) use ($methodKeys) {
                    $point = [
                        'date'      => isset($row['month']) ? $row['month'] . '-01' : ($row['date'] ?? ''),
                        'label'     => $row['label'] ?? ($row['date'] ?? ''),
                        'omzet'     => (float) ($row['omzet']     ?? 0),
                        'transaksi' => (int)   ($row['transaksi'] ?? 0),
                    ];
                    foreach ($methodKeys ?? [] as $mKey) {
                        $point[$mKey] = (float) ($row[$mKey] ?? 0);
                    }
                    return $point;
                })->values();

            /*
             * Per-method merged daily data — each row has ALL method keys
             * e.g. { date, cash, qris, debit }
             */
            $mergedMethodData = collect($dailySeries['total'] ?? [])
                ->values()
                ->map(function ($row, $i) use ($methodKeys, $dailySeries) {
                    $point = ['date' => $row['date'] ?? $row['label'] ?? ''];
                    foreach ($methodKeys ?? [] as $mKey) {
                        $mRow = ($dailySeries[$mKey] ?? [])[$i] ?? null;
                        $point[$mKey] = (float) ($mRow['omzet'] ?? 0);
                    }
                    return $point;
                })->values();
        ?>

        <div
            id="react-statistik-chart"
            data-daily="<?php echo e(json_encode($dailyChartData)); ?>"
            data-monthly="<?php echo e(json_encode($monthlyChartData)); ?>"
            data-method-data="<?php echo e(json_encode($mergedMethodData)); ?>"
            data-method-keys="<?php echo e(json_encode(array_values($methodKeys ?? []))); ?>"
            data-method-labels="<?php echo e(json_encode((object) ($methodLabels ?? []))); ?>"
            data-method-colors="<?php echo e(json_encode((object) ($methodColors ?? []))); ?>"
            data-awal="<?php echo e($tanggalAwal); ?>"
            data-akhir="<?php echo e($tanggalAkhir); ?>"
        ></div>
    </div>

    
    <div class="stat-tables">

        
        <div class="admin-soft-card stat-table-card">
            <div class="panel-head">
                <h2>Metode Pembayaran</h2>
                <span class="panel-sub">Nominal, trx, kontribusi</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Metode</th>
                            <th class="num">Total</th>
                            <th class="num">Trx</th>
                            <th class="num">%</th>
                            <th class="num">Avg/Trx</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $methodKeys ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td><?php echo e($methodLabels[$key] ?? strtoupper($key)); ?></td>
                                <td class="num">Rp <?php echo e(number_format((float) ($paymentBreakdown[$key]['total'] ?? 0), 0, ',', '.')); ?></td>
                                <td class="num"><?php echo e((int) ($paymentBreakdown[$key]['trx'] ?? 0)); ?></td>
                                <td class="num"><?php echo e(number_format((float) ($paymentBreakdown[$key]['pct'] ?? 0), 1, ',', '.')); ?>%</td>
                                <td class="num">Rp <?php echo e(number_format((float) ($paymentBreakdown[$key]['avg'] ?? 0), 0, ',', '.')); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="admin-soft-card stat-table-card">
            <div class="panel-head">
                <h2>Kontribusi Kategori</h2>
                <span class="panel-sub">Omzet per kategori</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th class="num">Qty</th>
                            <th class="num">Omzet</th>
                            <th class="num">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $categoryBreakdown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><?php echo e($item['nama_kategori']); ?></td>
                                <td class="num"><?php echo e((int) $item['qty']); ?></td>
                                <td class="num">Rp <?php echo e(number_format((float) $item['omzet'], 0, ',', '.')); ?></td>
                                <td class="num"><?php echo e(number_format((float) $item['pct'], 1, ',', '.')); ?>%</td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="4" class="u-text-muted">Belum ada data kategori.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="admin-soft-card stat-table-card">
            <div class="panel-head">
                <h2>Top Produk</h2>
                <span class="panel-sub">10 produk terlaris</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produk</th>
                            <th class="num">Qty</th>
                            <th class="num">Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $topProducts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td class="u-text-muted" style="width:2rem"><?php echo e($i + 1); ?></td>
                                <td><?php echo e($item->nama_produk); ?></td>
                                <td class="num"><?php echo e((int) $item->qty); ?></td>
                                <td class="num">Rp <?php echo e(number_format((float) $item->omzet, 0, ',', '.')); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="4" class="u-text-muted">Belum ada data produk.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="admin-soft-card stat-table-card">
            <div class="panel-head">
                <h2>Performa Promo</h2>
                <span class="panel-sub">Penggunaan promo / diskon</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Promo</th>
                            <th>Tipe</th>
                            <th class="num">Trx</th>
                            <th class="num">Potongan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $promoPerformance; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><?php echo e($item->promo_nama); ?></td>
                                <td><span class="badge"><?php echo e(strtoupper((string) $item->promo_tipe)); ?></span></td>
                                <td class="num"><?php echo e((int) $item->trx); ?></td>
                                <td class="num">Rp <?php echo e(number_format((float) $item->total_potongan, 0, ',', '.')); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="4" class="u-text-muted">Belum ada promo terpakai.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="admin-soft-card stat-heat-card">
            <div class="panel-head">
                <h2>Jam Ramai</h2>
                <span class="panel-sub">Transaksi &amp; omzet per jam</span>
            </div>
            <div class="heat-grid">
                <?php $__currentLoopData = $hourlyStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $ratio = (float) $row['trx'] / max(1, (float) $hourlyMaxTrx);
                        $intensity = min(28, max(6, (int) round($ratio * 28)));
                    ?>
                    <div class="heat-cell" style="background:color-mix(in srgb, var(--accent) <?php echo e($intensity); ?>%, transparent);">
                        <div class="h"><?php echo e($row['label']); ?></div>
                        <div class="v"><?php echo e((int) $row['trx']); ?> trx</div>
                        <div class="v">Rp <?php echo e(number_format((float) $row['omzet'], 0, ',', '.')); ?></div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<?php echo app('Illuminate\Foundation\Vite')(['resources/js/statistik-chart.jsx']); ?>
<script>
(function () {
    const toggleBtn = document.querySelector('[data-toggle="sales-mode"]');
    const panel = document.querySelector('[data-panel="sales-mode"]');
    if (!toggleBtn || !panel) return;
    const closePanel = () => panel.setAttribute('hidden', 'hidden');
    toggleBtn.addEventListener('click', function (event) {
        event.stopPropagation();
        panel.hasAttribute('hidden') ? panel.removeAttribute('hidden') : closePanel();
    });
    document.addEventListener('click', function (event) {
        if (!panel.hasAttribute('hidden') && !panel.contains(event.target) && event.target !== toggleBtn) {
            closePanel();
        }
    });
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\psrnl\laravel\kasir\resources\views/dashboard/statistik.blade.php ENDPATH**/ ?>