<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checker {{ $kodeChecker }}</title>
    <style>
        body { margin:0; font-family:'Courier New', monospace; color:#000; background:#eef3f2; }
        .paper { width: {{ ($paper ?? '80') === '58' ? '56mm' : '78mm' }}; margin: 0 auto; padding: 8px 6px; box-sizing: border-box; }
        .center { text-align:center; }
        .line { border-top:1px dashed #000; margin:8px 0; }
        .row { display:flex; justify-content:space-between; gap:8px; }
        .small { font-size:11px; }
        .bold { font-weight:bold; }
        .toolbar { display:flex; justify-content:center; gap:6px; margin-bottom:6px; }
        .toolbar a, .toolbar button { border:1px solid #cfdad7; background:#fff; padding:4px 8px; font-size:12px; border-radius:6px; text-decoration:none; color:#111; font-weight:700; }
        .toolbar .active { background:#111; color:#fff; border-color:#111; }
        .item { margin-bottom:8px; }
        @media (max-width: 900px) {
            .paper { width:min(calc(100vw - 20px), {{ ($paper ?? '80') === '58' ? '56mm' : '78mm' }}); }
            .toolbar { flex-wrap:wrap; }
            .toolbar a,
            .toolbar button {
                flex:1 1 120px;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                min-height:40px;
            }
        }
        @media print {
            @page { size: {{ ($paper ?? '80') === '58' ? '58mm' : '80mm' }} auto; margin:2mm; }
            .no-print { display:none; }
        }
    </style>
</head>
<body>
<div class="paper">
    <div class="toolbar no-print">
        <a class="{{ ($paper ?? '80') === '58' ? 'active' : '' }}" href="{{ route('kasir.checker', ['transaksi' => $transaksi, 'paper' => '58', 'autoprint' => (int) ($autoprint ?? false)]) }}">58mm</a>
        <a class="{{ ($paper ?? '80') === '80' ? 'active' : '' }}" href="{{ route('kasir.checker', ['transaksi' => $transaksi, 'paper' => '80', 'autoprint' => (int) ($autoprint ?? false)]) }}">80mm</a>
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Tutup</button>
    </div>

    <div class="center bold">CHECKER ORDER</div>
    <div class="center small">{{ $kodeChecker }}</div>
    <div class="center small">{{ $transaksi->waktu_pembayaran }}</div>
    <div class="line"></div>
    <div class="row small"><span>Kasir</span><span>{{ $transaksi->karyawan?->nama_karyawan ?? '-' }}</span></div>
    <div class="row small"><span>Pelanggan</span><span>{{ $transaksi->pelanggan?->nama ?? 'Umum' }}</span></div>
    <div class="line"></div>

    @foreach($transaksi->detail as $item)
        @php
            $opsi = [];
            if ($item->temperature) { $opsi[] = ucwords(str_replace('_', ' ', (string) $item->temperature)); }
            if ($item->sugar_level) { $opsi[] = ucwords(str_replace('_', ' ', (string) $item->sugar_level)); }
            if ($item->cup_size) { $opsi[] = ucwords(str_replace('_', ' ', (string) $item->cup_size)); }
            if ($item->spicy_level) { $opsi[] = ucwords(str_replace('_', ' ', (string) $item->spicy_level)); }
            if (is_array($item->selected_options ?? null)) {
                $catatanItem = null;
                foreach ($item->selected_options as $selectedKey => $selectedValue) {
                    if (in_array((string) $selectedKey, ['note', '_note'], true)) {
                        if (is_string($selectedValue)) {
                            $catatanItem = preg_replace('/\s+/', ' ', trim($selectedValue));
                        }
                        continue;
                    }
                    if (! is_string($selectedValue) || trim($selectedValue) === '') { continue; }
                    $opsi[] = ucwords(str_replace('_', ' ', $selectedValue));
                }
                if (! empty($catatanItem)) {
                    $opsi[] = 'Catatan: ' . $catatanItem;
                }
            }
        @endphp
        <div class="item">
            <div class="row">
                <span class="bold">{{ (int) $item->jumlah }}x {{ $item->produk?->nama_produk ?? 'Produk dihapus' }}</span>
            </div>
            @if(!empty($opsi))
                <div class="small">- {{ implode(' | ', $opsi) }}</div>
            @endif
        </div>
    @endforeach

    <div class="line"></div>
    <div class="center small">-- CHECKER --</div>
</div>

@if(!empty($autoprint))
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
                @if(empty($embedded))
                setTimeout(function () { window.close(); }, 250);
                @endif
            }, 150);
        });
    </script>
@endif
</body>
</html>
