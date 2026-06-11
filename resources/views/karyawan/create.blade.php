@extends('layouts.app')

@section('title', 'Tambah Karyawan')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <div>
            <h1>Tambah Karyawan</h1>
            <p class="sub">Tambahkan data petugas kasir dan staf operasional.</p>
            
        </div>
        <div class="right">
            <a class="btn-ghost" href="{{ route('karyawan.index') }}">Kembali</a>
        </div>
    </div>
    </div>

    <div class="panel form-panel">
        @if($errors->any())
            <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <form method="post" action="{{ route('karyawan.store') }}" class="form-grid">
            @csrf
            <div class="field full">
                <label>Nama Karyawan</label>
                <input type="text" name="nama_karyawan" value="{{ old('nama_karyawan') }}" required>
            </div>
            <div class="field">
                <label>Jabatan</label>
                <input type="text" name="jabatan" value="{{ old('jabatan') }}" placeholder="Kasir / Barista / Kitchen">
            </div>
            <div class="field">
                <label>No Telepon</label>
                <input type="text" name="no_telepon" value="{{ old('no_telepon') }}" placeholder="08xxxxxxxxxx">
                <div class="hint">Disarankan isi angka saja supaya login portal lebih mudah.</div>
            </div>
            <div class="field">
                <label>Tipe Kerja</label>
                <select name="employment_type" required>
                    @foreach(\App\Models\Karyawan::employmentTypeOptions() as $value => $option)
                        <option value="{{ $value }}" @selected(old('employment_type', \App\Models\Karyawan::EMPLOYMENT_FULL_TIME) === $value)>
                            {{ $option['label'] }} ({{ $option['duration_label'] }})
                        </option>
                    @endforeach
                </select>
                <div class="hint">Full Time bekerja 8 jam per shift, Part Time bekerja 4,5 jam per shift.</div>
            </div>
            <div class="field">
                <label>Gaji Bulanan</label>
                <input type="number" min="0" step="1000" name="monthly_salary" value="{{ old('monthly_salary') }}" placeholder="Contoh: 2800000">
                <div class="hint">Isi untuk karyawan Full Time. Dipakai sebagai gaji tetap per bulan.</div>
            </div>
            <div class="field">
                <label>Tarif per Jam</label>
                <input type="number" min="0" step="500" name="hourly_rate" value="{{ old('hourly_rate') }}" placeholder="Contoh: 20000">
                <div class="hint">Isi untuk karyawan Part Time. Gaji akan dihitung dari jam kerja terbayar.</div>
            </div>
            <div class="field full">
                <label>PIN Portal (4-8 angka)</label>
                <input type="password" inputmode="numeric" name="pin" value="{{ old('pin') }}" placeholder="4-8 angka" required>
                <div class="hint">PIN wajib. Digunakan untuk login portal dan absensi.</div>
            </div>
            <div class="full">
                <label>Status</label>
                <div class="checkline">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')>
                    <div>
                        <div class="u-font-900 u-text-ink">Karyawan Aktif</div>
                        <div class="hint u-mt-2">Jika nonaktif, tidak muncul di absensi/jadwal publik dan tidak bisa login portal.</div>
                    </div>
                </div>
            </div>
            <div class="actions full">
                <button class="btn-primary" type="submit">Simpan</button>
                <a class="btn-neutral" href="{{ route('karyawan.index') }}">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection

