@extends('layouts.app')

@section('title', 'Edit Produk')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <h1>Edit Produk</h1>
        <p class="hero-sub">Perbarui detail produk agar sinkron dengan kasir dan stok.</p>
        
    </div>
    </div>

    <div class="panel form-panel">
        <p class="sub">Perbarui informasi produk sesuai kebutuhan operasional.</p>
        @if ($errors->any())
            <div class="alert err">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @php
            $selectedMaster = collect($produk->resolvedCustomOptionGroups())->pluck('id')->all();
            $selectedMasterOld = old('master_opsi', $selectedMaster);
        @endphp

        <form method="post" action="{{ route('produk.update', $produk) }}" class="form-grid">
            @csrf
            @method('PUT')
            <div class="full section-title">Informasi Produk</div>

            <div class="full">
                <label>Nama Produk</label>
                <input type="text" name="nama_produk" value="{{ old('nama_produk', $produk->nama_produk) }}" required>
            </div>

            <div class="full">
                <label>Kategori</label>
                <select name="id_kategori" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($kategori as $item)
                        <option value="{{ $item->id_kategori }}" @selected(old('id_kategori', $produk->id_kategori) == $item->id_kategori)>{{ $item->nama_kategori }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Harga</label>
                <input type="number" name="harga" value="{{ old('harga', $produk->harga) }}" min="0" step="0.01" required>
            </div>

            <div>
                <label>Stok</label>
                <input type="number" name="stok" value="{{ old('stok', $produk->stok) }}" min="0" required>
            </div>

            <div class="full">
                <label>Deskripsi</label>
                <textarea name="deskripsi" rows="4">{{ old('deskripsi', $produk->deskripsi) }}</textarea>
            </div>

            <div class="full">
                <label>Pengaturan Opsi Kasir</label>
                <div class="check-grid">
                    <label class="check">
                        <input type="checkbox" name="is_temperature_enabled" value="1" {{ old('is_temperature_enabled', $produk->is_temperature_enabled) ? 'checked' : '' }}>
                        <span>Aktifkan pilihan suhu (Es / Less Es / Hot)</span>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="is_sugar_enabled" value="1" {{ old('is_sugar_enabled', $produk->is_sugar_enabled) ? 'checked' : '' }}>
                        <span>Aktifkan pilihan gula (No Sugar / Less Sugar / Normal)</span>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="is_cup_size_enabled" value="1" {{ old('is_cup_size_enabled', $produk->is_cup_size_enabled) ? 'checked' : '' }}>
                        <span>Aktifkan pilihan cup (Regular / Large)</span>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="is_spicy_enabled" value="1" {{ old('is_spicy_enabled', $produk->is_spicy_enabled) ? 'checked' : '' }}>
                        <span>Aktifkan pilihan pedas (Non Spicy / Spicy / Extra Spicy)</span>
                    </label>
                    @foreach($masterOpsiKasir as $opsi)
                        <label class="check">
                            <input type="checkbox" name="master_opsi[]" value="{{ $opsi->kode_opsi }}" @checked(in_array($opsi->kode_opsi, $selectedMasterOld, true))>
                            <span>{{ $opsi->nama_opsi }}{{ $opsi->is_required ? ' (Wajib pilih)' : '' }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="actions full">
                <button class="btn-primary" type="submit">Update</button>
                <a class="btn-neutral" href="{{ route('produk.index') }}">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection
