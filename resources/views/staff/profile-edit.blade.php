@extends('layouts.staff')

@section('title', 'Edit Profil')

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

        $telepon = trim((string) ($staffKaryawan->no_telepon ?? ''));
        $alamat = trim((string) ($staffKaryawan->alamat ?? ''));
        $tipeKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
        $photoUrl = $staffKaryawan && method_exists($staffKaryawan, 'profilePhotoUrl') ? $staffKaryawan->profilePhotoUrl() : null;
    @endphp

    <div class="staff-profile-app staff-profile-edit-app">
        <div class="staff-profile-topbar">
            <a class="staff-profile-topbar-btn" href="{{ route('staff.profile') }}" aria-label="Kembali ke profil">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M14.5 6.5 9 12l5.5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <strong>Edit Profil</strong>
            <span class="staff-profile-topbar-btn is-passive" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M5 18.5h3.2l8.9-8.9a1.8 1.8 0 0 0 0-2.6l-.9-.9a1.8 1.8 0 0 0-2.6 0L4.7 15v3.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                    <path d="m12.6 7 4.4 4.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </span>
        </div>

        @if ($errors->any())
            <div class="alert bad">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="staff-profile-edit-hero">
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
            <div class="staff-profile-edit-copy">
                <h1>{{ $nama }}</h1>
                <p>{{ $jabatan }} • {{ $tipeKerja }}</p>
                <small>Nama, jabatan, dan tipe kerja tetap mengikuti data dari admin.</small>
            </div>
        </section>

        <form method="post" action="{{ route('staff.profile.update') }}" enctype="multipart/form-data" class="staff-profile-edit-form-card">
            @csrf

            <label class="staff-profile-edit-field">
                <span>No. Telepon</span>
                <input type="text" name="no_telepon" value="{{ old('no_telepon', $telepon) }}" placeholder="08xxxxxxxxxx" required>
                <small>Nomor ini dipakai untuk login ke portal staf.</small>
                @error('no_telepon')
                    <small class="error-text">{{ $message }}</small>
                @enderror
            </label>

            <label class="staff-profile-edit-field">
                <span>Alamat</span>
                <textarea name="alamat" rows="4" placeholder="Masukkan alamat rumah atau domisili">{{ old('alamat', $alamat) }}</textarea>
                <small>Boleh dikosongkan kalau belum ingin diisi.</small>
                @error('alamat')
                    <small class="error-text">{{ $message }}</small>
                @enderror
            </label>

            <label class="staff-profile-edit-field">
                <span>Foto Profil</span>
                <input type="file" name="foto_profil" accept="image/*">
                <small>Maksimal 3MB. Gunakan foto persegi agar hasil avatar lebih rapi.</small>
                @error('foto_profil')
                    <small class="error-text">{{ $message }}</small>
                @enderror
            </label>

            <div class="staff-profile-edit-actions">
                <button class="btn-primary" type="submit">Simpan Profil</button>
                <a class="btn-neutral" href="{{ route('staff.profile') }}">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
