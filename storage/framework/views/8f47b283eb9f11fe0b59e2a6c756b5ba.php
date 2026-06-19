<?php $__env->startSection('title', 'Aktivitas Staf'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Monitoring</div>
            <h1>Aktivitas Staf</h1>
            <p>Pantau login, absensi, pengajuan, pesan, dan perubahan profil staf.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip">Total: <?php echo e((int) ($summary['total'] ?? 0)); ?></span>
            <span class="admin-chip soft">Hari ini: <?php echo e((int) ($summary['today'] ?? 0)); ?></span>
            <span class="admin-chip">Staf: <?php echo e((int) ($summary['staff'] ?? 0)); ?></span>
        </div>
    </div>

    <div class="panel">
        <form method="get" action="<?php echo e(route('dashboard.staff_activity.index')); ?>" class="staff-activity-filter-form" id="staff-activity-filter-form">
            <label>Cari
                <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Nama staf atau ringkasan aksi">
            </label>
            <label>Aksi
                <select name="action">
                    <option value="">Semua aksi</option>
                    <?php $__currentLoopData = $actionOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($option->action_key); ?>" <?php if($selectedAction === $option->action_key): echo 'selected'; endif; ?>><?php echo e($option->action_label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
            <input type="hidden" id="activity_awal"  name="date_from" value="<?php echo e($dateFrom); ?>">
            <input type="hidden" id="activity_akhir" name="date_to"   value="<?php echo e($dateTo); ?>">
            <button
                type="button"
                class="btn-daterange-trigger <?php echo e(($dateFrom || $dateTo) ? 'has-value' : ''); ?>"
                data-daterange-trigger
                data-start="#activity_awal"
                data-end="#activity_akhir"
            >
                <span class="dp-trigger-icon">&#128197;</span>
                <?php if($dateFrom && $dateTo): ?>
                    <span class="dp-trigger-range"><?php echo e(\Carbon\Carbon::parse($dateFrom)->translatedFormat('d M Y')); ?> &ndash; <?php echo e(\Carbon\Carbon::parse($dateTo)->translatedFormat('d M Y')); ?></span>
                <?php else: ?>
                    <span class="dp-trigger-label">Pilih Periode</span>
                <?php endif; ?>
            </button>
            <button class="btn-primary" type="submit">Terapkan</button>
            <a class="btn-neutral" href="<?php echo e(route('dashboard.staff_activity.index')); ?>">Reset</a>
        </form>
    </div>

    <div class="staff-activity-list">
        <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $initial = strtoupper(mb_substr((string) ($row->actor_name ?? 'S'), 0, 1));
                $employmentLabel = \App\Models\Karyawan::employmentTypeLabelFor($row->employment_type);
            ?>
            <article class="panel staff-activity-card">
                <div class="staff-activity-card-head">
                    <div class="staff-activity-avatar"><?php echo e($initial); ?></div>
                    <div class="staff-activity-copy">
                        <strong><?php echo e($row->action_label); ?></strong>
                        <div class="staff-activity-meta">
                            <span><?php echo e($row->actor_name ?: 'Staff'); ?></span>
                            <?php if(($row->actor_role ?? '') !== ''): ?>
                                <span><?php echo e($row->actor_role); ?></span>
                            <?php endif; ?>
                            <span><?php echo e($employmentLabel); ?></span>
                        </div>
                    </div>
                    <time class="staff-activity-time" datetime="<?php echo e($row->created_at?->toIso8601String()); ?>"><?php echo e($row->created_at?->translatedFormat('d M Y • H:i')); ?></time>
                </div>

                <p class="staff-activity-summary"><?php echo e($row->summary); ?></p>

                <?php if(($row->target_label ?? '') !== '' || !empty($row->meta)): ?>
                    <div class="staff-activity-tags">
                        <?php if(($row->target_label ?? '') !== ''): ?>
                            <span class="chip">Target: <?php echo e($row->target_label); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($row->meta['tanggal'])): ?>
                            <span class="chip"><?php echo e($row->meta['tanggal']); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($row->meta['shift'])): ?>
                            <span class="chip"><?php echo e($row->meta['shift']); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($row->meta['jumlah_tanggal'])): ?>
                            <span class="chip"><?php echo e((int) $row->meta['jumlah_tanggal']); ?> tanggal</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="panel staff-activity-empty">
                Belum ada log aktivitas staf yang cocok dengan filter ini.
            </div>
        <?php endif; ?>
    </div>

    <?php if(method_exists($rows, 'links')): ?>
        <div class="panel staff-activity-pagination">
            <?php echo e($rows->links()); ?>

        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\psrnl\laravel\kasir\resources\views/dashboard/staff-activity/index.blade.php ENDPATH**/ ?>