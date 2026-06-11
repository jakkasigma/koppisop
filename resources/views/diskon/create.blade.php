@extends('layouts.app')

@section('title', 'Tambah Diskon')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Tambah Diskon</h1>
            <p>Diskon ini akan muncul di tahap checkout kasir.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-neutral" href="{{ route('diskon.index') }}">Kembali</a>
        </div>
    </div>
    <div class="panel form-panel">
        @if ($errors->any())
            <div class="alert err">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif
        <form method="post" action="{{ route('diskon.store') }}" class="form-grid">
            @csrf
            <div class="section-title">Informasi Diskon</div>
            <div>
                <label>Nama Diskon</label>
                <input type="text" name="nama_diskon" value="{{ old('nama_diskon') }}" required>
            </div>
            <div class="row2">
                <div>
                    <label>Tipe Diskon</label>
                    <select id="tipeDiskon" name="tipe_diskon" required>
                        <option value="persen" @selected(old('tipe_diskon') === 'persen')>Persen (%)</option>
                        <option value="nominal" @selected(old('tipe_diskon') === 'nominal')>Nominal (Rp)</option>
                        <option value="harga_kategori" @selected(old('tipe_diskon') === 'harga_kategori')>Harga Spesial</option>
                    </select>
                </div>
                <div>
                    <label id="nilaiDiskonLabel">Nilai Diskon</label>
                    <input id="nilaiDiskonInput" type="number" step="0.01" min="0" name="nilai_diskon" value="{{ old('nilai_diskon') }}" required>
                </div>
            </div>
            <div class="row2">
                <div>
                    <label>Kategori Target (Opsional)</label>
                    <select id="kategoriTargetInput" name="id_kategori_target">
                        <option value="">Semua kategori</option>
                        @foreach($kategori as $item)
                            <option value="{{ $item->id_kategori }}" @selected((string) old('id_kategori_target') === (string) $item->id_kategori)>{{ $item->nama_kategori }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="hargaKategoriWrap" hidden>
                    <label>Harga Spesial per Item</label>
                    <input id="hargaSpesialInput" type="number" step="1" min="1" name="harga_spesial" value="{{ old('harga_spesial') }}" placeholder="Contoh: 12000">
                </div>
            </div>
            <div class="row2">
                <div>
                    <label>Minimal Belanja</label>
                    <input id="minimalBelanjaInput" type="number" step="1" min="0" name="minimal_belanja" value="{{ old('minimal_belanja', 0) }}">
                </div>
                <div>
                    <label>Maksimal Promo (Opsional, untuk persen)</label>
                    <input id="maksimalDiskonInput" type="number" step="1" min="0" name="maksimal_diskon" value="{{ old('maksimal_diskon') }}" placeholder="Contoh: 10000">
                </div>
            </div>
            <div class="row2">
                <div>
                    <label>Status</label>
                    <div class="check">
                        <input id="status_aktif" type="checkbox" name="status_aktif" value="1" @checked(old('status_aktif', '1') == '1')>
                        <label for="status_aktif" class="u-m-0">Aktif</label>
                    </div>
                </div>
            </div>
            <div class="row2">
                <div>
                    <label>Tanggal Mulai (Opsional)</label>
                    <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}">
                </div>
                <div>
                    <label>Tanggal Selesai (Opsional)</label>
                    <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}">
                </div>
            </div>
            <div>
                <label>Keterangan</label>
                <textarea name="keterangan" rows="3">{{ old('keterangan') }}</textarea>
            </div>
            <div class="actions full">
                <button class="btn-primary" type="submit">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const tipeDiskon = document.getElementById('tipeDiskon');
    const nilaiLabel = document.getElementById('nilaiDiskonLabel');
    const nilaiInput = document.getElementById('nilaiDiskonInput');
    const maksimalInput = document.getElementById('maksimalDiskonInput');
    const hargaKategoriWrap = document.getElementById('hargaKategoriWrap');
    const kategoriTargetInput = document.getElementById('kategoriTargetInput');
    const hargaSpesialInput = document.getElementById('hargaSpesialInput');

    const syncMode = () => {
        const mode = tipeDiskon.value;
        const isHargaKategori = mode === 'harga_kategori';
        const isPersen = mode === 'persen';

        hargaKategoriWrap.hidden = !isHargaKategori;
        hargaSpesialInput.required = isHargaKategori;

        nilaiInput.required = !isHargaKategori;
        nilaiInput.readOnly = false;
        nilaiLabel.textContent = isHargaKategori
            ? 'Nilai Diskon (otomatis dari Harga Spesial)'
            : 'Nilai Diskon';

        maksimalInput.disabled = !isPersen;
        if (!isPersen) {
            maksimalInput.value = '';
        }
    };

    tipeDiskon.addEventListener('change', syncMode);
    syncMode();
})();
</script>
@endsection
