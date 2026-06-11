@php
    $paperWidth = ($paper ?? '80') === '58' ? 58 : 80;
    $shopName = trim((string) (($strukSetting->nama_toko_kasir ?? null) ?: ($strukSetting->nama_toko ?? null) ?: 'KOPISOP'));
    $shopSub = trim((string) (($strukSetting->header_text_kasir ?? null) ?: ($strukSetting->header_text ?? null) ?: ''));
    $totalPenerimaan = (float) (($summary['total_cash'] ?? 0) + ($summary['total_qris'] ?? 0) + ($summary['total_debit'] ?? 0));
    $totalQtyProduk = collect($produkTerjualRows ?? [])->sum(fn ($item) => (int) ($item->qty ?? 0));
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk Tutup Shift #{{ $shift->id }}</title>
    <style>
        body { font-family: "Courier New", monospace; font-size:12px; margin:0; padding:8px; background:#eef3f2; }
        .sheet { width: {{ $paperWidth }}mm; max-width:100%; margin:0 auto; }
        .center { text-align:center; }
        .line { border-top:1px dashed #111; margin:6px 0; }
        .row { display:grid; grid-template-columns:1fr auto 1fr; column-gap:6px; }
        .row .c { text-align:center; }
        .row .v { text-align:right; }
        .row.menu { grid-template-columns:1fr auto; }
        .row.menu .v { text-align:right; }
        .muted { color:#444; font-size:11px; }
        .strong { font-weight:700; }
        .actions { margin:10px auto 0; width: {{ $paperWidth }}mm; display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
        .actions a,
        .actions button {
            border:1px solid #cfdad7;
            background:#fff;
            border-radius:8px;
            padding:6px 10px;
            text-decoration:none;
            color:#111827;
            font-family:inherit;
            font-size:12px;
            font-weight:700;
            cursor:pointer;
        }
        .mt { margin-top:4px; }
        @media (max-width: 900px) {
            .actions {
                width:min({{ $paperWidth }}mm, calc(100vw - 16px));
            }
            .actions a,
            .actions button {
                flex:1 1 180px;
                min-height:42px;
                display:inline-flex;
                align-items:center;
                justify-content:center;
            }
        }
        @media print { .actions { display:none; } body { padding:0; } }
    </style>
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</head>
<body>
<div class="sheet">
    <div class="center strong">{{ $shopName }}</div>
    @if($shopSub !== '')
        <div class="center muted">{{ $shopSub }}</div>
    @endif
    <div class="line"></div>
    <div class="center strong">LAPORAN TUTUP SHIFT</div>
    <div class="center strong">TRANSAKSI PENJUALAN</div>
    <div class="line"></div>

    <div class="row"><span>Kasir</span><span class="c">:</span><span class="v">SHIFT {{ (int) $shift->shift_ke }}</span></div>
    <div class="row"><span>Waktu Buka</span><span class="c">:</span><span class="v">{{ $shift->started_at }}</span></div>
    <div class="row"><span>Waktu Tutup</span><span class="c">:</span><span class="v">{{ $shift->ended_at ?? '-' }}</span></div>

    <div class="line"></div>
    <div class="row"><span>Modal Awal</span><span class="c">:</span><span class="v">{{ number_format((float) $shift->kas_awal, 0, ',', '.') }}</span></div>
    <div class="row"><span>Omzet Bruto</span><span class="c">:</span><span class="v">{{ number_format((float) ($summary['total_bruto'] ?? 0), 0, ',', '.') }}</span></div>
    <div class="row"><span>Total Pajak</span><span class="c">:</span><span class="v">{{ number_format((float) ($summary['total_pajak'] ?? 0), 0, ',', '.') }}</span></div>
    <div class="row"><span>Omzet Netto</span><span class="c">:</span><span class="v">{{ number_format((float) ($summary['total_omzet'] ?? 0), 0, ',', '.') }}</span></div>
    <div class="row"><span>Tunai (Tidak Dipotong Pajak)</span><span class="c">:</span><span class="v">{{ number_format((float) ($summary['total_cash'] ?? 0), 0, ',', '.') }}</span></div>
    <div class="row"><span>Delivery (Aplikasi)</span><span class="c">:</span><span class="v">{{ number_format((float) ($summary['total_delivery'] ?? 0), 0, ',', '.') }}</span></div>
    <div class="row"><span>QRIS</span><span class="c">:</span><span class="v">{{ number_format((float) ($summary['total_qris'] ?? 0), 0, ',', '.') }}</span></div>
    <div class="row"><span>Debit</span><span class="c">:</span><span class="v">{{ number_format((float) ($summary['total_debit'] ?? 0), 0, ',', '.') }}</span></div>
    <div class="row strong"><span>Total Penerimaan</span><span class="c">:</span><span class="v">{{ number_format($totalPenerimaan, 0, ',', '.') }}</span></div>
    <div class="row"><span>Kas Keluar</span><span class="c">:</span><span class="v">{{ number_format((float) ($summary['total_pengeluaran'] ?? 0), 0, ',', '.') }}</span></div>
    <div class="row strong"><span>Saldo Akhir (Cash Fisik)</span><span class="c">:</span><span class="v">{{ number_format((float) ($summary['estimasi_kas_akhir'] ?? 0), 0, ',', '.') }}</span></div>
    <div class="row"><span>Transaksi Selesai</span><span class="c">:</span><span class="v">{{ number_format((int) ($summary['total_trx'] ?? 0), 0, ',', '.') }}</span></div>

    <div class="line"></div>
    <div class="muted">Catatan: Tunai/Saldo Akhir pada laporan ini tidak dikurangi pajak.</div>
    <div class="line"></div>
    <div class="center strong">LAPORAN TUTUP KASIR</div>
    <div class="center strong">PENJUALAN MENU</div>
    <div class="line"></div>
    <div class="row"><span>Kasir</span><span class="c">:</span><span class="v">SHIFT {{ (int) $shift->shift_ke }}</span></div>
    <div class="row"><span>Waktu Buka</span><span class="c">:</span><span class="v">{{ $shift->started_at }}</span></div>
    <div class="row"><span>Waktu Tutup</span><span class="c">:</span><span class="v">{{ $shift->ended_at ?? '-' }}</span></div>
    <div class="mt"></div>
    <div class="strong">Produk Terjual</div>
    <div class="line"></div>
    @forelse(($produkTerjualRows ?? collect()) as $item)
        <div class="row menu"><span>{{ $item->nama_produk }}</span><span class="v">{{ number_format((int) $item->qty, 0, ',', '.') }}</span></div>
    @empty
        <div class="center muted">Tidak ada produk terjual pada shift ini.</div>
    @endforelse

    <div class="line"></div>
    <div class="row"><span>Total Qty Terjual</span><span class="c">:</span><span class="v">{{ number_format((int) $totalQtyProduk, 0, ',', '.') }}</span></div>

    <div class="line"></div>
    <div class="center muted">Dicetak {{ now()->format('Y-m-d H:i:s') }}</div>
</div>

<div class="actions">
    @if(auth()->user()?->role === 'kasir')
        <form method="post" action="{{ route('logout') }}" onsubmit="return confirm('Tutup kasir dan logout sekarang?')">
            @csrf
            <button type="submit">Selesai & Logout</button>
        </form>
    @else
        <a href="{{ route('dashboard.shift_history') }}">Kembali</a>
    @endif
</div>
</body>
</html>
