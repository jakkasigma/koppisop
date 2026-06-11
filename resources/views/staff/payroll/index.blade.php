@extends('layouts.staff')

@section('title', 'Slip Gaji')

@section('content')
@php
    $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
    $nama = (string) ($staffKaryawan->nama_karyawan ?? 'Karyawan');
    $baseSalaryLabel = ($staffKaryawan && method_exists($staffKaryawan, 'baseSalaryLabel')) ? $staffKaryawan->baseSalaryLabel() : 'Belum diatur admin';
    $employmentLabel = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
    $identityLine = collect([
        $staffKaryawan->jabatan ?? 'Staf',
        $liveSummary['salary_scheme_label'],
        $employmentLabel,
    ])->filter(fn ($item) => filled($item))->implode(' · ');
    $currentStatusLabel = $currentSlip ? $currentSlip->statusLabel() : 'Realtime';
    $currentStatusClass = $currentSlip
        ? ($currentSlip->status === \App\Models\PayrollSlip::STATUS_FINALIZED ? 'ok' : 'soft')
        : 'live';
@endphp

<div class="container app-shell">
    <div class="staff-payroll-mobile-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => 'Slip Gaji Realtime',
                'pageMark' => 'PY',
                'staffName' => $nama,
                'greetingTitle' => 'Halo, ' . $nama,
                'greetingSubtitle' => $identityLine,
                'employmentLabel' => '',
                'employmentMeta' => '',
            ])
        </section>

        <section class="staff-payroll-stage-app minimal">
            <div class="staff-payroll-account-card compact pay-head-card">
                <div class="staff-payroll-head-summary">
                    <div class="staff-payroll-head-summary-copy">
                        <strong>Ringkasan {{ $periodLabel }}</strong>
                        <span>{{ $identityLine }}</span>
                    </div>
                    <span class="staff-payroll-inline-pill {{ $currentStatusClass }}">{{ $currentStatusLabel }}</span>
                </div>

                <div class="staff-payroll-head-meta">
                    <article>
                        <span>Periode</span>
                        <strong>{{ $periodLabel }}</strong>
                    </article>
                    <article>
                        <span>Skema</span>
                        <strong>{{ $liveSummary['salary_scheme_label'] }}</strong>
                    </article>
                    <article>
                        <span>Dasar</span>
                        <strong>{{ $baseSalaryLabel }}</strong>
                    </article>
                </div>
            </div>

            <div class="staff-payroll-segmented two-up">
                <a class="{{ $rangeMonths === 12 ? 'active' : '' }}" href="{{ route('staff.payroll.index', ['bulan' => $periodKey, 'rentang' => 12]) }}">12 Bulan</a>
                <a class="{{ $rangeMonths === 6 ? 'active' : '' }}" href="{{ route('staff.payroll.index', ['bulan' => $periodKey, 'rentang' => 6]) }}">6 Bulan</a>
            </div>

            <article class="staff-payroll-summary-card pay-index-card" id="payroll-active-summary">
                <div class="staff-payroll-trend-copy">
                    <div>
                        <span class="staff-payroll-trend-label">Pendapatan {{ $periodLabel }}</span>
                        <strong>Rp {{ number_format((int) $selectedAmount, 0, ',', '.') }}</strong>
                    </div>
                    <span class="staff-payroll-inline-pill {{ $currentStatusClass }}">{{ $currentStatusLabel }}</span>
                </div>

                <div class="staff-payroll-chart-wrap" aria-label="Grafik tren slip gaji">
                    <svg viewBox="0 0 {{ $historyChart['width'] }} {{ $historyChart['height'] }}" role="img" aria-hidden="true">
                        <defs>
                            <linearGradient id="payrollAreaGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgba(255,255,255,.72)" />
                                <stop offset="100%" stop-color="rgba(255,255,255,0)" />
                            </linearGradient>
                        </defs>
                        @foreach($historyChart['grid_lines'] as $gridLine)
                            <line x1="14" y1="{{ $gridLine }}" x2="{{ $historyChart['width'] - 14 }}" y2="{{ $gridLine }}" class="chart-grid" />
                        @endforeach
                        @if($historyChart['area_path'] !== '')
                            <path d="{{ $historyChart['area_path'] }}" class="chart-area" />
                        @endif
                        @if($historyChart['polyline'] !== '')
                            <polyline points="{{ $historyChart['polyline'] }}" class="chart-line" />
                        @endif
                        @foreach($historyChart['points'] as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="{{ $point['is_current'] ? 4.5 : 3.3 }}" class="chart-point {{ $point['is_current'] ? 'current' : '' }}" />
                        @endforeach
                    </svg>
                    <div class="staff-payroll-chart-labels">
                        @foreach($historySeries as $series)
                            <span class="{{ $series['is_current'] ? 'active' : '' }}">{{ $series['short_label'] }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="staff-payroll-overview-grid slim">
                    <article class="staff-payroll-overview-tile">
                        <span>Jam Dibayar</span>
                        <strong>{{ $currentSlip ? $currentSlip->paidHoursLabel() : $liveSummary['paid_hours_label'] }}</strong>
                        <small>{{ $liveSummary['present_shift_count'] }} shift hadir</small>
                    </article>
                    <article class="staff-payroll-overview-tile">
                        <span>Potongan</span>
                        <strong>Rp {{ number_format((int) $liveSummary['auto_deduction_amount'], 0, ',', '.') }}</strong>
                        <small>{{ $liveSummary['alpha_shift_count'] }} shift alpa</small>
                    </article>
                    <article class="staff-payroll-overview-tile">
                        <span>Lembur</span>
                        <strong>{{ $liveSummary['overtime_hours_label'] }}</strong>
                        <small>{{ $savedSlipCount }} slip tersimpan</small>
                    </article>
                </div>

                @if($deltaLabel)
                    <div class="staff-payroll-compare-note compact">{{ $deltaLabel }}</div>
                @endif
            </article>
        </section>

        <section class="staff-payroll-mobile-section">
            <div class="staff-payroll-mobile-head">
                <div>
                    <h2>Pendapatan Bulanan</h2>
                    <p>Tap kartu bulan untuk lihat detail pendapatannya.</p>
                </div>
            </div>

            <div class="staff-payroll-income-list">
                @foreach($monthCards as $card)
                    <a class="staff-payroll-income-card compact tone-{{ $card['tone'] }}" href="{{ $card['detail_route'] }}">
                        <div class="staff-payroll-income-card-main">
                            <div class="staff-payroll-income-card-copy">
                                <div class="staff-payroll-income-card-copy-head">
                                    <strong>{{ $card['title'] }}</strong>
                                    @if(($card['status_variant'] ?? '') !== 'live')
                                        <span class="staff-payroll-inline-pill mini {{ $card['status_variant'] }}">{{ $card['status_label'] }}</span>
                                    @endif
                                </div>
                                <span>{{ $card['scheme_label'] }} &bull; {{ $card['hours_label'] }}</span>
                                <small>{{ $card['sub_label'] }}</small>
                            </div>
                            <div class="staff-payroll-income-card-side">
                                <div class="staff-payroll-income-amount">Rp {{ number_format((int) $card['amount'], 0, ',', '.') }}</div>
                                <span class="action">{{ $card['action_label'] }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            @if($slips->isEmpty())
                <div class="staff-payroll-empty-app">
                    <strong>Belum ada slip tersimpan.</strong>
                    <p>Untuk sekarang kamu masih bisa memantau estimasi gaji realtime pada periode aktif di atas.</p>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
