<?php $__env->startSection('title', 'Pengumuman'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Operasional</div>
            <h1>Pengumuman</h1>
            <p>Kelola informasi staf, promo internal, dan update operasional per jabatan.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip soft">Aktif: <?php echo e($items->where('is_active', true)->count()); ?></span>
            <span class="admin-chip">Nonaktif: <?php echo e($items->where('is_active', false)->count()); ?></span>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Tabel Pengumuman</h3>
                <div class="panel-sub">Status promo otomatis mengikuti periode. Non‑promo mengikuti aktif/nonaktif.</div>
            </div>
            <div class="panel-actions">
                <button class="btn-primary" type="button" id="openAnnouncementModal">+ Pengumuman</button>
            </div>
        </div>
        <div class="note">
            Filter di bawah mengikuti status promo: Terjadwal, Aktif, atau Berakhir.
            Promo yang berakhir tetap muncul di dashboard selama 3 hari.
        </div>
        <div class="tabs">
            <a class="tab-on <?php echo e(in_array(($status ?? 'all'), ['aktif', 'berjalan'], true) ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.announcements.index', ['status' => 'aktif'])); ?>">Aktif</a>
            <a class="tab-info <?php echo e(($status ?? '') === 'terjadwal' ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.announcements.index', ['status' => 'terjadwal'])); ?>">Terjadwal</a>
            <a class="tab-off <?php echo e(($status ?? '') === 'berakhir' ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.announcements.index', ['status' => 'berakhir'])); ?>">Berakhir</a>
            <a class="<?php echo e(($status ?? '') === 'all' ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.announcements.index', ['status' => 'all'])); ?>">Semua</a>
        </div>

        <?php if(session('success')): ?>
            <div class="alert ok u-mt-10"><?php echo e(session('success')); ?></div>
        <?php endif; ?>
        <?php if($errors->any()): ?>
            <div class="alert err u-mt-10"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($error); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
        <?php endif; ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th class="u-w-140">Target</th>
                        <th class="u-w-140">Status</th>
                        <th class="u-w-120">Dibaca</th>
                        <th class="u-w-200"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td>
                                <strong><?php echo e($item->title); ?></strong>
                                <div class="sub"><?php echo e($item->published_at ? $item->published_at->format('Y-m-d H:i') : '-'); ?></div>
                            </td>
                            <td><?php echo e($item->target_role ?? 'Semua'); ?></td>
                            <td>
                                <?php
                                    $promoStatus = $item->promo_status ?? null;
                                    $promoClass = match ($promoStatus) {
                                        'Aktif', 'Berjalan' => 'active',
                                        'Terjadwal' => 'warn',
                                        'Berakhir' => 'inactive',
                                        default => null,
                                    };
                                ?>
                                <?php if($promoStatus): ?>
                                    <span class="status <?php echo e($promoClass); ?>"><?php echo e(strtoupper($promoStatus)); ?></span>
                                <?php else: ?>
                                    <span class="status <?php echo e($item->is_active ? 'active' : 'inactive'); ?>">
                                        <?php echo e($item->is_active ? 'AKTIF' : 'NONAKTIF'); ?>

                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e((int) ($readCounts[$item->id] ?? 0)); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a class="btn-neutral" href="<?php echo e(route('dashboard.announcements.show', $item)); ?>" data-announcement-detail="<?php echo e($item->id); ?>">Detail</a>
                                    <a class="btn-neutral" href="<?php echo e(route('dashboard.announcements.edit', $item)); ?>">Edit</a>
                                    <form method="post" action="<?php echo e(route('dashboard.announcements.destroy', $item)); ?>" class="u-inline">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('delete'); ?>
                                        <button class="btn-neutral danger" type="submit" onclick="return confirm('Hapus pengumuman ini?')">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="5" class="sub">Belum ada pengumuman.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="announcementModal" aria-hidden="true" data-open="<?php echo e(old('title') ? '1' : '0'); ?>">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Buat Pengumuman">
        <div class="modal-head">
            <h3 class="modal-title">Buat Pengumuman</h3>
        </div>
        <div class="modal-body">
            <form method="post" action="<?php echo e(route('dashboard.announcements.store')); ?>" enctype="multipart/form-data" class="form-grid">
                <?php echo csrf_field(); ?>
                <div class="field">
                    <label>Judul</label>
                    <input type="text" name="title" value="<?php echo e(old('title')); ?>" required>
                </div>
                <div class="field">
                    <label>Isi Pengumuman</label>
                    <textarea name="body" required><?php echo e(old('body')); ?></textarea>
                </div>
                <div class="field">
                    <label>Target Jabatan</label>
                    <select name="target_role">
                        <option value="">Semua</option>
                        <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($role); ?>" <?php if(old('target_role') === $role): echo 'selected'; endif; ?>><?php echo e($role); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="field">
                    <label>Gambar (opsional)</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div class="field">
                    <label>Jadwal Tayang</label>
                    <input type="datetime-local" name="published_at" value="<?php echo e(old('published_at')); ?>">
                </div>
                <div class="field">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?php if(old('is_active', true)): echo 'checked'; endif; ?>>
                        Aktif
                    </label>
                </div>
                <div class="actions-inline">
                    <button class="btn-primary" type="submit">Simpan</button>
                    <button class="btn-neutral" type="button" id="cancelAnnouncementModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop detail-modal" id="announcementDetailModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Detail Pengumuman">
        <div class="modal-head">
            <h3 class="modal-title">Detail Pengumuman</h3>
        </div>
        <div class="modal-body">
            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $detailReaders = $readersByAnnouncement[$item->id] ?? [];
                ?>
                <div class="detail-panel" data-announcement-panel="<?php echo e($item->id); ?>" hidden>
                    <div class="detail-header">
                        <div>
                            <h2 class="detail-title"><?php echo e($item->title); ?></h2>
                            <div class="detail-meta">
                                <span class="detail-pill">Target: <?php echo e($item->target_role ?? 'Semua'); ?></span>
                                <span class="detail-pill">Tayang: <?php echo e($item->published_at ? $item->published_at->format('Y-m-d H:i') : '-'); ?></span>
                                <span class="detail-pill ok"><?php echo e($item->is_active ? 'AKTIF' : 'NONAKTIF'); ?></span>
                            </div>
                        </div>
                        <span class="detail-pill">Dibaca: <?php echo e((int) ($readCounts[$item->id] ?? 0)); ?></span>
                    </div>
                    <div class="detail-body u-pre-line"><?php echo e($item->body); ?></div>
                    <?php if($item->image_path): ?>
                        <div class="detail-poster">
                            <img src="<?php echo e(asset('storage/' . $item->image_path)); ?>" alt="Poster">
                        </div>
                    <?php endif; ?>
                    <div class="detail-section">
                        <h3>Read Receipt</h3>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th class="u-w-160">Jabatan</th>
                                        <th class="u-w-180">Dibaca</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $__empty_1 = true; $__currentLoopData = $detailReaders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                        <tr>
                                            <td><?php echo e($r->nama_karyawan ?? '-'); ?></td>
                                            <td><?php echo e($r->jabatan ?? '-'); ?></td>
                                            <td><?php echo e($r->read_at ? \Illuminate\Support\Carbon::parse($r->read_at)->format('Y-m-d H:i') : '-'); ?></td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                        <tr><td colspan="3" class="sub">Belum ada yang membaca.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <div class="detail-footer">
                <button class="btn-neutral" type="button" id="closeAnnouncementDetail">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
(() => {
    const openBtn = document.getElementById('openAnnouncementModal');
    const modal = document.getElementById('announcementModal');
    const cancelBtn = document.getElementById('cancelAnnouncementModal');
    if (!openBtn || !modal) return;
    const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); };
    const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); };
    openBtn.addEventListener('click', open);
    cancelBtn?.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    if (modal.dataset.open === '1') open();
})();
</script>
<script>
(() => {
    const detailModal = document.getElementById('announcementDetailModal');
    const closeBtn = document.getElementById('closeAnnouncementDetail');
    const openers = document.querySelectorAll('[data-announcement-detail]');
    if (!detailModal || openers.length === 0) return;
    const panels = detailModal.querySelectorAll('[data-announcement-panel]');
    const openDetail = (id) => {
        panels.forEach(panel => {
            panel.hidden = panel.dataset.announcementPanel !== id;
        });
        detailModal.classList.add('show');
        detailModal.setAttribute('aria-hidden', 'false');
    };
    const closeDetail = () => {
        detailModal.classList.remove('show');
        detailModal.setAttribute('aria-hidden', 'true');
    };
    openers.forEach(btn => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const id = btn.dataset.announcementDetail;
            if (id) openDetail(id);
        });
    });
    closeBtn?.addEventListener('click', closeDetail);
    detailModal.addEventListener('click', (event) => {
        if (event.target === detailModal) closeDetail();
    });
})();
</script>
<?php $__env->stopSection(); ?>




<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\psrnl\laravel\kasir\resources\views/dashboard/announcements/index.blade.php ENDPATH**/ ?>