@extends('layouts.staff')

@section('title', 'Notifikasi')

@section('content')
<div class="container app-shell">
    @php
        $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
        $nama = trim((string) ($staffKaryawan->nama_karyawan ?? 'Karyawan'));
        $unreadNotificationCount = (int) ($unreadNotificationCount ?? 0);
        $totalNotificationCount = (int) ($totalNotificationCount ?? 0);
        $activeNotificationFilter = (string) ($activeNotificationFilter ?? 'all');
        $categoryCounts = collect($categoryCounts ?? []);
        $latestLabel = $latestNotificationAt
            ? $latestNotificationAt->translatedFormat('d M Y • H:i')
            : 'Belum ada riwayat';

        $filterLinks = [
            ['key' => 'all', 'label' => 'Semua', 'count' => $totalNotificationCount],
            ['key' => 'unread', 'label' => 'Belum Dibaca', 'count' => $unreadNotificationCount],
        ];

        $categoryHighlights = [
            ['label' => 'Gaji', 'value' => (int) $categoryCounts->get(\App\Models\StaffNotification::CATEGORY_PAYROLL, 0)],
            ['label' => 'Izin', 'value' => (int) $categoryCounts->get(\App\Models\StaffNotification::CATEGORY_LEAVE, 0)],
            ['label' => 'Swap', 'value' => (int) $categoryCounts->get(\App\Models\StaffNotification::CATEGORY_SWAP, 0)],
            ['label' => 'Absen', 'value' => (int) $categoryCounts->get(\App\Models\StaffNotification::CATEGORY_ATTENDANCE, 0)],
        ];
    @endphp

    <div class="staff-mobile-page-screen staff-notification-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => 'Notifikasi',
                'pageIcon' => 'messages',
                'staffName' => $nama,
                'greetingTitle' => 'Riwayat Notifikasi',
                'greetingSubtitle' => 'Semua info gaji, izin, tukar shift, dan absensi staf tersimpan di sini.',
            ])

            <section class="staff-mobile-page-summary-card staff-notification-summary-card">
                <div class="staff-mobile-page-summary-topline">
                    <div class="staff-mobile-page-summary-period">
                        <span class="staff-mobile-page-summary-label">Total Riwayat</span>
                        <strong>{{ number_format($totalNotificationCount, 0, ',', '.') }} Notifikasi</strong>
                    </div>
                    <span class="staff-notification-summary-badge {{ $unreadNotificationCount > 0 ? 'is-unread' : 'is-clear' }}">
                        {{ $unreadNotificationCount > 0 ? $unreadNotificationCount . ' Baru' : 'Semua Sudah Dicek' }}
                    </span>
                </div>

                <div class="staff-mobile-page-summary-stats">
                    <article>
                        <span>Terakhir Masuk</span>
                        <strong>{{ $latestLabel }}</strong>
                    </article>
                    <article>
                        <span>Filter Aktif</span>
                        <strong>{{ $activeNotificationFilter === 'unread' ? 'Belum Dibaca' : 'Semua Riwayat' }}</strong>
                    </article>
                </div>

                <div class="staff-notification-highlight-grid">
                    @foreach($categoryHighlights as $highlight)
                        <article class="staff-notification-highlight-card">
                            <span>{{ $highlight['label'] }}</span>
                            <strong>{{ number_format((int) $highlight['value'], 0, ',', '.') }}</strong>
                        </article>
                    @endforeach
                </div>

                <div class="staff-mobile-page-summary-actions">
                    @foreach($filterLinks as $link)
                        <a
                            class="history-jump-link {{ $activeNotificationFilter === $link['key'] ? 'is-active' : '' }}"
                            href="{{ route('staff.notifications.index', ['filter' => $link['key']]) }}"
                        >
                            {{ $link['label'] }}
                            @if($link['count'] > 0)
                                <span>{{ $link['count'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>

            @if($notifications->count() > 0)
                <section class="staff-notification-list">
                    @foreach($notifications as $notification)
                        @php
                            $tone = match ((string) ($notification->category ?? 'system')) {
                                'payroll' => 'payroll',
                                'leave' => 'leave',
                                'swap' => 'swap',
                                'attendance' => 'attendance',
                                default => 'messages',
                            };
                            $detail = trim((string) ($notification->body ?? ''));
                            if ($notification->created_at) {
                                $detail = trim($detail . ($detail !== '' ? ' • ' : '') . $notification->created_at->translatedFormat('d M Y • H:i'));
                            }
                        @endphp

                        <a
                            class="staff-notification-card tone-{{ $tone }} {{ $notification->isUnread() ? 'is-unread' : 'is-read' }}"
                            href="{{ route('staff.notifications.open', ['notification' => $notification->getKey()]) }}"
                        >
                            <span class="staff-notification-card-icon" aria-hidden="true">
                                @switch($tone)
                                    @case('swap')
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M7 7h10m0 0-2.8-2.8M17 7l-2.8 2.8M17 17H7m0 0 2.8-2.8M7 17l2.8 2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        @break
                                    @case('payroll')
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <rect x="4.5" y="6" width="15" height="12" rx="2.8" stroke="currentColor" stroke-width="1.8"/>
                                            <path d="M4.5 10h15M8 14.3h3.6M8 16.8h5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        @break
                                    @case('attendance')
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M12 21a8 8 0 1 0 0-16a8 8 0 0 0 0 16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="m8.8 12.2 2.1 2.1 4.4-4.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        @break
                                    @case('leave')
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M8 4.8h8l2 2V19a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V6.8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="M9 12h6M12 9v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                        @break
                                    @default
                                        <svg viewBox="0 0 24 24" fill="none">
                                            <path d="M7 18.5 4.8 20V7.8A2.8 2.8 0 0 1 7.6 5h8.8a2.8 2.8 0 0 1 2.8 2.8v6.4a2.8 2.8 0 0 1-2.8 2.8H7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                            <path d="M8.5 9.5h7M8.5 13h4.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                        </svg>
                                @endswitch
                            </span>

                            <span class="staff-notification-card-copy">
                                <span class="staff-notification-card-row">
                                    <strong>{{ $notification->title }}</strong>
                                    <time>{{ $notification->created_at?->translatedFormat('d M') }}</time>
                                </span>
                                <small>{{ $detail !== '' ? $detail : 'Buka detail notifikasi staf.' }}</small>
                                <span class="staff-notification-card-meta">
                                    <span class="staff-notification-card-badge">{{ $notification->categoryBadge() }}</span>
                                    <span class="staff-notification-card-status {{ $notification->isUnread() ? 'is-unread' : 'is-read' }}">
                                        {{ $notification->isUnread() ? 'Baru' : 'Sudah dibaca' }}
                                    </span>
                                </span>
                            </span>
                        </a>
                    @endforeach
                </section>

                @if($notifications->hasPages())
                    <div class="staff-notification-pagination">
                        @if($notifications->previousPageUrl())
                            <a class="btn-neutral" href="{{ $notifications->previousPageUrl() }}">Lebih Baru</a>
                        @endif
                        @if($notifications->nextPageUrl())
                            <a class="btn-primary" href="{{ $notifications->nextPageUrl() }}">Lihat Riwayat Lama</a>
                        @endif
                    </div>
                @endif
            @else
                <section class="staff-notification-empty-card">
                    <strong>Belum ada notifikasi di filter ini.</strong>
                    <p>Begitu ada slip gaji, keputusan izin, tukar shift, atau update absensi, riwayatnya akan tampil di sini.</p>
                </section>
            @endif
        </section>
    </div>
</div>
@endsection
