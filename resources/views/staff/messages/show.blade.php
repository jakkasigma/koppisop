@extends('layouts.staff')

@section('title', 'Detail Pesan')

@section('content')
<div class="container app-shell">
    @php
        $thread = $threads->first(function ($item) use ($type, $threadId) {
            return (string) ($item['type'] ?? '') === (string) $type
                && (int) ($item['id'] ?? 0) === (int) $threadId;
        });
        $threadTitle = (string) ($thread['title'] ?? 'Percakapan');
        $threadStatus = (string) ($thread['status'] ?? '');
        $threadTypeKey = (string) ($thread['type'] ?? $type);
        $threadTypeLabel = match ($threadTypeKey) {
            'leave' => 'Izin',
            'swap' => 'Tukar',
            'absensi' => 'Absensi',
            'admin_chat' => 'Admin',
            default => ucfirst($threadTypeKey),
        };
        $threadInitial = strtoupper(mb_substr($threadTitle, 0, 1));
        $messageCount = $messages->count();
        $groupedMessages = $messages->groupBy(fn ($message) => $message->created_at?->toDateString() ?? 'no-date');
        $formatDayLabel = function (string $dateKey): string {
            if ($dateKey === 'no-date') {
                return 'Tanpa tanggal';
            }

            $date = \Illuminate\Support\Carbon::parse($dateKey);

            if ($date->isToday()) {
                return 'Hari Ini';
            }

            if ($date->isYesterday()) {
                return 'Kemarin';
            }

            return $date->translatedFormat('d M Y');
        };
    @endphp

    <div class="staff-chat-mobile-screen">
        <section class="staff-chat-mobile-card staff-chat-thread-card">
            <header class="staff-chat-conversation-topbar">
                <a href="{{ route('staff.messages.index') }}" class="staff-chat-icon-btn" aria-label="Kembali ke daftar pesan">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M15 6L9 12L15 18" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>

                <div class="staff-chat-conversation-head is-pill">
                    <div class="staff-chat-thread-avatar-wrap is-large">
                        <div class="staff-chat-thread-avatar large">{{ $threadInitial }}</div>
                        <span class="staff-chat-thread-presence is-large"></span>
                    </div>
                    <div class="staff-chat-conversation-copy">
                        <strong>{{ $threadTitle }}</strong>
                        <span>{{ $threadTypeLabel === 'Admin' ? 'Percakapan admin' : 'Percakapan ' . strtolower($threadTypeLabel) }}</span>
                    </div>
                </div>
            </header>

            @if(session('success'))
                <div class="staff-chat-flash is-ok">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="staff-chat-flash is-err">
                    @foreach($errors->all() as $error)
                        <span>{{ $error }}</span>
                    @endforeach
                </div>
            @endif

            <div class="staff-chat-conversation" data-chat-box>
                <div class="staff-chat-thread-meta-row">
                    <span>{{ $messageCount }} pesan</span>
                    <span class="staff-chat-thread-pill">{{ $threadTypeLabel }}</span>
                    @if($threadStatus !== '')
                        <span class="staff-chat-status-chip">{{ strtoupper($threadStatus) }}</span>
                    @endif
                </div>

                @forelse($groupedMessages as $dateKey => $dayMessages)
                    <div class="staff-chat-day-pill">{{ $formatDayLabel($dateKey) }}</div>

                    @foreach($dayMessages as $message)
                        @php
                            $isMe = (string) ($message->sender_role ?? '') === 'staff';
                            $action = $message->meta['action'] ?? null;
                            $actionUrl = null;

                            if ($action) {
                                $actionType = (string) ($action['type'] ?? '');
                                $actionId = (int) ($action['id'] ?? 0);

                                if ($actionType === 'leave') {
                                    $actionUrl = route('staff.leave.index');
                                } elseif ($actionType === 'swap' && $actionId > 0) {
                                    $actionUrl = route('staff.history', ['swap_id' => $actionId]);
                                } elseif ($actionType === 'absensi' && $actionId > 0) {
                                    $actionUrl = route('staff.messages.show', ['type' => 'absensi', 'id' => $actionId]);
                                }
                            }
                        @endphp

                        <div class="staff-chat-bubble-row {{ $isMe ? 'is-me' : '' }}">
                            <div class="staff-chat-bubble {{ $isMe ? 'is-me' : 'is-them' }}">
                                <div class="staff-chat-bubble-text">{{ $message->message }}</div>

                                @if($actionUrl)
                                    <a class="staff-chat-action-link" href="{{ $actionUrl }}">Lihat detail terkait</a>
                                @endif

                                <div class="staff-chat-bubble-meta">
                                    {{ $message->created_at ? $message->created_at->format('H:i') : '' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                @empty
                    <div class="staff-chat-empty-state is-thread">
                        <strong>Belum ada pesan</strong>
                        <span>Mulai balas percakapan ini dari kolom di bawah ya.</span>
                    </div>
                @endforelse
            </div>

            <form method="post" class="staff-chat-composer" action="{{ route('staff.messages.store', ['type' => $type, 'id' => $threadId]) }}">
                @csrf
                <div class="staff-chat-compose-label">Balas Percakapan</div>
                <div class="staff-chat-compose-field">
                    <textarea name="message" rows="1" placeholder="Ketik pesan..." required oninput="autoGrowChatInput(this)"></textarea>
                </div>

                <button class="staff-chat-send-btn" type="submit" aria-label="Kirim pesan">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 11.5L19 4L14 19L11 13L4 11.5Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
                    </svg>
                </button>
            </form>
        </section>
    </div>
</div>

<script>
    function autoGrowChatInput(element) {
        if (!element) {
            return;
        }

        element.style.height = '46px';
        element.style.height = Math.min(element.scrollHeight, 120) + 'px';
    }

    const chatBox = document.querySelector('[data-chat-box]');
    if (chatBox) {
        requestAnimationFrame(function () {
            chatBox.scrollTop = chatBox.scrollHeight;
        });
    }
</script>
@endsection
