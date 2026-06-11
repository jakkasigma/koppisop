<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota {{ $kodeNota }}</title>
    <style>
        body { margin: 0; font-family: 'Courier New', monospace; color:#000; }
        .paper { width: {{ ($paper ?? '80') === '58' ? '56mm' : '78mm' }}; margin: 0 auto; padding: 8px 6px; box-sizing: border-box; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #000; margin: 8px 0; }
        .muted { font-size: 12px; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .items { width: 100%; border-collapse: collapse; font-size: 12px; }
        .items td { padding: 2px 0; vertical-align: top; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .small { font-size: 11px; }
        .promo-meta { font-size: 11px; color: #4b5563; }
        .mt { margin-top: 6px; }
        .toolbar { display:flex; justify-content:center; gap:6px; margin-bottom:6px; }
        .toolbar a, .toolbar button { border:1px solid #ccc; background:#fff; padding:4px 8px; font-size:12px; border-radius:4px; text-decoration:none; color:#111; }
        .toolbar .active { background:#111; color:#fff; border-color:#111; }
        .toolbar .pref { border-style:dashed; color:#374151; background:#f8fafc; }
        .logo-img { max-height:80px; object-fit:contain; }
        .pre-line { white-space:pre-line; }
        @media screen and (pointer: coarse) {
            .toolbar { gap:8px; flex-wrap:wrap; }
            .toolbar a, .toolbar button, .toolbar .pref { min-height:44px; padding:10px 12px; font-size:14px; display:inline-flex; align-items:center; }
        }
        @media print {
            @page { size: {{ ($paper ?? '80') === '58' ? '58mm' : '80mm' }} auto; margin: 2mm; }
            .no-print { display: none; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="paper">
    @php
        $routeNota = $notaRouteName ?? 'transaksi.nota';
        $paperDefault = $paperDefault ?? ($paper ?? '80');
        $autoprint = (bool) ($autoprint ?? false);
        $nota = $nota ?? [];
        $branding = $nota['branding'] ?? [];
        $visibility = $nota['visibility'] ?? [];
        $meta = $nota['meta'] ?? [];
        $items = $nota['items'] ?? [];
        $totals = $nota['totals'] ?? [];
        $promo = $nota['promo'] ?? [];
    @endphp
    <div class="toolbar no-print">
        <a class="{{ ($paper ?? '80') === '58' ? 'active' : '' }}" href="{{ route($routeNota, ['transaksi' => $transaksi, 'paper' => '58', 'autoprint' => (int) $autoprint]) }}">58mm</a>
        <a class="{{ ($paper ?? '80') === '80' ? 'active' : '' }}" href="{{ route($routeNota, ['transaksi' => $transaksi, 'paper' => '80', 'autoprint' => (int) $autoprint]) }}">80mm</a>
        <span class="pref">Default: {{ $paperDefault }}mm</span>
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Tutup</button>
    </div>

    @if(($branding['logo_path'] ?? '') !== '')
        <div class="center"><img src="{{ asset('storage/' . $branding['logo_path']) }}" alt="Logo" class="logo-img" style="max-width:{{ $branding['logo_max_width'] ?? 120 }}px;"></div>
    @endif
    <div class="center bold">{{ ($branding['nama_toko'] ?? '') !== '' ? strtoupper((string) $branding['nama_toko']) : 'KOPISOP' }}</div>
    @if(($branding['nama_cabang'] ?? '') !== '')
        <div class="center muted">{{ $branding['nama_cabang'] }}</div>
    @endif
    @if(($branding['alamat_toko'] ?? '') !== '')
        <div class="center muted">{{ $branding['alamat_toko'] }}</div>
    @endif
    <div class="center muted">Nota Pembayaran</div>
    @if(($branding['header_text'] ?? '') !== '')
        <div class="center small pre-line">{{ $branding['header_text'] }}</div>
    @endif
    <div class="line"></div>

    <div class="small">
        @if($visibility['show_kode_nota'] ?? true)<div class="row"><span>No Nota</span><span>{{ $kodeNota }}</span></div>@endif
        @if($visibility['show_id_pesanan'] ?? true)<div class="row"><span>ID Pesanan</span><span>{{ $meta['public_order_id'] ?? '-' }}</span></div>@endif
        @if($visibility['show_waktu'] ?? true)<div class="row"><span>Waktu</span><span>{{ $meta['waktu'] ?? '-' }}</span></div>@endif
        @if($visibility['show_pelanggan'] ?? true)<div class="row"><span>Pelanggan</span><span>{{ $meta['pelanggan'] ?? '-' }}</span></div>@endif
        @if($visibility['show_kasir'] ?? true)<div class="row"><span>Kasir</span><span>{{ $meta['kasir'] ?? '-' }}</span></div>@endif
        @if($visibility['show_metode'] ?? true)<div class="row"><span>Metode</span><span>{{ $meta['metode'] ?? '-' }}</span></div>@endif
        @if($visibility['show_status'] ?? true)<div class="row"><span>Status</span><span>{{ $meta['status'] ?? '-' }}</span></div>@endif
        @if(($meta['catatan'] ?? '') !== '')
            <div class="row"><span>Catatan</span><span>{{ $meta['catatan'] }}</span></div>
        @endif
    </div>

    <div class="line"></div>

    <table class="items">
        <tbody>
        @forelse($items as $item)
            <tr>
                <td colspan="2">{{ $item['name'] ?? 'Produk dihapus' }}</td>
            </tr>
            @if(($item['options_label'] ?? null) !== null)
                <tr>
                    <td colspan="2" class="small muted">{{ $item['options_label'] }}</td>
                </tr>
            @endif
            <tr>
                <td>{{ (int) ($item['qty'] ?? 0) }} x {{ number_format((float) ($item['unit_price'] ?? 0), 0, ',', '.') }}</td>
                <td class="right">{{ number_format((float) ($item['subtotal'] ?? 0), 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2">Detail pesanan kosong.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div class="line"></div>

    <div class="row mt">
        <span>SUBTOTAL</span>
        <span>Rp {{ number_format((float) ($totals['subtotal'] ?? 0), 0, ',', '.') }}</span>
    </div>
    @if((float) ($totals['discount'] ?? 0) > 0)
        @if(($promo['description'] ?? null) !== null)
            <div class="row promo-meta">
                <span>{{ $promo['description'] }}</span>
                <span></span>
            </div>
        @endif
        <div class="row">
            <span>POTONGAN</span>
            <span>- Rp {{ number_format((float) ($totals['discount'] ?? 0), 0, ',', '.') }}</span>
        </div>
    @endif
    @if((float) ($totals['tax'] ?? 0) > 0)
        <div class="row">
            <span>PAJAK{{ (float) ($totals['tax_percent'] ?? 0) > 0 ? ' (' . rtrim(rtrim(number_format((float) ($totals['tax_percent'] ?? 0), 2, '.', ''), '0'), '.') . '%)' : '' }}</span>
            @if($totals['tax_charged_to_customer'] ?? false)
                <span>+ Rp {{ number_format((float) ($totals['tax'] ?? 0), 0, ',', '.') }}</span>
            @else
                <span>Rp {{ number_format((float) ($totals['tax'] ?? 0), 0, ',', '.') }} (Ditanggung Cafe)</span>
            @endif
        </div>
    @endif
    <div class="row bold mt">
        <span>TOTAL</span>
        <span>Rp {{ number_format((float) ($totals['grand_total'] ?? 0), 0, ',', '.') }}</span>
    </div>

    <div class="line"></div>

    @if(($branding['footer_text'] ?? '') !== '')
        <div class="center small pre-line">{{ $branding['footer_text'] }}</div>
    @else
        <div class="center small">Terima kasih telah berkunjung</div>
    @endif
</div>
@if($autoprint)
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 150);
        });
    </script>
@endif
</body>
</html>

