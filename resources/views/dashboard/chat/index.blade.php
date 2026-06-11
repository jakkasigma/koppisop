@extends('layouts.app')

@section('title', 'Chat Karyawan')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Komunikasi</div>
            <h1>Chat Karyawan</h1>
            <p>Pilih staf, baca pesan masuk, dan balas percakapan internal dari satu layar.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip soft">{{ number_format($karyawan->count(), 0, ',', '.') }} staf</span>
        </div>
    </div>

    <div class="wa-shell">
        <div class="wa-left">
            <div class="wa-left-head">
                <div>
                    <div class="wa-left-title">Inbox Staf</div>
                    <div class="wa-left-sub">Percakapan admin dan karyawan</div>
                </div>
            </div>
            <div class="wa-search">
                <input type="text" placeholder="Cari karyawan..." oninput="filterChatList(this.value)">
            </div>
            <div class="wa-list" id="chatList">
                @forelse($karyawan as $k)
                    @php
                        $initials = strtoupper(mb_substr((string) $k->nama_karyawan, 0, 1));
                        $key = 'admin_chat:' . (int) $k->id_karyawan;
                        $last = $lastMessages[$key] ?? null;
                        $unread = (int) ($unreadCounts[$key] ?? 0);
                    @endphp
                    <a class="wa-item" href="{{ route('dashboard.chat.show', $k) }}" data-name="{{ strtolower($k->nama_karyawan) }}">
                        <div class="wa-avatar">{{ $initials }}</div>
                        <div class="wa-meta">
                            <div class="wa-name">{{ $k->nama_karyawan }}</div>
                            <div class="wa-role">{{ $k->jabatan ?? 'Staff' }}</div>
                            @if($last)
                                <div class="wa-preview">{{ \Illuminate\Support\Str::limit($last->message, 60) }}</div>
                            @endif
                        </div>
                        @if($unread > 0)
                            <div class="unread-badge">{{ $unread }}</div>
                        @endif
                    </a>
                @empty
                    <div class="u-p-14 u-text-muted">Belum ada karyawan.</div>
                @endforelse
            </div>
        </div>
        <div class="wa-right">
            <div class="wa-right-head">
                <div class="wa-left-title">Pilih karyawan</div>
            </div>
            <div class="wa-empty">Pilih karyawan di sebelah kiri untuk mulai chat.</div>
        </div>
    </div>
</div>
<script>
    function filterChatList(value) {
        var term = (value || '').toLowerCase();
        document.querySelectorAll('#chatList .wa-item').forEach(function (el) {
            var name = el.getAttribute('data-name') || '';
            el.style.display = name.includes(term) ? '' : 'none';
        });
    }
</script>
@endsection
