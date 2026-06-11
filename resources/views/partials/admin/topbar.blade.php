<!-- resources/views/partials/admin/topbar.blade.php -->
<header class="admin-topbar">
    <button type="button" class="admin-topbar-menu" data-admin-sidebar-toggle aria-label="Buka menu admin">
        <span></span>
        <span></span>
        <span></span>
    </button>
    <div class="admin-topbar-context">
        <strong>KopiSop Ops</strong>
        <span>Live View</span>
    </div>
    <div class="admin-topbar-search">
        <input type="search" placeholder="Cari karyawan, transaksi, atau jadwal..." aria-label="Cari data admin">
    </div>
    <div class="admin-topbar-actions">
        <div class="admin-topbar-date">{{ $today ?? now()->format('d M Y') }}</div>
        {{-- Dark mode toggle --}}
        <button type="button"
            id="theme-toggle"
            class="admin-topbar-btn admin-topbar-theme-btn"
            aria-label="Toggle dark mode"
            title="Toggle dark mode">
            <span class="theme-icon-light" aria-hidden="true">☀️</span>
            <span class="theme-icon-dark" aria-hidden="true">🌙</span>
        </button>
        <a class="admin-topbar-btn" href="{{ route('dashboard.chat.index') }}">Inbox</a>
        <a class="admin-topbar-btn primary" href="{{ route('dashboard.index') }}">Dashboard</a>
    </div>
</header>
