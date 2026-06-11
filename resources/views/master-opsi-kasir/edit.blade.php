@extends('layouts.app')

@section('title', 'Edit Master Opsi Kasir')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Edit Master Opsi Kasir</h1>
            <p>Perbarui nama, kode, urutan, dan daftar pilihan opsi kasir ini.</p>
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
        @php
            $opsiText = old('opsi_text', collect($item->resolvedOptions())->map(fn ($row) => $row['value'].'|'.$row['label'].'|'.(int)($row['extra_price'] ?? 0))->implode("\n"));
        @endphp
        <form method="post" action="{{ route('master_opsi_kasir.update', $item) }}" class="form-grid">
            @csrf
            @method('PUT')
            <div>
                <label>Nama Opsi</label>
                <input type="text" name="nama_opsi" value="{{ old('nama_opsi', $item->nama_opsi) }}" required>
            </div>
            <div>
                <label>Kode Opsi</label>
                <input type="text" name="kode_opsi" value="{{ old('kode_opsi', $item->kode_opsi) }}" required>
            </div>
            <div>
                <label>Urutan</label>
                <input type="number" name="urutan" min="0" value="{{ old('urutan', $item->urutan) }}">
            </div>
            <div class="full">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                    <span>Aktif</span>
                </label>
            </div>
            <div class="full">
                <label class="check">
                    <input type="checkbox" name="is_required" value="1" {{ old('is_required', $item->is_required) ? 'checked' : '' }}>
                    <span>Wajib dipilih saat produk menggunakan opsi ini</span>
                </label>
            </div>
            <div class="full">
                <label>Daftar Pilihan</label>
                <textarea name="opsi_text" rows="6" required>{{ $opsiText }}</textarea>
                <div class="hint">Format: value|Label|ExtraHarga (1 baris 1 opsi).</div>
            </div>
            <div class="actions full">
                <button class="btn-primary" type="submit">Update</button>
                <a class="btn-neutral" href="{{ route('master_opsi_kasir.index') }}">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
