@extends('layouts.app')

@section('title', 'Riwayat Transaksi')

@section('content')
<div class="container">
    {{-- Page Header - Shadcn Style --}}
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Operasional</div>
            <h1>Riwayat Transaksi</h1>
            <p>Pantau semua transaksi penjualan dengan filter berdasarkan periode dan kasir.</p>
        </div>
        <div class="admin-page-actions">
            <span class="admin-chip soft">{{ number_format($pesanan->total(), 0, ',', '.') }} Transaksi</span>
            <span class="admin-chip">Hal {{ $pesanan->currentPage() }}/{{ $pesanan->lastPage() }}</span>
        </div>
    </div>

    {{-- Quick Filter Card --}}
    @php
        $activeOperasional = (string) ($filters['operasional'] ?? '');
        $activeChannel = (string) ($filters['channel'] ?? '');
    @endphp

    <div class="admin-section-card" style="margin-bottom: 1.5rem;">
        <div class="admin-card-header">
            <div>
                <h3 class="admin-card-title">
                    {{ $activeChannel === 'app' ? '📱 Transaksi Aplikasi' : '🛒 Transaksi Kasir' }}
                </h3>
                <p class="admin-card-description">Filter cepat berdasarkan periode operasional</p>
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem;">
            <a class="btn {{ $activeOperasional === 'today' ? 'btn-default' : 'btn-outline' }}"
               href="{{ route('transaksi.index', $operasionalQuickFilters['today']['query']) }}">
                {{ $operasionalQuickFilters['today']['label'] }}
            </a>
            <a class="btn {{ $activeOperasional === 'yesterday' ? 'btn-default' : 'btn-outline' }}"
               href="{{ route('transaksi.index', $operasionalQuickFilters['yesterday']['query']) }}">
                {{ $operasionalQuickFilters['yesterday']['label'] }}
            </a>
            @if($activeChannel === 'app')
                <a class="btn btn-outline"
                   href="{{ route('transaksi.index', array_filter(array_merge($filters, ['channel' => null]), fn ($value) => $value !== null && $value !== '')) }}">
                    Ke Transaksi Kasir
                </a>
            @else
                <a class="btn btn-outline"
                   href="{{ route('transaksi.index', array_merge($filters, ['channel' => 'app'])) }}">
                    Ke Transaksi Aplikasi
                </a>
            @endif
        </div>

        <div class="admin-section-card-note">
            <p>💡 Periode mengikuti jam operasional (reset harian) dari pengaturan sistem.</p>
        </div>
    </div>

    {{-- Filter & Table Card --}}
    <div class="admin-section-card">
        {{-- Filter Section --}}
        <div class="admin-card-header admin-card-header-bordered">
            <div>
                <h3 class="admin-card-title">Filter Transaksi</h3>
                <p class="admin-card-description">Cari transaksi berdasarkan periode dan kasir</p>
            </div>
            <a class="btn-sm btn-default"
               href="{{ route('transaksi.export_excel', ['tanggal_awal' => $filters['tanggal_awal'] ?? null, 'tanggal_akhir' => $filters['tanggal_akhir'] ?? null, 'id_karyawan' => $filters['id_karyawan'] ?? null, 'operasional' => $filters['operasional'] ?? null, 'channel' => $filters['channel'] ?? null]) }}">
                📥 Export Excel
            </a>
        </div>

        <form method="get" action="{{ route('transaksi.index') }}" class="admin-filter-form">
            @if(! empty($filters['operasional']))
                <input type="hidden" name="operasional" value="{{ $filters['operasional'] }}">
            @endif
            @if(! empty($filters['channel']))
                <input type="hidden" name="channel" value="{{ $filters['channel'] }}">
            @endif

            <div class="admin-filter-grid">
                @if(auth()->user()->role === 'admin')
                    <div>
                        <label>Periode Tanggal</label>
                        <input type="hidden" id="trx_awal" name="tanggal_awal" value="{{ $filters['tanggal_awal'] ?? '' }}">
                        <input type="hidden" id="trx_akhir" name="tanggal_akhir" value="{{ $filters['tanggal_akhir'] ?? '' }}">
                        <button type="button"
                            class="btn btn-outline"
                            style="width: 100%; justify-content: flex-start;"
                            data-daterange-trigger
                            data-start="#trx_awal"
                            data-end="#trx_akhir">
                            @if(!empty($filters['tanggal_awal']) && !empty($filters['tanggal_akhir']))
                                📅 {{ \Carbon\Carbon::parse($filters['tanggal_awal'])->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($filters['tanggal_akhir'])->translatedFormat('d M Y') }}
                            @else
                                📅 Pilih Periode
                            @endif
                        </button>
                    </div>
                @endif

                <div>
                    <label>Kasir</label>
                    <select name="id_karyawan" class="admin-filter-select">
                        <option value="">Semua kasir</option>
                        <option value="admin" @selected(($filters['id_karyawan'] ?? null) === 'admin')>Admin</option>
                        @foreach($karyawan as $item)
                            <option value="{{ $item->id_karyawan }}" @selected(($filters['id_karyawan'] ?? null) == $item->id_karyawan)>
                                {{ $item->nama_karyawan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="admin-filter-actions">
                    <button class="btn btn-default" type="submit">Filter</button>
                    <a class="btn btn-secondary" href="{{ route('transaksi.index') }}">Reset</a>
                </div>
            </div>
        </form>

        {{-- Stats Chips --}}
        <div class="admin-stats-row">
            <div class="admin-stats-chip">
                <span>Total Data:</span>
                <strong>{{ number_format($pesanan->total(), 0, ',', '.') }}</strong>
            </div>
            <div class="admin-stats-chip">
                <span>Halaman:</span>
                <strong>{{ $pesanan->currentPage() }}/{{ $pesanan->lastPage() }}</strong>
            </div>
            <div class="admin-stats-chip">
                <span>Per Halaman:</span>
                <strong>{{ $pesanan->perPage() }}</strong>
            </div>
        </div>

        {{-- Alerts --}}
        @if (session('success'))
            <div class="alert ok">✓ {{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err">
                @foreach($errors->all() as $error)
                    <div>⚠ {{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{-- Table --}}
        <div class="admin-table-wrapper" style="margin-top: 1rem;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID Pesanan</th>
                        <th>Waktu</th>
                        <th>Pelanggan</th>
                        <th>Kasir</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th class="admin-table-th-right">Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($pesanan as $item)
                    <tr>
                        <td>
                            <span class="id-chip">#{{ $item->id_pesanan }}</span>
                        </td>
                        <td class="u-text-sm">{{ $item->waktu_pembayaran }}</td>
                        <td>
                            @php
                                $isAdminKasir = trim((string) ($item->kasir_label ?? '')) !== '';
                            @endphp
                            {{ $item->pelanggan?->nama ?? ($isAdminKasir ? '👤 Admin' : '👤 Umum') }}
                        </td>
                        <td>
                            <div class="kasir-cell">
                                @if($isAdminKasir)
                                    <span class="admin-tag">Admin</span>
                                @else
                                    <span style="font-weight: 500;">{{ $item->karyawan?->nama_karyawan ?? '-' }}</span>
                                @endif
                                @if($item->shift)
                                    <span class="shift-meta">Shift {{ (int) $item->shift->shift_ke }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @php
                                $metode = strtolower((string) $item->metode_pembayaran);
                                $deliveryMethods = ['shopeefood', 'gofood', 'grabfood'];
                                $badgeClass = '';
                                if (in_array($metode, ['cash','qris','debit'], true)) {
                                    $badgeClass = $metode;
                                } elseif (in_array($metode, $deliveryMethods, true)) {
                                    $badgeClass = 'delivery';
                                }
                            @endphp
                            <span class="admin-card-badge trx-badge-uppercase {{ $badgeClass }}">
                                {{ $item->metode_pembayaran }}
                            </span>
                        </td>
                        <td>
                            @php
                                $status = strtolower((string) $item->status_pembayaran);
                            @endphp
                            <span class="admin-card-badge {{ $status === 'lunas' ? 'ok' : ($status === 'dibatalkan' ? 'err' : '') }}">
                                {{ $item->status_pembayaran }}
                            </span>
                        </td>
                        <td class="num money">
                            Rp {{ number_format((float) $item->total_harga, 0, ',', '.') }}
                        </td>
                        <td>
                            <div class="admin-table-actions">
                                <a class="btn-sm btn-outline" href="{{ route('transaksi.show', $item) }}">Detail</a>
                                @if(auth()->user()->role === 'admin')
                                    @if ($item->status_pembayaran !== 'dibatalkan')
                                        <form class="inline" method="post" action="{{ route('transaksi.batal', $item) }}"
                                              onsubmit="return confirm('Batalkan transaksi ini? Stok akan dikembalikan.')">
                                            @csrf
                                            <button class="btn-sm btn-destructive" type="submit">Batal</button>
                                        </form>
                                    @else
                                        <form class="inline" method="post" action="{{ route('transaksi.restore', $item) }}"
                                              onsubmit="return confirm('Restore transaksi ini? Stok akan dipotong kembali.')">
                                            @csrf
                                            <button class="btn-sm btn-secondary" type="submit">Restore</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="admin-table-empty">
                            <div class="admin-table-empty-inner">
                                <div class="admin-table-empty-icon">📋</div>
                                <p class="admin-table-empty-message">Belum ada transaksi.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="admin-pagination-wrap">
            {{ $pesanan->links() }}

            @if($pesanan->lastPage() > 1)
                <form method="get" action="{{ route('transaksi.index') }}" class="page-jump-form">
                    @if(! empty($filters['operasional']))
                        <input type="hidden" name="operasional" value="{{ $filters['operasional'] }}">
                    @endif
                    @if(! empty($filters['tanggal_awal']))
                        <input type="hidden" name="tanggal_awal" value="{{ $filters['tanggal_awal'] }}">
                    @endif
                    @if(! empty($filters['tanggal_akhir']))
                        <input type="hidden" name="tanggal_akhir" value="{{ $filters['tanggal_akhir'] }}">
                    @endif
                    @if(! empty($filters['id_karyawan']))
                        <input type="hidden" name="id_karyawan" value="{{ $filters['id_karyawan'] }}">
                    @endif

                    <label>Lompat ke halaman:</label>
                    <input type="number"
                           name="page"
                           min="1"
                           max="{{ $pesanan->lastPage() }}"
                           value="{{ $pesanan->currentPage() }}"
                           class="u-w-120">
                    <button class="btn-sm btn-default" type="submit">Buka</button>
                    <span class="u-text-sm u-text-muted">(Maks {{ $pesanan->lastPage() }})</span>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection