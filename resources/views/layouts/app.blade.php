<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Dark mode: apply saved theme ASAP to prevent flash --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var isDark = saved === 'dark' || (!saved && prefersDark);
                if (isDark) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @php
        $setting = \App\Models\StrukSetting::current();
        $themePrimary = $setting->theme_primary ?: '#003535';
        $themeSecondary = $setting->theme_secondary ?: '#32d1c3';
        $themeBg = $setting->theme_bg ?: '#f8f9ff';
    @endphp
    <meta name="theme-color" content="{{ $themePrimary }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="KopiSop">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/logo.png">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @php
        $isAdminCss = auth()->check() && (string) (auth()->user()->role ?? '') === 'admin';
    @endphp
    @if(request()->routeIs('kasir.*') || request()->routeIs('admin.kasir.*'))
        @vite('resources/css/kasir.css')
    @endif
    @if(request()->routeIs('transaksi.*') && !$isAdminCss)
        @vite('resources/css/transaksi.css')
    @endif
    @if($isAdminCss)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
        @vite(['resources/css/admin.css', 'resources/js/date-picker.js'])
    @endif
    <style>
        :root {
            --bg:{{ $themeBg }};
            --ink:#0f172a;
            --line:#d7e4e2;
            --panel:#ffffff;
            --muted:#64748b;
            --danger:#dc2626;
            --accent:{{ $themePrimary }};
            --accent-2:{{ $themeSecondary }};
        }
        @yield('styles')
    </style>
</head>
@php
    $isAdmin = auth()->check() && (string) (auth()->user()->role ?? '') === 'admin';
    $isMasterContext = request()->routeIs('master.index')
        || request()->routeIs('produk.*')
        || request()->routeIs('kategori.*')
        || request()->routeIs('diskon.*')
        || request()->routeIs('bundling.*')
        || request()->routeIs('struk_setting.*')
        || request()->routeIs('master_opsi_kasir.*')
        || request()->routeIs('pelanggan.*')
        || request()->routeIs('karyawan.*');
    $isOperasionalContext = request()->routeIs('dashboard.*')
        || request()->routeIs('kasir.*')
        || request()->routeIs('admin.kasir.*')
        || request()->routeIs('transaksi.*');
    $isKasirRoute = request()->routeIs('kasir.*') || request()->routeIs('admin.kasir.*');
    $isAdminKasirRoute = request()->routeIs('admin.kasir.*');
    $usesAdminShell = $isAdmin && (! $isKasirRoute || $isAdminKasirRoute);
    $kasirPage = '';
    if ($isKasirRoute) {
        if (request()->routeIs('kasir.index', 'admin.kasir.index')) {
            $kasirPage = 'index';
        } elseif (request()->routeIs('kasir.checkout_page', 'admin.kasir.checkout_page')) {
            $kasirPage = 'checkout';
        } elseif (request()->routeIs('kasir.shift.start')) {
            $kasirPage = 'shift-start';
        } elseif (request()->routeIs('kasir.shift.report')) {
            $kasirPage = 'shift-report';
        } elseif (request()->routeIs('kasir.shift.close')) {
            $kasirPage = 'shift-close';
        } elseif (request()->routeIs('kasir.absen.form')) {
            $kasirPage = 'absen';
        } elseif (request()->routeIs('kasir.receipt', 'kasir.nota', 'kasir.checker', 'kasir.shift.struk')) {
            $kasirPage = 'receipt';
        }
    }
    $bodyClass = $isAdmin ? 'admin-ui' : 'app-ui';
    if ($isKasirRoute) {
        $bodyClass .= ' kasir-ui';
        if ($kasirPage !== '') {
            $bodyClass .= ' kasir-' . $kasirPage;
        }
    }
    // Tambah trx-ui hanya untuk NON-admin (kasir role)
    if (request()->routeIs('transaksi.*') && !$isAdmin) {
        $bodyClass .= ' trx-ui';
    }
    $routeName = request()->route()?->getName();
    $pageClass = '';
    if (is_string($routeName) && $routeName !== '') {
        $pageClass = 'page-' . preg_replace('/[^a-z0-9\\-]+/', '-', str_replace(['.', '_'], '-', strtolower($routeName)));
        $pageClass = trim($pageClass, '-');
    }
    if ($pageClass !== '') {
        $bodyClass .= ' ' . $pageClass;
    }
    if ($isAdmin && $isMasterContext) {
        $bodyClass .= ' nav-master-context';
    }
    if ($isAdmin && $isOperasionalContext) {
        $bodyClass .= ' nav-operasional-context';
    }
