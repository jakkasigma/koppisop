@extends('layouts.app')

@section('title', 'Master Diskon')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Master Diskon</h1>
            <p>Kelola promo diskon dan bundling yang muncul otomatis di checkout kasir.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip soft">Diskon: {{ number_format($diskon->count(), 0, ',', '.') }}</span>
            <span class="admin-chip">Bundling: {{ number_format($promoBundling->count(), 0, ',', '.') }}</span>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Tabel Diskon</h3>
                <div class="panel-sub">Promo diskon tampil otomatis di kasir sesuai status dan periode.</div>
            </div>
            <div class="panel-actions">
                <button class="btn-primary" type="button" id="openDiskonModal">+ Diskon</button>
            </div>
        </div>
        <div class="note">Tips: Atur periode supaya diskon hanya aktif pada tanggal tertentu. Diskon nonaktif tidak akan muncul di kasir.</div>
        @php
            $now = now();
            $diskonAktif = $diskon->filter(fn($d) => $d->isAktifPada($now))->count();
            $diskonTerjadwal = $diskon->filter(fn($d) => $d->status_aktif && $d->tanggal_mulai && $d->tanggal_mulai->isFuture())->count();
            $diskonBerakhir = $diskon->filter(fn($d) => $d->status_aktif && $d->tanggal_selesai && $d->tanggal_selesai->isPast())->count();
            $diskonNonaktif = $diskon->filter(fn($d) => ! $d->status_aktif)->count();
        @endphp
        <div class="legend">
            <span class="pill"><span class="dot on"></span>Aktif: {{ $diskonAktif }}</span>
            <span class="pill"><span class="dot wait"></span>Terjadwal: {{ $diskonTerjadwal }}</span>
            <span class="pill"><span class="dot off"></span>Berakhir: {{ $diskonBerakhir }}</span>
            <span class="pill"><span class="dot off"></span>Nonaktif: {{ $diskonNonaktif }}</span>
        </div>
        @if(session('success')) <div class="alert ok">{{ session('success') }}</div> @endif
        @if($errors->any()) <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif
        <div class="table-wrap">
            <table id="diskonTable">
                <thead><tr><th>Nama</th><th>Tipe</th><th>Kategori</th><th>Nilai</th><th>Min Belanja</th><th>Maks Promo</th><th>Periode</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($diskon as $item)
                    @php
                        $today = now()->toDateString();
                        $mulai = $item->tanggal_mulai?->toDateString();
                        $selesai = $item->tanggal_selesai?->toDateString();
                        $sedangBerlaku = $item->isAktifPada(now());
                        $periodeMulai = $item->tanggal_mulai ? $item->tanggal_mulai->format('Y-m-d') : '-';
                        $periodeSelesai = $item->tanggal_selesai ? $item->tanggal_selesai->format('Y-m-d') : '-';
                    @endphp
                    <tr>
                        <td>{{ $item->nama_diskon }}</td>
                        <td>
                            @if($item->tipe_diskon === 'harga_kategori')
                                HARGA SPESIAL
                            @else
                                {{ strtoupper($item->tipe_diskon) }}
                            @endif
                        </td>
                        <td>
                            {{ $item->kategoriTarget?->nama_kategori ?? 'Semua kategori' }}
                        </td>
                        <td>
                            @if($item->tipe_diskon === 'persen')
                                {{ rtrim(rtrim(number_format((float) $item->nilai_diskon, 2, '.', ''), '0'), '.') }}%
                            @elseif($item->tipe_diskon === 'harga_kategori')
                                Rp {{ number_format((float) ($item->harga_spesial ?? 0), 0, ',', '.') }}
                            @else
                                Rp {{ number_format((float) $item->nilai_diskon, 0, ',', '.') }}
                            @endif
                        </td>
                        <td>Rp {{ number_format((float) $item->minimal_belanja, 0, ',', '.') }}</td>
                        <td>
                            @if($item->tipe_diskon === 'persen' && (float) ($item->maksimal_diskon ?? 0) > 0)
                                Rp {{ number_format((float) $item->maksimal_diskon, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <div class="period-line">{{ $periodeMulai }} s/d {{ $periodeSelesai }}</div>
                            @if($sedangBerlaku)
                                <div class="muted">Sedang berlaku</div>
                            @elseif($item->status_aktif && $mulai !== null && $mulai > $today)
                                <div class="muted">Belum mulai</div>
                            @elseif($item->status_aktif && $selesai !== null && $selesai < $today)
                                <div class="muted">Sudah berakhir</div>
                            @endif
                        </td>
                        <td>
                            @if(! $item->status_aktif)
                                <span class="badge off">Nonaktif</span>
                            @elseif($sedangBerlaku)
                                <span class="badge on">Aktif</span>
                            @elseif($mulai !== null && $mulai > $today)
                                <span class="badge wait">Terjadwal</span>
                            @else
                                <span class="badge off">Berakhir</span>
                            @endif
                        </td>
                        <td>
                            <div class="aksi">
                                <a class="btn-neutral btn-mini" href="{{ route('diskon.edit', $item) }}">Edit</a>
                                <form class="inline" method="post" action="{{ route('diskon.destroy', $item) }}" onsubmit="return confirm('Hapus diskon ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger btn-mini" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                Belum ada data diskon.
                                <div>
                                    <button class="btn-primary" type="button" id="openDiskonModalEmpty">+ Tambah Diskon</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel" id="promo-bundling">
        <div class="panel-head">
            <div>
                <h3 class="panel-title">Tabel Promo Bundling</h3>
                <div class="panel-sub">Bundling bisa aktif bersama diskon sesuai periode.</div>
            </div>
            <div class="panel-actions">
                <button class="btn-primary" type="button" id="openBundlingModal">+ Bundling</button>
            </div>
        </div>
        <div class="legend">
            <span class="pill"><span class="dot on"></span>Aktif</span>
            <span class="pill"><span class="dot off"></span>Nonaktif / Berakhir</span>
        </div>
        <div class="table-wrap">
            <table id="bundlingTable">
                <thead><tr><th>Nama Promo</th><th>Isi Bundling</th><th>Harga Bundle</th><th>Status</th><th>Periode</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($promoBundling as $item)
                    @php
                        $periodeMulai = $item->tanggal_mulai ? $item->tanggal_mulai->format('Y-m-d') : '-';
                        $periodeSelesai = $item->tanggal_selesai ? $item->tanggal_selesai->format('Y-m-d') : '-';
                    @endphp
                    <tr>
                        <td>{{ $item->nama_promo }}</td>
                        <td>
                            @foreach($item->items as $row)
                                <div>{{ $row->qty }}x {{ $row->produk?->nama_produk ?? '-' }}</div>
                            @endforeach
                        </td>
                        <td>Rp {{ number_format((float) $item->harga_bundle, 0, ',', '.') }}</td>
                        <td>
                            @if($item->isAktifPada(now()))
                                <span class="badge on">Aktif</span>
                            @else
                                <span class="badge off">{{ $item->status_aktif ? 'Tidak Berlaku' : 'Nonaktif' }}</span>
                            @endif
                        </td>
                        <td><span class="period-line">{{ $periodeMulai }} s/d {{ $periodeSelesai }}</span></td>
                        <td>
                            <div class="aksi">
                                <a class="btn-neutral btn-mini" href="{{ route('bundling.edit', $item) }}">Edit</a>
                                <form class="inline" method="post" action="{{ route('bundling.destroy', $item) }}" onsubmit="return confirm('Hapus promo bundling ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger btn-mini" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                Belum ada promo bundling.
                                <div>
                                    <button class="btn-primary" type="button" id="openBundlingModalEmpty">+ Tambah Bundling</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="diskonModal" aria-hidden="true" data-open="{{ old('nama_diskon') ? '1' : '0' }}">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Tambah Diskon">
        <div class="modal-head">
            <h3 class="modal-title">Tambah Diskon</h3>
        </div>
        <div class="modal-body">
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
                        <select id="tipeDiskonModal" name="tipe_diskon" required>
                            <option value="persen" @selected(old('tipe_diskon') === 'persen')>Persen (%)</option>
                            <option value="nominal" @selected(old('tipe_diskon') === 'nominal')>Nominal (Rp)</option>
                            <option value="harga_kategori" @selected(old('tipe_diskon') === 'harga_kategori')>Harga Spesial</option>
                        </select>
                    </div>
                    <div>
                        <label id="nilaiDiskonLabelModal">Nilai Diskon</label>
                        <input id="nilaiDiskonInputModal" type="number" step="0.01" min="0" name="nilai_diskon" value="{{ old('nilai_diskon') }}" required>
                    </div>
                </div>
                <div class="row2">
                    <div>
                        <label>Kategori Target (Opsional)</label>
                        <select id="kategoriTargetInputModal" name="id_kategori_target">
                            <option value="">Semua kategori</option>
                            @foreach($kategori as $item)
                                <option value="{{ $item->id_kategori }}" @selected((string) old('id_kategori_target') === (string) $item->id_kategori)>{{ $item->nama_kategori }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="hargaKategoriWrapModal" hidden>
                        <label>Harga Spesial per Item</label>
                        <input id="hargaSpesialInputModal" type="number" step="1" min="1" name="harga_spesial" value="{{ old('harga_spesial') }}" placeholder="Contoh: 12000">
                    </div>
                </div>
                <div class="row2">
                    <div>
                        <label>Minimal Belanja</label>
                        <input id="minimalBelanjaInputModal" type="number" step="1" min="0" name="minimal_belanja" value="{{ old('minimal_belanja', 0) }}">
                    </div>
                    <div>
                        <label>Maksimal Promo (Opsional, untuk persen)</label>
                        <input id="maksimalDiskonInputModal" type="number" step="1" min="0" name="maksimal_diskon" value="{{ old('maksimal_diskon') }}" placeholder="Contoh: 10000">
                    </div>
                </div>
                <div class="row2">
                    <div>
                        <label>Status</label>
                        <div class="check">
                            <input id="status_aktif_modal" type="checkbox" name="status_aktif" value="1" @checked(old('status_aktif', '1') == '1')>
                            <label for="status_aktif_modal" class="u-m-0">Aktif</label>
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
                <div class="actions">
                    <button class="btn-primary" type="submit">Simpan</button>
                    <button class="btn-neutral" type="button" id="cancelDiskonModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="bundlingModal" aria-hidden="true" data-open="{{ old('nama_promo') || old('items') ? '1' : '0' }}">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Tambah Promo Bundling">
        <div class="modal-head">
            <h3 class="modal-title">Tambah Promo Bundling</h3>
        </div>
        <div class="modal-body">
            @if ((old('nama_promo') || old('items')) && $errors->any())
                <div class="alert err">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            @endif
            <form method="post" action="{{ route('bundling.store') }}" class="form-grid" id="formBundlingModal">
                @csrf
                <div class="section-title">Informasi Bundling</div>
                <div>
                    <label>Nama Promo</label>
                    <input type="text" name="nama_promo" value="{{ old('nama_promo') }}" required>
                </div>
                <div class="row2">
                    <div>
                        <label>Harga Bundle</label>
                        <input type="number" step="1" min="1" name="harga_bundle" value="{{ old('harga_bundle') }}" required>
                    </div>
                    <div>
                        <label>Status</label>
                        <div class="check">
                            <input id="bundling_status_aktif" type="checkbox" name="status_aktif" value="1" @checked(old('status_aktif', '1') == '1')>
                            <label for="bundling_status_aktif" class="u-m-0">Aktif</label>
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
                    <textarea name="keterangan" rows="2">{{ old('keterangan') }}</textarea>
                </div>

                <div>
                    <label>Produk Bundling</label>
                    <div class="items" id="bundlingItemsWrap"></div>
                    <button type="button" id="bundlingAddItemBtn" class="btn-neutral">+ Tambah Produk</button>
                </div>

                <div class="actions">
                    <button class="btn-primary" type="submit">Simpan</button>
                    <button class="btn-neutral" type="button" id="cancelBundlingModal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const openBtn = document.getElementById('openDiskonModal');
    const modal = document.getElementById('diskonModal');
    const cancelBtn = document.getElementById('cancelDiskonModal');
    if (openBtn && modal) {
        const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); };
        const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); };
        openBtn.addEventListener('click', open);
        cancelBtn?.addEventListener('click', close);
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
        if (modal.dataset.open === '1') open();
    }

    const tipeDiskon = document.getElementById('tipeDiskonModal');
    const nilaiLabel = document.getElementById('nilaiDiskonLabelModal');
    const nilaiInput = document.getElementById('nilaiDiskonInputModal');
    const maksimalInput = document.getElementById('maksimalDiskonInputModal');
    const hargaKategoriWrap = document.getElementById('hargaKategoriWrapModal');
    const hargaSpesialInput = document.getElementById('hargaSpesialInputModal');

    const syncMode = () => {
        if (!tipeDiskon) return;
        const mode = tipeDiskon.value;
        const isHargaKategori = mode === 'harga_kategori';
        const isPersen = mode === 'persen';

        if (hargaKategoriWrap) hargaKategoriWrap.hidden = !isHargaKategori;
        if (hargaSpesialInput) hargaSpesialInput.required = isHargaKategori;
        if (nilaiInput) {
            nilaiInput.required = !isHargaKategori;
            nilaiInput.readOnly = false;
        }
        if (nilaiLabel) {
            nilaiLabel.textContent = isHargaKategori
                ? 'Nilai Diskon (otomatis dari Harga Spesial)'
                : 'Nilai Diskon';
        }
        if (maksimalInput) {
            maksimalInput.disabled = !isPersen;
            if (!isPersen) maksimalInput.value = '';
        }
    };

    if (tipeDiskon) {
        tipeDiskon.addEventListener('change', syncMode);
        syncMode();
    }

    const openBtnEmpty = document.getElementById('openDiskonModalEmpty');
    if (openBtnEmpty && openBtn) {
        openBtnEmpty.addEventListener('click', () => openBtn.click());
    }

    const openBundlingBtn = document.getElementById('openBundlingModal');
    const openBundlingEmptyBtn = document.getElementById('openBundlingModalEmpty');
    const bundlingModal = document.getElementById('bundlingModal');
    const cancelBundlingBtn = document.getElementById('cancelBundlingModal');
    if (openBundlingBtn && bundlingModal) {
        const openBundling = () => { bundlingModal.classList.add('show'); bundlingModal.setAttribute('aria-hidden', 'false'); };
        const closeBundling = () => { bundlingModal.classList.remove('show'); bundlingModal.setAttribute('aria-hidden', 'true'); };
        openBundlingBtn.addEventListener('click', openBundling);
        openBundlingEmptyBtn?.addEventListener('click', openBundling);
        cancelBundlingBtn?.addEventListener('click', closeBundling);
        bundlingModal.addEventListener('click', (e) => { if (e.target === bundlingModal) closeBundling(); });
        if (bundlingModal.dataset.open === '1') openBundling();
    }

    const produk = @json(($produk ?? collect())->map(fn($p) => ['id' => (int) $p->id_produk, 'nama' => $p->nama_produk])->values());
    const oldItems = @json(old('items', [['id_produk' => '', 'qty' => 1]]));
    const wrap = document.getElementById('bundlingItemsWrap');
    const addBtn = document.getElementById('bundlingAddItemBtn');
    let rows = Array.isArray(oldItems) && oldItems.length > 0 ? oldItems : [{ id_produk: '', qty: 1 }];
    const render = () => {
        if (!wrap) return;
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
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            rows.push({ id_produk: '', qty: 1 });
            render();
        });
    }
    render();
})();
</script>
@endsection


