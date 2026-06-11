@extends('layouts.staff')

@section('title', 'Detail Slip Gaji')

@section('content')
@php
    $staffKaryawan = $karyawan ?? request()->attributes->get('staff_karyawan');
    $nama = (string) ($staffKaryawan->nama_karyawan ?? 'Karyawan');
    $parts = array_values(array_filter(explode(' ', $nama)));
    $initials = collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    $employmentLabel = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
    $statusClass = $payrollSlip->status === \App\Models\PayrollSlip::STATUS_FINALIZED ? 'ok' : 'soft';
    $periodLabel = $payrollSlip->periodLabel();
    $grossAmount = (int) ($payrollSlip->gross_amount ?? 0);
    $netAmount = (int) ($payrollSlip->net_amount ?? 0);
    $bonusAmount = (int) ($payrollSlip->bonus_amount ?? 0);
    $manualDeduction = (int) ($payrollSlip->deduction_amount ?? 0);
    $autoDeductionAmount = (int) ($payrollSlip->auto_deduction_amount ?? 0);
    $totalDeduction = $autoDeductionAmount + $manualDeduction;
@endphp

<div class="container app-shell">
    <div class="staff-payroll-mobile-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => 'Slip Gaji',
                'pageMark' => 'PY',
                'staffName' => $nama,
                'greetingTitle' => 'Halo, ' . $nama,
                'greetingSubtitle' => $periodLabel,
                'employmentLabel' => $employmentLabel,
                'employmentMeta' => ($staffKaryawan->jabatan ?? 'Staf') . ' - ' . ($payrollSlip->salary_scheme === 'hourly' ? 'Per Jam' : 'Bulanan'),
            ])
        </section>

        <section class="staff-payroll-stage-app minimal detail">
            <div class="staff-payroll-account-card compact detail-card refined">
                <div class="staff-payroll-account-main">
                    <div class="staff-payroll-account-avatar">{{ $initials !== '' ? $initials : 'ST' }}</div>
                    <div class="staff-payroll-account-copy">
                        <strong>{{ $nama }}</strong>
                        <span>{{ $periodLabel }}</span>
                        <span>{{ $staffKaryawan->jabatan ?? 'Staf' }} · {{ $payrollSlip->salary_scheme === 'hourly' ? 'Per Jam' : 'Bulanan' }}</span>
                    </div>
                    <span class="staff-payroll-inline-pill {{ $statusClass }}">{{ $payrollSlip->statusLabel() }}</span>
                </div>
                <div class="staff-payroll-detail-actions">
                    <a class="btn-neutral" href="{{ route('staff.payroll.index', ['bulan' => $payrollSlip->period_month?->format('Y-m')]) }}">Kembali</a>
                    <a class="btn-primary" href="{{ route('staff.payroll.print', ['payrollSlip' => $payrollSlip, 'autoprint' => 1]) }}" target="_blank" rel="noopener">Cetak Slip</a>
                </div>
            </div>

            <article class="staff-payroll-summary-card detail-card detail-compact">
                <div class="staff-payroll-trend-copy">
                    <div>
                        <span class="staff-payroll-trend-label">Pendapatan Bersih</span>
                        <strong>Rp {{ number_format($netAmount, 0, ',', '.') }}</strong>
                    </div>
                    <span class="staff-payroll-type-pill">{{ $payrollSlip->statusLabel() }}</span>
                </div>

                <div class="staff-payroll-overview-grid">
                    <article class="staff-payroll-overview-tile">
                        <span>Gaji Kotor</span>
                        <strong>Rp {{ number_format($grossAmount, 0, ',', '.') }}</strong>
                        <small>Dasar + lembur + bonus</small>
                    </article>
                    <article class="staff-payroll-overview-tile">
                        <span>Total Potongan</span>
                        <strong>Rp {{ number_format($totalDeduction, 0, ',', '.') }}</strong>
                        <small>Otomatis + manual</small>
                    </article>
                    <article class="staff-payroll-overview-tile">
                        <span>Jam Dibayar</span>
                        <strong>{{ $payrollSlip->paidHoursLabel() }}</strong>
                        <small>{{ (int) $payrollSlip->present_shift_count }} shift hadir</small>
                    </article>
                    <article class="staff-payroll-overview-tile">
                        <span>Bonus</span>
                        <strong>Rp {{ number_format($bonusAmount, 0, ',', '.') }}</strong>
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
                        <p>Slip ini masih bersih tanpa bonus, lembur, atau potongan lain.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="staff-payroll-mobile-section compact-section">
            <div class="staff-payroll-mobile-head compact">
                <h2>Rincian Slip</h2>
            </div>

            <div class="staff-payroll-detail-groups compact-grid">
                <article class="staff-payroll-detail-card toned neutral">
                    <div class="detail-card-title">Kehadiran</div>
                    <div class="detail-row"><span>Shift terjadwal</span><strong>{{ (int) $payrollSlip->scheduled_shift_count }} shift</strong></div>
                    <div class="detail-row"><span>Shift hadir</span><strong>{{ (int) $payrollSlip->present_shift_count }} shift</strong></div>
                    @if((int) ($payrollSlip->approved_leave_shift_count ?? 0) > 0)
                        <div class="detail-row"><span>Izin / sakit approved</span><strong>{{ (int) ($payrollSlip->approved_leave_shift_count ?? 0) }} shift &bull; {{ (int) ($payrollSlip->approved_leave_day_count ?? 0) }} hari</strong></div>
                    @endif
                    <div class="detail-row"><span>Shift alpa</span><strong>{{ (int) $payrollSlip->alpha_shift_count }} shift</strong></div>
                    <div class="detail-row"><span>Jam dibayar</span><strong>{{ $payrollSlip->paidHoursLabel() }}</strong></div>
                </article>

                <article class="staff-payroll-detail-card toned positive">
                    <div class="detail-card-title">Tambahan</div>
                    <div class="detail-row"><span>Gaji dasar</span><strong>Rp {{ number_format((int) $payrollSlip->base_amount, 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Lembur</span><strong>{{ $payrollSlip->overtimeHoursLabel() }}</strong></div>
                    <div class="detail-row"><span>Bayar lembur</span><strong>Rp {{ number_format((int) ($payrollSlip->overtime_amount ?? 0), 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Bonus</span><strong>Rp {{ number_format($bonusAmount, 0, ',', '.') }}</strong></div>
                </article>

                <article class="staff-payroll-detail-card toned negative">
                    <div class="detail-card-title">Potongan</div>
                    <div class="detail-row"><span>Potongan alpa</span><strong>Rp {{ number_format((int) ($payrollSlip->auto_alpha_deduction ?? 0), 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Potongan izin / sakit</span><strong>Rp {{ number_format((int) ($payrollSlip->auto_approved_leave_deduction ?? 0), 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Telat</span><strong>{{ (int) ($payrollSlip->late_count ?? 0) }}x &bull; {{ (int) ($payrollSlip->late_minutes ?? 0) }} menit</strong></div>
                    <div class="detail-row"><span>Pulang cepat</span><strong>{{ (int) ($payrollSlip->early_leave_count ?? 0) }}x &bull; {{ $payrollSlip->earlyLeaveHoursLabel() }}</strong></div>
                    <div class="detail-row"><span>Potongan otomatis</span><strong>Rp {{ number_format($autoDeductionAmount, 0, ',', '.') }}</strong></div>
                    <div class="detail-row"><span>Potongan manual</span><strong>Rp {{ number_format($manualDeduction, 0, ',', '.') }}</strong></div>
                </article>
            </div>

            @if($payrollSlip->notes)
                <div class="staff-payroll-note-card">
                    <span>Catatan admin</span>
                    <strong>{{ $payrollSlip->notes }}</strong>
                </div>
            @endif

            <div class="staff-payroll-neighbour-nav">
                @if($nextSlip)
                    <a class="btn-neutral" href="{{ route('staff.payroll.show', $nextSlip) }}">Bulan Berikutnya</a>
                @endif
                @if($previousSlip)
                    <a class="btn-neutral" href="{{ route('staff.payroll.show', $previousSlip) }}">Bulan Sebelumnya</a>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
