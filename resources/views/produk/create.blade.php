@extends('layouts.app')

@section('title', 'Tambah Produk')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <h1>Tambah Produk</h1>
        <p class="hero-sub">Tambah produk baru agar langsung muncul di kasir dan laporan.</p>
        
    </div>
    </div>

    <div class="panel form-panel">
        <p class="sub">Isi detail produk baru yang akan muncul di kasir.</p>
        @if ($errors->any())
            <div class="alert err">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('produk.store') }}" class="form-grid">
            @csrf
            <div class="full section-title">Informasi Produk</div>
            <div class="full">
                <label>Nama Produk</label>
                <input type="text" name="nama_produk" value="{{ old('nama_produk') }}" required>
            </div>

            <div>
                <label>Kategori</label>
                <select name="id_kategori" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($kategori as $item)
                        <option value="{{ $item->id_kategori }}" @selected(old('id_kategori') == $item->id_kategori)>{{ $item->nama_kategori }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Harga</label>
                <input type="number" name="harga" value="{{ old('harga') }}" min="0" step="0.01" required>
            </div>

            <div>
                <label>Stok</label>
                <input type="number" name="stok" value="{{ old('stok') }}" min="0" required>
            </div>

            <div class="full">
                <label>Deskripsi</label>
                <textarea name="deskripsi" rows="3">{{ old('deskripsi') }}</textarea>
            </div>

            <div class="full">
                <label>Pengaturan Opsi Kasir</label>
                <div class="check-grid">
                    <label class="check">
                        <input type="checkbox" name="is_temperature_enabled" value="1" {{ old('is_temperature_enabled') ? 'checked' : '' }}>
                        <span>Aktifkan pilihan suhu (Es / Less Es / Hot)</span>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="is_sugar_enabled" value="1" {{ old('is_sugar_enabled') ? 'checked' : '' }}>
                        <span>Aktifkan pilihan gula (No Sugar / Less Sugar / Normal)</span>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="is_cup_size_enabled" value="1" {{ old('is_cup_size_enabled') ? 'checked' : '' }}>
                        <span>Aktifkan pilihan cup (Regular / Large)</span>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="is_spicy_enabled" value="1" {{ old('is_spicy_enabled') ? 'checked' : '' }}>
                        <span>Aktifkan pilihan pedas (Non Spicy / Spicy / Extra Spicy)</span>
                    </label>
                    @foreach($masterOpsiKasir as $opsi)
                        <label class="check">
                            <input type="checkbox" name="master_opsi[]" value="{{ $opsi->kode_opsi }}" @checked(in_array($opsi->kode_opsi, old('master_opsi', []), true))>
                            <span>{{ $opsi->nama_opsi }}{{ $opsi->is_required ? ' (Wajib pilih)' : '' }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="actions full">
                <button class="btn-primary" type="submit">Simpan</button>
                <a class="btn-neutral" href="{{ route('produk.index') }}">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
