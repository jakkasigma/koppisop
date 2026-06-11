@extends('layouts.staff')

@section('title', 'Login Karyawan')

@section('content')
<div class="container">
    <div class="hero">
        <div class="hero-inner">
            <div>
                <div class="brand">
                    <div class="mark">K</div>
                    <div>
                        <h1>Portal Karyawan</h1>
                        <p>Login untuk absen masuk, melihat jadwal, dan cek riwayat absensi.</p>
                    </div>
                </div>
            </div>
            <div class="hero-actions">
                {{-- Akses absen hanya lewat Portal Karyawan setelah login. --}}
            </div>
        </div>
    </div>

    <div class="panel">
        @if($errors->any())
            <div class="alert err">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('staff.login.submit') }}">
            @csrf
            <div class="grid">
                <div class="field">
                    <label for="no_telepon">No Telepon</label>
                    <input id="no_telepon" name="no_telepon" inputmode="tel" autocomplete="tel" value="{{ old('no_telepon') }}" placeholder="08xxxxxxxxxx" autofocus>
                    <div class="hint">Gunakan nomor yang terdaftar di data karyawan.</div>
                </div>
                <div class="field">
                    <label for="pin">PIN</label>
                    <input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="one-time-code" value="{{ old('pin') }}" placeholder="4-8 angka" required>
                    <div class="hint">PIN wajib untuk login karyawan.</div>
                </div>
            </div>

            <div class="actions">
                <button class="btn-primary" type="submit">Login</button>
                {{-- Jadwal sekarang tersedia di Portal Karyawan setelah login (1 jalur). --}}
            </div>
        </form>

        <div class="help">
            <b>Catatan</b>
            <div>Jika kamu tidak bisa login, pastikan nomor telepon terdaftar dan status karyawan aktif.</div>
            <div class="row">
                <span class="pill">Login wajib sebelum absen</span>
                <span class="pill">Jadwal ada di portal setelah login</span>
            </div>
        </div>
    </div>
</div>
@endsection

