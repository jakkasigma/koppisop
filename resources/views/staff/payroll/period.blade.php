@extends('layouts.staff')

@section('title', 'Detail Pendapatan')

@section('content')
@php
    $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
    $nama = (string) ($staffKaryawan->nama_karyawan ?? 'Karyawan');
    $parts = array_values(array_filter(explode(' ', $nama)));
    $initials = collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $employmentLabel = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
    $isCurrentPeriod = $period->isSameMonth(now(), false);
    $statusLabel = $isCurrentPeriod ? 'Realtime' : 'Belum ada slip';
    $statusClass = $isCurrentPeriod ? 'live' : 'muted';
    $periodLabel = $period->locale('id')->translatedFormat('F Y');
    $grossAmount = (int) ($liveSummary['gross_amount'] ?? 0);
    $netAmount = (int) ($liveSummary['estimated_net_amount'] ?? 0);
    $autoDeductionAmount = (int) ($liveSummary['auto_deduction_amount'] ?? 0);
@endphp

<div class="container app-shell">
    <div class="staff-payroll-mobile-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => 'Detail Pendapatan',
                'pageMark' => 'PY',
                'staffName' => $nama,
                'greetingTitle' => 'Halo, ' . $nama,
                'greetingSubtitle' => $periodLabel,
                'employmentLabel' => $employmentLabel,
                'employmentMeta' => ($staffKaryawan->jabatan ?? 'Staf') . ' - ' . $liveSummary['salary_scheme_label'],
            ])
        </section>

        <section class="staff-payroll-stage-app minimal detail">
            <article class="staff-payroll-summary-card detail-card detail-compact">
                <div class="staff-payroll-trend-copy">
                    <div>
                        <span class="staff-payroll-trend-label">Pendapatan Bersih</span>
                        <strong>Rp {{ number_format($netAmount, 0, ',', '.') }}</strong>
                    </div>
                    <span class="staff-payroll-type-pill">{{ $statusLabel }}</span>
                </div>

                <div class="staff-payroll-overview-grid">
                    <article class="staff-payroll-overview-tile">
                        <span>Gaji Kotor</span>
                        <strong>Rp {{ number_format($grossAmount, 0, ',', '.') }}</strong>
                        <small>Dasar + lembur</small>
                    </article>
                    <article class="staff-payroll-overview-tile">
                        <span>Potongan</span>
                        <strong>Rp {{ number_format($autoDeductionAmount, 0, ',', '.') }}</strong>
                        <small>Potongan otomatis berjalan</small>
                    </article>
                    <article class="staff-payroll-overview-tile">
                        <span>Jam Dibayar</span>
                        <strong>{{ $liveSummary['paid_hours_label'] }}</strong>
                        <small>{{ (int) $liveSummary['present_shift_count'] }} shift hadir</small>
                    </article>
                    <article class="staff-payroll-overview-tile">
                        <span>Status</span>
                        <strong>{{ $statusLabel }}</strong>
                        <small>{{ $employmentLabel }}</small>
                    </article>
                </div>

                @if($compareLabel)
                    <div class="staff-payroll-compare-note">{{ $compareLabel }}</div>
                @endif
            </article>
        </section>

        <section class="staff-payroll-mobile-section compact-section">
            <div class="staff-payroll-mobile-head compact">
                <h2>Komponen Pendapatan</h2>
            </div>

            <div class="staff-payroll-composition-list compact-grid">
                @forelse($compositionItems as $item)
                    <article class="staff-payroll-composition-chip tone-{{ $item['tone'] }}">
                        <span class="eyebrow">{{ $item['tone'] === 'negative' ? 'Potongan' : ($item['tone'] === 'positive' ? 'Tambahan' : 'Dasar') }}</span>
                        <strong>{{ $item['label'] }}</strong>
                        <span>Rp {{ number_format((int) $item['amount'], 0, ',', '.') }}</span>
                    </article>
                @empty
                    <div class="staff-payroll-empty-app compact">
                        <strong>Belum ada komponen tambahan.</strong>
                        <p>Bulan ini masih hanya menampilkan nominal dasar.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="staff-payroll-mobile-section compact-section">
            <div class="staff-payroll-mobile-head compact">
                <h2>Rincian Bulan Ini</h2>
            </div>

            <div class="staff-payroll-detail-groups compact-grid">
                <article class="staff-payroll-detail-card toned neutral">
                    <div class="detail-card-title">Kehadiran</div>
                    <div class="detail-row"><span>Shift terjadwal</span><strong>{{ (int) $liveSummary['scheduled_shift_count'] }} shift</strong></div>
                    <div class="detail-row"><span>Shift hadir</span><strong>{{ (int) $liveSummary['present_shift_count'] }} shift</strong></div>
                    @if(($liveSummary['approved_leave_shift_count'] ?? 0) > 0)
                        <div class="detail-row"><span>Izin / sakit approved</span><strong>{{ (int) $liveSummary['approved_leave_shift_count'] }} shift &middot; {{ (int) ($liveSummary['approved_leave_day_count'] ?? 0) }} hari</strong></div>
                    @endif
                    <div class="detail-row"><span>Shift alpa</span><strong>{{ (int) $liveSummary['alpha_shift_count'] }} shift @if(($liveSummary['alpha_day_count'] ?? 0) > 0)&middot; {{ (int) $liveSummary['alpha_day_count'] }} hari @endif</strong></div>
                    <div class="detail-row"><span>Jam dibayar</span><strong>{{ $liveSummary['paid_hours_label'] }}</strong></div>
                </article>

                <article class="staff-payroll-detail-card toned positive">
                    <div class="detail-card-title">Tambahan</div>
                    <div class="detail-row"><span>Gaji dasar</span><strong>Rp {{ number_format((int) $liveSummary['base_amount'], 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Lembur</span><strong>{{ $liveSummary['overtime_hours_label'] }}</strong></div>
                    <div class="detail-row"><span>Bayar lembur</span><strong>Rp {{ number_format((int) $liveSummary['overtime_amount'], 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Skema</span><strong>{{ $liveSummary['salary_scheme_label'] }}</strong></div>
                </article>

                <article class="staff-payroll-detail-card toned negative">
                    <div class="detail-card-title">Potongan</div>
                    <div class="detail-row"><span>Potongan alpa</span><strong>Rp {{ number_format((int) $liveSummary['auto_alpha_deduction'], 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Potongan izin / sakit</span><strong>Rp {{ number_format((int) ($liveSummary['auto_approved_leave_deduction'] ?? 0), 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Telat</span><strong>{{ (int) $liveSummary['late_count'] }}x &bull; {{ (int) $liveSummary['late_minutes'] }} menit</strong></div>
                    <div class="detail-row"><span>Pulang cepat</span><strong>{{ (int) $liveSummary['early_leave_count'] }}x &bull; {{ $liveSummary['early_leave_hours_label'] }}</strong></div>
                    <div class="detail-row"><span>Potongan otomatis</span><strong>Rp {{ number_format($autoDeductionAmount, 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Kebijakan harian</span><strong>Rp {{ number_format((int) ($liveSummary['policy']['alpha_deduction_unit_amount'] ?? 0), 0, ',', '.') }} / {{ $liveSummary['policy']['alpha_deduction_unit_label'] ?? 'shift' }}</strong></div>
                </article>
            </div>

            <div class="staff-payroll-neighbour-nav">
                @if($nextSlip)
                    <a class="btn-neutral" href="{{ route('staff.payroll.period', ['period' => $nextSlip->period_month?->format('Y-m')]) }}">Bulan Berikutnya</a>
                @endif
                @if($previousSlip)
                    <a class="btn-neutral" href="{{ route('staff.payroll.period', ['period' => $previousSlip->period_month?->format('Y-m')]) }}">Bulan Sebelumnya</a>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
