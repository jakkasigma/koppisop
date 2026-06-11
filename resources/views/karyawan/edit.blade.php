@extends('layouts.app')

@section('title', 'Edit Karyawan')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <div>
            <h1>Edit Karyawan</h1>
            <p class="sub">Perbarui data karyawan sesuai perubahan operasional.</p>
            
        </div>
        <div class="right">
            <a class="btn-ghost" href="{{ route('karyawan.show', $karyawan) }}">Detail</a>
            <a class="btn-ghost" href="{{ route('karyawan.index') }}">Kembali</a>
        </div>
    </div>
    </div>

    <div class="panel form-panel">
        @if($errors->any())
            <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <form method="post" action="{{ route('karyawan.update', $karyawan) }}" class="form-grid">
            @csrf
            @method('PUT')
            <div class="field full">
                <label>Nama Karyawan</label>
                <input type="text" name="nama_karyawan" value="{{ old('nama_karyawan', $karyawan->nama_karyawan) }}" required>
            </div>
            <div class="field">
                <label>Jabatan</label>
                <input type="text" name="jabatan" value="{{ old('jabatan', $karyawan->jabatan) }}" placeholder="Kasir / Barista / Kitchen">
            </div>
            <div class="field">
                <label>No Telepon</label>
                <input type="text" name="no_telepon" value="{{ old('no_telepon', $karyawan->no_telepon) }}" placeholder="08xxxxxxxxxx">
                <div class="hint">Disarankan isi angka saja supaya login portal lebih mudah.</div>
            </div>
            <div class="field">
                <label>Tipe Kerja</label>
                <select name="employment_type" required>
                    @foreach(\App\Models\Karyawan::employmentTypeOptions() as $value => $option)
                        <option value="{{ $value }}" @selected(old('employment_type', method_exists($karyawan, 'employmentTypeValue') ? $karyawan->employmentTypeValue() : \App\Models\Karyawan::EMPLOYMENT_FULL_TIME) === $value)>
                            {{ $option['label'] }} ({{ $option['duration_label'] }})
                        </option>
                    @endforeach
                </select>
                <div class="hint">Gunakan Part Time untuk shift 4,5 jam dan Full Time untuk shift 8 jam.</div>
            </div>
            <div class="field">
                <label>Gaji Bulanan</label>
                <input type="number" min="0" step="1000" name="monthly_salary" value="{{ old('monthly_salary', (int) ($karyawan->monthly_salary ?? 0) ?: '') }}" placeholder="Contoh: 2800000">
                <div class="hint">Isi untuk Full Time. Jika dikosongkan, slip full time akan bernilai Rp 0.</div>
            </div>
            <div class="field">
                <label>Tarif per Jam</label>
                <input type="number" min="0" step="500" name="hourly_rate" value="{{ old('hourly_rate', (int) ($karyawan->hourly_rate ?? 0) ?: '') }}" placeholder="Contoh: 20000">
                <div class="hint">Isi untuk Part Time. Slip part time dihitung dari total jam kerja.</div>
            </div>
            <div class="field full">
                <label>PIN Portal (4-8 angka)</label>
                <input
                    type="password"
                    inputmode="numeric"
                    name="pin"
                    value="{{ old('pin') }}"
                    placeholder="{{ empty($karyawan->pin_digest) ? 'Wajib diisi (akun ini belum punya PIN).' : 'Isi untuk ganti PIN. Kosongkan untuk tetap.' }}"
                    @if(empty($karyawan->pin_digest)) required @endif
                >
                <div class="hint">PIN wajib untuk login portal. Kosongkan hanya jika tidak ingin mengganti PIN.</div>
            </div>
            <div class="full">
                <label>Status</label>
                <div class="checkline">
                    <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', (int) ($karyawan->is_active ?? 1) === 1))>
                    <div>
                        <div class="u-font-900 u-text-ink">Karyawan Aktif</div>
                        <div class="hint u-mt-2">Nonaktifkan jika karyawan sudah tidak bekerja.</div>
                    </div>
                </div>
            </div>
            <div class="actions full">
                <button class="btn-primary" type="submit">Update</button>
                <a class="btn-neutral" href="{{ route('karyawan.index') }}">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection

