@extends('layouts.app')

@section('title', 'Tutup Shift Kasir')

@section('content')
@php
    $totalTrx = (int) ($summary['total_trx'] ?? 0);
    $estimasiKasAkhir = (float) ($summary['estimasi_kas_akhir'] ?? 0);
@endphp
<div class="container">
    <div class="hero flow-hero">
        <div class="flow-hero-head">
            <div>
                <span class="flow-badge">Finalisasi Shift</span>
                <h1>Konfirmasi Tutup Shift</h1>
                <p class="flow-sub">Periksa semua angka terakhir. Kalau masih ada yang kurang, kembali dulu ke laporan shift supaya penutupan tetap aman.</p>
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
            <span class="flow-step">2. Laporan Shift</span>
            <span class="flow-step active">3. Tutup Shift</span>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="close-grid">
        <div class="card close-card">
            <div class="label">Info Shift</div>
            <div class="summary-list">
                <div class="summary-row"><span>Shift</span><strong>{{ (int) $shift->shift_ke }}</strong></div>
                <div class="summary-row"><span>Mulai</span><strong>{{ $shift->started_at }}</strong></div>
                <div class="summary-row"><span>Kas Awal</span><strong>Rp {{ number_format((float) $shift->kas_awal, 0, ',', '.') }}</strong></div>
            </div>
        </div>
        <div class="card close-card emphasis">
            <div class="label">Ringkasan Penjualan</div>
            <div class="value">{{ (int) ($summary['total_trx'] ?? 0) }} trx</div>
            <div class="value sm">Omzet Netto Rp {{ number_format((float) ($summary['total_omzet'] ?? 0), 0, ',', '.') }}</div>
            <div class="summary-list">
                <div class="summary-row"><span>Omzet Bruto</span><strong>Rp {{ number_format((float) ($summary['total_bruto'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Total Pajak</span><strong>Rp {{ number_format((float) ($summary['total_pajak'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Cash (Tidak Dipotong Pajak)</span><strong>Rp {{ number_format((float) ($summary['total_cash'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Delivery (Aplikasi)</span><strong>Rp {{ number_format((float) ($summary['total_delivery'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>QRIS</span><strong>Rp {{ number_format((float) ($summary['total_qris'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Debit</span><strong>Rp {{ number_format((float) ($summary['total_debit'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Pengeluaran Shift</span><strong>Rp {{ number_format((float) ($summary['total_pengeluaran'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Estimasi Kas Akhir (Cash Fisik)</span><strong>Rp {{ number_format((float) ($summary['estimasi_kas_akhir'] ?? 0), 0, ',', '.') }}</strong></div>
            </div>
            <div class="hint u-mt-8">Cash dan estimasi kas akhir pada shift ini tidak dikurangi pajak.</div>
        </div>
    </div>

    <div class="panel close-confirm-panel u-mt-12">
        <form method="post" action="{{ route('kasir.shift.close.submit') }}">
            @csrf
            <div class="flow-panel-head">
                <div>
                    <h2 class="flow-panel-title">Finalisasi penutupan</h2>
                    <p class="flow-panel-sub">Sesudah tombol ditekan, laporan transaksi shift akan dibuka dan otomatis dicetak sebagai arsip operasional.</p>
                </div>
            </div>

            <div class="close-confirm-note">
                Pastikan kas fisik, total pengeluaran, dan hasil rekap shift sudah sesuai. Kalau masih ragu, lebih aman kembali dulu ke halaman laporan shift.
            </div>

            <div class="actions">
                <a class="btn-neutral" href="{{ route('kasir.shift.report') }}">Kembali ke Laporan Shift</a>
                <a class="btn-neutral" href="{{ route('kasir.index') }}">Kembali ke Kasir</a>
                <button class="btn-primary" type="submit" onclick="return confirm('Tutup shift sekarang?');">Tutup Shift Sekarang</button>
            </div>
        </form>
    </div>
</div>
@endsection
