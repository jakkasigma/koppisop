@extends('layouts.app')

@section('title', 'Edit Promo Bundling')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Edit Promo Bundling</h1>
            <p>Perbarui paket bundling agar harga dan komposisinya selalu akurat.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-neutral" href="{{ route('bundling.index') }}">Kembali</a>
        </div>
    </div>

    <div class="panel form-panel">
        @if ($errors->any())
            <div class="alert err">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <form method="post" action="{{ route('bundling.update', $bundling) }}" class="form-grid" id="formBundling">
            @csrf
            @method('PUT')
            <div class="section-title">Informasi Bundling</div>
            <div class="full">
                <label>Nama Promo</label>
                <input type="text" name="nama_promo" value="{{ old('nama_promo', $bundling->nama_promo) }}" required>
            </div>
            <div class="row2">
                <div>
                    <label>Harga Bundle</label>
                    <input type="number" step="1" min="1" name="harga_bundle" value="{{ old('harga_bundle', $bundling->harga_bundle) }}" required>
                </div>
                <div>
                    <label>Status</label>
                    <div class="check">
                        <input id="status_aktif" type="checkbox" name="status_aktif" value="1" @checked(old('status_aktif', $bundling->status_aktif) == '1')>
                        <label for="status_aktif" class="u-m-0">Aktif</label>
                    </div>
                </div>
            </div>
            <div class="row2">
                <div>
                    <label>Tanggal Mulai (Opsional)</label>
                    <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $bundling->tanggal_mulai?->toDateString()) }}">
                </div>
                <div>
                    <label>Tanggal Selesai (Opsional)</label>
                    <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $bundling->tanggal_selesai?->toDateString()) }}">
                </div>
            </div>
            <div class="full">
                <label>Keterangan</label>
                <textarea name="keterangan" rows="2">{{ old('keterangan', $bundling->keterangan) }}</textarea>
            </div>
            <div class="full">
                <label>Produk Bundling</label>
                <div class="items" id="itemsWrap"></div>
                <button type="button" id="addItemBtn" class="btn-neutral">+ Tambah Produk</button>
            </div>
            <div class="actions full">
                <button class="btn-primary" type="submit">Update</button>
                <a class="btn-neutral" href="{{ route('bundling.index') }}">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const produk = @json($produk->map(fn($p) => ['id' => (int)$p->id_produk, 'nama' => $p->nama_produk])->values());
    const dbItems = @json($bundling->items->map(fn($i) => ['id_produk' => (int)$i->id_produk, 'qty' => (int)$i->qty])->values());
    const oldItems = @json(old('items'));
    const wrap = document.getElementById('itemsWrap');
    const addBtn = document.getElementById('addItemBtn');

    let rows = Array.isArray(oldItems) ? oldItems : (dbItems.length > 0 ? dbItems : [{ id_produk: '', qty: 1 }]);

    const render = () => {
        wrap.innerHTML = '';
        rows.forEach((row, i) => {
            const el = document.createElement('div');
            el.className = 'item-row';
            const options = ['<option value="">Pilih produk</option>']
                .concat(produk.map((p) => `<option value="${p.id}" ${String(row.id_produk) === String(p.id) ? 'selected' : ''}>${p.nama}</option>`))
                .join('');
            el.innerHTML = `
                <select name="items[${i}][id_produk]" required>${options}</select>
                <input type="number" min="1" step="1" name="items[${i}][qty]" value="${Math.max(1, Number(row.qty || 1))}" required>
                <button type="button" class="btn-danger js-del">Hapus</button>
            `;
            el.querySelector('.js-del').addEventListener('click', () => {
                rows.splice(i, 1);
                if (rows.length === 0) rows.push({ id_produk: '', qty: 1 });
                render();
            });
            wrap.appendChild(el);
        });
    };

    addBtn.addEventListener('click', () => {
        rows.push({ id_produk: '', qty: 1 });
        render();
    });

    render();
})();
</script>
@endsection
