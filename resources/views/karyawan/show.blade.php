@extends('layouts.app')

@section('title', 'Detail Karyawan')

@section('content')
@php
    $nama = trim((string) ($karyawan->nama_karyawan ?? ''));
    $jabatan = trim((string) ($karyawan->jabatan ?? ''));
    $telepon = trim((string) ($karyawan->no_telepon ?? ''));
    $alamat = trim((string) ($karyawan->alamat ?? ''));
    $employmentTypeLabel = method_exists($karyawan, 'employmentTypeLabel') ? $karyawan->employmentTypeLabel() : 'Full Time';
    $employmentDuration = method_exists($karyawan, 'employmentDurationLabel') ? $karyawan->employmentDurationLabel() : '8 jam';
    $salaryScheme = method_exists($karyawan, 'salarySchemeLabel') ? $karyawan->salarySchemeLabel() : 'Bulanan';
    $baseSalary = method_exists($karyawan, 'baseSalaryLabel') ? $karyawan->baseSalaryLabel() : 'Rp 0 / bulan';
    $photoUrl = method_exists($karyawan, 'profilePhotoUrl') ? $karyawan->profilePhotoUrl() : null;
    $staffId = (int) ($karyawan->id_karyawan ?? 0);
    $staffCode = 'ST-' . str_pad((string) $staffId, 5, '0', STR_PAD_LEFT);
    $initials = collect(explode(' ', $nama))
        ->filter()
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $profileChecks = [
        'Telepon' => $telepon !== '',
        'Alamat' => $alamat !== '',
        'Foto Profil' => trim((string) ($karyawan->foto_profil_path ?? '')) !== '',
        'PIN Portal' => ! empty($karyawan->pin_digest),
    ];
    $profileReadyCount = collect($profileChecks)->filter()->count();
    $profileReadyTotal = count($profileChecks);
    $profilePercent = $profileReadyTotal > 0 ? (int) round(($profileReadyCount / $profileReadyTotal) * 100) : 0;
    $detailCards = [
        ['label' => 'Nomor Telepon', 'value' => $telepon !== '' ? $telepon : 'Belum diisi', 'sub' => 'Dipakai untuk login dan kontak staf.'],
        ['label' => 'Alamat', 'value' => $alamat !== '' ? $alamat : 'Belum diisi', 'sub' => 'Data domisili yang dilengkapi sendiri oleh staf.'],
        ['label' => 'Skema Gaji', 'value' => $salaryScheme, 'sub' => $baseSalary],
        ['label' => 'Status Portal', 'value' => ! empty($karyawan->pin_digest) ? 'Siap digunakan' : 'Belum siap', 'sub' => ! empty($karyawan->pin_digest) ? 'PIN sudah diset untuk login staf.' : 'PIN masih perlu dibuat admin.'],
        ['label' => 'Tanggal Dibuat', 'value' => optional($karyawan->created_at)->format('d M Y') ?? '-', 'sub' => 'Pertama kali masuk ke sistem.'],
        ['label' => 'Terakhir Diubah', 'value' => optional($karyawan->updated_at)->format('d M Y') ?? '-', 'sub' => 'Update terbaru pada profil ini.'],
    ];
@endphp

<div class="container">
    <div class="admin-page-head">
    <div>
        <div>
            <h1>Detail Karyawan</h1>
            <p class="sub">Lihat identitas staf, kelengkapan profil, dan kesiapan akun portal dalam satu halaman.</p>
            
        </div>
        <div class="right">
            <a class="btn-ghost" href="{{ route('karyawan.edit', $karyawan) }}">Edit</a>
            <a class="btn-ghost" href="{{ route('karyawan.index') }}">Kembali</a>
        </div>
    </div>

    <div class="panel karyawan-profile-panel">
        <section class="karyawan-profile-spotlight">
            <div class="karyawan-profile-identity">
                <div class="karyawan-profile-avatar-shell">
                    <div class="karyawan-profile-avatar">
                        @if($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $nama }}">
                        @else
                            <span>{{ $initials !== '' ? $initials : 'NA' }}</span>
                        @endif
                    </div>
                </div>
                <div class="karyawan-profile-copy">
                    <div class="karyawan-profile-code">{{ $staffCode }}</div>
                    <div class="karyawan-profile-name">{{ $nama !== '' ? $nama : 'Tanpa Nama' }}</div>
                    <div class="karyawan-profile-role">{{ $jabatan !== '' ? $jabatan : 'Jabatan belum diisi' }}</div>
                    <div class="karyawan-profile-badges">
                        <span class="pill gray">{{ $employmentTypeLabel }}</span>
                        <span class="pill neu">{{ $employmentDuration }}</span>
                        <span class="pill mid">{{ $salaryScheme }}</span>
                        @if((int) ($karyawan->is_active ?? 1) === 1)
                            <span class="pill ok">Aktif</span>
                        @else
                            <span class="pill off">Nonaktif</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="karyawan-profile-summary">
                <article class="karyawan-profile-summary-card is-accent">
                    <span class="label">Kelengkapan Profil</span>
                    <strong>{{ $profilePercent }}%</strong>
                    <small>{{ $profileReadyCount }} dari {{ $profileReadyTotal }} komponen sudah siap.</small>
                </article>
                <article class="karyawan-profile-summary-card">
                    <span class="label">Akun Portal</span>
                    <strong>{{ ! empty($karyawan->pin_digest) ? 'Aktif' : 'Perlu PIN' }}</strong>
                    <small>{{ ! empty($karyawan->pin_digest) ? 'Staf bisa login ke portal.' : 'Siapkan PIN agar staf bisa masuk.' }}</small>
                </article>
                <article class="karyawan-profile-summary-card">
                    <span class="label">Kontak Staf</span>
                    <strong>{{ $telepon !== '' ? $telepon : 'Belum diisi' }}</strong>
                    <small>{{ $alamat !== '' ? 'Alamat sudah tersedia.' : 'Alamat masih kosong.' }}</small>
                </article>
            </div>
        </section>

        <section class="karyawan-profile-checklist">
            @foreach($profileChecks as $label => $isReady)
                <article class="karyawan-profile-check {{ $isReady ? 'is-ready' : 'is-pending' }}">
                    <span>{{ $label }}</span>
                    <strong>{{ $isReady ? 'Sudah ada' : 'Belum ada' }}</strong>
                </article>
            @endforeach
        </section>

        <section class="karyawan-profile-grid">
            @foreach($detailCards as $card)
                <article class="karyawan-profile-card">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value">{{ $card['value'] }}</div>
                    <div class="sub">{{ $card['sub'] }}</div>
                </article>
            @endforeach
        </section>
    </div>
</div>
@endsection
