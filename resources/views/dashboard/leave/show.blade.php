@extends('layouts.app')

@section('title', 'Detail Izin')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Persetujuan</div>
            <h1>Detail Izin & Sakit</h1>
            <p>{{ $leave->karyawan?->nama_karyawan ?? '-' }} - {{ strtoupper((string) $leave->jenis) }}</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-neutral" href="{{ route('dashboard.leave.index') }}">Kembali</a>
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

        <div class="info">
            <div class="admin-soft-card">
                <div class="label">Periode</div>
                <div class="value">{{ $leave->tanggal_awal?->format('Y-m-d') ?? '-' }} s/d {{ $leave->tanggal_akhir?->format('Y-m-d') ?? '-' }}</div>
            </div>
            <div class="admin-soft-card">
                <div class="label">Status</div>
                @php
                    $st = (string) ($leave->status ?? 'pending');
                    $cls = $st === 'approved' ? 'ok' : ($st === 'rejected' ? 'bad' : 'warn');
                @endphp
                <div class="value"><span class="pill {{ $cls }}">{{ strtoupper($st) }}</span></div>
            </div>
            <div class="admin-soft-card u-grid-span-full">
                <div class="label">Alasan</div>
                <div class="value">{{ $leave->alasan ?? '-' }}</div>
            </div>
            @if($leave->bukti_path)
                <div class="admin-soft-card u-grid-span-full">
                    <div class="label">Bukti</div>
                    <div class="value"><a class="btn-neutral btn-mini" href="{{ asset('storage/' . $leave->bukti_path) }}" target="_blank" rel="noopener">Lihat Bukti</a></div>
                </div>
            @endif
        </div>

        <div class="actions">
            @if($leave->status === 'pending')
                <form method="post" action="{{ route('dashboard.leave.approve', $leave) }}">
                    @csrf
                    <input type="text" name="note" placeholder="Catatan (opsional)" class="u-input-lg">
                    <button class="btn-primary" type="submit">Approve</button>
                </form>
                <form method="post" action="{{ route('dashboard.leave.reject', $leave) }}">
                    @csrf
                    <input type="text" name="note" placeholder="Alasan penolakan" required class="u-input-lg">
                    <button class="btn-danger" type="submit">Reject</button>
                </form>
            @endif
        </div>
    </div>

    <div class="panel">
        <h3 class="u-m-0 u-mb-8">Pesan</h3>
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
        <form method="post" class="compose" action="{{ route('dashboard.leave.message', $leave) }}">
            @csrf
            <textarea name="message" placeholder="Tulis pesan..." required></textarea>
            <div class="actions">
                <button class="btn-primary" type="submit">Kirim</button>
            </div>
        </form>
    </div>
</div>
@endsection
