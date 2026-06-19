<?php $__env->startSection('title', 'Chat Karyawan'); ?>

<?php $__env->startSection('content'); ?>
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Komunikasi</div>
            <h1>Chat Karyawan</h1>
            <p>Pilih staf, baca pesan masuk, dan balas percakapan internal dari satu layar.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip soft"><?php echo e(number_format($karyawan->count(), 0, ',', '.')); ?> staf</span>
        </div>
    </div>

    <div class="wa-shell">
        <div class="wa-left">
            <div class="wa-left-head">
                <div>
                    <div class="wa-left-title">Inbox Staf</div>
                    <div class="wa-left-sub">Percakapan admin dan karyawan</div>
                </div>
            </div>
            <div class="wa-search">
                <input type="text" placeholder="Cari karyawan..." oninput="filterChatList(this.value)">
            </div>
            <div class="wa-list" id="chatList">
                <?php $__empty_1 = true; $__currentLoopData = $karyawan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $initials = strtoupper(mb_substr((string) $k->nama_karyawan, 0, 1));
                        $key = 'admin_chat:' . (int) $k->id_karyawan;
                        $last = $lastMessages[$key] ?? null;
                        $unread = (int) ($unreadCounts[$key] ?? 0);
                    ?>
                    <a class="wa-item" href="<?php echo e(route('dashboard.chat.show', $k)); ?>" data-name="<?php echo e(strtolower($k->nama_karyawan)); ?>">
                        <div class="wa-avatar"><?php echo e($initials); ?></div>
                        <div class="wa-meta">
                            <div class="wa-name"><?php echo e($k->nama_karyawan); ?></div>
                            <div class="wa-role"><?php echo e($k->jabatan ?? 'Staff'); ?></div>
                            <?php if($last): ?>
                                <div class="wa-preview"><?php echo e(\Illuminate\Support\Str::limit($last->message, 60)); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if($unread > 0): ?>
                            <div class="unread-badge"><?php echo e($unread); ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="u-p-14 u-text-muted">Belum ada karyawan.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="wa-right">
            <div class="wa-right-head">
                <div class="wa-left-title">Pilih karyawan</div>
            </div>
            <div class="wa-empty">Pilih karyawan di sebelah kiri untuk mulai chat.</div>
        </div>
    </div>
</div>
<script>
    function filterChatList(value) {
        var term = (value || '').toLowerCase();
        document.querySelectorAll('#chatList .wa-item').forEach(function (el) {
            var name = el.getAttribute('data-name') || '';
            el.style.display = name.includes(term) ? '' : 'none';
        });
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\psrnl\laravel\kasir\resources\views/dashboard/chat/index.blade.php ENDPATH**/ ?>