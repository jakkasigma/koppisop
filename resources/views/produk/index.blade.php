@extends('layouts.app')

@section('title', 'Master Produk')

@section('content')
<div class="container">
    @php
        $produkRows = method_exists($produk, 'items') ? collect($produk->items()) : collect($produk);
    @endphp
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Master Produk</h1>
            <p>Kelola menu yang dijual, stok, harga, dan opsi kasir per produk.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip soft">Produk: {{ number_format(method_exists($produk, 'total') ? $produk->total() : $produkRows->count(), 0, ',', '.') }}</span>
            <span class="admin-chip">Kategori: {{ number_format($produkRows->pluck('id_kategori')->filter()->unique()->count(), 0, ',', '.') }}</span>
            <button class="btn-primary" type="button" id="openProdukModal">+ Produk</button>
            <a class="btn-neutral" href="{{ route('master_opsi_kasir.index') }}">Opsi Kasir</a>
        </div>
    </div>

    <div class="panel">
        @if (session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Opsi Kasir</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($produk as $item)
                    <tr>
                        <td><span class="id-chip">#{{ $item->id_produk }}</span></td>
                        <td>{{ $item->nama_produk }}</td>
                        <td>{{ $item->kategori?->nama_kategori ?? '-' }}</td>
                        <td><span class="money">Rp {{ number_format((float) $item->harga, 0, ',', '.') }}</span></td>
                        <td>{{ $item->stok }}</td>
                        <td>
                            @if($item->is_temperature_enabled)
                                <span class="opt-pill">Es/Less Es/Hot</span>
                            @endif
                            @if($item->is_sugar_enabled)
                                <span class="opt-pill">Sugar</span>
                            @endif
                            @if($item->is_cup_size_enabled)
                                <span class="opt-pill">Cup</span>
                            @endif
                            @if($item->is_spicy_enabled)
                                <span class="opt-pill">Spicy</span>
                            @endif
                            @foreach($item->resolvedCustomOptionGroups() as $group)
                                <span class="opt-pill">{{ $group['label'] ?? $group['id'] ?? 'Opsi Tambahan' }}</span>
                            @endforeach
                            @if(! $item->is_temperature_enabled && ! $item->is_sugar_enabled && ! $item->is_cup_size_enabled && ! $item->is_spicy_enabled)
                                @if(empty($item->resolvedCustomOptionGroups()))
                                    <span>-</span>
                                @endif
                            @endif
                        </td>
                        <td>
                            <div class="aksi">
                                <a class="btn-neutral btn-mini" href="{{ route('produk.edit', $item) }}">Edit</a>
                                <form class="inline" method="post" action="{{ route('produk.destroy', $item) }}" onsubmit="return confirm('Hapus produk ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger btn-mini" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada data produk.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="produkModal" aria-hidden="true" data-open="{{ old('nama_produk') ? '1' : '0' }}">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Tambah Produk">
        <div class="modal-head">
            <h3 class="modal-title">Tambah Produk</h3>
        </div>
        <div class="modal-body">
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
                    <button class="btn-neutral" type="button" id="cancelProdukModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const openBtn = document.getElementById('openProdukModal');
    const modal = document.getElementById('produkModal');
    const cancelBtn = document.getElementById('cancelProdukModal');
    if (!openBtn || !modal) return;
    const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); };
    const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); };
    openBtn.addEventListener('click', open);
    cancelBtn?.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    if (modal.dataset.open === '1') open();
})();
</script>
@endsection

