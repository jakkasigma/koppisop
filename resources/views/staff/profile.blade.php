@extends('layouts.staff')

@section('title', 'Profil Saya')

@section('content')
<div class="container app-shell">
    @php
        $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
        $nama = (string) ($staffKaryawan->nama_karyawan ?? 'Karyawan');
        $jabatan = trim((string) ($staffKaryawan->jabatan ?? 'Staff')) ?: 'Staff';
        $inisial = trim(mb_substr($nama, 0, 1));
        if (str_contains($nama, ' ')) {
            $parts = array_values(array_filter(explode(' ', $nama)));
            if (count($parts) > 1) {
                $inisial = mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1);
            }
        }

        $staffId = (int) ($staffKaryawan->id_karyawan ?? 0);
        $staffCode = trim((string) ($staffKaryawan->kode_karyawan ?? ''));
        if ($staffCode === '' && $staffId > 0) {
            $staffCode = 'ST-' . str_pad((string) $staffId, 5, '0', STR_PAD_LEFT);
        }

        $telepon = trim((string) ($staffKaryawan->no_telepon ?? ''));
        $alamat = trim((string) ($staffKaryawan->alamat ?? ''));
        $tipeKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
        $durasiKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentDurationLabel')) ? $staffKaryawan->employmentDurationLabel() : '8 jam';
        $employmentType = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeValue')) ? $staffKaryawan->employmentTypeValue() : ($staffKaryawan->employment_type ?? null);
        $statusAkun = (int) ($staffKaryawan->is_active ?? 0) === 1 ? 'Aktif' : 'Nonaktif';
        $statusAkunDisplay = $statusAkun === 'Aktif' ? 'Terverifikasi' : 'Nonaktif';

        $shiftHariIni = $jadwalHariIni
            ? ($setting->shiftCodeFor((int) ($jadwalHariIni->shift_ke ?? 0), $employmentType) . ' • ' . $setting->shiftRangeLabel((int) ($jadwalHariIni->shift_ke ?? 0), $employmentType, $today))
            : 'Tidak dijadwalkan';

        $rawStatusAbsen = strtolower(trim((string) ($absenHariIni->status ?? '')));
        $statusAbsen = match ($rawStatusAbsen) {
            'hadir' => 'Sudah Absen',
            'pending' => 'Menunggu Verifikasi',
            'alpa' => 'Alpa',
            default => $absenHariIni ? 'Sudah Absen' : 'Belum Absen',
        };

        $photoUrl = $staffKaryawan && method_exists($staffKaryawan, 'profilePhotoUrl') ? $staffKaryawan->profilePhotoUrl() : null;

        $infoRows = [
            ['label' => 'Nama Lengkap', 'value' => $nama],
            ['label' => 'Posisi', 'value' => $jabatan],
            ['label' => 'Tipe Kerja', 'value' => $tipeKerja],
            ['label' => 'Durasi Shift', 'value' => $durasiKerja . ' / Hari'],
            ['label' => 'Status Akun', 'value' => $statusAkunDisplay],
            ['label' => 'Alamat', 'value' => $alamat !== '' ? $alamat : 'Belum diisi'],
        ];
    @endphp

    <div class="staff-profile-app">
        <div class="staff-profile-topbar">
            <a class="staff-profile-topbar-btn" href="{{ route('staff.home') }}" aria-label="Kembali ke beranda">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M14.5 6.5 9 12l5.5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <strong>Profil Saya</strong>
            <a class="staff-profile-topbar-btn is-accent" href="{{ route('staff.profile.edit') }}" aria-label="Buka pengaturan profil">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 8.7a3.3 3.3 0 1 1 0 6.6a3.3 3.3 0 0 1 0-6.6Z" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M19 12a7 7 0 0 0-.1-1.1l1.7-1.3l-1.8-3.1l-2 .7a7.7 7.7 0 0 0-1.9-1.1l-.3-2.1H9.4l-.3 2.1a7.7 7.7 0 0 0-1.9 1.1l-2-.7l-1.8 3.1l1.7 1.3A7 7 0 0 0 5 12c0 .37.03.74.1 1.1l-1.7 1.3l1.8 3.1l2-.7c.58.47 1.22.84 1.9 1.1l.3 2.1h5.2l.3-2.1c.68-.26 1.32-.63 1.9-1.1l2 .7l1.8-3.1l-1.7-1.3c.07-.36.1-.73.1-1.1Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>

        @if (session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert bad">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="staff-profile-hero-card">
            <div class="staff-profile-avatar-wrap">
                <div class="staff-profile-avatar-ring">
                    <div class="staff-profile-avatar">
                        @if($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $nama }}">
                        @else
                            {{ strtoupper($inisial !== '' ? $inisial : 'S') }}
                        @endif
                    </div>
                </div>
                <span class="staff-profile-avatar-status" aria-hidden="true"></span>
            </div>

            <div class="staff-profile-hero-copy">
                <h1>{{ $nama }}</h1>
                <p>{{ $jabatan }}</p>
            </div>

            <div class="staff-profile-chip-row">
                <span class="staff-profile-chip is-highlight">{{ strtoupper($tipeKerja) }}</span>
                <span class="staff-profile-chip">{{ strtoupper($statusAkun) }}</span>
            </div>
        </section>

        <section class="staff-profile-summary-grid">
            <article class="staff-profile-summary-item">
                <span>Shift Hari Ini</span>
                <strong>{{ $shiftHariIni }}</strong>
            </article>
            <article class="staff-profile-summary-item">
                <span>Status Absen</span>
                <strong class="{{ $statusAbsen === 'Belum Absen' || $statusAbsen === 'Alpa' ? 'is-danger' : 'is-accent' }}">{{ $statusAbsen }}</strong>
            </article>
            <article class="staff-profile-summary-item">
                <span>Telepon</span>
                <strong>{{ $telepon !== '' ? $telepon : 'Belum diisi' }}</strong>
            </article>
            <article class="staff-profile-summary-item">
                <span>ID Staff</span>
                <strong>{{ $staffCode !== '' ? $staffCode : '-' }}</strong>
            </article>
        </section>

        <section class="staff-profile-info-list">
            @foreach($infoRows as $row)
                <article class="staff-profile-info-row">
                    <div class="staff-profile-info-copy">
                        <span>{{ $row['label'] }}</span>
                        <strong>{{ $row['value'] }}</strong>
                    </div>
                    <span class="staff-profile-info-arrow" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M10 7.5 14.5 12 10 16.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </article>
            @endforeach
        </section>

        <div class="staff-profile-footer-actions">
            <a class="staff-profile-footer-btn" href="{{ route('staff.messages.index') }}">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M7 18.5 4.8 20V7.8A2.8 2.8 0 0 1 7.6 5h8.8a2.8 2.8 0 0 1 2.8 2.8v6.4a2.8 2.8 0 0 1-2.8 2.8H7Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="M8.5 9.5h7M8.5 13h4.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                <span>Hubungi Admin</span>
            </a>
            <form method="post" action="{{ route('staff.logout') }}">
                @csrf
                <button class="staff-profile-footer-btn is-soft" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M10 7V5.5A1.5 1.5 0 0 1 11.5 4h5A1.5 1.5 0 0 1 18 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-5A1.5 1.5 0 0 1 10 18.5V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M6 12h9M11.5 8.5 15 12l-3.5 3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
