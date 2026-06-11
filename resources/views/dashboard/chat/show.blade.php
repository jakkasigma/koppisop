@extends('layouts.app')

@section('title', 'Chat Karyawan')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Komunikasi</div>
            <h1>Chat: {{ $karyawan->nama_karyawan }}</h1>
            <p>{{ $karyawan->jabatan ?? 'Staff' }}</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-neutral" href="{{ route('dashboard.chat.index') }}">Kembali</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="wa-shell">
        <div class="wa-left">
            <div class="wa-left-head">
                <div class="wa-left-title">Chat Karyawan</div>
            </div>
            <div class="wa-search">
                <input type="text" placeholder="Cari karyawan..." oninput="filterChatList(this.value)">
            </div>
            <div class="wa-list" id="chatList">
                @foreach($karyawanList as $k)
                    @php
                        $initials = strtoupper(mb_substr((string) $k->nama_karyawan, 0, 1));
                        $key = 'admin_chat:' . (int) $k->id_karyawan;
                        $last = $lastMessages[$key] ?? null;
                        $unread = (int) ($unreadCounts[$key] ?? 0);
                    @endphp
                    <a class="wa-item {{ (int) $k->id_karyawan === (int) $karyawan->id_karyawan ? 'active' : '' }}" href="{{ route('dashboard.chat.show', $k) }}" data-name="{{ strtolower($k->nama_karyawan) }}">
                        <div class="wa-avatar">{{ $initials }}</div>
                        <div class="wa-meta">
                            <div class="wa-name">{{ $k->nama_karyawan }}</div>
                            <div class="wa-role">{{ $k->jabatan ?? 'Staff' }}</div>
                            @if($last)
                                <div class="wa-preview">{{ \Illuminate\Support\Str::limit($last->message, 60) }}</div>
                            @endif
                        </div>
                        @if($unread > 0 && (int) $k->id_karyawan !== (int) $karyawan->id_karyawan)
                            <div class="unread-badge">{{ $unread }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
        <div class="wa-right">
            <div class="wa-right-head">
                <div class="wa-avatar">{{ strtoupper(mb_substr((string) $karyawan->nama_karyawan, 0, 1)) }}</div>
                <div>
                    <div class="title">{{ $karyawan->nama_karyawan }}</div>
                    <div class="wa-role">{{ $karyawan->jabatan ?? 'Staff' }}</div>
                </div>
            </div>
            <div class="chat">
                @forelse($messages as $msg)
                    @php $isMe = (string) ($msg->sender_role ?? '') === 'admin'; @endphp
                <div class="bubble {{ $isMe ? 'me' : '' }}">
                    <div>{{ $msg->message }}</div>
                    @php $action = $msg->meta['action'] ?? null; @endphp
                    @if($action)
                        @php
                            $actionType = (string) ($action['type'] ?? '');
                            $actionId = (int) ($action['id'] ?? 0);
                            $actionUrl = null;
                            if ($actionType === 'leave' && $actionId > 0) {
                                $status = $leaveStatusMap[$actionId] ?? 'pending';
                                $actionUrl = route('dashboard.leave.index', ['status' => $status, 'leave_id' => $actionId]);
                            } elseif ($actionType === 'swap') {
                                $status = $swapStatusMap[$actionId] ?? 'pending';
                                $actionUrl = route('dashboard.jadwal.swap_requests', ['status' => $status, 'swap_id' => $actionId]);
                            } elseif ($actionType === 'absensi') {
                                $actionUrl = route('dashboard.absensi', ['absensi_id' => $actionId]);
                            }
                        @endphp
                        @if($actionUrl)
                            <a class="msg-action" href="{{ $actionUrl }}">Buka Detail</a>
                        @endif
                    @endif
                    <div class="meta">{{ $msg->created_at ? $msg->created_at->format('Y-m-d H:i') : '' }}</div>
                </div>
                @empty
                    <div class="u-text-muted">Belum ada pesan.</div>
                @endforelse
            </div>
            <form method="post" class="compose" action="{{ route('dashboard.chat.store', $karyawan) }}">
                @csrf
                <textarea name="message" placeholder="Tulis pesan..." required></textarea>
                <button class="btn-primary" type="submit">Kirim</button>
            </form>
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

    const chatBox = document.querySelector('.chat');
    if (chatBox) {
        requestAnimationFrame(() => {
            chatBox.scrollTop = chatBox.scrollHeight;
        });
    }
</script>
@endsection