@endphp
<body class="{{ $bodyClass }}">
@php
    $quickName = '';
    if (auth()->check()) {
        $quickName = (string) (auth()->user()->name ?? 'Admin');
    }
    $isOperationalNav = request()->routeIs(
        'dashboard.index',
        'dashboard.statistik*',
        'dashboard.keuangan*',
        'dashboard.jadwal.*',
        'dashboard.absensi',
        'dashboard.staff_activity.*',
        'dashboard.announcements.*',
        'dashboard.leave.*',
        'dashboard.payroll.*',
        'dashboard.shift_history',
        'admin.kasir.*',
        'transaksi.*'
    );
    $isMasterNav = request()->routeIs(
        'master.index',
        'karyawan.*',
        'produk.*',
        'kategori.*',
        'diskon.*',
        'bundling.*',
        'pelanggan.*',
        'struk_setting.*',
        'master_opsi_kasir.*'
    );
    $isSettingsNav = request()->routeIs(
        'cafe_profile.edit',
        'theme_setting.edit',
        'dashboard.workspace'
    );
    $showQuickMenu = $quickName !== '' && $isAdmin && request()->routeIs(
        'dashboard.index',
        'dashboard.statistik',
        'dashboard.keuangan',
        'dashboard.jadwal.index',
        'dashboard.absensi',
        'dashboard.leave.index',
        'dashboard.payroll.index',
        'dashboard.payroll.show',
        'dashboard.shift_history',
        'admin.kasir.index',
        'transaksi.index',
        'master.index',
        'produk.index',
        'kategori.index',
        'diskon.index',
        'bundling.index',
        'struk_setting.edit',
        'pelanggan.index',
        'karyawan.index',
        'master_opsi_kasir.index',
        'dashboard.chat.index',
        'dashboard.staff_activity.index',
        'dashboard.announcements.index',
        'cafe_profile.edit',
        'theme_setting.edit',
        'dashboard.workspace'
    );
    if ($isAdmin) {
        $showQuickMenu = false;
    }
    $keuanganMenuEnabled = (bool) ($setting->enable_keuangan_menu ?? true);
@endphp
@if($showQuickMenu)
    <div class="quick-menu" id="quickMenu">
        <button type="button" aria-label="Menu" onclick="toggleQuickMenu()">
            &#9776;
        </button>
        <div class="quick-menu-panel" id="quickMenuPanel">
            <div class="item is-static">
                <div class="quick-menu-name">{{ $quickName }}</div>
            </div>
            <div class="sep"></div>
            @if(auth()->check())
                <a class="item accent" href="{{ route('cafe_profile.edit') }}">Profil Cafe</a>
                <a class="item accent" href="{{ route('theme_setting.edit') }}">Tema Aplikasi</a>
                <a class="item accent" href="{{ route('dashboard.chat.index') }}">Chat Karyawan</a>
                <a class="item" href="{{ route('dashboard.staff_activity.index') }}">Aktivitas Staf</a>
                <a class="item" href="{{ route('dashboard.workspace') }}">Ruang Kerja</a>
                <a class="item" href="{{ route('dashboard.index') }}">Kembali ke Dashboard</a>
                <form method="post" action="{{ route('logout') }}" onsubmit="return confirm('Yakin ingin logout sekarang?')" class="quick-menu-logout-form">
                    @csrf
                    <button class="item quick-menu-logout-btn" type="submit">
                        Logout
                    </button>
                </form>
            @endif
        </div>
    </div>
@endif

@if($usesAdminShell)
    <div class="admin-shell">
        @include('partials.admin.sidebar')
        @include('partials.admin.topbar')
        <div class="admin-main">
            @yield('content')
        </div>
    </div>
