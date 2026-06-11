@extends('layouts.app')

@section('title', 'Kasir - Pilih Produk')

@section('content')
<div class="container">
    @php
        $setting = \App\Models\StrukSetting::current();
        $logoPath = trim((string) ($setting?->logo_path ?? ''));
        $produkCount = (int) $produk->count();
        $kategoriCount = (int) $produk->pluck('kategori.nama_kategori')->filter()->unique()->count();
        $bundlingCount = (int) (($promoBundling ?? collect())->count());
        $shiftBadge = !empty($activeShift) ? 'Shift ' . (int) $activeShift->shift_ke : 'Belum ada shift';
    @endphp

    {{-- Hero — hanya untuk kasir role, admin tidak perlu --}}
    @if(auth()->user()->role !== 'admin')
    <div class="hero kasir-admin-hero">
        <div class="hero-head">
            <div class="hero-copy">
                <div class="hero-logo-wrap">
                    @if($logoPath !== '')
                        <div class="hero-logo">
                            <img src="{{ asset('storage/' . $logoPath) }}" alt="Logo Cafe">
                        </div>
                    @endif
                    <div class="hero-copy-text">
                        <h1>Transaksi Kasir</h1>
                        <div class="hero-sub">Pilih menu, cek promo yang sedang berjalan, lalu lanjutkan ke checkout setelah pesanan sudah lengkap.</div>
                    </div>
                </div>
                <div class="hero-stats-line">
                    <span class="stat-chip">Produk <strong>{{ number_format($produkCount, 0, ',', '.') }}</strong></span>
                    <span class="stat-chip">Kategori <strong>{{ number_format($kategoriCount, 0, ',', '.') }}</strong></span>
                    @if($bundlingCount > 0)
                    <span class="stat-chip">Bundling <strong>{{ number_format($bundlingCount, 0, ',', '.') }}</strong></span>
                    @endif
                    <span class="stat-chip">Shift <strong>{{ $shiftBadge }}</strong></span>
                </div>
            </div>
            <div class="hero-side">
                @if(auth()->user()->role !== 'admin' && !empty($activeShift))
                    <div class="shift-flip" id="shiftFlip">
                        <div class="shift-flip-card" role="button" aria-label="Detail kas shift" tabindex="0">
                            <span class="shift-face front">
                                <span class="shift-dot"></span>
                                Shift {{ (int) $activeShift->shift_ke }} | Kas Awal Rp {{ number_format((float) $activeShift->kas_awal, 0, ',', '.') }}
                            </span>
                            <span class="shift-face back">
                                <span class="shift-dot"></span>
                                Shift {{ (int) $activeShift->shift_ke }} | Kas Sekarang Rp {{ number_format((float) ($kasSekarang ?? $activeShift->kas_awal), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                    <a class="btn-neutral" href="{{ route('kasir.shift.report') }}">Tutup Shift</a>
                @endif
            </div>
        </div>
        <div class="nav">
            @include('partials.top-nav-links')
        </div>
    </div>
    @else
    {{-- Admin: page head ringkas tanpa hero --}}
    <div class="admin-page-head" style="margin-bottom:1rem;">
        <div>
            <div class="admin-page-label">Kasir Admin</div>
            <h1 style="margin:0.25rem 0 0;font-size:1.5rem;font-weight:700;">Transaksi Kasir</h1>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip">Produk: {{ $produkCount }}</span>
            <span class="admin-chip">Kategori: {{ $kategoriCount }}</span>
            <span class="admin-chip soft">{{ $shiftBadge }}</span>
        </div>
    </div>
    @endif

    {{-- Alerts --}}
    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Promo board --}}
    @if(($promoAnnouncements ?? collect())->count() > 0)
        <section class="promo-board">
            <div class="promo-head">
                <h2>Pengumuman Promo</h2>
                <div class="promo-sub">Diskon, bundling, dan info promo terbaru untuk kasir</div>
            </div>
            <div class="promo-grid">
                @foreach($promoAnnouncements as $promo)
                    @php
                        $status = $promo->promo_status ?? null;
                        $statusLabel = $status ?: (($promo->is_active ?? false) ? 'Aktif' : 'Info');
                        $statusClass = 'neutral';
                        if ($statusLabel === 'Aktif' || $statusLabel === 'Berjalan') {
                            $statusClass = 'ok';
                        } elseif ($statusLabel === 'Terjadwal') {
                            $statusClass = 'info';
                        } elseif ($statusLabel === 'Berakhir' || $statusLabel === 'Nonaktif') {
                            $statusClass = 'bad';
                        }
                        $bodyPreview = \Illuminate\Support\Str::limit(trim((string) $promo->body), 160);
                        $publishedAt = $promo->published_at ? $promo->published_at->format('d M Y') : null;
                    @endphp
                    <article class="promo-card">
                        <div class="promo-title">{{ $promo->title }}</div>
                        <span class="promo-pill {{ $statusClass }}">{{ $statusLabel }}</span>
                        @if(!empty($promo->image_path))
                            <img class="promo-thumb" src="{{ asset('storage/' . $promo->image_path) }}" alt="Poster promo">
                        @endif
                        <div class="promo-body">{{ $bodyPreview }}</div>
                        @if($publishedAt)
                            <div class="promo-meta">{{ $publishedAt }}</div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Main form --}}
    <form id="kasirForm" method="post" action="{{ route(($kasirRoutePrefix ?? 'kasir') . '.preview') }}">
        @csrf
        <input type="hidden" id="selectedBundlingInput" name="id_promo_bundling" value="{{ (int) ($selectedBundlingId ?? 0) }}">
        <div id="itemsHolder"></div>

        @php
            $produkByKategori = $produk
                ->groupBy(fn ($item) => \App\Models\Kategori::normalizedKey($item->kategori?->nama_kategori))
                ->map(function ($itemsKategori) {
                    $namaKategori = $itemsKategori
                        ->map(fn ($item) => trim((string) ($item->kategori?->nama_kategori ?? '')))
                        ->filter()
                        ->sortBy([
                            fn ($nama) => str_contains($nama, ' ') ? 0 : 1,
                            fn ($nama) => mb_strlen($nama),
                        ])
                        ->first() ?? 'Tanpa Kategori';

                    return [
                        'nama' => $namaKategori,
                        'slug' => \Illuminate\Support\Str::slug($namaKategori),
                        'items' => $itemsKategori->sortBy('nama_produk')->values(),
                    ];
                })
                ->sortBy(fn ($group) => mb_strtolower($group['nama']))
                ->values();
        @endphp

        <div class="panel transaction-panel">
            <div class="workspace-head">
                <div class="workspace-title">Pilih Menu</div>
                <div class="workspace-sub">Tampilan ini disamakan dengan gaya transaksi admin supaya lebih konsisten saat dipakai bergantian.</div>
            </div>

            {{-- Kategori nav --}}
            <div class="kategori-nav">
                @if(($promoBundling ?? collect())->count() > 0)
                    <a href="#kategori-bundling">Bundling ({{ ($promoBundling ?? collect())->count() }})</a>
                @endif
                @foreach($produkByKategori as $groupKategori)
                    <a href="#kategori-{{ $groupKategori['slug'] }}">{{ $groupKategori['nama'] }} ({{ $groupKategori['items']->count() }})</a>
                @endforeach
            </div>

            <div class="layout">
                <div class="menu-list">

                    {{-- Bundling section --}}
                    @if(($promoBundling ?? collect())->count() > 0)
                        <section id="kategori-bundling" class="kategori-block">
                            <h3 class="kategori-title">Bundling Aktif</h3>
                            <div class="grid">
                                @foreach(($promoBundling ?? collect()) as $bundle)
                                    @php
                                        $normal = (float) $bundle->items->sum(fn ($row) => ((float) ($row->produk?->harga ?? 0) * (int) $row->qty));
                                        $hemat = max(0, $normal - (float) $bundle->harga_bundle);
                                        $bundleItemsPayload = $bundle->items->map(function ($it) {
                                            return [
                                                'id_produk' => (int) $it->id_produk,
                                                'nama_produk' => (string) ($it->produk?->nama_produk ?? '-'),
                                                'qty' => max(1, (int) $it->qty),
                                            ];
                                        })->values()->toJson();
                                    @endphp
                                    <div class="card bundle-card">
                                        <div class="card-head">
                                            <div class="card-kicker">Promo Bundling</div>
                                            <div class="title">{{ $bundle->nama_promo }}</div>
                                            <div class="card-note">Pilih paket ini untuk langsung menambahkan semua item bundling ke pesanan.</div>
                                        </div>
                                        <div class="meta meta-pills">
                                            <span class="meta-pill price">Rp {{ number_format((float) $bundle->harga_bundle, 0, ',', '.') }}</span>
                                            <span class="meta-pill save">Hemat Rp {{ number_format($hemat, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="picked">
                                            <span class="picked-label">Isi bundling</span>
                                            @foreach($bundle->items as $it)
                                                <div class="picked-item compact">
                                                    <div>{{ $it->produk?->nama_produk ?? '-' }}</div>
                                                    <div>x{{ (int) $it->qty }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="card-actions">
                                            <button
                                                type="button"
                                                class="btn-primary js-pick-bundling"
                                                data-bundling-id="{{ $bundle->id_promo_bundling }}"
                                                data-bundling-name="{{ $bundle->nama_promo }}"
                                                data-bundling-price="{{ (float) $bundle->harga_bundle }}"
                                                data-bundling-items='{{ $bundleItemsPayload }}'
                                            >Tambah</button>
                                            <button
                                                type="button"
                                                class="btn-neutral js-reset-bundling"
                                                data-bundling-id="{{ $bundle->id_promo_bundling }}"
                                            >Reset</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    {{-- Product sections --}}
                    @foreach($produkByKategori as $groupKategori)
                        <section id="kategori-{{ $groupKategori['slug'] }}" class="kategori-block">
                            <h3 class="kategori-title">{{ $groupKategori['nama'] }}</h3>
                            <div class="grid">
                                @foreach($groupKategori['items'] as $item)
                                    @php
                                        $stok = (int) ($item->stok ?? 0);
                                        $varianList = collect([
                                            $item->is_temperature_enabled ? 'Suhu' : null,
                                            $item->is_sugar_enabled ? 'Sugar' : null,
                                            $item->is_cup_size_enabled ? 'Cup' : null,
                                            $item->is_spicy_enabled ? 'Pedas' : null,
                                            collect($item->resolvedCustomOptionGroups())->isNotEmpty() ? 'Opsi tambahan' : null,
                                        ])->filter()->values();
                                        $stokClass = $stok <= 0 ? 'empty' : ($stok <= 5 ? 'low' : 'ready');
                                    @endphp
                                    <div class="card js-product-card"
                                         data-id="{{ $item->id_produk }}"
                                         data-nama="{{ $item->nama_produk }}"
                                         data-harga="{{ (float) $item->harga }}"
                                         data-stok="{{ (int) ($item->stok ?? 0) }}"
                                         data-temperature-enabled="{{ $item->is_temperature_enabled ? 1 : 0 }}"
                                         data-sugar-enabled="{{ $item->is_sugar_enabled ? 1 : 0 }}"
                                         data-cup-enabled="{{ $item->is_cup_size_enabled ? 1 : 0 }}"
                                         data-spicy-enabled="{{ $item->is_spicy_enabled ? 1 : 0 }}"
                                         data-temperature-options='@json($item->resolvedTemperatureOptions())'
                                         data-sugar-options='@json($item->resolvedSugarOptions())'
                                         data-cup-options='@json($item->resolvedCupSizeOptions())'
                                         data-spicy-options='@json($item->resolvedSpicyOptions())'
                                         data-custom-groups='@json($item->resolvedCustomOptionGroups())'>
                                        <div class="card-head">
                                            <div class="card-kicker">{{ $groupKategori['nama'] }}</div>
                                            <div class="title">{{ $item->nama_produk }}</div>
                                            @if($varianList->isNotEmpty())
                                                <div class="card-varian-tags">
                                                    @foreach($varianList as $v)
                                                        <span class="card-varian-tag">{{ $v }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="meta meta-pills">
                                            <span class="meta-pill price">Rp {{ number_format((float)$item->harga, 0, ',', '.') }}</span>
                                            <span class="meta-pill stock {{ $stokClass }}">Stok {{ $item->stok }}</span>
                                        </div>
                                        <div class="picked">
                                            <span class="picked-label">Pilihan tersimpan</span>
                                            <span class="state js-picked-state">Belum dipilih</span>
                                            <div class="picked-list js-picked-list"></div>
                                        </div>
                                        <div class="card-actions">
                                            <button type="button" class="btn-primary js-open-modal">Tambah</button>
                                            <button type="button" class="btn-neutral js-clear-item">Reset</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                </div>

                {{-- Summary sidebar --}}
                <aside class="summary">
                    <div class="summary-head">
                        <div>
                            <h3 class="summary-title">Ringkasan Sementara</h3>
                            <p class="summary-sub">Pesanan yang sedang dipilih akan muncul di sini secara realtime.</p>
                        </div>
                        <span class="summary-badge">Live</span>
                    </div>
                    <div class="row"><span>Total Item</span><strong id="sumQty">0</strong></div>
                    <div class="row"><span>Jenis Produk Dipilih</span><strong id="sumProduk">0</strong></div>
                    <div class="row"><span>Paket Bundling</span><strong id="sumBundling">{{ $selectedBundlingName ?? '-' }}</strong></div>
                    <div id="sumItems" class="summary-items"></div>
                    <div class="total-line"><span>Estimasi Total</span><span id="sumTotal">Rp 0</span></div>
                    <div class="hint">Total final tetap dihitung ulang saat checkout.</div>
                </aside>
            </div>
        </div>

        {{-- Footer bar --}}
        <div class="footer">
            <div class="footer-note">
                <div class="footer-label">Siap lanjut</div>
                <div class="hint">Langkah berikutnya: isi data pelanggan + pilih metode pembayaran di halaman checkout.</div>
                <div id="stockWarning" class="stock-warning" hidden></div>
            </div>
            <button id="checkoutBtn" class="btn-primary" type="submit">Lanjut ke Checkout</button>
        </div>
    </form>
</div>

{{-- Variant modal --}}
<div id="variantModalBackdrop" class="modal-backdrop" aria-hidden="true">
    <div class="variant-modal-card" role="dialog" aria-modal="true" aria-labelledby="variantModalTitle" tabindex="-1">
        <div class="variant-modal-header">
            <div class="variant-modal-heading">
                <div class="variant-modal-kicker">Opsi Produk</div>
                <h3 id="variantModalTitle">Atur Pilihan Produk</h3>
                <p id="variantModalSub" class="hint variant-modal-sub"></p>
            </div>
            <button id="variantModalCloseBtn" type="button" class="variant-modal-close" aria-label="Tutup popup">&times;</button>
        </div>
        <div id="variantModalError" class="modal-error"></div>
        <div class="variant-modal-body">
            <div class="variant-main-grid">
                <div class="variant-panel">
                    <div class="variant-panel-title">Jumlah & Opsi Utama</div>
                    <div class="modal-grid">
                        <div class="modal-row"><label for="variantQty">Qty</label><input id="variantQty" type="number" min="1" step="1"></div>
                        <div id="modalTempRow" class="modal-row"><label for="variantTemp">Suhu</label><select id="variantTemp"><option value="">Pilih suhu</option></select></div>
                        <div id="modalSugarRow" class="modal-row"><label for="variantSugar">Sugar</label><select id="variantSugar"><option value="">Pilih sugar</option></select></div>
                        <div id="modalCupRow" class="modal-row"><label for="variantCup">Cup</label><select id="variantCup"><option value="">Pilih cup</option></select></div>
                        <div id="modalSpicyRow" class="modal-row"><label for="variantSpicy">Level Pedas</label><select id="variantSpicy"><option value="">Pilih level pedas</option></select></div>
                    </div>
                    <div class="modal-row modal-row-full variant-note-row">
                        <label for="variantNote">Catatan Produk</label>
                        <textarea id="variantNote" rows="3" maxlength="255" placeholder="Misal: less sugar, no ice, ekstra saus"></textarea>
                    </div>
                </div>
                <div class="variant-panel">
                    <div class="variant-panel-title">Opsi Tambahan</div>
                    <div id="modalCustomRows" class="variant-custom-list"></div>
                </div>
            </div>
        </div>
        <div class="modal-actions variant-modal-footer">
            <button id="variantDeleteBtn" type="button" class="btn-danger">Hapus Varian Ini</button>
            <button id="variantCancelBtn" type="button" class="btn-neutral">Batal</button>
            <button id="variantSaveBtn" type="button" class="btn-primary">Simpan Pilihan</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const card = document.querySelector('.shift-flip-card');
    if (!card) return;
    let autoBackTimer = null;
    const flipToBack = () => {
        card.classList.add('is-flipped');
        if (autoBackTimer) clearTimeout(autoBackTimer);
        autoBackTimer = setTimeout(() => {
            card.classList.remove('is-flipped');
        }, 5000);
    };
    const toggle = () => {
        if (card.classList.contains('is-flipped')) {
            card.classList.remove('is-flipped');
            if (autoBackTimer) clearTimeout(autoBackTimer);
            return;
        }
        flipToBack();
    };
    card.addEventListener('click', toggle);
    card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggle();
        }
    });
})();
</script>
<script>
(function () {
    const cards = Array.from(document.querySelectorAll('.js-product-card'));
    const form = document.getElementById('kasirForm');
    const holder = document.getElementById('itemsHolder');
    const sumQty = document.getElementById('sumQty');
    const sumProduk = document.getElementById('sumProduk');
    const sumTotal = document.getElementById('sumTotal');
    const sumItems = document.getElementById('sumItems');
    const stockWarning = document.getElementById('stockWarning');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const bundlingButtons = Array.from(document.querySelectorAll('.js-pick-bundling'));
    const bundlingResetButtons = Array.from(document.querySelectorAll('.js-reset-bundling'));
    const bundlingStorageKey = 'kasir_preferred_bundling_id';
    const bundlingStorageNameKey = 'kasir_preferred_bundling_name';
    const selectedBundlingInput = document.getElementById('selectedBundlingInput');
    const sumBundling = document.getElementById('sumBundling');
    const bundlingCatalog = new Map();

    const modal = document.getElementById('variantModalBackdrop');
    const modalTitle = document.getElementById('variantModalTitle');
    const modalSub = document.getElementById('variantModalSub');
    const modalError = document.getElementById('variantModalError');
    const mQty = document.getElementById('variantQty');
    const mTemp = document.getElementById('variantTemp');
    const mSugar = document.getElementById('variantSugar');
    const mCup = document.getElementById('variantCup');
    const mSpicy = document.getElementById('variantSpicy');
    const mNote = document.getElementById('variantNote');
    const rowTemp = document.getElementById('modalTempRow');
    const rowSugar = document.getElementById('modalSugarRow');
    const rowCup = document.getElementById('modalCupRow');
    const rowSpicy = document.getElementById('modalSpicyRow');
    const customRowsWrap = document.getElementById('modalCustomRows');
    const btnSave = document.getElementById('variantSaveBtn');
    const btnCancel = document.getElementById('variantCancelBtn');
    const btnDelete = document.getElementById('variantDeleteBtn');
    const btnClose = document.getElementById('variantModalCloseBtn');

    const initialSessionItems = @json($selectedLines ?? []);
    const initialOldItems = @json(old('items', []));
    const largeCupSurcharge = 2000;
    const state = new Map();
    let activeCard = null;
    let activeEditIndex = null;

    const rupiah = (n) => 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(n);
    const createUid = () => `ln-${Date.now()}-${Math.random().toString(16).slice(2, 9)}`;
    const parseOptions = (raw) => {
        try {
            const parsed = JSON.parse(raw || '[]');
            if (!Array.isArray(parsed)) return [];
            return parsed
                .map((row) => ({
                    value: String(row?.value || '').toLowerCase().trim(),
                    label: String(row?.label || '').trim(),
                    extra_price: Number(row?.extra_price || 0),
                }))
                .filter((row) => row.value !== '' && row.label !== '');
        } catch (_) {
            return [];
        }
    };
    const parseBundlingItems = (raw) => {
        try {
            const parsed = JSON.parse(raw || '[]');
            if (!Array.isArray(parsed)) return [];
            return parsed
                .map((row) => ({
                    id_produk: Number(row?.id_produk || 0),
                    nama_produk: String(row?.nama_produk || '').trim(),
                    qty: Math.max(1, Number(row?.qty || 0)),
                }))
                .filter((row) => row.id_produk > 0 && row.qty > 0);
        } catch (_) {
            return [];
        }
    };
    const getMeta = (c) => ({
        id:Number(c.dataset.id),
        nama:c.dataset.nama,
        harga:Number(c.dataset.harga),
        stok:Number(c.dataset.stok),
        temp:c.dataset.temperatureEnabled==='1',
        sugar:c.dataset.sugarEnabled==='1',
        cup:c.dataset.cupEnabled==='1',
        spicy:c.dataset.spicyEnabled==='1',
        temperatureOptions: parseOptions(c.dataset.temperatureOptions),
        sugarOptions: parseOptions(c.dataset.sugarOptions),
        cupOptions: parseOptions(c.dataset.cupOptions),
        spicyOptions: parseOptions(c.dataset.spicyOptions),
        customGroups: parseGroups(c.dataset.customGroups),
    });
    const linesOf = (id) => state.get(id) || [];
    const setLines = (id, lines) => state.set(id, lines);
    const showErr = (msg) => { modalError.textContent = msg; modalError.classList.add('show'); };
    const clearErr = () => { modalError.textContent = ''; modalError.classList.remove('show'); };
    const humanize = (value) => String(value || '').replaceAll('_', ' ').trim().replace(/\b\w/g, (m) => m.toUpperCase());
    const normalizeNote = (value) => String(value || '').replace(/\s+/g, ' ').trim();
    const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[ch]);
    const optionValueSet = (options) => new Set((options || []).map((o) => o.value));
    const optionMap = (meta) => {
        const map = {};
        [...meta.temperatureOptions, ...meta.sugarOptions, ...meta.cupOptions, ...meta.spicyOptions].forEach((row) => {
            map[row.value] = Number(row.extra_price || 0) > 0
                ? `${row.label} (+${rupiah(Number(row.extra_price || 0)).replace('Rp ', '')})`
                : row.label;
        });
        (meta.customGroups || []).forEach((group) => {
            (group.options || []).forEach((row) => {
                map[row.value] = row.label;
            });
        });
        return map;
    };
    const optionLabel = (meta, t, s, c, p, custom = {}, note = '') => {
        const map = optionMap(meta);
        const parts = [t, s, c, p, ...Object.values(custom || {})]
            .filter((v) => !!v)
            .map((v) => map[v] || humanize(v));
        const cleanNote = normalizeNote(note || '');
        if (cleanNote !== '') {
            parts.push(`Catatan: ${escapeHtml(cleanNote)}`);
        }
        return parts.join(' | ');
    };
    const parseGroups = (raw) => {
        try {
            const parsed = JSON.parse(raw || '[]');
            if (!Array.isArray(parsed)) return [];
            return parsed
                .map((group) => ({
                    id: String(group?.id || '').toLowerCase().trim(),
                    label: String(group?.label || '').trim(),
                    required: !!group?.required,
                    options: (Array.isArray(group?.options) ? group.options : [])
                        .map((row) => ({
                            value: String(row?.value || '').toLowerCase().trim(),
                            label: String(row?.label || '').trim(),
                            extra_price: Number(row?.extra_price || 0),
                        }))
                        .filter((row) => row.value !== '' && row.label !== ''),
                }))
                .filter((group) => group.id !== '' && group.label !== '' && group.options.length > 0);
        } catch (_) {
            return [];
        }
    };
    const cardByProductId = new Map(cards.map((card) => [getMeta(card).id, card]));
    const pickOptionValue = (options, preferred = '') => {
        const list = Array.isArray(options) ? options : [];
        if (list.length === 0) return '';
        const pref = String(preferred || '').toLowerCase().trim();
        if (pref !== '' && list.some((row) => row.value === pref)) return pref;
        return String(list[0].value || '');
    };
    const sameOptions = (a, b) => {
        if ((a.temperature || '') !== (b.temperature || '')) return false;
        if ((a.sugar_level || '') !== (b.sugar_level || '')) return false;
        if ((a.cup_size || '') !== (b.cup_size || '')) return false;
        if ((a.spicy_level || '') !== (b.spicy_level || '')) return false;
        if (normalizeNote(a.note || '') !== normalizeNote(b.note || '')) return false;
        const aKeys = Object.keys(a.custom_options || {}).sort();
        const bKeys = Object.keys(b.custom_options || {}).sort();
        if (aKeys.length !== bKeys.length) return false;
        for (let i = 0; i < aKeys.length; i += 1) {
            const key = aKeys[i];
            if (key !== bKeys[i]) return false;
            if (String(a.custom_options[key] || '') !== String((b.custom_options || {})[key] || '')) return false;
        }
        return true;
    };
    const buildBundlingDefaultLine = (meta, qty) => {
        let temperature = '';
        let sugar = '';
        let cup = '';
        let spicy = '';
        const custom = {};

        if (meta.temp) {
            temperature = pickOptionValue(meta.temperatureOptions, 'ice');
            if (temperature === 'hot') {
                sugar = '';
                cup = meta.cup ? (pickOptionValue(meta.cupOptions, 'regular') || 'regular') : '';
            } else {
                sugar = meta.sugar ? pickOptionValue(meta.sugarOptions, 'normal') : '';
                cup = meta.cup ? pickOptionValue(meta.cupOptions, 'regular') : '';
            }
        } else {
            sugar = meta.sugar ? pickOptionValue(meta.sugarOptions, 'normal') : '';
            cup = meta.cup ? pickOptionValue(meta.cupOptions, 'regular') : '';
        }

        if (meta.spicy) {
            spicy = pickOptionValue(meta.spicyOptions);
        }

        (meta.customGroups || []).forEach((group) => {
            const picked = pickOptionValue(group.options || []);
            if (picked) custom[group.id] = picked;
        });

        return {
            uid: createUid(),
            id_produk: meta.id,
            qty: Math.max(1, Number(qty || 1)),
            temperature,
            sugar_level: sugar,
            cup_size: cup,
            spicy_level: spicy,
            note: '',
            custom_options: custom,
        };
    };
    const calculateCustomExtra = (meta, customOptions = {}) => {
        let total = 0;
        (meta.customGroups || []).forEach((group) => {
            const picked = customOptions[group.id];
            if (!picked) return;
            const opt = (group.options || []).find((row) => row.value === picked);
            if (!opt) return;
            total += Number(opt.extra_price || 0);
        });
        return total;
    };
    const findOptionExtra = (options, value) => {
        const opt = (options || []).find((row) => row.value === value);
        return opt ? Number(opt.extra_price || 0) : 0;
    };
    const calculateBuiltInExtra = (meta, line) => {
        let total = 0;
        total += findOptionExtra(meta.temperatureOptions, line.temperature || '');
        total += findOptionExtra(meta.sugarOptions, line.sugar_level || '');
        total += findOptionExtra(meta.cupOptions, line.cup_size || '');
        total += findOptionExtra(meta.spicyOptions, line.spicy_level || '');
        if ((line.cup_size || '') === 'large' && findOptionExtra(meta.cupOptions, 'large') <= 0) {
            total += largeCupSurcharge;
        }
        return total;
    };
    const refillSelect = (select, options, placeholder) => {
        const prev = select.value;
        select.innerHTML = '';
        const first = document.createElement('option');
        first.value = '';
        first.textContent = placeholder;
        select.appendChild(first);

        (options || []).forEach((row) => {
            const opt = document.createElement('option');
            opt.value = row.value;
            opt.textContent = row.label;
            select.appendChild(opt);
        });

        if ((options || []).some((row) => row.value === prev)) {
            select.value = prev;
        }
    };
    const toggle = (el, show) => { el.hidden = !show; const s=el.querySelector('select'); if(s) s.disabled=!show; };

    const parseRaw = (raw) => {
        if (!raw || typeof raw !== 'object') return [];
        const out = [];
        Object.keys(raw).forEach((k) => {
            const v = raw[k] || {};
            const id = Number(v.id_produk ?? k);
            const qty = Number(v.qty ?? 0);
            if (id > 0 && qty > 0) {
                out.push({
                    id_produk:id,
                    qty,
                    temperature:String(v.temperature||'').toLowerCase(),
                    sugar_level:String(v.sugar_level||'').toLowerCase(),
                    cup_size:String(v.cup_size||'').toLowerCase(),
                    spicy_level:String(v.spicy_level||'').toLowerCase(),
                    note: normalizeNote(v.note || ''),
                    custom_options: (v.custom_options && typeof v.custom_options === 'object') ? v.custom_options : {}
                });
            }
        });
        return out;
    };

    const renderCustomRows = (meta, line = null) => {
        customRowsWrap.innerHTML = '';
        if (!Array.isArray(meta.customGroups) || meta.customGroups.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'variant-empty';
            empty.textContent = 'Produk ini tidak punya opsi tambahan.';
            customRowsWrap.appendChild(empty);
            return;
        }
        (meta.customGroups || []).forEach((group) => {
            const row = document.createElement('div');
            row.className = 'modal-row';
            const label = document.createElement('label');
            label.setAttribute('for', `variantCustom_${group.id}`);
            label.textContent = group.label;
            if (group.required) {
                label.textContent += ' *';
            }
            const select = document.createElement('select');
            select.id = `variantCustom_${group.id}`;
            select.dataset.customGroup = group.id;
            const first = document.createElement('option');
            first.value = '';
            first.textContent = `Pilih ${group.label}`;
            select.appendChild(first);
            (group.options || []).forEach((opt) => {
                const optionEl = document.createElement('option');
                optionEl.value = opt.value;
                optionEl.textContent = Number(opt.extra_price || 0) > 0
                    ? `${opt.label} (+${rupiah(Number(opt.extra_price || 0)).replace('Rp ', '')})`
                    : opt.label;
                select.appendChild(optionEl);
            });
            if (line?.custom_options?.[group.id]) {
                select.value = line.custom_options[group.id];
            }
            row.appendChild(label);
            row.appendChild(select);
            customRowsWrap.appendChild(row);
        });
    };

    const syncRows = () => {
        if (!activeCard) return;
        const m = getMeta(activeCard);
        const hot = mTemp.value === 'hot';
        toggle(rowTemp, m.temp);
        toggle(rowSugar, m.sugar && (!m.temp || !hot));
        toggle(rowCup, m.cup && (!m.temp || !hot));
        toggle(rowSpicy, m.spicy);
        if (m.temp && hot) { mSugar.value = ''; if (m.cup) mCup.value = 'regular'; }
    };

    const open = (card, idx = null) => {
        activeCard = card;
        activeEditIndex = idx;
        const m = getMeta(card);
        const line = idx !== null ? linesOf(m.id)[idx] : null;
        modalTitle.textContent = `${line ? 'Ubah' : 'Tambah'} ${m.nama}`;
        modalSub.textContent = `Harga ${rupiah(m.harga)} | Stok ${m.stok}`;
        refillSelect(mTemp, m.temperatureOptions, 'Pilih suhu');
        refillSelect(mSugar, m.sugarOptions, 'Pilih sugar');
        refillSelect(mCup, m.cupOptions, 'Pilih cup');
        refillSelect(mSpicy, m.spicyOptions, 'Pilih level pedas');
        renderCustomRows(m, line);
        mQty.value = String(Math.max(1, Number(line?.qty || 1)));
        mQty.max = String(Math.max(1, m.stok));
        mTemp.value = line?.temperature || '';
        mSugar.value = line?.sugar_level || '';
        mCup.value = line?.cup_size || '';
        mSpicy.value = line?.spicy_level || '';
        if (m.sugar && !mSugar.value) {
            mSugar.value = pickOptionValue(m.sugarOptions, 'normal') || '';
        }
        if (m.cup && !mCup.value) {
            mCup.value = pickOptionValue(m.cupOptions, 'regular') || '';
        }
        mNote.value = line?.note || '';
        btnDelete.style.display = line ? 'inline-flex' : 'none';
        clearErr();
        syncRows();
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        window.requestAnimationFrame(() => {
            mQty.focus();
            mQty.select?.();
        });
    };

    const close = () => {
        activeCard = null;
        activeEditIndex = null;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        clearErr();
    };

    const validate = () => {
        if (!activeCard) return { error:'Produk tidak dipilih.' };
        const m = getMeta(activeCard);
        const qty = Number(mQty.value || 0);
        const t = String(mTemp.value || '');
        let s = String(mSugar.value || '');
        let c = String(mCup.value || '');
        let p = String(mSpicy.value || '');
        const note = normalizeNote(mNote.value || '');
        const custom = {};
        const tempValues = optionValueSet(m.temperatureOptions);
        const sugarValues = optionValueSet(m.sugarOptions);
        const cupValues = optionValueSet(m.cupOptions);
        const spicyValues = optionValueSet(m.spicyOptions);
        if (m.sugar && !s) {
            s = pickOptionValue(m.sugarOptions, 'normal') || '';
        }
        if (m.cup && !c) {
            c = pickOptionValue(m.cupOptions, 'regular') || '';
        }
        if (!Number.isInteger(qty) || qty <= 0) return { error:'Qty harus lebih dari 0.' };
        if (m.temp) {
            if (!tempValues.has(t)) return { error:'Pilih opsi suhu.' };
            if (t === 'hot') { s = ''; c = m.cup ? 'regular' : ''; }
            if (t !== 'hot') {
                if (m.sugar && !sugarValues.has(s)) return { error:'Pilih sugar untuk varian ini.' };
                if (m.cup && !cupValues.has(c)) return { error:'Pilih cup untuk varian ini.' };
            }
        } else {
            if (m.sugar && !sugarValues.has(s)) return { error:'Pilih sugar.' };
            if (m.cup && !cupValues.has(c)) return { error:'Pilih cup.' };
            if (!m.sugar) s = '';
            if (!m.cup) c = '';
        }
        if (m.spicy && !spicyValues.has(p)) return { error:'Pilih level pedas.' };
        if (!m.spicy) p = '';
        for (const group of (m.customGroups || [])) {
            const select = customRowsWrap.querySelector(`[data-custom-group="${group.id}"]`);
            const value = String(select?.value || '');
            if (group.required && value === '') {
                return { error:`Pilih ${group.label}.` };
            }
            if (value !== '') {
                const allowed = (group.options || []).some((opt) => opt.value === value);
                if (!allowed) return { error:`Pilihan ${group.label} tidak valid.` };
                custom[group.id] = value;
            }
        }
        const lines = linesOf(m.id);
        const total = lines.reduce((acc, ln, idx) => acc + ((activeEditIndex !== null && idx === activeEditIndex) ? 0 : Number(ln.qty || 0)), 0) + qty;
        if (total > m.stok) return { error:`Total qty ${m.nama} melebihi stok ${m.stok}.` };
        return {
            uid: activeEditIndex !== null ? lines[activeEditIndex].uid : createUid(),
            id_produk:m.id,
            qty,
            temperature:m.temp?t:'',
            sugar_level:s,
            cup_size:c,
            spicy_level:p,
            note,
            custom_options: custom,
        };
    };

    const renderCard = (card) => {
        const m = getMeta(card);
        const lines = linesOf(m.id);
        const stateEl = card.querySelector('.js-picked-state');
        const list = card.querySelector('.js-picked-list');
        list.innerHTML = '';
        if (lines.length === 0) {
            stateEl.textContent = 'Belum dipilih';
            const e = document.createElement('div');
            e.className = 'picked-empty';
            e.textContent = '-';
            list.appendChild(e);
            return;
        }
        const tQty = lines.reduce((a, l) => a + Number(l.qty || 0), 0);
        stateEl.textContent = `${lines.length} varian | Total qty: ${tQty}`;
        lines.forEach((line, idx) => {
            const row = document.createElement('div');
            row.className = 'picked-item';
            const info = document.createElement('div');
            info.innerHTML = `<strong>Qty ${line.qty}</strong><div class="opt">${optionLabel(m, line.temperature, line.sugar_level, line.cup_size, line.spicy_level, line.custom_options || {}, line.note || '') || '-'}</div>`;
            const bEdit = document.createElement('button');
            bEdit.type = 'button';
            bEdit.textContent = 'Edit';
            bEdit.addEventListener('click', () => open(card, idx));
            const bDel = document.createElement('button');
            bDel.type = 'button';
            bDel.textContent = 'Hapus';
            bDel.addEventListener('click', () => { const next = [...lines]; next.splice(idx,1); setLines(m.id,next); render(); });
            row.appendChild(info); row.appendChild(bEdit); row.appendChild(bDel);
            list.appendChild(row);
        });
    };

    const rebuildInputs = () => {
        holder.innerHTML = '';
        let i = 0;
        cards.forEach((card) => {
            const m = getMeta(card);
            linesOf(m.id).forEach((line) => {
                const fields = { id_produk:m.id, qty:line.qty, temperature:line.temperature || '', sugar_level:line.sugar_level || '', cup_size:line.cup_size || '', spicy_level:line.spicy_level || '', note:line.note || '' };
                Object.keys(fields).forEach((f) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `items[${i}][${f}]`;
                    input.value = String(fields[f]);
                    holder.appendChild(input);
                });
                Object.entries(line.custom_options || {}).forEach(([groupId, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `items[${i}][custom_options][${groupId}]`;
                    input.value = String(value);
                    holder.appendChild(input);
                });
                i += 1;
            });
        });
    };

    const selectedBundlingMeta = () => {
        const id = String(selectedBundlingInput?.value || '').trim();
        if (id === '') return null;
        return bundlingCatalog.get(id) || null;
    };
    const ensureValidBundlingSelection = () => {
        const id = String(selectedBundlingInput?.value || '').trim();
        if (id === '') return;
        if (!bundlingCatalog.has(id)) {
            if (selectedBundlingInput) selectedBundlingInput.value = '';
            localStorage.removeItem(bundlingStorageKey);
            localStorage.removeItem(bundlingStorageNameKey);
            if (sumBundling) sumBundling.textContent = '-';
        }
    };
    const applyBundlingToCart = (bundle) => {
        if (!bundle || !Array.isArray(bundle.items) || bundle.items.length === 0) return;
        const warnings = [];
        bundle.items.forEach((req) => {
            const productId = Number(req.id_produk || 0);
            const reqQty = Math.max(1, Number(req.qty || 1));
            const card = cardByProductId.get(productId);
            if (!card) {
                warnings.push(`Produk #${productId} tidak ditemukan di daftar kasir.`);
                return;
            }
            const meta = getMeta(card);
            const lines = [...linesOf(productId)];
            const defaultLine = buildBundlingDefaultLine(meta, reqQty);
            const currentQty = lines.reduce((acc, line) => acc + Number(line.qty || 0), 0);
            if (currentQty + reqQty > meta.stok) {
                warnings.push(`Stok ${meta.nama} tidak cukup untuk paket bundling.`);
                return;
            }
            const sameIdx = lines.findIndex((line) => sameOptions(line, defaultLine));
            if (sameIdx >= 0) {
                lines[sameIdx].qty = Number(lines[sameIdx].qty || 0) + reqQty;
            } else {
                lines.push(defaultLine);
            }
            setLines(productId, lines);
        });
        if (warnings.length > 0) {
            stockWarning.hidden = false;
            stockWarning.textContent = warnings.join(' ');
        }
    };

    const qtyByProduk = () => {
        const map = new Map();
        cards.forEach((card) => {
            const m = getMeta(card);
            const totalQty = linesOf(m.id).reduce((acc, line) => acc + Number(line.qty || 0), 0);
            if (totalQty > 0) {
                map.set(m.id, totalQty);
            }
        });
        return map;
    };

    const bundlingApplyCount = (bundle, productQtyMap) => {
        if (!bundle || !Array.isArray(bundle.items) || bundle.items.length === 0) return 0;
        let minPossible = null;
        bundle.items.forEach((req) => {
            const available = Number(productQtyMap.get(Number(req.id_produk)) || 0);
            const possible = Math.floor(available / Math.max(1, Number(req.qty || 1)));
            minPossible = minPossible === null ? possible : Math.min(minPossible, possible);
        });
        return Math.max(0, Number(minPossible || 0));
    };

    const render = () => {
        let tQty = 0, tHarga = 0, pCount = 0;
        const errs = [];
        const summaryRows = [];
        cards.forEach((card) => {
            const m = getMeta(card);
            const lines = linesOf(m.id);
            const qtyPer = lines.reduce((a, l) => a + Number(l.qty || 0), 0);
            if (qtyPer > 0) pCount += 1;
            if (qtyPer > m.stok) errs.push(`${m.nama} melebihi stok (${m.stok})`);
            lines.forEach((l) => {
                const unitPrice = m.harga + calculateBuiltInExtra(m, l) + calculateCustomExtra(m, l.custom_options || {});
                tQty += Number(l.qty || 0);
                tHarga += Number(l.qty || 0) * unitPrice;
                summaryRows.push({
                    nama: m.nama,
                    qty: Number(l.qty || 0),
                    harga: unitPrice,
                    opsi: optionLabel(m, l.temperature, l.sugar_level, l.cup_size, l.spicy_level, l.custom_options || {}, l.note || ''),
                });
            });
            renderCard(card);
        });
        const selectedBundle = selectedBundlingMeta();
        let estimatedBundlingDiscount = 0;
        if (selectedBundle) {
            const productQtyMap = qtyByProduk();
            const applies = bundlingApplyCount(selectedBundle, productQtyMap);
            const normalBundlePrice = (selectedBundle.items || []).reduce((acc, it) => {
                const card = cardByProductId.get(Number(it.id_produk || 0));
                if (!card) return acc;
                return acc + (getMeta(card).harga * Number(it.qty || 0));
            }, 0);
            const bundlePrice = Number(selectedBundle.price || 0);
            const discountPerBundle = Math.max(0, normalBundlePrice - bundlePrice);
            estimatedBundlingDiscount = Math.max(0, applies * discountPerBundle);
            const composition = selectedBundle.items
                .map((it) => `${it.nama_produk || 'Produk'} x${it.qty}`)
                .join(' + ');
            const desc = applies > 0
                ? `Komposisi: ${composition} | Terpakai ${applies} paket`
                : `Komposisi: ${composition} | Belum memenuhi syarat paket`;
            summaryRows.push({
                nama: `Paket Bundling: ${selectedBundle.name}`,
                qty: 1,
                harga: 0,
                opsi: desc,
            });
            if (estimatedBundlingDiscount > 0) {
                summaryRows.push({
                    nama: 'Potongan Bundling',
                    qty: 1,
                    harga: -estimatedBundlingDiscount,
                    opsi: `Estimasi diskon ${selectedBundle.name}`,
                });
            }
            if (sumBundling) {
                sumBundling.textContent = applies > 0
                    ? `${selectedBundle.name} (x${applies})`
                    : selectedBundle.name;
            }
        } else if (sumBundling) {
            sumBundling.textContent = '-';
        }
        rebuildInputs();
        sumQty.textContent = tQty;
        sumProduk.textContent = pCount;
        sumTotal.textContent = rupiah(Math.max(0, tHarga - estimatedBundlingDiscount));
        sumItems.innerHTML = '';
        if (summaryRows.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'summary-empty';
            empty.textContent = 'Belum ada produk dipilih.';
            sumItems.appendChild(empty);
        } else {
            summaryRows.forEach((row) => {
                const lineTotal = row.qty * row.harga;
                const item = document.createElement('div');
                item.className = 'summary-item';
                item.innerHTML = `<div class="summary-item-top"><span class="summary-item-name">${row.nama}</span><span>${row.qty}x</span></div>
                                  <div class="summary-item-opt">${row.opsi || '-'}</div>
                                  <div class="summary-item-sub">${rupiah(lineTotal)}</div>`;
                sumItems.appendChild(item);
            });
        }
        if (errs.length > 0) {
            stockWarning.hidden = false;
            stockWarning.textContent = errs.join(', ');
            checkoutBtn.disabled = true;
            return;
        }
        if (tQty <= 0) {
            stockWarning.hidden = false;
            stockWarning.textContent = 'Pilih minimal 1 produk sebelum lanjut checkout.';
            checkoutBtn.disabled = true;
            return;
        }
        stockWarning.hidden = true;
        stockWarning.textContent = '';
        checkoutBtn.disabled = false;
    };

    const hydrate = () => {
        cards.forEach((c) => setLines(getMeta(c).id, []));
        const source = (initialOldItems && Object.keys(initialOldItems).length > 0) ? parseRaw(initialOldItems) : parseRaw(initialSessionItems);
        source.forEach((line) => {
            const c = cards.find((x) => Number(x.dataset.id) === Number(line.id_produk));
            if (!c) return;
            const arr = linesOf(line.id_produk);
            arr.push({
                uid:createUid(),
                qty:Number(line.qty || 0),
                temperature:line.temperature,
                sugar_level:line.sugar_level,
                cup_size:line.cup_size,
                spicy_level:line.spicy_level,
                note: normalizeNote(line.note || ''),
                custom_options: line.custom_options || {},
            });
            setLines(line.id_produk, arr);
        });
    };

    cards.forEach((card) => {
        const m = getMeta(card);
        card.querySelector('.js-open-modal')?.addEventListener('click', () => open(card));
        card.querySelector('.js-clear-item')?.addEventListener('click', () => { setLines(m.id, []); render(); });
    });
    bundlingButtons.forEach((btn) => {
        const id = String(btn.dataset.bundlingId || '').trim();
        if (id !== '') {
            bundlingCatalog.set(id, {
                id,
                name: String(btn.dataset.bundlingName || '').trim() || id,
                price: Number(btn.dataset.bundlingPrice || 0),
                items: parseBundlingItems(btn.dataset.bundlingItems || '[]'),
            });
        }
        btn.addEventListener('click', () => {
            const id = String(btn.dataset.bundlingId || '').trim();
            const name = String(btn.dataset.bundlingName || '').trim();
            if (!id) return;
            localStorage.setItem(bundlingStorageKey, id);
            localStorage.setItem(bundlingStorageNameKey, name);
            if (selectedBundlingInput) selectedBundlingInput.value = id;
            if (sumBundling) sumBundling.textContent = name || id;
            const bundle = bundlingCatalog.get(id);
            applyBundlingToCart(bundle);
            render();
        });
    });
    bundlingResetButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = String(btn.dataset.bundlingId || '').trim();
            if (selectedBundlingInput && String(selectedBundlingInput.value || '').trim() === id) {
                selectedBundlingInput.value = '';
            }
            localStorage.removeItem(bundlingStorageKey);
            localStorage.removeItem(bundlingStorageNameKey);
            if (sumBundling) sumBundling.textContent = '-';
            render();
        });
    });
    ensureValidBundlingSelection();
    mTemp.addEventListener('change', syncRows);
    btnSave.addEventListener('click', () => {
        const res = validate();
        if (res.error) { showErr(res.error); return; }
        const lines = linesOf(res.id_produk);
        if (activeEditIndex !== null) lines[activeEditIndex] = res; else lines.push(res);
        setLines(res.id_produk, lines);
        close(); render();
    });
    btnDelete.addEventListener('click', () => {
        if (!activeCard || activeEditIndex === null) return;
        const m = getMeta(activeCard);
        const lines = linesOf(m.id);
        lines.splice(activeEditIndex, 1);
        setLines(m.id, lines);
        close(); render();
    });
    btnClose.addEventListener('click', close);
    btnCancel.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) {
            close();
        }
    });
    form.addEventListener('submit', (e) => { render(); if (checkoutBtn.disabled) e.preventDefault(); });

    hydrate();
    render();
})();
</script>
@endsection
