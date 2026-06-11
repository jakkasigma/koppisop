@extends('layouts.app')

@section('title', 'Pesan')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Komunikasi</div>
            <h1>Pesan</h1>
            <p>Komunikasi dengan staf terkait izin, absensi, atau tukar shift.</p>
        </div>
    </div>

    <div class="panel">
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

        <div class="chat">
            @forelse($messages as $msg)
                @php $isMe = (string) ($msg->sender_role ?? '') === 'admin'; @endphp
                <div class="bubble {{ $isMe ? 'me' : '' }}">
                    <div>{{ $msg->message }}</div>
                    <div class="meta">{{ $msg->created_at ? $msg->created_at->format('Y-m-d H:i') : '' }}</div>
                </div>
            @empty
                <div class="u-text-muted">Belum ada pesan.</div>
            @endforelse
        </div>

        <form method="post" class="compose" action="{{ route('dashboard.messages.store', ['type' => $type, 'id' => $threadId]) }}">
            @csrf
            <textarea name="message" placeholder="Tulis pesan..." required></textarea>
            <div class="actions">
                <button class="btn-primary" type="submit">Kirim</button>
            </div>
        </form>
    </div>
</div>
@endsection
