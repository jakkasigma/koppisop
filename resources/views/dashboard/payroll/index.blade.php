@extends('layouts.app')

@section('title', 'Gaji Karyawan')

@section('content')
<div class="container admin-form-page">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Payroll Desk</div>
            <h1>Gaji Karyawan</h1>
            <p>Kelola slip gaji staf per bulan. Full Time memakai gaji bulanan dengan potongan alpa prorata harian, Part Time dihitung per jam hadir.</p>
        </div>
        <div class="admin-page-actions">
            <form method="get" action="{{ route('dashboard.payroll.index') }}" class="payroll-month-filter">
                <label for="bulan">Periode Slip</label>
                <input id="bulan" type="month" name="bulan" value="{{ $periodKey }}">
                <button class="btn-primary" type="submit">Buka</button>
            </form>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi gray">
            <div class="label">Periode</div>
            <div class="value">{{ $periodLabel }}</div>
            <div class="sub">Slip gaji dan estimasi dihitung untuk bulan ini.</div>
        </div>
        <div class="kpi">
            <div class="label">Total Karyawan</div>
            <div class="value">{{ number_format((int) $summaryCards['totalEmployees'], 0, ',', '.') }}</div>
            <div class="sub">{{ number_format((int) $summaryCards['fullTime'], 0, ',', '.') }} full time • {{ number_format((int) $summaryCards['partTime'], 0, ',', '.') }} part time</div>
        </div>
        <div class="kpi blue">
            <div class="label">Estimasi Payroll</div>
            <div class="value">Rp {{ number_format((int) $summaryCards['estimatedPayroll'], 0, ',', '.') }}</div>
            <div class="sub">Total nilai slip saat ini untuk periode terpilih.</div>
        </div>
        <div class="kpi">
            <div class="label">Slip Final</div>
            <div class="value">{{ number_format((int) $summaryCards['finalizedCount'], 0, ',', '.') }}</div>
            <div class="sub">Slip yang sudah dikunci dan siap dibagikan ke staf.</div>
        </div>
    </div>

    <div class="panel">
        @if(session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif

        <div class="payroll-panel-head">
            <div>
                <h2>Aturan Payroll</h2>
                <p>Atur potongan alpa part time, potongan telat, dan tarif lembur full time. Full time sekarang otomatis pakai nilai per hari kerja untuk alpa serta izin/sakit approved.</p>
            </div>
        </div>

        <div class="payroll-policy-summary">
            <article class="payroll-policy-card">
                <span>Alpa Full Time</span>
                <strong>Prorata Harian</strong>
                <p>Otomatis dihitung dari gaji bulanan dibagi jumlah hari kerja terjadwal.</p>
            </article>
            <article class="payroll-policy-card">
                <span>Izin / Sakit FT</span>
                <strong>Kurangi Hari Bayar</strong>
                <p>Approved tidak dianggap alpa, tapi tetap mengurangi nilai satu hari kerja full time.</p>
            </article>
            <article class="payroll-policy-card">
                <span>Alpa Part Time</span>
                <strong>Rp {{ number_format((int) ($policy['alpha_part_time'] ?? 0), 0, ',', '.') }}</strong>
                <p>Dipakai untuk staf part time yang tidak hadir.</p>
            </article>
            <article class="payroll-policy-card">
                <span>Telat per Menit</span>
                <strong>Rp {{ number_format((int) ($policy['late_per_minute'] ?? 0), 0, ',', '.') }}</strong>
                <p>Berlaku untuk skema yang memakai potongan telat.</p>
            </article>
            <article class="payroll-policy-card">
                <span>Lembur Full Time</span>
                <strong>Rp {{ number_format((int) ($policy['overtime_full_time'] ?? 0), 0, ',', '.') }}</strong>
                <p>Tarif lembur resmi per jam untuk full time.</p>
            </article>
        </div>

        <form method="post" action="{{ route('dashboard.payroll.policy.update') }}" class="form-grid payroll-policy-form">
            @csrf
            <input type="hidden" name="bulan" value="{{ $periodKey }}">
            <div class="field">
                <label>Potongan Alpa Full Time</label>
                <div class="hint">Otomatis: gaji bulanan &divide; hari kerja terjadwal. Nilai per hari ini dipakai untuk alpa serta izin/sakit approved full time.</div>
            </div>
            <div class="field">
                <label>Potongan Alpa Part Time / shift</label>
                <input type="number" min="0" step="1000" name="payroll_alpha_deduction_part_time" value="{{ old('payroll_alpha_deduction_part_time', (int) ($policy['alpha_part_time'] ?? 0)) }}">
            </div>
            <div class="field">
                <label>Potongan Telat / menit</label>
                <input type="number" min="0" step="100" name="payroll_late_deduction_per_minute" value="{{ old('payroll_late_deduction_per_minute', (int) ($policy['late_per_minute'] ?? 0)) }}">
            </div>
            <div class="field">
                <label>Tarif Lembur Full Time / jam</label>
                <input type="number" min="0" step="1000" name="payroll_overtime_rate_full_time" value="{{ old('payroll_overtime_rate_full_time', (int) ($policy['overtime_full_time'] ?? 0)) }}">
            </div>
            <div class="actions full">
                <button class="btn-primary" type="submit">Simpan Aturan</button>
            </div>
        </form>

        <div class="payroll-panel-head">
            <div>
                <h2>Daftar Slip Gaji</h2>
                <p>Pilih karyawan untuk cek hitungan realtime, lalu simpan sebagai draft atau final.</p>
            </div>
            <div class="payroll-legend">
                <span class="pill ok">Final</span>
                <span class="pill gray">Draft</span>
                <span class="pill neu">Belum dibuat</span>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Karyawan</th>
                    <th>Tipe</th>
                    <th>Skema</th>
                    <th>Jadwal</th>
                    <th>Jam Dibayar</th>
                    <th>Nilai Dasar</th>
                    <th>Slip</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    @php
                        /** @var \App\Models\Karyawan $employee */
                        $employee = $row['employee'];
                        $summary = $row['summary'];
                        $slip = $row['slip'];
                    @endphp
                    <tr>
                        <td>
                            <div class="payroll-employee">
                                <div class="payroll-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $employee->nama_karyawan, 0, 1)) }}</div>
                                <div>
                                    <strong>{{ $employee->nama_karyawan }}</strong>
                                    <span>{{ $employee->jabatan ?: 'Staf' }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="pill gray">{{ $employee->employmentTypeLabel() }}</span>
                            <div class="pin-help u-mt-2">{{ $employee->employmentDurationLabel() }} / shift</div>
                        </td>
                        <td>
                            <span class="pill neu">{{ $employee->salarySchemeLabel() }}</span>
                            <div class="pin-help u-mt-2">{{ $employee->baseSalaryLabel() }}</div>
                        </td>
                        <td>
                            <div class="payroll-count-grid">
                                <span>Terjadwal <strong>{{ $summary['scheduled_shift_count'] }}</strong></span>
                                <span>Hadir <strong>{{ $summary['present_shift_count'] }}</strong></span>
                                @if(($summary['approved_leave_shift_count'] ?? 0) > 0)
                                    <span>Izin/Sakit <strong>{{ $summary['approved_leave_shift_count'] }}</strong></span>
                                @endif
                                <span>Alpa <strong>{{ $summary['alpha_shift_count'] }}</strong></span>
                            </div>
                        </td>
                        <td>
                            <strong>{{ $summary['paid_hours_label'] }}</strong>
                            <div class="pin-help u-mt-2">{{ $summary['present_day_count'] }} hari masuk</div>
                        </td>
                        <td>
                            <strong>Rp {{ number_format((int) $summary['gross_amount'], 0, ',', '.') }}</strong>
                            <div class="pin-help u-mt-2">Net saat ini: Rp {{ number_format((int) $row['net_amount'], 0, ',', '.') }}</div>
                        </td>
                        <td>
                            @if($slip)
                                <span class="pill {{ $slip->status === \App\Models\PayrollSlip::STATUS_FINALIZED ? 'ok' : 'gray' }}">{{ $slip->statusLabel() }}</span>
                                <div class="pin-help u-mt-2">Update {{ $slip->updated_at?->format('d M H:i') ?? '-' }}</div>
                            @else
                                <span class="pill neu">Belum dibuat</span>
                                <div class="pin-help u-mt-2">Hitungan masih realtime.</div>
                            @endif
                        </td>
                        <td>
                            <a class="btn-primary btn-mini" href="{{ route('dashboard.payroll.show', ['karyawan' => $employee, 'bulan' => $periodKey]) }}">Kelola Slip</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="u-text-center u-text-muted u-p-18">Belum ada data karyawan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
