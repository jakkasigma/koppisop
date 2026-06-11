@php
    $pageTitle = (string) ($pageTitle ?? 'Portal Staf');
    $pageMark = strtoupper((string) ($pageMark ?? mb_substr($pageTitle, 0, 1)));
    $pageTitleKey = mb_strtolower($pageTitle);
    $pageIcon = trim((string) ($pageIcon ?? ''));
    if ($pageIcon === '') {
        $pageIcon = match (true) {
            str_contains($pageTitleKey, 'ambil jadwal') => 'calendar-add',
            str_contains($pageTitleKey, 'jadwal') => 'calendar',
            str_contains($pageTitleKey, 'absen') => 'attendance',
            str_contains($pageTitleKey, 'slip gaji'), str_contains($pageTitleKey, 'pendapatan') => 'payroll',
            str_contains($pageTitleKey, 'profil') => 'profile',
            str_contains($pageTitleKey, 'pesan') => 'messages',
            str_contains($pageTitleKey, 'izin'), str_contains($pageTitleKey, 'sakit') => 'leave',
            str_contains($pageTitleKey, 'tukar') => 'swap',
            str_contains($pageTitleKey, 'riwayat') => 'history',
            default => 'app',
        };
    }
    $staffName = trim((string) ($staffName ?? 'Karyawan'));
    $greetingTitle = (string) ($greetingTitle ?? ('Halo, ' . $staffName));
    $greetingSubtitle = trim((string) ($greetingSubtitle ?? ''));
    $employmentLabel = trim((string) ($employmentLabel ?? ''));
    $employmentMeta = trim((string) ($employmentMeta ?? ''));
@endphp

<div class="staff-mobile-page-topbar">
    <div class="staff-mobile-page-topbar-title">
        <span class="staff-mobile-page-top-icon" aria-hidden="true">
            @switch($pageIcon)
                @case('calendar')
                    <svg viewBox="0 0 24 24" fill="none">
                        <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    @break
                @case('calendar-add')
                    <svg viewBox="0 0 24 24" fill="none">
                        <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M8 3.8v3M16 3.8v3M4 9.5h16M12 12.5v4M10 14.5h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    @break
                @case('attendance')
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 21a8 8 0 1 0 0-16a8 8 0 0 0 0 16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="m8.8 12.2 2.1 2.1 4.4-4.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    @break
                @case('payroll')
                    <svg viewBox="0 0 24 24" fill="none">
                        <rect x="4.5" y="6" width="15" height="12" rx="2.8" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M4.5 10h15M8 14.3h3.6M8 16.8h5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    @break
                @case('profile')
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 12a3.7 3.7 0 1 0 0-7.4a3.7 3.7 0 0 0 0 7.4Z" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M5.5 19.2a6.8 6.8 0 0 1 13 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    @break
                @case('messages')
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M7 18.5 4.8 20V7.8A2.8 2.8 0 0 1 7.6 5h8.8a2.8 2.8 0 0 1 2.8 2.8v6.4a2.8 2.8 0 0 1-2.8 2.8H7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="M8.5 9.5h7M8.5 13h4.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    @break
                @case('leave')
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M8 4.8h8l2 2V19a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6.8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="M9 11.5h6M12 8.5v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                    @break
                @case('swap')
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M7 7h10m0 0-2.8-2.8M17 7l-2.8 2.8M17 17H7m0 0 2.8-2.8M7 17l2.8 2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    @break
                @case('history')
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 7.2v5l3.2 1.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M20 12a8 8 0 1 1-2.3-5.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M20 4.8v4h-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    @break
                @default
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 4.5 14 9l4.5.7-3.2 3.1.8 4.7L12 15.2 7.9 17.5l.8-4.7L5.5 9.7 10 9l2-4.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    </svg>
            @endswitch
        </span>
        <strong>{{ $pageTitle }}</strong>
    </div>
</div>

<div class="staff-mobile-page-greeting-card">
    <div class="staff-mobile-page-greeting-copy">
        <h1>{{ $greetingTitle }}</h1>
        @if($greetingSubtitle !== '')
            <p>{{ $greetingSubtitle }}</p>
        @endif
    </div>
    @if($employmentLabel !== '' || $employmentMeta !== '')
        <div class="staff-mobile-page-employment-card">
            @if($employmentLabel !== '')
                <span class="staff-mobile-page-pill">{{ strtoupper($employmentLabel) }}</span>
            @endif
            @if($employmentMeta !== '')
                <small>{{ $employmentMeta }}</small>
            @endif
        </div>
    @endif
</div>
