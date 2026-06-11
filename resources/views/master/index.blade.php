@extends('layouts.app')

@section('title', 'Master Data')

@section('content')
<div class="container">

    {{-- ── Page Head ─────────────────────────────────── --}}
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Master Data</h1>
            <p>Kelola data inti cafe. Ringkasan ini membantu cek kesiapan menu dan data sebelum operasional.</p>
            <div class="u-mt-6 u-flex u-gap-8 u-flex-wrap">
                <span class="admin-chip soft">Update: {{ $today }}</span>
                @if(($stokMenipisCount ?? 0) > 0)
                    <span class="admin-chip">Stok menipis: {{ (int) $stokMenipisCount }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- ── KPI Strip ─────────────────────────────────── --}}
    <div class="admin-kpi-grid">
        <div class="admin-kpi-card">
            <div class="label">Produk</div>
            <div class="value">{{ (int) ($totalProduk ?? 0) }}</div>
            <div class="muted u-text-sm">Total menu aktif di sistem.</div>
        </div>
        <div class="admin-kpi-card">
            <div class="label">Kategori</div>
            <div class="value">{{ (int) ($totalKategori ?? 0) }}</div>
            <div class="muted u-text-sm">Pengelompokan menu kasir.</div>
        </div>
        <div class="admin-kpi-card">
            <div class="label">Promo Aktif</div>
            <div class="value">{{ (int) ($diskonAktif ?? 0) + (int) ($bundlingAktif ?? 0) }}</div>
            <div class="muted u-text-sm">Diskon: {{ (int) ($diskonAktif ?? 0) }}, Bundling: {{ (int) ($bundlingAktif ?? 0) }}.</div>
        </div>
        <div class="admin-kpi-card">
            <div class="label">Opsi Kasir Aktif</div>
            <div class="value">{{ (int) ($opsiKasirAktif ?? 0) }}</div>
            <div class="muted u-text-sm">Opsi tambahan dinamis.</div>
        </div>
    </div>

    {{-- ── Cards Grid ────────────────────────────────── --}}
    <div class="master-cards-grid">

        {{-- Stok Menipis --}}
        <div class="admin-soft-card">
            <div class="panel-head">
                <h2>Stok Menipis</h2>
                <span class="panel-sub">&le; {{ (int) ($lowStockThreshold ?? 5) }}</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th class="num">Stok</th>
                            <th class="num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($stokMenipis ?? []) as $p)
                            <tr>
                                <td><strong>{{ $p->nama_produk }}</strong></td>
                                <td class="muted">{{ $p->kategori?->nama_kategori ?? '-' }}</td>
                                <td class="num {{ (int) ($p->stok ?? 0) <= 0 ? 'danger' : '' }}">{{ (int) ($p->stok ?? 0) }}</td>
                                <td class="num">
                                    <a class="btn-neutral btn-mini" href="{{ route('produk.edit', $p->id_produk) }}">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="u-text-muted">Tidak ada stok menipis saat ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Produk & Kategori --}}
        <div class="admin-soft-card">
            <div class="panel-head">
                <h2>Produk &amp; Kategori</h2>
                <span class="panel-sub">Menu, harga, stok</span>
            </div>
            <div class="admin-grid-secondary-links">
                <a class="link-card" href="{{ route('produk.index') }}">
                    <div class="link-title">Produk</div>
                    <div class="link-desc">Tambah/edit produk, opsi kasir, stok.</div>
                </a>
                <a class="link-card" href="{{ route('kategori.index') }}">
                    <div class="link-title">Kategori</div>
                    <div class="link-desc">Kelompokkan produk agar rapi.</div>
                </a>
                <a class="link-card" href="{{ route('master_opsi_kasir.index') }}">
                    <div class="link-title">Opsi Kasir</div>
                    <div class="link-desc">Kelola opsi tambahan dinamis.</div>
                </a>
            </div>
        </div>

        {{-- Promo & Struk --}}
        <div class="admin-soft-card">
            <div class="panel-head">
                <h2>Promo &amp; Struk</h2>
                <span class="panel-sub">Diskon, bundling, tampilan nota</span>
            </div>
            <div class="admin-grid-secondary-links">
                <a class="link-card" href="{{ route('diskon.index') }}">
                    <div class="link-title">Diskon</div>
                    <div class="link-desc">Persen, nominal, harga khusus.</div>
                </a>
                <a class="link-card" href="{{ route('bundling.index') }}">
                    <div class="link-title">Promo Bundling</div>
                    <div class="link-desc">Paket bundling dinamis.</div>
                </a>
                <a class="link-card" href="{{ route('struk_setting.edit') }}">
                    <div class="link-title">Pengaturan Struk</div>
                    <div class="link-desc">Header/footer, identitas cafe.</div>
                </a>
            </div>
        </div>

        {{-- Pelanggan & Karyawan --}}
        <div class="admin-soft-card">
            <div class="panel-head">
                <h2>Pelanggan &amp; Karyawan</h2>
                <span class="panel-sub">Data pendukung transaksi</span>
            </div>
            <div class="admin-grid-secondary-links">
                <a class="link-card" href="{{ route('pelanggan.index') }}">
                    <div class="link-title">Pelanggan</div>
                    <div class="link-desc">Total: {{ (int) ($totalPelanggan ?? 0) }}.</div>
                </a>
                <a class="link-card" href="{{ route('karyawan.index') }}">
                    <div class="link-title">Karyawan</div>
                    <div class="link-desc">Total: {{ (int) ($totalKaryawan ?? 0) }}.</div>
                </a>
            </div>
        </div>

        {{-- Cepat --}}
        <div class="admin-soft-card">
            <div class="panel-head">
                <h2>Shortcut</h2>
                <span class="panel-sub">Akses cepat operasional</span>
            </div>
            <div class="admin-grid-secondary-links">
                <a class="link-card" href="{{ route('kasir.index') }}">
                    <div class="link-title">Masuk Kasir</div>
                    <div class="link-desc">Mulai transaksi.</div>
                </a>
                <a class="link-card" href="{{ route('transaksi.index') }}">
                    <div class="link-title">Riwayat Transaksi</div>
                    <div class="link-desc">Cek detail transaksi.</div>
                </a>
            </div>
        </div>

    </div>{{-- /.master-cards-grid --}}

</div>
@endsection
