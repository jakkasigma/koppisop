@extends('layouts.app')

@section('title', 'Mulai Shift Kasir')

@section('content')
@php
    $activeShiftCount = max(1, min(3, (int) ($activeShiftCount ?? 2)));
    $suggestedShiftValue = max(1, min($activeShiftCount, (int) ($suggestedShift ?? 1)));
    $oldShift = (int) old('shift_ke', $suggestedShiftValue);
@endphp
<div class="container">
    <div class="hero flow-hero">
        <div class="flow-hero-head">
            <div>
                <span class="flow-badge">Awal Operasional</span>
                <h1>Mulai Shift Kasir</h1>
                <p class="flow-sub">Pilih shift aktif, cocokkan kas fisik dengan kas sistem, lalu masuk ke halaman kasir tanpa bingung.</p>
            </div>
            <div class="flow-stats">
                <div class="flow-stat">
                    <span>Shift aktif</span>
                    <strong>{{ $activeShiftCount }}</strong>
                </div>
                <div class="flow-stat">
                    <span>Kas awal sistem</span>
                    <strong>Rp {{ number_format((float) ($kasAwalSistem ?? 0), 0, ',', '.') }}</strong>
                </div>
                <div class="flow-stat">
                    <span>Saran sistem</span>
                    <strong>Shift {{ $suggestedShiftValue }}</strong>
                </div>
            </div>
        </div>
        <div class="flow-steps">
            <span class="flow-step active">1. Pilih Shift</span>
            <span class="flow-step">2. Transaksi Kasir</span>
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

    <form method="post" action="{{ route('kasir.shift.store') }}">
        @csrf
        <div class="start-grid">
            <div class="panel">
                <div class="flow-panel-head">
                    <div>
                        <h2 class="flow-panel-title">Pilih shift yang akan dibuka</h2>
                        <p class="flow-panel-sub">Kasir cukup pilih sesi yang sedang berjalan. Sistem tetap mengunci jumlah shift harian sesuai pengaturan.</p>
                    </div>
                </div>

                <div class="shift-grid">
                    @for($shift = 1; $shift <= $activeShiftCount; $shift++)
                        <label class="shift-card">
                            <input type="radio" name="shift_ke" value="{{ $shift }}" @checked($oldShift === $shift)>
                            <span class="shift-card-body">
                                <span class="shift-title">Shift {{ $shift }}</span>
                                <span class="shift-sub">Sesi operasional ke-{{ $shift }}</span>
                                <span class="shift-meta">Klik kartu ini kalau kamu ingin memakai shift {{ $shift }}.</span>
                            </span>
                        </label>
                    @endfor
                </div>

                <div class="system-cash">
                    <span>Kas Awal Sistem: Rp {{ number_format((float) ($kasAwalSistem ?? 0), 0, ',', '.') }}</span>
                    <label class="ack-inline">
                        <input type="checkbox" name="ack_kas_awal" value="1" required>
                        <span>Sudah cek</span>
                    </label>
                </div>
                <div class="notice">
                    Jatah shift hari ini: {{ $activeShiftCount }} shift.
                    Pilihan kamu sekarang: <span id="selectedShiftNotice">Shift {{ $oldShift }}</span>.
                </div>
                <div class="hint">Nilai ini otomatis diambil dari saldo akhir estimasi shift sebelumnya.</div>
            </div>

            <div class="start-side">
                <div class="panel">
                    <div class="flow-panel-head">
                        <div>
                            <h2 class="flow-panel-title">Pengecekan cepat</h2>
                            <p class="flow-panel-sub">Ringkasan ini membantu kasir memastikan shift yang dibuka sudah benar sebelum lanjut transaksi.</p>
                        </div>
                    </div>

                    <div class="start-facts">
                        <div class="fact-card">
                            <span class="fact-label">Shift pilihan</span>
                            <strong id="selectedShiftValue">Shift {{ $oldShift }}</strong>
                            <small>Akan ikut berubah jika kamu memilih kartu shift lain.</small>
                        </div>
                        <div class="fact-card">
                            <span class="fact-label">Kas awal</span>
                            <strong>Rp {{ number_format((float) ($kasAwalSistem ?? 0), 0, ',', '.') }}</strong>
                            <small>Nominal diambil otomatis dari penutupan shift sebelumnya.</small>
                        </div>
                        <div class="fact-card">
                            <span class="fact-label">Jumlah sesi</span>
                            <strong>{{ $activeShiftCount }} shift</strong>
                            <small>Admin bisa membatasi jumlah sesi aktif per hari.</small>
                        </div>
                    </div>
                </div>

                <div class="panel flow-note note-warn">
                    <strong>Pengingat sebelum mulai</strong>
                    <span>Kas fisik saat mulai shift seharusnya sama dengan nominal kas sistem di atas. Jika ada selisih, cek kembali laporan shift terakhir sebelum lanjut transaksi.</span>
                </div>
            </div>
        </div>

        <div class="flow-actions">
            <button class="btn-primary" type="submit">Mulai Shift & Masuk Kasir</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    (() => {
        const shiftInputs = document.querySelectorAll('input[name="shift_ke"]');
        const valueTarget = document.getElementById('selectedShiftValue');
        const noticeTarget = document.getElementById('selectedShiftNotice');

        const syncShiftLabel = () => {
            const active = document.querySelector('input[name="shift_ke"]:checked');
            const label = active ? `Shift ${active.value}` : '-';

            if (valueTarget) valueTarget.textContent = label;
            if (noticeTarget) noticeTarget.textContent = label;
        };

        shiftInputs.forEach((input) => input.addEventListener('change', syncShiftLabel));
        syncShiftLabel();
    })();
</script>
@endsection

