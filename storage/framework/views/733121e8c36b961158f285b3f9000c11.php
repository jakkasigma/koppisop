

<?php $__env->startSection('title', 'Detail Transaksi #' . $transaksi->id_pesanan); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Transaksi</div>
            <h1>Detail Transaksi #<?php echo e($transaksi->id_pesanan); ?></h1>
            <p>Lihat rincian pesanan, promo, pajak, dan status pembayaran secara lengkap.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-primary" href="<?php echo e(route('transaksi.nota', ['transaksi' => $transaksi, 'autoprint' => 1])); ?>" target="_blank" rel="noopener">Print Nota</a>
            <a class="btn-neutral" href="<?php echo e(route('transaksi.index')); ?>">Kembali</a>
        </div>
    </div>

    <?php if(session('success')): ?>
        <div class="alert ok"><?php echo e(session('success')); ?></div>
    <?php endif; ?>
    <?php if($errors->any()): ?>
        <div class="alert err">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div><?php echo e($error); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <div class="meta-grid">
            <div class="meta-card">
                <span class="meta-label">Waktu</span>
                <span class="meta-value"><?php echo e($transaksi->waktu_pembayaran); ?></span>
            </div>
            <div class="meta-card">
                <span class="meta-label">Pelanggan</span>
                <span class="meta-value"><?php echo e($transaksi->pelanggan?->nama ?? ($transaksi->kasir_label ? 'Admin' : 'Umum')); ?></span>
            </div>
            <div class="meta-card">
                <span class="meta-label">Kasir</span>
                <span class="meta-value"><?php echo e($transaksi->kasir_label ?: ($transaksi->karyawan?->nama_karyawan ?? '-')); ?></span>
            </div>
            <?php if($transaksi->shift): ?>
                <div class="meta-card">
                    <span class="meta-label">Shift</span>
                    <span class="meta-value">
                        <span class="shift-pill">Shift <?php echo e((int) $transaksi->shift->shift_ke); ?></span>
                        <?php if(!empty($transaksi->no_urut_shift)): ?>
                            <span class="shift-order">No. <?php echo e(str_pad((string) ((int) $transaksi->no_urut_shift), 3, '0', STR_PAD_LEFT)); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
            <div class="meta-card">
                <span class="meta-label">Metode</span>
                <span class="meta-value"><?php echo e(strtoupper((string) $transaksi->metode_pembayaran)); ?></span>
            </div>
            <div class="meta-card">
                <span class="meta-label">Status</span>
                <?php
                    $status = strtolower((string) $transaksi->status_pembayaran);
                ?>
                <span>
                    <span class="status-pill <?php echo e($status === 'dibatalkan' ? 'cancel' : 'ok'); ?>">
                        <?php echo e(strtoupper((string) $transaksi->status_pembayaran)); ?>

                    </span>
                </span>
            </div>
            <?php if(trim((string) ($transaksi->catatan_pesanan ?? '')) !== ''): ?>
                <div class="meta-card">
                    <span class="meta-label">Catatan</span>
                    <span class="meta-value"><?php echo e($transaksi->catatan_pesanan); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if(auth()->user()->role === 'admin'): ?>
            <div class="admin-actions">
                <?php if($transaksi->status_pembayaran !== 'dibatalkan'): ?>
                    <form method="post" action="<?php echo e(route('transaksi.batal', $transaksi)); ?>" onsubmit="return confirm('Batalkan transaksi ini? Stok akan dikembalikan.')">
                        <?php echo csrf_field(); ?>
                        <button class="btn-danger btn-mini" type="submit">Batalkan Transaksi</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?php echo e(route('transaksi.restore', $transaksi)); ?>" onsubmit="return confirm('Restore transaksi ini? Stok akan dipotong kembali.')">
                        <?php echo csrf_field(); ?>
                        <button class="btn-neutral btn-mini" type="submit">Restore Transaksi</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Produk</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th></tr></thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $transaksi->detail; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $opsi = [];
                        if ($item->temperature) {
                            $opsi[] = match ($item->temperature) {
                                'hot' => 'Hot',
                                'less_ice' => 'Less Es',
                                'ice' => 'Es',
                                default => ucwords(str_replace('_', ' ', (string) $item->temperature)),
                            };
                        }
                        if ($item->sugar_level) {
                            $opsi[] = match ($item->sugar_level) {
                                'none' => 'No Sugar',
                                'less' => 'Less Sugar',
                                'normal' => 'Normal Sugar',
                                default => ucwords(str_replace('_', ' ', (string) $item->sugar_level)),
                            };
                        }
                        if ($item->cup_size) {
                            $opsi[] = match ($item->cup_size) {
                                'large' => 'Cup Large',
                                'regular' => 'Cup Regular',
                                default => ucwords(str_replace('_', ' ', (string) $item->cup_size)),
                            };
                        }
                        if ($item->spicy_level) {
                            $opsi[] = match ($item->spicy_level) {
                                'extra_spicy' => 'Extra Spicy',
                                'spicy' => 'Spicy',
                                'non_spicy' => 'Non Spicy',
                                default => ucwords(str_replace('_', ' ', (string) $item->spicy_level)),
                            };
                        }
                        if (is_array($item->selected_options ?? null)) {
                            $catatanItem = null;
                            foreach ($item->selected_options as $selectedKey => $selectedValue) {
                                if (in_array((string) $selectedKey, ['note', '_note'], true)) {
                                    if (is_string($selectedValue)) {
                                        $catatanItem = preg_replace('/\s+/', ' ', trim($selectedValue));
                                    }
                                    continue;
                                }
                                if (! is_string($selectedValue) || trim($selectedValue) === '') {
                                    continue;
                                }
                                $opsi[] = ucwords(str_replace('_', ' ', $selectedValue));
                            }
                            if (! empty($catatanItem)) {
                                $opsi[] = 'Catatan: ' . $catatanItem;
                            }
                        }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo e($item->produk?->nama_produk ?? 'Produk dihapus'); ?></strong>
                            <?php if(!empty($opsi)): ?>
                                <div class="hint"><?php echo e(implode(' | ', $opsi)); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($item->jumlah); ?></td>
                        <td>Rp <?php echo e(number_format((float) $item->harga_satuan, 0, ',', '.')); ?></td>
                        <td>Rp <?php echo e(number_format((float) $item->harga_satuan * (int) $item->jumlah, 0, ',', '.')); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="4">Detail transaksi kosong.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
            $subtotalHarga = $transaksi->subtotal_harga !== null ? (float) $transaksi->subtotal_harga : (float) $transaksi->total_harga;
            $diskonNominal = (float) ($transaksi->diskon_nominal ?? 0);
            $pajakNominal = (float) ($transaksi->pajak_nominal ?? 0);
            $pajakPersen = (float) ($transaksi->pajak_persen ?? 0);
            $totalSebelumPajak = max(0, $subtotalHarga - $diskonNominal);
            $totalJikaPajakKePembeli = $totalSebelumPajak + $pajakNominal;
            $pajakDibebankanKePembeli = abs($totalJikaPajakKePembeli - (float) $transaksi->total_harga) < 0.01;
            $diskonTipe = (string) ($transaksi->diskon_tipe ?? '');
            $diskonTipeLabel = match ($diskonTipe) {
                'persen' => 'Persen',
                'nominal' => 'Nominal',
                'harga_kategori' => 'Harga Spesial',
                'bundling' => 'Bundling',
                default => 'Promo',
            };
            $diskonNilaiLabel = match ($diskonTipe) {
                'persen' => rtrim(rtrim(number_format((float) ($transaksi->diskon_nilai ?? 0), 2, '.', ''), '0'), '.') . '%',
                'bundling', 'nominal', 'harga_kategori' => 'Rp ' . number_format((float) ($transaksi->diskon_nilai ?? 0), 0, ',', '.'),
                default => null,
            };
        ?>
        <div class="total">
            <div class="row"><span>Subtotal</span><strong>Rp <?php echo e(number_format($subtotalHarga, 0, ',', '.')); ?></strong></div>
            <?php if($diskonNominal > 0): ?>
                <div class="row"><span>Promo</span><strong><?php echo e($transaksi->diskon_nama ?: '-'); ?></strong></div>
                <div class="row"><span>Tipe Promo</span><strong><?php echo e($diskonTipeLabel); ?><?php echo e($diskonNilaiLabel ? ' (' . $diskonNilaiLabel . ')' : ''); ?></strong></div>
                <div class="row"><span>Potongan</span><strong>- Rp <?php echo e(number_format($diskonNominal, 0, ',', '.')); ?></strong></div>
            <?php endif; ?>
            <?php if($pajakNominal > 0): ?>
                <div class="row">
                    <span>Pajak<?php echo e($pajakPersen > 0 ? ' (' . rtrim(rtrim(number_format($pajakPersen, 2, '.', ''), '0'), '.') . '%)' : ''); ?></span>
                    <?php if($pajakDibebankanKePembeli): ?>
                        <strong>+ Rp <?php echo e(number_format($pajakNominal, 0, ',', '.')); ?></strong>
                    <?php else: ?>
                        <strong>Rp <?php echo e(number_format($pajakNominal, 0, ',', '.')); ?> (Ditanggung Cafe)</strong>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="row grand"><span>Total</span><strong>Rp <?php echo e(number_format((float) $transaksi->total_harga, 0, ',', '.')); ?></strong></div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\psrnl\laravel\kasir\resources\views/transaksi/show.blade.php ENDPATH**/ ?>