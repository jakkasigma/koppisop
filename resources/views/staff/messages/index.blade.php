@extends('layouts.staff')

@section('title', 'Pesan')

@section('content')
<div class="container app-shell">
    @php
        $totalUnread = array_sum(array_map('intval', $unreadCounts));
        $resolveTypeLabel = function (string $typeKey): string {
            return match ($typeKey) {
                'leave' => 'Izin',
                'swap' => 'Tukar',
                'absensi' => 'Absensi',
                'admin_chat' => 'Admin',
                default => ucfirst($typeKey),
            };
        };
        $filterChips = [
            ['key' => 'all', 'label' => 'Semua'],
            ['key' => 'unread', 'label' => 'Belum Dibaca'],
            ['key' => 'admin_chat', 'label' => 'Admin'],
            ['key' => 'system', 'label' => 'Sistem'],
        ];
        $systemTypes = ['absensi', 'leave', 'swap'];
        $unreadThreadCount = $threads->filter(function ($thread) use ($unreadCounts, $systemTypes) {
            $key = ($thread['type'] ?? 'admin_chat') . ':' . ($thread['id'] ?? '0');
            return ! in_array((string) ($thread['type'] ?? ''), $systemTypes, true)
                && (int) ($unreadCounts[$key] ?? 0) > 0;
        })->count();
    @endphp

    <div class="staff-chat-mobile-screen">
        <section class="staff-chat-mobile-card staff-chat-list-card">
            <header class="staff-chat-topbar">
                <div class="staff-chat-brand-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6.5 8.5C6.5 7.39543 7.39543 6.5 8.5 6.5H15.5C16.6046 6.5 17.5 7.39543 17.5 8.5V13.5C17.5 14.6046 16.6046 15.5 15.5 15.5H11.4L8.6 17.8C8.26667 18.0733 7.76667 17.8361 7.76667 17.4051V15.5H8.5C7.39543 15.5 6.5 14.6046 6.5 13.5V8.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        <path d="M9.5 10.5H14.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M9.5 13H12.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </div>

                <div class="staff-chat-topbar-copy">
                    <strong>Pesan</strong>
                    <span>{{ $totalUnread > 0 ? $totalUnread . ' chat belum dibaca' : 'Inbox staf aktif' }}</span>
                </div>
            </header>

            <div class="staff-chat-searchbar">
                <span class="staff-chat-search-icon">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.8"/>
                        <path d="M16 16L20 20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <input id="staffMessageSearch" type="text" placeholder="Cari percakapan..." oninput="applyThreadFilters()">
            </div>

            <div class="staff-chat-filter-row" id="threadFilterRow">
                @foreach($filterChips as $chip)
                    @php
                        $count = match ($chip['key']) {
                            'all', 'unread' => $unreadThreadCount,
                            'system' => $threads->filter(function ($thread) use ($unreadCounts, $systemTypes) {
                                $type = (string) ($thread['type'] ?? '');
                                $key = $type . ':' . ($thread['id'] ?? '0');
                                return in_array($type, $systemTypes, true) && (int) ($unreadCounts[$key] ?? 0) > 0;
                            })->count(),
                            default => $threads->filter(function ($thread) use ($unreadCounts, $chip) {
                                $type = (string) ($thread['type'] ?? '');
                                $key = $type . ':' . ($thread['id'] ?? '0');
                                return $type === $chip['key'] && (int) ($unreadCounts[$key] ?? 0) > 0;
                            })->count(),
                        };
                    @endphp
                    <button
                        type="button"
                        class="staff-chat-filter-chip {{ $loop->first ? 'active' : '' }}"
                        data-thread-filter="{{ $chip['key'] }}"
                        onclick="setThreadFilter('{{ $chip['key'] }}')"
                    >
                        {{ $chip['label'] }}
                        @if($chip['key'] !== 'all' && $count > 0)
                            <span>{{ $count }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="staff-chat-section-caption">Daftar Percakapan</div>

            <div class="staff-chat-thread-list" id="threadList">
                @forelse($threads as $thread)
                    @php
                        $key = $thread['type'] . ':' . $thread['id'];
                        $last = $lastMessages[$key] ?? null;
                        $unread = (int) ($unreadCounts[$key] ?? 0);
                        $typeKey = (string) ($thread['type'] ?? 'admin_chat');
                        $typeLabel = $resolveTypeLabel($typeKey);
                        $initials = strtoupper(mb_substr((string) $thread['title'], 0, 1));
                        $preview = $last ? \Illuminate\Support\Str::limit((string) $last->message, 62) : 'Belum ada pesan di percakapan ini.';
                        $timeLabel = $last && $last->created_at
                            ? ($last->created_at->isToday() ? $last->created_at->format('H:i') : $last->created_at->translatedFormat('d/m'))
                            : '';
                    @endphp

                    <a
                        class="staff-chat-thread-item {{ $unread > 0 ? 'has-unread' : '' }}"
                        href="{{ route('staff.messages.show', ['type' => $thread['type'], 'id' => $thread['id']]) }}"
                        data-name="{{ strtolower($thread['title'] . ' ' . ($thread['subtitle'] ?? '') . ' ' . $preview) }}"
                        data-filter="{{ $typeKey }}"
                        data-unread="{{ $unread > 0 ? '1' : '0' }}"
                    >
                        <div class="staff-chat-thread-avatar-wrap">
                            <div class="staff-chat-thread-avatar">{{ $initials }}</div>
                            @if($unread > 0)
                                <span class="staff-chat-thread-presence"></span>
                            @endif
                        </div>

                        <div class="staff-chat-thread-content">
                            <div class="staff-chat-thread-row">
                                <strong>{{ $thread['title'] }}</strong>
                                @if($timeLabel !== '')
                                    <time>{{ $timeLabel }}</time>
                                @endif
                            </div>

                            <div class="staff-chat-thread-row is-preview">
                                <p>{{ $preview }}</p>
                                @if($unread > 0)
                                    <span class="staff-chat-thread-unread">{{ $unread }}</span>
                                @else
                                    <span class="staff-chat-thread-tag">{{ $typeLabel }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="staff-chat-empty-state">
                        <strong>Belum ada percakapan</strong>
                        <span>Nanti balasan admin dan notifikasi operasional akan muncul di sini.</span>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>

<script>
    let currentThreadFilter = 'all';

    function setThreadFilter(filter) {
        currentThreadFilter = filter || 'all';

        document.querySelectorAll('[data-thread-filter]').forEach(function (button) {
            button.classList.toggle('active', button.getAttribute('data-thread-filter') === currentThreadFilter);
        });

        applyThreadFilters();
    }

    function applyThreadFilters() {
        const term = (document.getElementById('staffMessageSearch')?.value || '').toLowerCase();

        document.querySelectorAll('#threadList .staff-chat-thread-item').forEach(function (element) {
            const name = element.getAttribute('data-name') || '';
            const type = element.getAttribute('data-filter') || '';
            const unread = element.getAttribute('data-unread') === '1';

            const matchesSearch = name.includes(term);
            const isSystemThread = type === 'absensi' || type === 'leave' || type === 'swap';
            const matchesFilter = currentThreadFilter === 'all'
                ? !isSystemThread
                : currentThreadFilter === 'unread'
                    ? (unread && !isSystemThread)
                    : currentThreadFilter === 'system'
                        ? isSystemThread
                        : type === currentThreadFilter;

            const shouldShow = matchesSearch && matchesFilter;
            element.hidden = !shouldShow;
            element.style.display = shouldShow ? '' : 'none';
        });
    }

    window.addEventListener('DOMContentLoaded', function () {
        setThreadFilter('all');
    });
</script>
@endsection
