@extends('layouts.app')

@section('title', 'Laporan Shift Kasir')

@section('content')
@php
    $totalTrx = (int) ($summary['total_trx'] ?? 0);
    $totalOmzet = (float) ($summary['total_omzet'] ?? 0);
    $totalCash = (float) ($summary['total_cash'] ?? 0);
    $totalPengeluaran = (float) ($summary['total_pengeluaran'] ?? 0);
    $estimasiKasAkhir = (float) ($summary['estimasi_kas_akhir'] ?? 0);
@endphp
<div class="container">
    <div class="hero flow-hero">
        <div class="flow-hero-head">
            <div>
                <span class="flow-badge">Sebelum Tutup Shift</span>
                <h1>Laporan Shift Kasir</h1>
                <p class="flow-sub">Catat pengeluaran dulu, cek rekap penjualan, lalu lanjut ke konfirmasi tutup shift saat semua angka sudah sesuai.</p>
            </div>
            <div class="flow-stats">
                <div class="flow-stat">
                    <span>Shift</span>
                    <strong>{{ (int) $shift->shift_ke }}</strong>
                </div>
                <div class="flow-stat">
                    <span>Total trx</span>
                    <strong>{{ number_format($totalTrx, 0, ',', '.') }}</strong>
                </div>
                <div class="flow-stat">
                    <span>Kas akhir estimasi</span>
                    <strong>Rp {{ number_format($estimasiKasAkhir, 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>
        <div class="flow-steps">
            <span class="flow-step">1. Shift Aktif</span>
            <span class="flow-step active">2. Catat Pengeluaran</span>
            <span class="flow-step">3. Tutup Shift</span>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif

    <div class="report-mini-grid">
        <div class="report-mini-card">
            <span class="report-mini-label">Omzet Netto</span>
            <strong>Rp {{ number_format($totalOmzet, 0, ',', '.') }}</strong>
            <small>Total penjualan setelah pajak.</small>
        </div>
        <div class="report-mini-card">
            <span class="report-mini-label">Cash Masuk</span>
            <strong>Rp {{ number_format($totalCash, 0, ',', '.') }}</strong>
            <small>Cash tidak dipotong pajak untuk rekap shift.</small>
        </div>
        <div class="report-mini-card">
            <span class="report-mini-label">Pengeluaran</span>
            <strong>Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</strong>
            <small>Belanja operasional yang dicatat pada shift ini.</small>
        </div>
        <div class="report-mini-card accent">
            <span class="report-mini-label">Estimasi Kas Akhir</span>
            <strong>Rp {{ number_format($estimasiKasAkhir, 0, ',', '.') }}</strong>
            <small>Patokan cash fisik sebelum shift ditutup.</small>
        </div>
    </div>

    <div class="report-grid">
        <div class="panel">
            <div class="flow-panel-head">
                <div>
                    <h3 class="u-mt-0">Input Pengeluaran</h3>
                    <p class="flow-panel-sub">Isi nominal dan keterangan pengeluaran shift supaya laporan akhir kas tetap akurat.</p>
                </div>
            </div>
            <form method="post" action="{{ route('kasir.shift.expense.store') }}" class="form-card">
                @csrf
                <div class="input-row">
                    <label for="nominal">Nominal Pengeluaran</label>
                    <div class="input-wrap">
                        <span class="input-prefix">Rp</span>
                        <input id="nominal" type="number" name="nominal" min="1" step="1" value="{{ old('nominal') }}" placeholder="Contoh: 15000" required>
                    </div>
                    <div class="input-hint">Masukkan angka tanpa titik/koma.</div>
                </div>

                <div class="input-row">
                    <label for="keterangan">Keterangan</label>
                    <div class="input-wrap">
                        <input id="keterangan" type="text" name="keterangan" maxlength="200" value="{{ old('keterangan') }}" placeholder="Contoh: Beli gas / tissue / es batu">
                    </div>
                </div>

                <div class="u-flex u-gap-8 u-flex-wrap u-mt-10">
                    <a class="btn-neutral" href="{{ route('kasir.index') }}">Kembali ke Menu Kasir</a>
                    <button class="btn-primary" type="submit">Simpan Pengeluaran</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="flow-panel-head">
                <div>
                    <h3 class="u-mt-0">Ringkasan Shift</h3>
                    <p class="flow-panel-sub">Semua angka penting shift tampil di satu tempat agar kasir bisa cek cepat sebelum finalisasi.</p>
                </div>
            </div>
            <div class="summary-list">
                <div class="summary-row"><span>Shift</span><strong>{{ (int) $shift->shift_ke }}</strong></div>
                <div class="summary-row"><span>Kas Awal</span><strong>Rp {{ number_format((float) $shift->kas_awal, 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Omzet Bruto</span><strong>Rp {{ number_format((float) ($summary['total_bruto'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Total Pajak</span><strong>Rp {{ number_format((float) ($summary['total_pajak'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Omzet Netto</span><strong>Rp {{ number_format((float) ($summary['total_omzet'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Total Cash Masuk (Tidak Dipotong Pajak)</span><strong>Rp {{ number_format((float) ($summary['total_cash'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Penjualan Delivery (Aplikasi)</span><strong>Rp {{ number_format((float) ($summary['total_delivery'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Total Pengeluaran</span><strong>Rp {{ number_format((float) ($summary['total_pengeluaran'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Estimasi Kas Akhir (Cash Fisik)</span><strong>Rp {{ number_format((float) ($summary['estimasi_kas_akhir'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Total Trx</span><strong>{{ number_format((int) ($summary['total_trx'] ?? 0), 0, ',', '.') }}</strong></div>
            </div>
            <div class="muted u-mt-8 u-text-sm">Catatan: nilai cash pada ringkasan shift tidak dikurangi pajak.</div>
            <div class="u-flex u-gap-8 u-flex-wrap u-mt-10">
                <form method="post" action="{{ route('kasir.shift.close.submit') }}" onsubmit="return confirm('Konfirmasi tutup shift sekarang? Laporan shift akan langsung dicetak.');">
                    @csrf
                    <button class="btn-primary" type="submit">Konfirmasi Tutup Shift</button>
                </form>
                <a class="btn-neutral" href="{{ route('kasir.shift.close') }}">Lihat Halaman Konfirmasi</a>
            </div>
        </div>
    </div>

    <div class="panel u-mt-12">
        <div class="flow-panel-head">
            <div>
                <h3 class="u-mt-0">Daftar Pengeluaran Shift</h3>
                <p class="flow-panel-sub">Riwayat ini membantu kasir dan admin melacak pengeluaran yang sudah masuk sebelum shift ditutup.</p>
            </div>
        </div>
        <div class="u-grid u-gap-8">
            @forelse($pengeluaran as $row)
                <div class="expense-item">
                    <div class="expense-head">
                        <span>{{ $row->keterangan ?: 'Pengeluaran shift' }}</span>
                        <span>Rp {{ number_format((float) $row->nominal, 0, ',', '.') }}</span>
                    </div>
                    <div class="expense-meta">
                        <span>{{ $row->pengeluaran_at }}</span>
                        <span>Input: {{ $row->user?->name ?? '-' }}</span>
                    </div>
                    <div class="expense-actions">
                        <form method="post" action="{{ route('kasir.shift.expense.destroy', ['expense' => $row->id]) }}" onsubmit="return confirm('Hapus pengeluaran ini?')">
                            @csrf
                            @method('delete')
                            <button class="btn-neutral" type="submit">Hapus</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="muted">Belum ada pengeluaran pada shift ini.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
