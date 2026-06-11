@extends('layouts.app')

@section('title', 'Tambah Master Opsi Kasir')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Tambah Master Opsi Kasir</h1>
            <p>Tambahkan opsi pilihan dinamis yang bisa digunakan oleh produk di kasir.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-neutral" href="{{ route('master_opsi_kasir.index') }}">Kembali</a>
        </div>
    </div>

    <div class="panel form-panel">
        @if ($errors->any())
            <div class="alert err">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('master_opsi_kasir.store') }}" class="form-grid">
            @csrf
            <div>
                <label>Nama Opsi</label>
                <input type="text" name="nama_opsi" value="{{ old('nama_opsi') }}" required>
            </div>
            <div>
                <label>Kode Opsi (opsional)</label>
                <input type="text" name="kode_opsi" value="{{ old('kode_opsi') }}">
            </div>
            <div>
                <label>Urutan</label>
                <input type="number" name="urutan" min="0" value="{{ old('urutan', 0) }}">
            </div>
            <div class="full">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                    <span>Aktif</span>
                </label>
            </div>
            <div class="full">
                <label class="check">
                    <input type="checkbox" name="is_required" value="1" {{ old('is_required') ? 'checked' : '' }}>
                    <span>Wajib dipilih saat produk menggunakan opsi ini</span>
                </label>
            </div>
            <div class="full">
                <label>Daftar Pilihan</label>
                <textarea name="opsi_text" rows="6" placeholder="tanpa_saos|Tanpa Saos|0&#10;extra_saos|Extra Saos|2000" required>{{ old('opsi_text') }}</textarea>
                <div class="hint">Format: value|Label|ExtraHarga (1 baris 1 opsi).</div>
            </div>
            <div class="actions full">
                <button class="btn-primary" type="submit">Simpan</button>
                <a class="btn-neutral" href="{{ route('master_opsi_kasir.index') }}">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
