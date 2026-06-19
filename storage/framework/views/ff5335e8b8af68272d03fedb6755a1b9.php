

<?php $__env->startSection('title', 'Riwayat Shift Kasir'); ?>
<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="admin-page-head">
    <div>
        <h1>Riwayat Shift Kasir</h1>
        <p>Monitoring pembukaan dan penutupan shift kasir.</p>
        
    </div>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Kasir</th>
                    <th>Shift</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th>Kas Awal</th>
                    <th>Omzet</th>
                    <th>Cash</th>
                    <th>Delivery</th>
                    <th>Pengeluaran</th>
                    <th>Trx</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td>#<?php echo e($row->id); ?></td>
                        <td><?php echo e($row->user?->name ?? '-'); ?></td>
                        <td><?php echo e((int) $row->shift_ke); ?></td>
                        <td><?php echo e($row->started_at); ?></td>
                        <td><?php echo e($row->ended_at ?? '-'); ?></td>
                        <td>Rp <?php echo e(number_format((float) $row->kas_awal, 0, ',', '.')); ?></td>
                        <td>Rp <?php echo e(number_format((float) $row->total_omzet, 0, ',', '.')); ?></td>
                        <td>Rp <?php echo e(number_format((float) $row->total_cash, 0, ',', '.')); ?></td>
                        <td>Rp <?php echo e(number_format((float) ($row->total_delivery ?? 0), 0, ',', '.')); ?></td>
                        <td>Rp <?php echo e(number_format((float) $row->total_pengeluaran, 0, ',', '.')); ?></td>
                        <td><?php echo e(number_format((int) $row->total_trx, 0, ',', '.')); ?></td>
                        <td><a class="btn-neutral" href="<?php echo e(route('kasir.shift.struk', ['shift' => $row->id])); ?>" target="_blank" rel="noopener">Struk</a></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="12" class="muted">Belum ada data shift.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pages"><?php echo e($rows->links()); ?></div>
        <?php if($rows->lastPage() > 1): ?>
            <div class="page-jump-wrap">
                <form class="page-jump-form" method="get" action="<?php echo e(route('dashboard.shift_history')); ?>">
                    <label for="shiftPageJumpInput">Halaman</label>
                    <input
                        id="shiftPageJumpInput"
                        type="number"
                        name="page"
                        min="1"
                        max="<?php echo e($rows->lastPage()); ?>"
                        value="<?php echo e(min(max((int) request('page', $rows->currentPage()), 1), $rows->lastPage())); ?>"
                    >
                    <button class="btn-primary" type="submit">Buka</button>
                    <span class="hint u-m-0">Maks <?php echo e($rows->lastPage()); ?></span>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\psrnl\laravel\kasir\resources\views/dashboard/shift-history.blade.php ENDPATH**/ ?>