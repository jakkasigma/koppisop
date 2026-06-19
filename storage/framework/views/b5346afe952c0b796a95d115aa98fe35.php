<!-- resources/views/partials/admin/topbar.blade.php -->
<header class="admin-topbar">
    <button type="button" class="admin-topbar-menu" data-admin-sidebar-toggle aria-label="Buka menu admin">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="admin-topbar-context">
    </div>
    <div class="admin-topbar-search">
        <input type="search" placeholder="Cari karyawan, transaksi, atau jadwal..." aria-label="Cari data admin">
    </div>
    <div class="admin-topbar-actions">
        <div class="admin-topbar-date"><?php echo e($today ?? now()->format('d M Y')); ?></div>
        
        <button type="button"
            id="theme-toggle"
            class="admin-topbar-btn admin-topbar-theme-btn"
            aria-label="Toggle dark mode"
            title="Toggle dark mode">
            <span class="theme-icon-light" aria-hidden="true">☀️</span>
            <span class="theme-icon-dark" aria-hidden="true">🌙</span>
        </button>
        <a class="admin-topbar-btn primary" href="<?php echo e(route('dashboard.chat.index')); ?>">Inbox</a>
    </div>
</header>
<?php /**PATH D:\psrnl\laravel\kasir\resources\views/partials/admin/topbar.blade.php ENDPATH**/ ?>