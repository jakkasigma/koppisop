@extends('layouts.app')

@section('title', 'Struk #' . $transaksi->id_pesanan)

@section('content')
<div class="container">
    @php
        $setting = $strukSetting ?? null;
        $modeTemplate = (string) ($setting?->mode_template ?? 'global');
        $viewerRole = (string) (auth()->user()?->role ?? 'kasir');
        $pakaiTemplateRole = $modeTemplate === 'per_role' && in_array($viewerRole, ['admin', 'kasir'], true);
        $suffixRole = $pakaiTemplateRole ? '_' . $viewerRole : '';
        $namaTokoRole = trim((string) data_get($setting, 'nama_toko' . $suffixRole, ''));
        $alamatTokoRole = trim((string) data_get($setting, 'alamat_toko' . $suffixRole, ''));
        $headerTextRole = trim((string) data_get($setting, 'header_text' . $suffixRole, ''));
        $footerTextRole = trim((string) data_get($setting, 'footer_text' . $suffixRole, ''));
        $namaToko = $namaTokoRole !== '' ? $namaTokoRole : trim((string) ($setting?->nama_toko ?? 'KopiSop'));
        $alamatToko = $alamatTokoRole !== '' ? $alamatTokoRole : trim((string) ($setting?->alamat_toko ?? ''));
        $headerText = $headerTextRole !== '' ? $headerTextRole : trim((string) ($setting?->header_text ?? ''));
        $footerText = $footerTextRole !== '' ? $footerTextRole : trim((string) ($setting?->footer_text ?? ''));
        $namaCabang = trim((string) ($setting?->nama_cabang ?? ''));
        $logoPath = (bool) ($setting?->show_logo ?? false) ? trim((string) ($setting?->logo_path ?? '')) : '';
        $logoMaxWidth = max(60, min(220, (int) ($setting?->logo_max_width ?? 120)));
        $showKodeNota = (bool) ($setting?->show_kode_nota ?? true);
        $showIdPesanan = (bool) ($setting?->show_id_pesanan ?? true);
        $showWaktu = (bool) ($setting?->show_waktu ?? true);
        $showPelanggan = (bool) ($setting?->show_pelanggan ?? true);
        $showKasir = (bool) ($setting?->show_kasir ?? true);
        $showMetode = (bool) ($setting?->show_metode ?? true);
        $publicOrderId = $transaksi->no_urut_shift
            ? ('S' . (int) ($transaksi->shift?->shift_ke ?? 0) . '-' . str_pad((string) $transaksi->no_urut_shift, 3, '0', STR_PAD_LEFT))
            : ('#' . $transaksi->id_pesanan);
    @endphp
    <div class="actions">
        <a class="btn-primary" href="{{ route('kasir.nota', ['transaksi' => $transaksi, 'autoprint' => 1]) }}" target="_blank" rel="noopener">Print Struk</a>
        <a class="btn-neutral" href="{{ route('kasir.checker', ['transaksi' => $transaksi, 'paper' => auth()->user()->paper_preference ?? '80']) }}" target="_blank" rel="noopener">Print Checker</a>
        <a class="btn-neutral" href="{{ route(($kasirRoutePrefix ?? 'kasir') . '.index') }}">Transaksi Baru</a>
        @if(auth()->user()->role === 'admin')
            <a class="btn-neutral" href="{{ route('transaksi.show', $transaksi) }}">Lihat Detail Admin</a>
        @endif
    </div>

    <div class="paper">
        <div class="brand">
            @if($logoPath !== '')
                <img src="{{ asset('storage/' . $logoPath) }}" alt="Logo" class="receipt-logo" style="max-width:{{ $logoMaxWidth }}px;">
            @endif
            <h1>{{ $namaToko !== '' ? $namaToko : 'KopiSop' }}</h1>
            @if($namaCabang !== '')
                <p>{{ $namaCabang }}</p>
            @endif
            @if($alamatToko !== '')
                <p>{{ $alamatToko }}</p>
            @endif
            <p>Struk Pembayaran</p>
            @if($headerText !== '')
                <p class="u-pre-line">{{ $headerText }}</p>
            @endif
        </div>

        <div class="meta">
            @if($showKodeNota)<div><span class="muted">Kode Nota:</span> {{ $kodeNota }}</div>@endif
            @if($showIdPesanan)<div><span class="muted">No Pesanan:</span> {{ $publicOrderId }}</div>@endif
            @if($showWaktu)<div><span class="muted">Waktu:</span> {{ $transaksi->waktu_pembayaran }}</div>@endif
            @if($showKasir)
                <div><span class="muted">Kasir:</span> {{ $transaksi->kasir_label ?: ($transaksi->karyawan?->nama_karyawan ?? '-') }}</div>
            @endif
            @if($showPelanggan)<div><span class="muted">Pelanggan:</span> {{ $transaksi->pelanggan?->nama ?? ($transaksi->kasir_label ? 'Admin' : 'Umum') }}</div>@endif
            @if($showMetode)<div><span class="muted">Metode:</span> {{ strtoupper($transaksi->metode_pembayaran) }}</div>@endif
            @if(trim((string) ($transaksi->catatan_pesanan ?? '')) !== '')
                <div><span class="muted">Catatan:</span> {{ $transaksi->catatan_pesanan }}</div>
            @endif
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Produk</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th></tr>
                </thead>
                <tbody>
                @foreach($transaksi->detail as $item)
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
                                        $catatanItem = preg_replace('/\s+/', ' ', trim($selectedValue)) ?? '';
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
                        <td class="num">{{ (int) $item->jumlah }}</td>
                        <td class="num">Rp {{ number_format((float) $item->harga_satuan, 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format((float) $item->harga_satuan * (int) $item->jumlah, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="sum">
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
                $diskonDeskripsiDasar = match ($diskonTipe) {
                    'bundling' => trim('Bundling ' . ($transaksi->diskon_nama ?: 'Paket') . ' ' . ($diskonNilaiLabel ?? '')),
                    'harga_kategori' => trim('Promo ' . ($transaksi->diskon_nama ?: 'Kategori') . ' Harga ' . ($diskonNilaiLabel ?? '')),
                    'persen' => trim('Promo ' . ($transaksi->diskon_nama ?: 'Diskon') . ' ' . ($diskonNilaiLabel ?? '')),
                    'nominal' => trim('Promo ' . ($transaksi->diskon_nama ?: 'Diskon') . ' ' . ($diskonNilaiLabel ?? '')),
                    default => null,
                };
                $diskonKategoriTargetId = (int) ($transaksi->diskon?->id_kategori_target ?? 0);
                $diskonKategoriTargetName = trim((string) ($transaksi->diskon?->kategoriTarget?->nama_kategori ?? ''));
                $diskonMenuTerdampak = [];
                if ($diskonKategoriTargetId > 0) {
                    foreach ($transaksi->detail as $detailItem) {
                        $produk = $detailItem->produk;
                        if (! $produk || (int) ($produk->id_kategori ?? 0) !== $diskonKategoriTargetId) {
                            continue;
                        }
                        $namaProduk = trim((string) ($produk->nama_produk ?? ''));
                        if ($namaProduk === '') {
                            continue;
                        }
                        $diskonMenuTerdampak[$namaProduk] = ($diskonMenuTerdampak[$namaProduk] ?? 0) + (int) ($detailItem->jumlah ?? 0);
                    }
                }
                $diskonMenuTerdampakLabel = null;
                if (! empty($diskonMenuTerdampak)) {
                    $menuLabels = [];
                    foreach ($diskonMenuTerdampak as $menuNama => $menuQty) {
                        $menuLabels[] = $menuNama . ' x' . max(1, (int) $menuQty);
                    }
                    $diskonMenuTerdampakLabel = implode(', ', $menuLabels);
                }
                $diskonCakupanLabel = null;
                if ($diskonTipe === 'harga_kategori' && $diskonMenuTerdampakLabel) {
                    $diskonCakupanLabel = 'Menu promo: ' . $diskonMenuTerdampakLabel;
                } elseif (in_array($diskonTipe, ['persen', 'nominal'], true) && $diskonKategoriTargetId > 0) {
                    $namaKategori = $diskonKategoriTargetName !== '' ? $diskonKategoriTargetName : ('#' . $diskonKategoriTargetId);
                    $diskonCakupanLabel = 'Kategori ' . $namaKategori;
                    if ($diskonMenuTerdampakLabel) {
                        $diskonCakupanLabel .= ' | Menu: ' . $diskonMenuTerdampakLabel;
                    }
                } elseif ($diskonTipe === 'bundling') {
                    $diskonCakupanLabel = 'Paket bundling pada pesanan ini';
                }
                $diskonDeskripsi = $diskonDeskripsiDasar;
                if ($diskonDeskripsiDasar && $diskonCakupanLabel) {
                    $diskonDeskripsi = $diskonDeskripsiDasar . ' - ' . $diskonCakupanLabel;
                }
            @endphp
            <div class="row"><span>Subtotal</span><strong>Rp {{ number_format($subtotalHarga, 0, ',', '.') }}</strong></div>
            @if($diskonNominal > 0)
                @if($diskonDeskripsi)
                    <div class="row promo-meta">
                        <span>{{ $diskonDeskripsi }}</span>
                        <span></span>
                    </div>
                @endif
                <div class="row">
                    <span>Total Potongan</span>
                    <strong>- Rp {{ number_format($diskonNominal, 0, ',', '.') }}</strong>
                </div>
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
            @if($jumlahBayarCash !== null)
                <div class="row"><span>Bayar Cash</span><strong>Rp {{ number_format((float) $jumlahBayarCash, 0, ',', '.') }}</strong></div>
                <div class="row"><span>Kembalian</span><strong>Rp {{ number_format((float) $kembalian, 0, ',', '.') }}</strong></div>
            @endif
            <div class="row grand">
                <span>Total</span>
                <span>Rp {{ number_format((float) $transaksi->total_harga, 0, ',', '.') }}</span>
            </div>
        </div>

        @if($footerText !== '')
            <div class="footer-note u-pre-line">{{ $footerText }}</div>
        @else
            <div class="footer-note">Terima kasih. Simpan struk ini sebagai bukti pembayaran.</div>
        @endif
    </div>
</div>
@if(!empty($autoPrintChecker) && (bool) ($strukSetting?->auto_print_checker ?? true))
    @php
        $checkerAutoPrintUrl = route('kasir.checker', [
            'transaksi' => $transaksi,
            'paper' => auth()->user()->paper_preference ?? '80',
            'autoprint' => 1,
            'embedded' => 1,
        ]);
    @endphp
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                const url = @json($checkerAutoPrintUrl);
                const iframe = document.createElement('iframe');
                iframe.id = 'checker-print-frame';
                iframe.src = url;
                iframe.setAttribute('aria-hidden', 'true');
                iframe.style.position = 'fixed';
                iframe.style.width = '1px';
                iframe.style.height = '1px';
                iframe.style.opacity = '0';
                iframe.style.pointerEvents = 'none';
                iframe.style.border = '0';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                document.body.appendChild(iframe);

                setTimeout(function () {
                    const oldFrame = document.getElementById('checker-print-frame');
                    if (oldFrame) {
                        oldFrame.remove();
                    }
                }, 15000);
            }, 200);
        });
    </script>
@endif
@endsection
