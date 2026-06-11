<!-- resources/views/partials/admin/sidebar.blade.php -->
<aside class="admin-sidebar">
    <div class="admin-brand">
        <span class="admin-brand-mark material-symbols-outlined" aria-hidden="true">coffee</span>
        <div>
            <div class="admin-brand-name">KopiSop Admin</div>
            <div class="admin-brand-sub">Command Center</div>
        </div>
    </div>
    <nav class="admin-nav">
        <!-- Operasional -->
        <details class="admin-nav-group admin-nav-section" <?php if($isOperationalNav || ! $isMasterNav && ! $isSettingsNav): ?> open <?php endif; ?>>
            <summary class="admin-nav-title">Operasional</summary>
            <div class="admin-nav-links">
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.index') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.statistik*') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.statistik')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">analytics</span>
                    <span>Statistik</span>
                </a>
                <?php if($keuanganMenuEnabled): ?>
                    <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.keuangan*') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.keuangan')); ?>">
                        <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">payments</span>
                        <span>Keuangan</span>
                    </a>
                <?php endif; ?>
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.jadwal.*') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.jadwal.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">schedule</span>
                    <span>Jadwal</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.absensi') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.absensi')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">how_to_reg</span>
                    <span>Absensi</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.staff_activity.*') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.staff_activity.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">monitoring</span>
                    <span>Aktivitas Staf</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.announcements.*') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.announcements.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">campaign</span>
                    <span>Pengumuman</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.leave.*') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.leave.index', ['status' => 'pending'])); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">event_busy</span>
                    <span>Izin/Sakit</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.payroll.*') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.payroll.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">request_quote</span>
                    <span>Gaji</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.shift_history') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.shift_history')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">history</span>
                    <span>Shift</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('admin.kasir.*') ? 'active' : ''); ?>" href="<?php echo e(route('admin.kasir.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">point_of_sale</span>
                    <span>Kasir Admin</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('transaksi.*') ? 'active' : ''); ?>" href="<?php echo e(route('transaksi.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">receipt_long</span>
                    <span>Transaksi</span>
                </a>
            </div>
        </details>
        <!-- Master Data -->
        <details class="admin-nav-group admin-nav-section" <?php if($isMasterNav): ?> open <?php endif; ?>>
            <summary class="admin-nav-title">Master Data</summary>
            <div class="admin-nav-links">
                <a class="admin-nav-item <?php echo e(request()->routeIs('master.index') ? 'active' : ''); ?>" href="<?php echo e(route('master.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">dataset</span>
                    <span>Master Data</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('karyawan.*') ? 'active' : ''); ?>" href="<?php echo e(route('karyawan.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">badge</span>
                    <span>Karyawan</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('produk.*') ? 'active' : ''); ?>" href="<?php echo e(route('produk.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">inventory_2</span>
                    <span>Produk</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('kategori.*') ? 'active' : ''); ?>" href="<?php echo e(route('kategori.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">category</span>
                    <span>Kategori</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('diskon.*') ? 'active' : ''); ?>" href="<?php echo e(route('diskon.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">sell</span>
                    <span>Diskon</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('bundling.*') ? 'active' : ''); ?>" href="<?php echo e(route('bundling.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">redeem</span>
                    <span>Bundling</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('pelanggan.*') ? 'active' : ''); ?>" href="<?php echo e(route('pelanggan.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">groups</span>
                    <span>Pelanggan</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('struk_setting.*') ? 'active' : ''); ?>" href="<?php echo e(route('struk_setting.edit')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">receipt</span>
                    <span>Struk</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('master_opsi_kasir.*') ? 'active' : ''); ?>" href="<?php echo e(route('master_opsi_kasir.index')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">tune</span>
                    <span>Opsi Kasir</span>
                </a>
            </div>
        </details>
        <!-- Pengaturan -->
        <details class="admin-nav-group admin-nav-section" <?php if($isSettingsNav): ?> open <?php endif; ?>>
            <summary class="admin-nav-title">Pengaturan</summary>
            <div class="admin-nav-links">
                <a class="admin-nav-item <?php echo e(request()->routeIs('cafe_profile.edit') ? 'active' : ''); ?>" href="<?php echo e(route('cafe_profile.edit')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">storefront</span>
                    <span>Profil Cafe</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('theme_setting.edit') ? 'active' : ''); ?>" href="<?php echo e(route('theme_setting.edit')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">palette</span>
                    <span>Tema Aplikasi</span>
                </a>
                <a class="admin-nav-item <?php echo e(request()->routeIs('dashboard.workspace') ? 'active' : ''); ?>" href="<?php echo e(route('dashboard.workspace')); ?>">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">admin_panel_settings</span>
                    <span>Ruang Kerja</span>
                </a>
            </div>
        </details>
    </nav>
    <div class="admin-sidebar-footer">
        <div class="admin-user">
            <div class="admin-user-avatar"><?php echo e(strtoupper(substr($quickName, 0, 1))); ?></div>
            <div>
                <div class="admin-user-name"><?php echo e($quickName); ?></div>
                <div class="admin-user-role"><?php echo e(ucfirst(auth()->user()->role ?? 'Admin')); ?></div>
            </div>
        </div>
        <form method="post" action="<?php echo e(route('logout')); ?>" onsubmit="return confirm('Yakin ingin logout sekarang?')" class="quick-menu-logout-form">
            <?php echo csrf_field(); ?>
            <button class="admin-logout" type="submit">Logout</button>
        </form>
    </div>
</aside>
<?php /**PATH D:\psrnl\laravel\kasir\resources\views/partials/admin/sidebar.blade.php ENDPATH**/ ?>