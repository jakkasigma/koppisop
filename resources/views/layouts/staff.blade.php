<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $setting = \App\Models\StrukSetting::current();
        $themePrimary = $setting->theme_primary ?: '#0b6e68';
        $themeSecondary = $setting->theme_secondary ?: '#22c55e';
        $themeBg = $setting->theme_bg ?: '#f3f5f4';
    @endphp
    <meta name="theme-color" content="{{ $themePrimary }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="KopiSop">
    <link rel="manifest" href="/manifest-staff.json">
    <link rel="apple-touch-icon" href="/logo.png">
    <title>@yield('title', config('app.name'))</title>
    @vite('resources/css/staff.css')
    <style>
        :root {
            --bg:{{ $themeBg }};
            --ink:#0f172a;
            --line:#d7e4e2;
            --panel:#ffffff;
            --accent:{{ $themePrimary }};
            --accent-2:{{ $themeSecondary }};
            --muted:#64748b;
            --danger:#dc2626;
        }
    </style>
    @stack('page_styles')
</head>
@php
    $routeName = request()->route()?->getName() ?? '';
    $staffPage = 'default';
    if (str_starts_with($routeName, 'staff.login')) {
        $staffPage = 'login';
    } elseif ($routeName === 'staff.home') {
        $staffPage = 'home';
    } elseif ($routeName === 'staff.profile') {
        $staffPage = 'profile';
    } elseif ($routeName === 'staff.profile.edit') {
        $staffPage = 'profile-edit';
    } elseif ($routeName === 'staff.notifications.index') {
        $staffPage = 'notifications';
    } elseif ($routeName === 'staff.jadwal') {
        $staffPage = 'jadwal';
    } elseif ($routeName === 'staff.history') {
        $staffPage = 'history';
    } elseif ($routeName === 'staff.payroll.index') {
        $staffPage = 'payroll-index';
    } elseif (in_array($routeName, ['staff.payroll.show', 'staff.payroll.period'], true)) {
        $staffPage = 'payroll-show';
    } elseif ($routeName === 'staff.self_schedule') {
        $staffPage = 'ambil-jadwal';
    } elseif ($routeName === 'staff.swap.index') {
        $staffPage = 'tukar-jadwal';
    } elseif (str_starts_with($routeName, 'absen.')) {
        $staffPage = 'absen';
    } elseif (str_starts_with($routeName, 'staff.leave')) {
        $staffPage = 'leave';
    } elseif ($routeName === 'staff.messages.index') {
        $staffPage = 'messages-index';
    } elseif (str_starts_with($routeName, 'staff.messages')) {
        $staffPage = 'messages-show';
    }
    $hideBottomNav = in_array($staffPage, ['messages-show'], true)
        || request()->routeIs('absen.masuk.page', 'absen.pulang.page');
@endphp
<body class="staff-ui staff-{{ $staffPage }}">
@php
    $showStaffNav = (bool) session('staff_karyawan_id');
    $staffKaryawan = request()->attributes->get('staff_karyawan');
    $staffMenuTitle = match ($staffPage) {
        'home' => 'Beranda',
        'profile' => 'Profil Saya',
        'notifications' => 'Notifikasi',
        'jadwal' => 'Jadwal Saya',
        'history' => 'Riwayat Pengajuan',
        'payroll-index', 'payroll-show' => 'Slip Gaji',
        'ambil-jadwal' => 'Ambil Jadwal',
        'tukar-jadwal' => 'Tukar Shift',
        'absen' => 'Absen',
        'leave' => 'Izin & Sakit',
        'messages-index', 'messages-show' => 'Pesan',
        default => 'Portal Karyawan',
    };
