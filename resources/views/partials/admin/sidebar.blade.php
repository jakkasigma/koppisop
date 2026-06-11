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
        <details class="admin-nav-group admin-nav-section" @if($isOperationalNav || ! $isMasterNav && ! $isSettingsNav) open @endif>
            <summary class="admin-nav-title">Operasional</summary>
            <div class="admin-nav-links">
                <a class="admin-nav-item {{ request()->routeIs('dashboard.index') ? 'active' : '' }}" href="{{ route('dashboard.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('dashboard.statistik*') ? 'active' : '' }}" href="{{ route('dashboard.statistik') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">analytics</span>
                    <span>Statistik</span>
                </a>
                @if($keuanganMenuEnabled)
                    <a class="admin-nav-item {{ request()->routeIs('dashboard.keuangan*') ? 'active' : '' }}" href="{{ route('dashboard.keuangan') }}">
                        <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">payments</span>
                        <span>Keuangan</span>
                    </a>
                @endif
                <a class="admin-nav-item {{ request()->routeIs('dashboard.jadwal.*') ? 'active' : '' }}" href="{{ route('dashboard.jadwal.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">schedule</span>
                    <span>Jadwal</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('dashboard.absensi') ? 'active' : '' }}" href="{{ route('dashboard.absensi') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">how_to_reg</span>
                    <span>Absensi</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('dashboard.staff_activity.*') ? 'active' : '' }}" href="{{ route('dashboard.staff_activity.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">monitoring</span>
                    <span>Aktivitas Staf</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('dashboard.announcements.*') ? 'active' : '' }}" href="{{ route('dashboard.announcements.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">campaign</span>
                    <span>Pengumuman</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('dashboard.leave.*') ? 'active' : '' }}" href="{{ route('dashboard.leave.index', ['status' => 'pending']) }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">event_busy</span>
                    <span>Izin/Sakit</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('dashboard.payroll.*') ? 'active' : '' }}" href="{{ route('dashboard.payroll.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">request_quote</span>
                    <span>Gaji</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('dashboard.shift_history') ? 'active' : '' }}" href="{{ route('dashboard.shift_history') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">history</span>
                    <span>Shift</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('admin.kasir.*') ? 'active' : '' }}" href="{{ route('admin.kasir.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">point_of_sale</span>
                    <span>Kasir Admin</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('transaksi.*') ? 'active' : '' }}" href="{{ route('transaksi.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">receipt_long</span>
                    <span>Transaksi</span>
                </a>
            </div>
        </details>
        <!-- Master Data -->
        <details class="admin-nav-group admin-nav-section" @if($isMasterNav) open @endif>
            <summary class="admin-nav-title">Master Data</summary>
            <div class="admin-nav-links">
                <a class="admin-nav-item {{ request()->routeIs('master.index') ? 'active' : '' }}" href="{{ route('master.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">dataset</span>
                    <span>Master Data</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('karyawan.*') ? 'active' : '' }}" href="{{ route('karyawan.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">badge</span>
                    <span>Karyawan</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('produk.*') ? 'active' : '' }}" href="{{ route('produk.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">inventory_2</span>
                    <span>Produk</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('kategori.*') ? 'active' : '' }}" href="{{ route('kategori.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">category</span>
                    <span>Kategori</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('diskon.*') ? 'active' : '' }}" href="{{ route('diskon.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">sell</span>
                    <span>Diskon</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('bundling.*') ? 'active' : '' }}" href="{{ route('bundling.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">redeem</span>
                    <span>Bundling</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('pelanggan.*') ? 'active' : '' }}" href="{{ route('pelanggan.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">groups</span>
                    <span>Pelanggan</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('struk_setting.*') ? 'active' : '' }}" href="{{ route('struk_setting.edit') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">receipt</span>
                    <span>Struk</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('master_opsi_kasir.*') ? 'active' : '' }}" href="{{ route('master_opsi_kasir.index') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">tune</span>
                    <span>Opsi Kasir</span>
                </a>
            </div>
        </details>
        <!-- Pengaturan -->
        <details class="admin-nav-group admin-nav-section" @if($isSettingsNav) open @endif>
            <summary class="admin-nav-title">Pengaturan</summary>
            <div class="admin-nav-links">
                <a class="admin-nav-item {{ request()->routeIs('cafe_profile.edit') ? 'active' : '' }}" href="{{ route('cafe_profile.edit') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">storefront</span>
                    <span>Profil Cafe</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('theme_setting.edit') ? 'active' : '' }}" href="{{ route('theme_setting.edit') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">palette</span>
                    <span>Tema Aplikasi</span>
                </a>
                <a class="admin-nav-item {{ request()->routeIs('dashboard.workspace') ? 'active' : '' }}" href="{{ route('dashboard.workspace') }}">
                    <span class="admin-nav-icon material-symbols-outlined" aria-hidden="true">admin_panel_settings</span>
                    <span>Ruang Kerja</span>
                </a>
            </div>
        </details>
    </nav>
    <div class="admin-sidebar-footer">
        <div class="admin-user">
            <div class="admin-user-avatar">{{ strtoupper(substr($quickName, 0, 1)) }}</div>
            <div>
                <div class="admin-user-name">{{ $quickName }}</div>
                <div class="admin-user-role">{{ ucfirst(auth()->user()->role ?? 'Admin') }}</div>
            </div>
        </div>
        <form method="post" action="{{ route('logout') }}" onsubmit="return confirm('Yakin ingin logout sekarang?')" class="quick-menu-logout-form">
            @csrf
            <button class="admin-logout" type="submit">Logout</button>
        </form>
    </div>
</aside>
