<?php $__env->startSection('title', 'Keuangan'); ?>

<?php $__env->startSection('scripts'); ?>
<script>
    (function () {
        const openButtons = document.querySelectorAll('[data-open-modal]');
        const closeButtons = document.querySelectorAll('[data-close-modal]');
        const openModal = (id) => {
            const modal = document.getElementById(id);
            if (modal) modal.removeAttribute('hidden');
        };
        const closeModal = (id) => {
            const modal = document.getElementById(id);
            if (modal) modal.setAttribute('hidden', 'hidden');
        };
        openButtons.forEach((btn) => {
            btn.addEventListener('click', () => openModal(btn.getAttribute('data-open-modal')));
        });
        closeButtons.forEach((btn) => {
            btn.addEventListener('click', () => closeModal(btn.getAttribute('data-close-modal')));
        });
    })();
</script>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Keuangan</div>
            <h1>Keuangan</h1>
            <p>Kelola setoran kas, saldo belum disetor, dan jadwal setor operasional.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip <?php echo e($isSetoranOverdue ? '' : 'soft'); ?>"><?php echo e($isSetoranOverdue ? 'Setoran jatuh tempo' : 'Setoran aman'); ?></span>
            <span class="admin-chip">Interval <?php echo e((int) $setoranIntervalDays); ?> hari</span>
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

    <div class="admin-kpi-grid">
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Status Jadwal Setoran</div>
            <div class="admin-kpi-value">
                <span class="pill <?php echo e($isSetoranOverdue ? 'warn' : 'ok'); ?>">
                    <?php echo e($isSetoranOverdue ? 'Jatuh Tempo' : 'Aman'); ?>

                </span>
            </div>
            <div class="admin-kpi-meta">Interval: <?php echo e((int) $setoranIntervalDays); ?> hari</div>
        </div>
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Saldo Belum Disetor</div>
            <div class="admin-kpi-value">Rp <?php echo e(number_format((float) $saldoBelumDisetor, 0, ',', '.')); ?></div>
            <div class="admin-kpi-meta">Cash all-time - pengeluaran - total setor</div>
        </div>
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Setoran Bersih Hari Ini</div>
            <div class="admin-kpi-value">Rp <?php echo e(number_format((float) $setoranHariIni, 0, ',', '.')); ?></div>
            <div class="admin-kpi-meta">Cash hari ini Rp <?php echo e(number_format((float) $totalCashHariIni, 0, ',', '.')); ?> | Pengeluaran Rp <?php echo e(number_format((float) $totalPengeluaranHariIni, 0, ',', '.')); ?></div>
        </div>
        <div class="admin-kpi-card">
            <div class="admin-kpi-label">Setor Terakhir</div>
            <div class="admin-kpi-value value-sm"><?php echo e($lastSetoranAt ? $lastSetoranAt->format('Y-m-d H:i') : 'Belum ada'); ?></div>
            <div class="admin-kpi-meta">
                Jatuh tempo berikutnya:
                <?php echo e($nextSetoranDueAt ? $nextSetoranDueAt->format('Y-m-d H:i') : 'Segera setor pertama'); ?>

            </div>
            <?php if($setoranDueDays !== null): ?>
                <div class="admin-kpi-meta u-mt-4">
                    <?php if($setoranDueDays >= 0): ?>
                        Sisa <?php echo e($setoranDueDays); ?> hari
                    <?php else: ?>
                        Terlambat <?php echo e(abs((int) $setoranDueDays)); ?> hari
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <h3 class="u-m-0 u-mb-8">Tandai Setoran</h3>
        <form class="setor-form" method="post" action="<?php echo e(route('dashboard.setoran.store')); ?>">
            <?php echo csrf_field(); ?>
            <div class="row">
                <input type="number" name="nominal" min="0" step="1" placeholder="Nominal setor (opsional)">
                <input type="text" name="catatan" maxlength="255" placeholder="Catatan setor (opsional)">
                <button class="btn-primary" type="submit">Tandai Setor</button>
            </div>
        </form>
    </div>

    <div class="panel">
        <h3 class="u-m-0 u-mb-8">Histori Setoran</h3>
        <form method="get" action="<?php echo e(route('dashboard.keuangan')); ?>" class="form-inline" id="keuangan-filter-form">
            <input type="hidden" id="keuangan_awal"  name="tanggal_awal"  value="<?php echo e($filters['tanggal_awal'] ?? ''); ?>">
            <input type="hidden" id="keuangan_akhir" name="tanggal_akhir" value="<?php echo e($filters['tanggal_akhir'] ?? ''); ?>">
            <button
                type="button"
                class="btn-daterange-trigger <?php echo e((!empty($filters['tanggal_awal']) || !empty($filters['tanggal_akhir'])) ? 'has-value' : ''); ?>"
                data-daterange-trigger
                data-start="#keuangan_awal"
                data-end="#keuangan_akhir"
            >
                <span class="dp-trigger-icon">&#128197;</span>
                <?php if(!empty($filters['tanggal_awal']) && !empty($filters['tanggal_akhir'])): ?>
                    <span class="dp-trigger-range"><?php echo e(\Carbon\Carbon::parse($filters['tanggal_awal'])->translatedFormat('d M Y')); ?> &ndash; <?php echo e(\Carbon\Carbon::parse($filters['tanggal_akhir'])->translatedFormat('d M Y')); ?></span>
                <?php else: ?>
                    <span class="dp-trigger-label">Pilih Periode</span>
                <?php endif; ?>
            </button>
            <button class="btn-primary" type="submit">Filter</button>
            <a class="btn-primary" href="<?php echo e(route('dashboard.keuangan.export_excel', ['tanggal_awal' => $filters['tanggal_awal'] ?? null, 'tanggal_akhir' => $filters['tanggal_akhir'] ?? null])); ?>">Export Excel</a>
            <a class="btn-neutral" href="<?php echo e(route('dashboard.keuangan')); ?>">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Tanggal Setor</th>
                    <th>Jenis</th>
                    <th>Nominal</th>
                    <th>Catatan</th>
                    <th>User</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $setoranRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $catatanText = (string) ($row->catatan ?? '');
                        $isKoreksi = str_starts_with($catatanText, 'Koreksi setoran #');
                        $nominal = (float) ($row->nominal ?? 0);
                    ?>
                    <tr>
                        <td><?php echo e($row->tanggal_setor); ?></td>
                        <td><span class="tag <?php echo e($isKoreksi ? 'koreksi' : 'normal'); ?>"><?php echo e($isKoreksi ? 'Koreksi' : 'Normal'); ?></span></td>
                        <td class="num <?php echo e($nominal < 0 ? 'minus' : 'plus'); ?>">
                            <?php echo e($nominal < 0 ? '-' : ''); ?>Rp <?php echo e(number_format(abs($nominal), 0, ',', '.')); ?>

                        </td>
                        <td><?php echo e($row->catatan ?: '-'); ?></td>
                        <td><?php echo e($row->user_name ?: '-'); ?></td>
                        <td>
                            <div class="u-grid u-gap-6 u-minw-220">
                                <form method="post" action="<?php echo e(route('dashboard.setoran.catatan.update', ['setoran' => $row->id])); ?>" class="u-flex u-gap-6 u-align-center">
                                    <?php echo csrf_field(); ?>
                                    <input type="text" name="catatan" maxlength="255" value="<?php echo e($row->catatan); ?>" placeholder="Edit catatan" class="u-input-sm">
                                    <button class="btn-neutral btn-sm" type="submit">Simpan</button>
                                </form>
                                <button class="btn-primary btn-sm" type="button" data-open-modal="koreksi-<?php echo e($row->id); ?>">Koreksi Nominal</button>
                            </div>
                            <div class="modal" id="koreksi-<?php echo e($row->id); ?>" hidden>
                                <div class="modal-backdrop" data-close-modal="koreksi-<?php echo e($row->id); ?>"></div>
                                <div class="modal-card">
                                    <div class="modal-title">Koreksi Nominal Setoran</div>
                                    <div class="modal-sub">Nominal sebelumnya: <strong>Rp <?php echo e(number_format((float) ($row->nominal ?? 0), 0, ',', '.')); ?></strong></div>
                                    <form method="post" action="<?php echo e(route('dashboard.setoran.nominal.correct', ['setoran' => $row->id])); ?>" class="u-grid u-gap-8" onsubmit="return confirm('Buat koreksi nominal untuk data setoran ini?');">
                                        <?php echo csrf_field(); ?>
                                        <label class="u-text-sm u-font-700">Nominal Baru
                                            <input type="number" name="nominal_baru" min="0" step="1" value="<?php echo e((int) round((float) ($row->nominal ?? 0))); ?>" class="u-input-md">
                                        </label>
                                        <label class="u-text-sm u-font-700">Alasan Koreksi
                                            <input type="text" name="catatan" maxlength="255" placeholder="Contoh: salah input nominal" class="u-input-md">
                                        </label>
                                        <div class="modal-actions">
                                            <button class="btn-neutral" type="button" data-close-modal="koreksi-<?php echo e($row->id); ?>">Batal</button>
                                            <button class="btn-primary" type="submit">Simpan Koreksi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="6" class="muted">Belum ada histori setoran.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pages"><?php echo e($setoranRows->appends(['audit_page' => request('audit_page')])->links()); ?></div>
    </div>

    <div class="panel">
        <h3 class="u-m-0 u-mb-8">Log Audit Setoran</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Aksi</th>
                    <th>Setoran ID</th>
                    <th>Perubahan Nominal</th>
                    <th>Perubahan Catatan</th>
                    <th>User</th>
                </tr>
                </thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $auditRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $aksi = (string) ($row->aksi ?? '-');
                        $aksiLabel = match ($aksi) {
                            'buat_setoran' => 'Buat Setoran',
                            'ubah_catatan' => 'Ubah Catatan',
                            'koreksi_nominal' => 'Koreksi Nominal',
                            default => strtoupper($aksi),
                        };
                        $nominalLama = $row->nominal_lama !== null ? (float) $row->nominal_lama : null;
                        $nominalBaru = $row->nominal_baru !== null ? (float) $row->nominal_baru : null;
                        $catatanLama = $row->catatan_lama !== null ? (string) $row->catatan_lama : '';
                        $catatanBaru = $row->catatan_baru !== null ? (string) $row->catatan_baru : '';
                    ?>
                    <tr>
                        <td><?php echo e($row->dibuat_pada); ?></td>
                        <td><?php echo e($aksiLabel); ?></td>
                        <td>#<?php echo e((int) ($row->setoran_id ?? 0)); ?></td>
                        <td class="num">
                            <?php if($nominalLama !== null || $nominalBaru !== null): ?>
                                <?php echo e($nominalLama !== null ? 'Rp ' . number_format($nominalLama, 0, ',', '.') : '-'); ?>

                                ->
                                <?php echo e($nominalBaru !== null ? 'Rp ' . number_format($nominalBaru, 0, ',', '.') : '-'); ?>

                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo e($catatanLama !== '' ? $catatanLama : '-'); ?>

                            ->
                            <?php echo e($catatanBaru !== '' ? $catatanBaru : '-'); ?>

                        </td>
                        <td><?php echo e($row->user_name ?: '-'); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="6" class="muted">Belum ada log audit setoran.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pages"><?php echo e($auditRows->appends(['setoran_page' => request('setoran_page')])->links()); ?></div>
    </div>
</div>
<?php $__env->stopSection(); ?>



<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\psrnl\laravel\kasir\resources\views/dashboard/keuangan.blade.php ENDPATH**/ ?>