@endphp
@if($showStaffNav)
    @php
        $badgeMessages = (int) (request()->attributes->get('staff_unread_messages') ?? 0);
        $staffEmploymentLabel = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel'))
            ? $staffKaryawan->employmentTypeLabel()
            : 'Full Time';
    @endphp
    <div class="staff-global-menu" id="staffGlobalMenu">
        <div class="staff-menu-backdrop" data-staff-menu-close hidden></div>
        <aside class="staff-menu-drawer" id="staffGlobalMenuPanel" hidden>
            <div class="staff-menu-drawer-head">
                <div class="staff-menu-drawer-mark">PK</div>
                <div>
                    <div class="staff-menu-drawer-title">{{ $staffKaryawan->nama_karyawan ?? session('staff_karyawan_name', 'Portal Karyawan') }}</div>
                    <div class="staff-menu-drawer-sub">{{ $staffKaryawan->jabatan ?? $staffMenuTitle }}</div>
                </div>
            </div>
            <div class="staff-menu-current">Menu Personal</div>
            <nav class="staff-menu-drawer-list" aria-label="Menu personal portal staf">
                <a class="staff-menu-link {{ request()->routeIs('staff.profile') ? 'active' : '' }}" href="{{ route('staff.profile') }}">
                    <span class="staff-menu-link-icon">PF</span>
                    <span class="staff-menu-link-copy"><span class="label">Profil Saya</span></span>
                </a>
            </nav>
            <div class="staff-menu-current">{{ $staffEmploymentLabel }}</div>
            <div class="staff-menu-install-simple" data-pwa-install-wrap>
                <div class="staff-menu-install-title">Install App</div>
                <div class="staff-menu-install-sub" id="pwa-install-tip">Pasang portal staf ke layar utama.</div>
                <button id="pwa-install-btn" class="btn-primary pwa-install staff-menu-install-btn" type="button">Install App</button>
            </div>
            <form method="post" action="{{ route('staff.logout') }}" class="staff-menu-logout">
                @csrf
                <button type="submit" class="staff-menu-logout-btn">Logout</button>
            </form>
        </aside>
    </div>
@endif
<main class="app-main">
    @yield('content')
</main>
@if($showStaffNav && !$hideBottomNav)
    <nav class="app-bottom-nav" aria-label="Navigasi Portal Karyawan">
        <a class="nav-item {{ request()->routeIs('staff.home') ? 'active' : '' }}" href="{{ route('staff.home') }}">
            <span class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M4 10.5 12 4l8 6.5V20H4v-9.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M9.5 20v-5.5h5V20" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
            </span>
            <span>Beranda</span>
        </a>
        <a class="nav-item {{ request()->routeIs('staff.jadwal') ? 'active' : '' }}" href="{{ route('staff.jadwal', ['bulan' => now()->format('Y-m')]) }}">
            <span class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span>Jadwal</span>
        </a>
        <a class="nav-item nav-item-center {{ request()->routeIs('absen.*') ? 'active' : '' }}" href="{{ url('/absen') }}">
            <span class="center-bubble" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 21a8 8 0 1 0 0-16a8 8 0 0 0 0 16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="m8.8 12.2 2.1 2.1 4.4-4.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span>Absen</span>
        </a>
        <a class="nav-item {{ request()->routeIs('staff.payroll.*') ? 'active' : '' }}" href="{{ route('staff.payroll.index') }}">
            <span class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <rect x="4.5" y="6" width="15" height="12" rx="2.8" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M4.5 10h15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M8 14.3h3.6M8 16.8h5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span>Pay</span>
        </a>
        <a
            class="nav-item has-badge {{ in_array($staffPage, ['messages-index', 'messages-show']) ? 'active' : '' }}"
            href="{{ route('staff.messages.index') }}"
            aria-label="Buka pesan"
        >
            <span class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M7 18.5 4.8 20V7.8A2.8 2.8 0 0 1 7.6 5h8.8a2.8 2.8 0 0 1 2.8 2.8v6.4a2.8 2.8 0 0 1-2.8 2.8H7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M8.5 9.5h7M8.5 13h4.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
            <span>Pesan</span>
            @if($badgeMessages > 0)
                <span class="badge-dot">{{ $badgeMessages }}</span>
            @endif
        </a>
    </nav>
@endif
@yield('scripts')
<script src="/pwa-install.js"></script>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function (error) {
                console.error('SW register failed:', error);
            });
        });
    }

    (function () {
        const root = document.getElementById('staffGlobalMenu');
        if (!root) return;

        const triggers = document.querySelectorAll('[data-staff-menu-toggle]');
        const panel = root.querySelector('#staffGlobalMenuPanel');
        const backdrop = root.querySelector('[data-staff-menu-close]');

        if (!triggers.length || !panel || !backdrop) return;

        function setMenuState(open) {
            panel.hidden = !open;
            backdrop.hidden = !open;
            root.classList.toggle('is-open', open);
            triggers.forEach(function (trigger) {
                trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                setMenuState(panel.hidden);
            });
        });

        backdrop.addEventListener('click', function () {
            setMenuState(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMenuState(false);
            }
        });
    })();
</script>
</body>
</html>