@elseif(request()->routeIs('transaksi.*') && !$isAdmin)
    {{-- Kasir role di halaman transaksi: topbar sederhana --}}
    <header class="trx-topbar">
        <span class="trx-topbar-title">Riwayat Transaksi</span>
        <div class="trx-topbar-actions">
            <a href="{{ route('kasir.index') }}" class="trx-topbar-btn">← Kasir</a>
            <form method="post" action="{{ route('logout') }}" onsubmit="return confirm('Logout?')" style="display:inline">
                @csrf
                <button type="submit" class="trx-topbar-btn">Logout</button>
            </form>
        </div>
    </header>
    @yield('content')
@else
    @yield('content')
@endif
<button id="pwa-install-btn" class="btn-primary pwa-install" type="button">Install App</button>

{{-- Dark mode toggle untuk kasir/non-admin role --}}
@if(!($isAdmin ?? false))
<button id="theme-toggle-kasir"
    type="button"
    class="kasir-theme-toggle"
    aria-label="Toggle dark mode"
    aria-pressed="false">
    <span class="theme-icon-light" aria-hidden="true">☀️</span>
    <span class="theme-icon-dark" aria-hidden="true">🌙</span>
</button>
@endif

@yield('scripts')
<script src="/pwa-install.js"></script>
<script>
    function toggleAdminSidebar() {
        var body = document.body;
        if (!body.classList.contains('admin-ui')) return;
        body.classList.toggle('admin-sidebar-open');
    }

    // Close sidebar when clicking the overlay (::before pseudo-element covers the rest)
    document.addEventListener('click', function (e) {
        var body = document.body;
        if (!body.classList.contains('admin-sidebar-open')) return;
        var sidebar = document.querySelector('.admin-sidebar');
        if (!sidebar) return;
        // If click is outside sidebar and not on a toggle button, close it
        if (!sidebar.contains(e.target) && !e.target.closest('[data-admin-sidebar-toggle]')) {
            body.classList.remove('admin-sidebar-open');
        }
    });
    function toggleQuickMenu() {
        var panel = document.getElementById('quickMenuPanel');
        if (!panel) return;
        panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
    }
    document.addEventListener('click', function (e) {
        var panel = document.getElementById('quickMenuPanel');
        var wrapper = document.getElementById('quickMenu');
        if (!panel || !wrapper) return;
        if (!wrapper.contains(e.target)) {
            panel.style.display = 'none';
        }
    });
    document.querySelectorAll('[data-admin-sidebar-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            toggleAdminSidebar();
        });
    });
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function (error) {
                console.error('SW register failed:', error);
            });
        });
    }

    // ── Dark mode toggle ──────────────────────────────────────────────────────
    (function () {
        function getTheme() {
            try { return localStorage.getItem('theme'); } catch (e) { return null; }
        }
        function setTheme(theme) {
            try { localStorage.setItem('theme', theme); } catch (e) {}
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                document.documentElement.classList.add('dark');
                document.body.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
                document.documentElement.classList.remove('dark');
                document.body.removeAttribute('data-theme');
            }
            updateToggleUI();
        }
        function isDark() {
            return document.documentElement.getAttribute('data-theme') === 'dark'
                || document.documentElement.classList.contains('dark');
        }
        function updateToggleUI() {
            // Update all toggle buttons (admin topbar + kasir floating)
            document.querySelectorAll('[id^="theme-toggle"]').forEach(function (btn) {
                btn.setAttribute('aria-pressed', isDark() ? 'true' : 'false');
                btn.setAttribute('title', isDark() ? 'Ganti ke Light Mode' : 'Ganti ke Dark Mode');
            });
        }
        // Handle toggle clicks (delegated, works for any button added later)
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[id^="theme-toggle"]');
            if (!btn) return;
            setTheme(isDark() ? 'light' : 'dark');
        });
        // Sync on load
        updateToggleUI();
        // Listen for system preference changes
        try {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
                if (!getTheme()) setTheme(e.matches ? 'dark' : 'light');
            });
        } catch (e) {}
    })();
</script>
</body>
</html>
