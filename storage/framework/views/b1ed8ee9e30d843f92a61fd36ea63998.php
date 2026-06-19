<?php $__env->startSection('title', 'Riwayat Transaksi'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Operasional</div>
            <h1>Riwayat Transaksi</h1>
            <p>Pantau semua transaksi penjualan dengan filter berdasarkan periode dan kasir.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip soft"><?php echo e(number_format($pesanan->total(), 0, ',', '.')); ?> Transaksi</span>
            <span class="admin-chip">Hal <?php echo e($pesanan->currentPage()); ?>/<?php echo e($pesanan->lastPage()); ?></span>
        </div>
    </div>

    
    <?php
        $activeOperasional = (string) ($filters['operasional'] ?? '');
        $activeChannel = (string) ($filters['channel'] ?? '');
    ?>

    <div class="admin-section-card" style="margin-bottom: 1.5rem;">
        <div class="admin-card-header">
            <div>
                <h3 class="admin-card-title">
                    <?php echo e($activeChannel === 'app' ? '📱 Transaksi Aplikasi' : '🛒 Transaksi Kasir'); ?>

                </h3>
                <p class="admin-card-description">Filter cepat berdasarkan periode operasional</p>
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem;">
            <a class="btn <?php echo e($activeOperasional === 'today' ? 'btn-default' : 'btn-outline'); ?>"
               href="<?php echo e(route('transaksi.index', $operasionalQuickFilters['today']['query'])); ?>">
                <?php echo e($operasionalQuickFilters['today']['label']); ?>

            </a>
            <a class="btn <?php echo e($activeOperasional === 'yesterday' ? 'btn-default' : 'btn-outline'); ?>"
               href="<?php echo e(route('transaksi.index', $operasionalQuickFilters['yesterday']['query'])); ?>">
                <?php echo e($operasionalQuickFilters['yesterday']['label']); ?>

            </a>
            <?php if($activeChannel === 'app'): ?>
                <a class="btn btn-outline"
                   href="<?php echo e(route('transaksi.index', array_filter(array_merge($filters, ['channel' => null]), fn ($value) => $value !== null && $value !== ''))); ?>">
                    Ke Transaksi Kasir
                </a>
            <?php else: ?>
                <a class="btn btn-outline"
                   href="<?php echo e(route('transaksi.index', array_merge($filters, ['channel' => 'app']))); ?>">
                    Ke Transaksi Aplikasi
                </a>
            <?php endif; ?>
        </div>

        <div class="admin-section-card-note">
            <p>💡 Periode mengikuti jam operasional (reset harian) dari pengaturan sistem.</p>
        </div>
    </div>

    
    <div class="admin-section-card">
        
        <div class="admin-card-header admin-card-header-bordered">
            <div>
                <h3 class="admin-card-title">Filter Transaksi</h3>
                <p class="admin-card-description">Cari transaksi berdasarkan periode dan kasir</p>
            </div>
            <a class="btn-sm btn-default"
               href="<?php echo e(route('transaksi.export_excel', ['tanggal_awal' => $filters['tanggal_awal'] ?? null, 'tanggal_akhir' => $filters['tanggal_akhir'] ?? null, 'id_karyawan' => $filters['id_karyawan'] ?? null, 'operasional' => $filters['operasional'] ?? null, 'channel' => $filters['channel'] ?? null])); ?>">
                📥 Export Excel
            </a>
        </div>

        <form method="get" action="<?php echo e(route('transaksi.index')); ?>" class="admin-filter-form">
            <?php if(! empty($filters['operasional'])): ?>
                <input type="hidden" name="operasional" value="<?php echo e($filters['operasional']); ?>">
            <?php endif; ?>
            <?php if(! empty($filters['channel'])): ?>
                <input type="hidden" name="channel" value="<?php echo e($filters['channel']); ?>">
            <?php endif; ?>

            <div class="admin-filter-grid">
                <?php if(auth()->user()->role === 'admin'): ?>
                    <div>
                        <label>Periode Tanggal</label>
                        <input type="hidden" id="trx_awal" name="tanggal_awal" value="<?php echo e($filters['tanggal_awal'] ?? ''); ?>">
                        <input type="hidden" id="trx_akhir" name="tanggal_akhir" value="<?php echo e($filters['tanggal_akhir'] ?? ''); ?>">
                        <button type="button"
                            class="btn btn-outline"
                            style="width: 100%; justify-content: flex-start;"
                            data-daterange-trigger
                            data-start="#trx_awal"
                            data-end="#trx_akhir">
                            <?php if(!empty($filters['tanggal_awal']) && !empty($filters['tanggal_akhir'])): ?>
                                📅 <?php echo e(\Carbon\Carbon::parse($filters['tanggal_awal'])->translatedFormat('d M Y')); ?> - <?php echo e(\Carbon\Carbon::parse($filters['tanggal_akhir'])->translatedFormat('d M Y')); ?>

                            <?php else: ?>
                                📅 Pilih Periode
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endif; ?>

                <div>
                    <label>Kasir</label>
                    <select name="id_karyawan" class="admin-filter-select">
                        <option value="">Semua kasir</option>
                        <option value="admin" <?php if(($filters['id_karyawan'] ?? null) === 'admin'): echo 'selected'; endif; ?>>Admin</option>
                        <?php $__currentLoopData = $karyawan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($item->id_karyawan); ?>" <?php if(($filters['id_karyawan'] ?? null) == $item->id_karyawan): echo 'selected'; endif; ?>>
                                <?php echo e($item->nama_karyawan); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="admin-filter-actions">
                    <button class="btn btn-default" type="submit">Filter</button>
                    <a class="btn btn-secondary" href="<?php echo e(route('transaksi.index')); ?>">Reset</a>
                </div>
            </div>
        </form>

        
        <div class="admin-stats-row">
            <div class="admin-stats-chip">
                <span>Total Data:</span>
                <strong><?php echo e(number_format($pesanan->total(), 0, ',', '.')); ?></strong>
            </div>
            <div class="admin-stats-chip">
                <span>Halaman:</span>
                <strong><?php echo e($pesanan->currentPage()); ?>/<?php echo e($pesanan->lastPage()); ?></strong>
            </div>
            <div class="admin-stats-chip">
                <span>Per Halaman:</span>
                <strong><?php echo e($pesanan->perPage()); ?></strong>
            </div>
        </div>

        
        <?php if(session('success')): ?>
            <div class="alert ok">✓ <?php echo e(session('success')); ?></div>
        <?php endif; ?>
        <?php if($errors->any()): ?>
            <div class="alert err">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div>⚠ <?php echo e($error); ?></div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>

        
        <div class="admin-table-wrapper" style="margin-top: 1rem;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Waktu</th>
                        <th>Pelanggan</th>
                        <th>Kasir</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th class="admin-table-th-right">Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $pesanan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td>
                            <span class="id-chip">#<?php echo e($item->id_pesanan); ?></span>
                        </td>
                        <td class="u-text-sm"><?php echo e($item->waktu_pembayaran); ?></td>
                        <td>
                            <?php
                                $isAdminKasir = trim((string) ($item->kasir_label ?? '')) !== '';
                            ?>
                            <?php echo e($item->pelanggan?->nama ?? ($isAdminKasir ? '👤 Admin' : '👤 Umum')); ?>

                        </td>
                        <td>
                            <div class="kasir-cell">
                                <?php if($isAdminKasir): ?>
                                    <span class="admin-tag">Admin</span>
                                <?php else: ?>
                                    <span style="font-weight: 500;"><?php echo e($item->karyawan?->nama_karyawan ?? '-'); ?></span>
                                <?php endif; ?>
                                <?php if($item->shift): ?>
                                    <span class="shift-meta">Shift <?php echo e((int) $item->shift->shift_ke); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php
                                $metode = strtolower((string) $item->metode_pembayaran);
                                $deliveryMethods = ['shopeefood', 'gofood', 'grabfood'];
                                $badgeClass = '';
                                if (in_array($metode, ['cash','qris','debit'], true)) {
                                    $badgeClass = $metode;
                                } elseif (in_array($metode, $deliveryMethods, true)) {
                                    $badgeClass = 'delivery';
                                }
                            ?>
                            <span class="admin-card-badge trx-badge-uppercase <?php echo e($badgeClass); ?>">
                                <?php echo e($item->metode_pembayaran); ?>

                            </span>
                        </td>
                        <td>
                            <?php
                                $status = strtolower((string) $item->status_pembayaran);
                            ?>
                            <span class="admin-card-badge <?php echo e($status === 'lunas' ? 'ok' : ($status === 'dibatalkan' ? 'err' : '')); ?>">
                                <?php echo e($item->status_pembayaran); ?>

                            </span>
                        </td>
                        <td class="num money">
                            Rp <?php echo e(number_format((float) $item->total_harga, 0, ',', '.')); ?>

                        </td>
                        <td>
                            <div class="admin-table-actions">
                                <a class="btn btn-outline" href="<?php echo e(route('transaksi.show', $item)); ?>">Detail</a>
                                <?php if(auth()->user()->role === 'admin'): ?>
                                    <?php if($item->status_pembayaran !== 'dibatalkan'): ?>
                                        <form class="inline" method="post" action="<?php echo e(route('transaksi.batal', $item)); ?>"
                                              onsubmit="return confirm('Batalkan transaksi ini? Stok akan dikembalikan.')">
                                            <?php echo csrf_field(); ?>
                                            <button class="btn btn-destructive" type="submit">Batal</button>
                                        </form>
                                    <?php else: ?>
                                        <form class="inline" method="post" action="<?php echo e(route('transaksi.restore', $item)); ?>"
                                              onsubmit="return confirm('Restore transaksi ini? Stok akan dipotong kembali.')">
                                            <?php echo csrf_field(); ?>
                                            <button class="btn btn-secondary" type="submit">Restore</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="8" class="admin-table-empty">
                            <div class="admin-table-empty-inner">
                                <div class="admin-table-empty-icon">📋</div>
                                <p class="admin-table-empty-message">Belum ada transaksi.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        
        <div class="admin-pagination-wrap">
            <?php echo e($pesanan->links()); ?>


            <?php if($pesanan->lastPage() > 1): ?>
                <form method="get" action="<?php echo e(route('transaksi.index')); ?>" class="xpage-jump-form">
                    <?php if(! empty($filters['operasional'])): ?>
                        <input type="hidden" name="operasional" value="<?php echo e($filters['operasional']); ?>">
                    <?php endif; ?>
                    <?php if(! empty($filters['tanggal_awal'])): ?>
                        <input type="hidden" name="tanggal_awal" value="<?php echo e($filters['tanggal_awal']); ?>">
                    <?php endif; ?>
                    <?php if(! empty($filters['tanggal_akhir'])): ?>
                        <input type="hidden" name="tanggal_akhir" value="<?php echo e($filters['tanggal_akhir']); ?>">
                    <?php endif; ?>
                    <?php if(! empty($filters['id_karyawan'])): ?>
                        <input type="hidden" name="id_karyawan" value="<?php echo e($filters['id_karyawan']); ?>">
                    <?php endif; ?>

                    <label>Lompat ke halaman:</label>
                    <input type="number"
                           name="page"
                           min="1"
                           max="<?php echo e($pesanan->lastPage()); ?>"
                           value="<?php echo e($pesanan->currentPage()); ?>"
                           class="u-w-120">
                    <button class="btn btn-default" type="submit">Buka</button>
                    <span class="u-text-sm u-text-muted">(Maks <?php echo e($pesanan->lastPage()); ?>)</span>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\psrnl\laravel\kasir\resources\views/transaksi/index.blade.php ENDPATH**/ ?>