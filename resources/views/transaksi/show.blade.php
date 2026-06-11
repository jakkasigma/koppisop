@extends('layouts.app')

@section('title', 'Detail Transaksi #' . $transaksi->id_pesanan)

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Transaksi</div>
            <h1>Detail Transaksi #{{ $transaksi->id_pesanan }}</h1>
            <p>Lihat rincian pesanan, promo, pajak, dan status pembayaran secara lengkap.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-primary" href="{{ route('transaksi.nota', ['transaksi' => $transaksi, 'autoprint' => 1]) }}" target="_blank" rel="noopener">Print Nota</a>
            <a class="btn-neutral" href="{{ route('transaksi.index') }}">Kembali</a>
        </div>
    </div>

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

    <div class="panel">
        <div class="meta-grid">
            <div class="meta-card">
                <span class="meta-label">Waktu</span>
                <span class="meta-value">{{ $transaksi->waktu_pembayaran }}</span>
            </div>
            <div class="meta-card">
                <span class="meta-label">Pelanggan</span>
                <span class="meta-value">{{ $transaksi->pelanggan?->nama ?? ($transaksi->kasir_label ? 'Admin' : 'Umum') }}</span>
            </div>
            <div class="meta-card">
                <span class="meta-label">Kasir</span>
                <span class="meta-value">{{ $transaksi->kasir_label ?: ($transaksi->karyawan?->nama_karyawan ?? '-') }}</span>
            </div>
            @if($transaksi->shift)
                <div class="meta-card">
                    <span class="meta-label">Shift</span>
                    <span class="meta-value">
                        <span class="shift-pill">Shift {{ (int) $transaksi->shift->shift_ke }}</span>
                        @if(!empty($transaksi->no_urut_shift))
                            <span class="shift-order">No. {{ str_pad((string) ((int) $transaksi->no_urut_shift), 3, '0', STR_PAD_LEFT) }}</span>
                        @endif
                    </span>
                </div>
            @endif
            <div class="meta-card">
                <span class="meta-label">Metode</span>
                <span class="meta-value">{{ strtoupper((string) $transaksi->metode_pembayaran) }}</span>
            </div>
            <div class="meta-card">
                <span class="meta-label">Status</span>
                @php
                    $status = strtolower((string) $transaksi->status_pembayaran);
                @endphp
                <span>
                    <span class="status-pill {{ $status === 'dibatalkan' ? 'cancel' : 'ok' }}">
                        {{ strtoupper((string) $transaksi->status_pembayaran) }}
                    </span>
                </span>
            </div>
            @if(trim((string) ($transaksi->catatan_pesanan ?? '')) !== '')
                <div class="meta-card">
                    <span class="meta-label">Catatan</span>
                    <span class="meta-value">{{ $transaksi->catatan_pesanan }}</span>
                </div>
            @endif
        </div>

        @if(auth()->user()->role === 'admin')
            <div class="admin-actions">
                @if ($transaksi->status_pembayaran !== 'dibatalkan')
                    <form method="post" action="{{ route('transaksi.batal', $transaksi) }}" onsubmit="return confirm('Batalkan transaksi ini? Stok akan dikembalikan.')">
                        @csrf
                        <button class="btn-danger btn-mini" type="submit">Batalkan Transaksi</button>
                    </form>
                @else
                    <form method="post" action="{{ route('transaksi.restore', $transaksi) }}" onsubmit="return confirm('Restore transaksi ini? Stok akan dipotong kembali.')">
                        @csrf
                        <button class="btn-neutral btn-mini" type="submit">Restore Transaksi</button>
                    </form>
                @endif
            </div>
        @endif

        <div class="table-wrap">
            <table>
                <thead><tr><th>Produk</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th></tr></thead>
                <tbody>
                @forelse($transaksi->detail as $item)
                    @php
                        $opsi = [];
                        if ($item->temperature) {
                            $opsi[] = match ($item->temperature) {
                                'hot' => 'Hot',
                                'less_ice' => 'Less Es',
                                'ice' => 'Es',
                                default => ucwords(str_replace('_', ' ', (string) $item->temperature)),
                            };
                        }
                        if ($item->sugar_level) {
                            $opsi[] = match ($item->sugar_level) {
                                'none' => 'No Sugar',
                                'less' => 'Less Sugar',
                                'normal' => 'Normal Sugar',
                                default => ucwords(str_replace('_', ' ', (string) $item->sugar_level)),
                            };
                        }
                        if ($item->cup_size) {
                            $opsi[] = match ($item->cup_size) {
                                'large' => 'Cup Large',
                                'regular' => 'Cup Regular',
                                default => ucwords(str_replace('_', ' ', (string) $item->cup_size)),
                            };
                        }
                        if ($item->spicy_level) {
                            $opsi[] = match ($item->spicy_level) {
                                'extra_spicy' => 'Extra Spicy',
                                'spicy' => 'Spicy',
                                'non_spicy' => 'Non Spicy',
                                default => ucwords(str_replace('_', ' ', (string) $item->spicy_level)),
                            };
                        }
                        if (is_array($item->selected_options ?? null)) {
                            $catatanItem = null;
                            foreach ($item->selected_options as $selectedKey => $selectedValue) {
                                if (in_array((string) $selectedKey, ['note', '_note'], true)) {
                                    if (is_string($selectedValue)) {
                                        $catatanItem = preg_replace('/\s+/', ' ', trim($selectedValue));
                                    }
                                    continue;
                                }
                                if (! is_string($selectedValue) || trim($selectedValue) === '') {
                                    continue;
                                }
                                $opsi[] = ucwords(str_replace('_', ' ', $selectedValue));
                            }
                            if (! empty($catatanItem)) {
                                $opsi[] = 'Catatan: ' . $catatanItem;
                            }
                        }
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $item->produk?->nama_produk ?? 'Produk dihapus' }}</strong>
                            @if(!empty($opsi))
                                <div class="hint">{{ implode(' | ', $opsi) }}</div>
                            @endif
                        </td>
                        <td>{{ $item->jumlah }}</td>
                        <td>Rp {{ number_format((float) $item->harga_satuan, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $item->harga_satuan * (int) $item->jumlah, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Detail transaksi kosong.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @php
            $subtotalHarga = $transaksi->subtotal_harga !== null ? (float) $transaksi->subtotal_harga : (float) $transaksi->total_harga;
            $diskonNominal = (float) ($transaksi->diskon_nominal ?? 0);
            $pajakNominal = (float) ($transaksi->pajak_nominal ?? 0);
            $pajakPersen = (float) ($transaksi->pajak_persen ?? 0);
            $totalSebelumPajak = max(0, $subtotalHarga - $diskonNominal);
            $totalJikaPajakKePembeli = $totalSebelumPajak + $pajakNominal;
            $pajakDibebankanKePembeli = abs($totalJikaPajakKePembeli - (float) $transaksi->total_harga) < 0.01;
            $diskonTipe = (string) ($transaksi->diskon_tipe ?? '');
            $diskonTipeLabel = match ($diskonTipe) {
                'persen' => 'Persen',
                'nominal' => 'Nominal',
                'harga_kategori' => 'Harga Spesial',
                'bundling' => 'Bundling',
                default => 'Promo',
            };
            $diskonNilaiLabel = match ($diskonTipe) {
                'persen' => rtrim(rtrim(number_format((float) ($transaksi->diskon_nilai ?? 0), 2, '.', ''), '0'), '.') . '%',
                'bundling', 'nominal', 'harga_kategori' => 'Rp ' . number_format((float) ($transaksi->diskon_nilai ?? 0), 0, ',', '.'),
                default => null,
            };
        @endphp
        <div class="total">
            <div class="row"><span>Subtotal</span><strong>Rp {{ number_format($subtotalHarga, 0, ',', '.') }}</strong></div>
            @if($diskonNominal > 0)
                <div class="row"><span>Promo</span><strong>{{ $transaksi->diskon_nama ?: '-' }}</strong></div>
                <div class="row"><span>Tipe Promo</span><strong>{{ $diskonTipeLabel }}{{ $diskonNilaiLabel ? ' (' . $diskonNilaiLabel . ')' : '' }}</strong></div>
                <div class="row"><span>Potongan</span><strong>- Rp {{ number_format($diskonNominal, 0, ',', '.') }}</strong></div>
            @endif
            @if($pajakNominal > 0)
                <div class="row">
                    <span>Pajak{{ $pajakPersen > 0 ? ' (' . rtrim(rtrim(number_format($pajakPersen, 2, '.', ''), '0'), '.') . '%)' : '' }}</span>
                    @if($pajakDibebankanKePembeli)
                        <strong>+ Rp {{ number_format($pajakNominal, 0, ',', '.') }}</strong>
                    @else
                        <strong>Rp {{ number_format($pajakNominal, 0, ',', '.') }} (Ditanggung Cafe)</strong>
                    @endif
                </div>
            @endif
            <div class="row grand"><span>Total</span><strong>Rp {{ number_format((float) $transaksi->total_harga, 0, ',', '.') }}</strong></div>
        </div>
    </div>
</div>
@endsection

