@extends('layouts.staff')

@section('title', 'Portal Karyawan')

@section('content')
<div class="container app-shell">
    @php
        $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
        $selfEnabled = (bool) ($setting->self_schedule_enabled ?? false);
        $selfOpen = $selfEnabled && (bool) ($setting->self_schedule_is_open ?? false);
        $openStart = $setting->self_schedule_open_start_date ? $setting->self_schedule_open_start_date->format('Y-m-d') : null;
        $openEnd = $setting->self_schedule_open_end_date ? $setting->self_schedule_open_end_date->format('Y-m-d') : null;
        $today = $today ?? now()->toDateString();
        $outOfOpenWindow = $selfOpen && $openStart && $openEnd && ($today < $openStart || $today > $openEnd);
        $unreadMessages = (int) (request()->attributes->get('staff_unread_messages') ?? 0);

        $nama = (string) ($staffKaryawan->nama_karyawan ?? 'Karyawan');
        $inisial = trim(mb_substr($nama, 0, 1));
        if (str_contains($nama, ' ')) {
            $parts = array_values(array_filter(explode(' ', $nama)));
            if (count($parts) > 1) {
                $inisial = mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1);
            }
        }

        $staffNotifications = collect($staffNotifications ?? []);
        $messageAlertItems = collect($messageAlertItems ?? []);
        $unreadAnnouncementCount = isset($unreadAnnouncements) ? (int) $unreadAnnouncements->count() : 0;
        $pendingSwapForStaff = (int) ($pendingSwapForStaff ?? 0);
        $pendingSwapWaitingAdmin = (int) ($pendingSwapWaitingAdmin ?? 0);
        $pendingLeaveCount = (int) ($pendingLeaveCount ?? 0);
        $heroAlertCount = (int) ($unreadNotificationCount ?? $staffNotifications->whereNull('read_at')->count());
        $employmentLabel = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
        $employmentType = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeValue')) ? $staffKaryawan->employmentTypeValue() : ($staffKaryawan->employment_type ?? null);
        $shiftCodeFor = fn (?int $shiftNo) => $shiftNo && $shiftNo > 0 ? $setting->shiftCodeFor((int) $shiftNo, $employmentType) : null;
        $hourNow = (int) now()->format('H');
        $greeting = match (true) {
            $hourNow < 11 => 'Selamat Pagi',
            $hourNow < 15 => 'Selamat Siang',
            $hourNow < 18 => 'Selamat Sore',
            default => 'Selamat Malam',
        };
        $notificationItems = $staffNotifications->map(function ($notification) {
            $detail = trim((string) ($notification->body ?? ''));
            if ($notification->created_at) {
                $detail = trim($detail . ($detail !== '' ? ' • ' : '') . $notification->created_at->translatedFormat('d M Y • H:i'));
            }

            return [
                'title' => (string) ($notification->title ?? 'Notifikasi'),
                'detail' => $detail !== '' ? $detail : 'Buka detail notifikasi staf.',
                'href' => route('staff.notifications.open', ['notification' => $notification->getKey()]),
                'badge' => method_exists($notification, 'categoryBadge') ? $notification->categoryBadge() : 'Info',
                'tone' => match ((string) ($notification->category ?? 'system')) {
                    'payroll' => 'payroll',
                    'leave' => 'leave',
                    'swap' => 'swap',
                    'attendance' => 'attendance',
                    default => 'messages',
                },
                'isUnread' => (bool) method_exists($notification, 'isUnread') ? $notification->isUnread() : empty($notification->read_at),
                'created_at' => $notification->created_at,
            ];
        });

        $notificationItems = $notificationItems
            ->map(function ($item) {
                $item['created_at'] = $item['created_at'] ?? null;
                return $item;
            })
            ->merge($messageAlertItems)
            ->sortByDesc(fn ($item) => $item['created_at']?->timestamp ?? 0)
            ->take(6)
            ->values();

        $heroAlertCount = (int) (($unreadNotificationCount ?? $staffNotifications->whereNull('read_at')->count()) + $unreadMessages);

        $absenStatusPill = null;
        if (isset($absenSummary) && is_array($absenSummary) && !empty($absenSummary['statusLabel'])) {
            $absenStatusPill = [
                'label' => (string) $absenSummary['statusLabel'],
                'class' => ($absenSummary['statusClass'] ?? '') === 'bad' ? 'bad' : 'ok',
            ];
        } elseif ($absenHariIni && (string) ($absenHariIni->status ?? '') === 'alpa') {
            $absenStatusPill = ['label' => 'Alpa', 'class' => 'bad'];
        }

        $statusLabel = 'Belum absen';
        $statusClass = 'warn';
        if (isset($absenSummary) && is_array($absenSummary) && !empty($absenSummary['statusLabel'])) {
            $statusLabel = (string) $absenSummary['statusLabel'];
            $statusClass = ($absenSummary['statusClass'] ?? '') === 'bad' ? 'bad' : 'ok';
        } elseif ($absenHariIni && (string) ($absenHariIni->status ?? '') === 'alpa') {
            $statusLabel = 'Alpa';
            $statusClass = 'bad';
        } elseif (! $jadwalHariIni) {
            $statusLabel = 'Tidak dijadwalkan';
            $statusClass = 'gray';
        }

        $swapNoticeCount = $pendingSwapForStaff + $pendingSwapWaitingAdmin;
        $todayShiftNo = (int) ($jadwalHariIni->shift_ke ?? ($absenSummary['shiftNo'] ?? 0));
        $todayShiftCode = $todayShiftNo > 0 ? ($shiftCodeFor($todayShiftNo) ?? 'Shift') : 'Libur';
        $todayShiftRange = $todayShiftNo > 0
            ? $setting->shiftRangeLabel($todayShiftNo, $employmentType, $today)
            : 'Belum ada shift';
        $clockInLabel = $absenHariIni?->waktu_masuk?->format('H:i') ?? '--:--';
        $clockOutLabel = $absenHariIni?->waktu_pulang?->format('H:i') ?? 'Belum';

        $workedMinutes = 0;
        if ($absenHariIni?->waktu_masuk && $absenHariIni?->waktu_pulang) {
            $workedMinutes = max(0, $absenHariIni->waktu_pulang->diffInMinutes($absenHariIni->waktu_masuk));
        } elseif ($absenHariIni?->waktu_masuk && ! $absenHariIni?->waktu_pulang && $today === now()->toDateString()) {
            $workedMinutes = max(0, now()->diffInMinutes($absenHariIni->waktu_masuk));
        }

        $workedHours = intdiv($workedMinutes, 60);
        $workedRemainder = $workedMinutes % 60;
        $workedDuration = $workedMinutes > 0
            ? $workedHours . 'j ' . str_pad((string) $workedRemainder, 2, '0', STR_PAD_LEFT) . 'm'
            : '--';

        $statusHeadline = 'Belum Absen';
        $statusVisualState = 'pending';
        if ($absenHariIni?->waktu_masuk && ! $absenHariIni?->waktu_pulang) {
            $statusHeadline = 'Clocked In';
            $statusVisualState = 'live';
        } elseif ($absenHariIni?->waktu_pulang) {
            $statusHeadline = 'Shift Selesai';
            $statusVisualState = 'done';
        } elseif (! $jadwalHariIni) {
            $statusHeadline = 'Hari Libur';
            $statusVisualState = 'idle';
        } elseif (($absenHariIni?->status ?? '') === 'alpa') {
            $statusHeadline = 'Alpa';
            $statusVisualState = 'alert';
        }
    @endphp

    <div class="home-top-stage">
        <section class="home-app-hero" id="staff-profile-card">
            <div class="home-brand-row">
                <div class="home-brand-logo" aria-label="Logo cafe">
                    @if(!empty($setting->logo_path))
                        <img src="{{ asset('storage/' . $setting->logo_path) }}" alt="Logo {{ $setting->nama_toko ?? config('app.name') }}">
                    @else
                        <span>{{ $setting->nama_toko ?? config('app.name') }}</span>
                    @endif
                </div>
                <div class="home-hero-actions">
                    <button class="home-hero-icon {{ $heroAlertCount > 0 ? 'has-alert' : '' }}" type="button" data-home-alert-toggle data-home-alert-history="{{ route('staff.notifications.index') }}" aria-label="Buka notifikasi staf" @if($notificationItems->isNotEmpty()) aria-controls="staffHomeNotificationPanel" aria-expanded="false" @endif>
                        @if($heroAlertCount > 0)
                            <span class="home-hero-alert-dot" aria-hidden="true"></span>
                        @endif
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 5a4 4 0 0 0-4 4v1.5c0 .9-.32 1.76-.9 2.45L6 14.25V16h12v-1.75l-1.1-1.3A3.75 3.75 0 0 1 16 10.5V9a4 4 0 0 0-4-4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M10.5 18a1.5 1.5 0 0 0 3 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <a class="home-hero-icon home-hero-profile-btn" href="{{ route('staff.profile') }}" aria-label="Buka profil saya">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 12a3.5 3.5 0 1 0 0-7a3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M5.5 19.2a6.9 6.9 0 0 1 13 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </a>
                </div>
            </div>

            @if($notificationItems->isNotEmpty())
                <div class="home-notification-flyout" id="staffHomeNotificationPanel" hidden>
                    <div class="home-notification-flyout-head">
                        <div>
                            <strong>Notifikasi</strong>
                            <small>{{ $heroAlertCount }} info perlu dicek</small>
                        </div>
                        <div class="home-notification-head-actions">
                            <a class="home-notification-history-link" href="{{ route('staff.notifications.index') }}">Semua</a>
                            <button type="button" class="home-notification-close" data-home-alert-close aria-label="Tutup notifikasi">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M7 7 17 17M17 7 7 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="home-notification-list">
                        @foreach($notificationItems as $item)
                            <a class="home-notification-item {{ !empty($item['isUnread']) ? 'is-unread' : 'is-known' }} tone-{{ $item['tone'] ?? 'messages' }}" href="{{ $item['href'] }}">
                                <span class="home-notification-item-icon" aria-hidden="true">
                                    @switch($item['tone'] ?? 'messages')
                                        @case('announcement')
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M5.5 10.5a9.5 9.5 0 0 0 8-5v13a9.5 9.5 0 0 0-8-5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                                                <path d="M13.5 7.2h1.3a3.7 3.7 0 0 1 3.7 3.7v2.2a3.7 3.7 0 0 1-3.7 3.7h-1.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                            </svg>
                                            @break
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
                                <span class="home-notification-item-copy">
                                    <strong>{{ $item['title'] }}</strong>
                                    <small>{{ $item['detail'] }}</small>
                                </span>
                                <span class="home-notification-item-badge">{{ $item['badge'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="home-greeting-block">
                <div class="home-greeting-label">{{ $greeting }}</div>
                <div class="home-greeting-name">{{ $nama }}</div>
                <div class="home-greeting-role">{{ $karyawan->jabatan ?? 'Staff Cafe' }}</div>
                <div class="home-greeting-meta">
                    <span>{{ \Carbon\Carbon::parse($today)->translatedFormat('d M Y') }}</span>
                    <span>{{ $employmentLabel }}</span>
                </div>
            </div>

            <div class="home-chip-row">
                <span class="home-chip is-highlight">{{ strtoupper($employmentLabel) }}</span>
                <span class="home-chip">{{ $unreadMessages }} Unread Messages</span>
                <span class="home-chip">{{ $swapNoticeCount }} Pending Swap</span>
                @if($pendingLeaveCount > 0)
                    <span class="home-chip">{{ $pendingLeaveCount }} Pending Leave</span>
                @endif
            </div>

            <div class="home-status-card is-{{ $statusVisualState }}">
                <div class="home-status-head">
                    <div class="home-status-title-wrap">
                        <span class="home-status-kicker">Current Status</span>
                        <strong>{{ $statusHeadline }}</strong>
                    </div>
                    <span class="home-status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
                <div class="home-status-grid">
                    <article class="home-status-item is-clock-in">
                        <span>Clock In</span>
                        <strong>{{ $clockInLabel }}</strong>
                        <small>{{ $absenHariIni?->waktu_masuk ? 'Sudah tercatat' : 'Belum masuk' }}</small>
                    </article>
                    <article class="home-status-item is-shift">
                        <span>Today's Shift</span>
                        <strong>{{ $todayShiftCode }}</strong>
                        <small>{{ $todayShiftRange }}</small>
                    </article>
                    <article class="home-status-item is-clock-out">
                        <span>Clock Out</span>
                        <strong>{{ $clockOutLabel }}</strong>
                        <small>{{ $absenHariIni?->waktu_pulang ? 'Shift selesai' : 'Menunggu pulang' }}</small>
                    </article>
                    <article class="home-status-item is-hours">
                        <span>Hours Logged</span>
                        <strong>{{ $workedDuration }}</strong>
                        <small>{{ $workedMinutes > 0 ? 'Tercatat hari ini' : 'Belum ada durasi' }}</small>
                    </article>
                </div>
            </div>
        </section>
    </div>

    @if(isset($unreadAnnouncements) && $unreadAnnouncements->count() > 0)
        <div class="modal-wrap" id="announce-modal">
            <div class="modal-card">
                <div class="row u-between u-center u-mt-0 u-mb-6">
                    <h3 class="u-m-0">Pengumuman Baru</h3>
                    <span class="announce-badge">Belum Dibaca</span>
                </div>
                <div class="small" id="announce-indicator">1/{{ $unreadAnnouncements->count() }}</div>

                @foreach($unreadAnnouncements as $i => $ann)
                    <div class="announce-slide {{ $i === 0 ? 'is-active' : '' }}" data-index="{{ $i }}">
                        <div class="announce-title u-mt-8">{{ $ann->title }}</div>
                        <div class="announce-meta u-mt-4">
                            {{ $ann->published_at ? $ann->published_at->format('Y-m-d H:i') : '' }}
                        </div>
                        <div class="modal-body u-max-h-260 u-overflow-auto">{!! nl2br(e($ann->body)) !!}</div>
                        @if($ann->image_path)
                            <div class="poster u-mt-10">
                                <img src="{{ asset('storage/' . $ann->image_path) }}" alt="Poster">
                            </div>
                        @endif
                        <div class="modal-actions">
                            <form method="post" action="{{ route('staff.announcement.read', $ann) }}">
                                @csrf
                                <button class="btn-primary" type="submit">Baca</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="home-dashboard-sheet">
        <section class="home-announcement-section" id="staff-announcements">
            <div class="home-announcement-header-shell">
                <div class="home-announcement-head-card">
                    <h2>Announcements</h2>
                </div>
                @if($unreadAnnouncementCount > 0)
                    <span class="announce-badge">{{ $unreadAnnouncementCount }} baru</span>
                @endif
            </div>
            @if(isset($announcements) && $announcements->count() > 0)
                <div class="home-announcement-carousel" data-announcement-carousel>
                    @foreach($announcements as $a)
                        @php
                            $read = in_array((int) $a->id, (array) ($announcementReadIds ?? []), true);
                        @endphp
                        <article class="home-announcement-card {{ $read ? 'is-read' : '' }} type-{{ $a->staff_card_type ?? 'admin' }}" data-announcement-card>
                            <div class="home-announcement-surface">
                                <div class="home-announcement-card-top">
                                    <div class="home-announcement-card-headline">
                                        <span class="home-announcement-card-title">{{ $a->staff_card_title ?? $a->title }}</span>
                                        <span class="home-announcement-card-subtitle">{{ $a->staff_card_subtitle ?? 'Informasi terbaru untuk staf' }}</span>
                                    </div>
                                    <span class="home-announcement-chip {{ $read ? 'read' : '' }}">{{ $read ? 'Sudah dibaca' : 'Baru' }}</span>
                                </div>
                                <div class="home-announcement-main {{ $a->image_path ? 'has-poster' : 'is-fallback' }}">
                                    <div class="home-announcement-copy">
                                        <span class="home-announcement-kicker">{{ $a->staff_card_label ?? 'Update operasional' }}</span>
                                        <p class="home-announcement-highlight">{{ $a->staff_card_summary ?? '' }}</p>
                                        @if(! empty($a->staff_card_status))
                                            <span class="home-announcement-status-badge {{ $a->staff_card_status['class'] ?? 'plain' }}">
                                                {{ $a->staff_card_status['label'] ?? 'Info' }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($a->image_path)
                                        <div class="home-announcement-visual has-image">
                                            <img src="{{ asset('storage/' . $a->image_path) }}" alt="Poster pengumuman {{ $a->title }}">
                                        </div>
                                    @endif
                                </div>
                                <div class="home-announcement-footer">
                                    <div class="home-announcement-details">
                                        @foreach(($a->staff_card_details ?? []) as $detail)
                                            <span class="home-announcement-detail {{ ($detail['class'] ?? 'plain') !== 'plain' ? 'is-' . $detail['class'] : '' }}">
                                                <span class="home-announcement-detail-label">{{ $detail['label'] ?? '-' }}</span>
                                                <strong class="home-announcement-detail-value">{{ $detail['value'] ?? '-' }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                    @if(! $read)
                                        <form method="post" action="{{ route('staff.announcement.read', $a) }}">
                                            @csrf
                                            <button class="btn-mini primary" type="submit">Tandai dibaca</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
                @if($announcements->count() > 1)
                    <div class="home-announcement-dots">
                        @foreach($announcements as $index => $a)
                            <button type="button" class="dot {{ $loop->first ? 'active' : '' }}" data-announcement-dot="{{ $index }}" aria-label="Pengumuman {{ $index + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="card home-announcement-empty">
                    <h3>Belum ada pengumuman</h3>
                    <div class="sub">Nanti update terbaru dari admin akan muncul di bagian ini.</div>
                </div>
            @endif
        </section>

        <section class="card quick-actions-card">
            <div class="home-section-head">
                <div>
                    <h2>Quick Actions</h2>
                    <div class="sub">Shortcut operasional yang paling sering dipakai.</div>
                </div>
            </div>
            <div class="menu-grid app-shortcut-grid">
                <a class="menu-btn" href="{{ url('/absen') }}">
                    <div class="icon tone-blue" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 21a8 8 0 1 0 0-16a8 8 0 0 0 0 16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="m8.8 12.2 2.1 2.1 4.4-4.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="t">Absen</div>
                    <div class="tile-meta">
                        <span class="status-pill {{ $absenStatusPill['class'] ?? '' }}">
                            {{ strtoupper((string) ($absenStatusPill['label'] ?? ($jadwalHariIni ? 'S' . (int) ($jadwalHariIni->shift_ke ?? 0) : 'SIAP'))) }}
                        </span>
                    </div>
                </a>

                <a class="menu-btn" href="{{ route('staff.jadwal', ['bulan' => now()->format('Y-m')]) }}">
                    <div class="icon tone-purple" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="t">Jadwal</div>
                    <div class="tile-meta">
                        <span class="status-pill">Bulan ini</span>
                    </div>
                </a>

                @if(! $selfEnabled)
                    <button class="menu-btn disabled" type="button" data-self-modal-open="modal-self-off">
                        <div class="icon tone-teal" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 10v6M9 13h6M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="t">Self-Sched</div>
                        <div class="tile-meta">
                            <span class="status-pill bad">Off</span>
                        </div>
                    </button>
                @elseif($outOfOpenWindow)
                    <button class="menu-btn disabled" type="button" data-self-modal-open="modal-self-window">
                        <div class="icon tone-teal" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 10v6M9 13h6M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="t">Self-Sched</div>
                        <div class="tile-meta">
                            <span class="status-pill warn">Di luar</span>
                        </div>
                    </button>
                @elseif($selfOpen)
                    <a class="menu-btn" href="{{ route('staff.self_schedule') }}">
                        <div class="icon tone-teal" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 10v6M9 13h6M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="t">Self-Sched</div>
                        <div class="tile-meta">
                            <span class="status-pill ok">Dibuka</span>
                        </div>
                    </a>
                @else
                    <button class="menu-btn disabled" type="button" data-self-modal-open="modal-self-closed">
                        <div class="icon tone-teal" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <rect x="4" y="5" width="16" height="15" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                <path d="M12 10v6M9 13h6M8 3.8v3M16 3.8v3M4 9.5h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="t">Self-Sched</div>
                        <div class="tile-meta">
                            <span class="status-pill">Ditutup</span>
                        </div>
                    </button>
                @endif

                <a class="menu-btn" href="{{ route('staff.swap.index') }}">
                    <div class="icon tone-pink" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7 7h10m0 0-3-3m3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M17 17H7m0 0 3 3m-3-3 3-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="t">Swap</div>
                    @if($swapNoticeCount > 0)
                        <span class="badge">{{ $swapNoticeCount }}</span>
                    @endif
                    <div class="tile-meta">
                        <span class="status-pill {{ $swapNoticeCount > 0 ? 'warn' : '' }}">{{ $swapNoticeCount > 0 ? $swapNoticeCount . ' notif' : 'Atur' }}</span>
                    </div>
                </a>

                <a class="menu-btn" href="{{ route('staff.leave.index') }}">
                    <div class="icon tone-orange" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M8.5 5.5h5l3 3V18a2 2 0 0 1-2 2h-6A2.5 2.5 0 0 1 6 17.5v-9A3 3 0 0 1 8.5 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="M13.5 5.5V9h3" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="M9 13h6M9 16h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="t">Leave</div>
                    @if($pendingLeaveCount > 0)
                        <span class="badge">{{ $pendingLeaveCount }}</span>
                    @endif
                    <div class="tile-meta">
                        <span class="status-pill {{ $pendingLeaveCount > 0 ? 'warn' : '' }}">{{ $pendingLeaveCount > 0 ? $pendingLeaveCount . ' proses' : 'Ajukan' }}</span>
                    </div>
                </a>

                <a class="menu-btn secondary" href="{{ route('staff.history') }}">
                    <div class="icon tone-indigo" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M12 7.5v5l3.2 1.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4.5 12a7.5 7.5 0 1 0 2.1-5.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M4.5 5.5v3.7h3.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="t">Riwayat</div>
                    <div class="tile-meta">
                        <span class="status-pill">Riwayat</span>
                    </div>
                </a>

                <a class="menu-btn secondary" href="{{ route('staff.payroll.index') }}">
                    <div class="icon tone-orange" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect x="4.5" y="6" width="15" height="12" rx="2.8" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M4.5 10h15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <path d="M8 14.3h3.6M8 16.8h5.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="t">Payroll</div>
                    <div class="tile-meta">
                        <span class="status-pill">{{ ($staffKaryawan && method_exists($staffKaryawan, 'salarySchemeLabel')) ? $staffKaryawan->salarySchemeLabel() : 'Realtime' }}</span>
                    </div>
                </a>

                <a class="menu-btn" href="{{ route('staff.messages.index') }}">
                    <div class="icon tone-sky" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6.5 7.5h11a2.5 2.5 0 0 1 2.5 2.5v5a2.5 2.5 0 0 1-2.5 2.5H11l-4.5 3v-3H6.5A2.5 2.5 0 0 1 4 15v-5a2.5 2.5 0 0 1 2.5-2.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="M8.5 11h7M8.5 14h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="t">Messages</div>
                    @if($unreadMessages > 0)
                        <span class="badge">{{ $unreadMessages }}</span>
                    @endif
                    <div class="tile-meta">
                        <span class="status-pill {{ $unreadMessages > 0 ? 'ok' : '' }}">{{ $unreadMessages > 0 ? $unreadMessages . ' baru' : 'Buka' }}</span>
                    </div>
                </a>
            </div>
        </section>
    </div>

    <div id="modal-self-off" class="modal-wrap" hidden>
        <div class="modal-card">
            <h3>Ambil Jadwal</h3>
            <div class="modal-body">Fitur ambil jadwal sedang dinonaktifkan oleh admin.</div>
            <div class="modal-actions">
                <button class="btn-neutral" type="button" data-self-modal-close>Tutup</button>
            </div>
        </div>
    </div>
    <div id="modal-self-closed" class="modal-wrap" hidden>
        <div class="modal-card">
            <h3>Ambil Jadwal</h3>
            <div class="modal-body">Pendaftaran belum dibuka oleh admin. Coba lagi nanti.</div>
            <div class="modal-actions">
                <button class="btn-neutral" type="button" data-self-modal-close>Tutup</button>
            </div>
        </div>
    </div>
    <div id="modal-self-window" class="modal-wrap" hidden>
        <div class="modal-card">
            <h3>Ambil Jadwal</h3>
            <div class="modal-body">
                Pendaftaran hanya dibuka pada
                <b>{{ $openStart ?? '-' }}</b> s/d <b>{{ $openEnd ?? '-' }}</b>.
            </div>
            <div class="modal-actions">
                <button class="btn-neutral" type="button" data-self-modal-close>Tutup</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.querySelectorAll('[data-self-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = button.getAttribute('data-self-modal-open');
            var modal = target ? document.getElementById(target) : null;
            if (modal) {
                modal.hidden = false;
            }
        });
    });

    document.querySelectorAll('[data-self-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            var modal = button.closest('.modal-wrap');
            if (modal) {
                modal.hidden = true;
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.modal-wrap').forEach(function (modal) {
            modal.hidden = true;
        });
    });

    var announcementCarousel = document.querySelector('[data-announcement-carousel]');
    var announcementDots = Array.from(document.querySelectorAll('[data-announcement-dot]'));
    if (announcementCarousel) {
        var announcementCards = Array.from(announcementCarousel.querySelectorAll('[data-announcement-card]'));

        var setActiveAnnouncement = function (activeIndex) {
            announcementDots.forEach(function (dot, index) {
                dot.classList.toggle('active', index === activeIndex);
            });

            announcementCards.forEach(function (card, index) {
                card.classList.toggle('is-active-card', index === activeIndex);
            });
        };

        announcementDots.forEach(function (dot, index) {
            dot.addEventListener('click', function () {
                var card = announcementCards[index];
                if (!card) {
                    return;
                }

                card.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'center'
                });
                setActiveAnnouncement(index);
            });
        });

        var syncActiveAnnouncement = function () {
            if (!announcementCards.length) {
                return;
            }

            var bestIndex = 0;
            var bestDistance = Infinity;
            var carouselRect = announcementCarousel.getBoundingClientRect();
            var carouselCenter = carouselRect.left + (carouselRect.width / 2);

            announcementCards.forEach(function (card, index) {
                var cardRect = card.getBoundingClientRect();
                var cardCenter = cardRect.left + (cardRect.width / 2);
                var distance = Math.abs(cardCenter - carouselCenter);
                if (distance < bestDistance) {
                    bestDistance = distance;
                    bestIndex = index;
                }
            });

            setActiveAnnouncement(bestIndex);
        };

        syncActiveAnnouncement();
        announcementCarousel.addEventListener('scroll', syncActiveAnnouncement, { passive: true });
    }

    (function () {
        var trigger = document.querySelector('[data-home-alert-toggle]');
        var panel = document.getElementById('staffHomeNotificationPanel');
        var closeButton = document.querySelector('[data-home-alert-close]');
        var historyUrl = trigger ? trigger.getAttribute('data-home-alert-history') : null;
        var hideTimer = null;

        if (!trigger) {
            return;
        }

        if (!panel) {
            if (historyUrl) {
                trigger.addEventListener('click', function () {
                    window.location.href = historyUrl;
                });
            }
            return;
        }

        var setNotificationState = function (open) {
            if (hideTimer) {
                window.clearTimeout(hideTimer);
                hideTimer = null;
            }
            panel.hidden = !open;
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open && panel.dataset.emptyNotifications === 'true') {
                hideTimer = window.setTimeout(function () {
                    setNotificationState(false);
                }, 1600);
            }
        };

        trigger.addEventListener('click', function () {
            setNotificationState(panel.hidden);
        });

        if (closeButton) {
            closeButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                setNotificationState(false);
            });
        }

        panel.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setNotificationState(false);
            });
        });

        document.addEventListener('click', function (event) {
            if (panel.hidden) {
                return;
            }
            if (panel.contains(event.target) || trigger.contains(event.target)) {
                return;
            }
            setNotificationState(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setNotificationState(false);
            }
        });
    })();
</script>
@endsection
