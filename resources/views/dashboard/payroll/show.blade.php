@extends('layouts.app')

@section('title', 'Kelola Slip Gaji')

@section('content')
<div class="container admin-form-page">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Slip Builder</div>
            <h1>Kelola Slip Gaji</h1>
            <p>{{ $karyawan->nama_karyawan }} &middot; {{ $periodLabel }} &middot; {{ $karyawan->employmentTypeLabel() }}</p>
        </div>
        <div class="admin-page-actions">
            <a class="admin-chip" href="{{ route('dashboard.payroll.index', ['bulan' => $periodKey]) }}">Kembali ke daftar</a>
            @if($slip)
                <a class="admin-chip soft" href="{{ route('dashboard.payroll.print', ['payrollSlip' => $slip, 'autoprint' => 1]) }}" target="_blank" rel="noopener">Cetak Slip</a>
            @endif
        </div>
    </div>

    <div class="payroll-person-card panel">
        <div class="payroll-person-main">
            <div class="payroll-person-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $karyawan->nama_karyawan, 0, 1)) }}</div>
            <div class="payroll-person-copy">
                <strong>{{ $karyawan->nama_karyawan }}</strong>
                <span>{{ $karyawan->jabatan ?: 'Staf Operasional' }}</span>
                <div class="payroll-person-meta">
                    <span class="pill gray">{{ $karyawan->employmentTypeLabel() }}</span>
                    <span class="pill neu">{{ $summary['salary_scheme_label'] }}</span>
                    <span class="pill {{ ($slip?->status ?? '') === \App\Models\PayrollSlip::STATUS_FINALIZED ? 'ok' : 'gray' }}">{{ $slip?->statusLabel() ?? 'Belum dibuat' }}</span>
                </div>
            </div>
        </div>
        <div class="payroll-person-side">
            <div class="payroll-person-stat">
                <span>Periode</span>
                <strong>{{ $periodLabel }}</strong>
            </div>
            <div class="payroll-person-stat">
                <span>Gaji Bersih Saat Ini</span>
                <strong>Rp {{ number_format((int) ($slip->net_amount ?? $summary['gross_amount'] - $summary['auto_deduction_amount']), 0, ',', '.') }}</strong>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="kpi-grid payroll-kpi-grid">
        <div class="kpi">
            <div class="label">Skema Gaji</div>
            <div class="value">{{ $summary['salary_scheme_label'] }}</div>
            <div class="sub">{{ $karyawan->baseSalaryLabel() }}</div>
        </div>
        <div class="kpi gray">
            <div class="label">Jam Dibayar</div>
            <div class="value">{{ $summary['paid_hours_label'] }}</div>
            <div class="sub">Reguler {{ $summary['regular_paid_hours_label'] }} @if($summary['overtime_minutes'] > 0)&middot; Lembur {{ $summary['overtime_hours_label'] }}@endif</div>
        </div>
        <div class="kpi blue">
            <div class="label">Gaji Kotor</div>
            <div class="value">Rp {{ number_format((int) $summary['gross_amount'], 0, ',', '.') }}</div>
            <div class="sub">Dasar Rp {{ number_format((int) $summary['base_amount'], 0, ',', '.') }} @if($summary['overtime_amount'] > 0)&middot; Lembur Rp {{ number_format((int) $summary['overtime_amount'], 0, ',', '.') }}@endif</div>
        </div>
        <div class="kpi {{ ($slip?->status ?? '') === \App\Models\PayrollSlip::STATUS_FINALIZED ? '' : 'gray' }}">
            <div class="label">Status Slip</div>
            <div class="value">{{ $slip?->statusLabel() ?? 'Belum dibuat' }}</div>
            <div class="sub">{{ $slip?->updated_at?->format('d M Y H:i') ?? 'Belum pernah disimpan admin.' }}</div>
        </div>
    </div>

    <div class="payroll-detail-grid">
        <div class="panel">
            <div class="payroll-panel-head">
                <div>
                    <h2>Ringkasan Realtime</h2>
                    <p>Hitungan ini mengikuti tipe kerja, jadwal, dan absensi pada periode terpilih.</p>
                </div>
            </div>

            <div class="payroll-summary-list">
                <div class="summary-row"><span>Shift terjadwal</span><strong>{{ $summary['scheduled_shift_count'] }} shift</strong></div>
                <div class="summary-row"><span>Shift hadir</span><strong>{{ $summary['present_shift_count'] }} shift</strong></div>
                @if(($summary['approved_leave_shift_count'] ?? 0) > 0)
                    <div class="summary-row"><span>Izin / sakit disetujui</span><strong>{{ $summary['approved_leave_shift_count'] }} shift &middot; {{ $summary['approved_leave_day_count'] ?? 0 }} hari</strong></div>
                @endif
                <div class="summary-row"><span>Shift alpa</span><strong>{{ $summary['alpha_shift_count'] }} shift @if(($summary['alpha_day_count'] ?? 0) > 0)&middot; {{ $summary['alpha_day_count'] }} hari @endif</strong></div>
                <div class="summary-row"><span>Telat tercatat</span><strong>{{ $summary['late_count'] }} kali &middot; {{ $summary['late_minutes'] }} menit</strong></div>
                <div class="summary-row"><span>Pulang cepat</span><strong>{{ $summary['early_leave_count'] }} kali &middot; {{ $summary['early_leave_hours_label'] }}</strong></div>
                <div class="summary-row"><span>Hari hadir tercatat</span><strong>{{ $summary['present_day_count'] }} hari</strong></div>
                <div class="summary-row"><span>Jam reguler</span><strong>{{ $summary['regular_paid_hours_label'] }}</strong></div>
                <div class="summary-row"><span>Jam lembur</span><strong>{{ $summary['overtime_hours_label'] }}</strong></div>
                @if($summary['salary_scheme'] === 'hourly')
                    <div class="summary-row"><span>Tarif per jam</span><strong>Rp {{ number_format((int) $summary['hourly_rate'], 0, ',', '.') }}</strong></div>
                @else
                    <div class="summary-row"><span>Gaji bulanan</span><strong>Rp {{ number_format((int) $summary['monthly_salary'], 0, ',', '.') }}</strong></div>
                @endif
                <div class="summary-row grand"><span>Gaji dasar</span><strong>Rp {{ number_format((int) $summary['base_amount'], 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Tarif lembur</span><strong>Rp {{ number_format((int) $summary['overtime_rate'], 0, ',', '.') }} / jam</strong></div>
                <div class="summary-row"><span>Bayar lembur</span><strong>Rp {{ number_format((int) $summary['overtime_amount'], 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Potongan alpa otomatis</span><strong>Rp {{ number_format((int) $summary['auto_alpha_deduction'], 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Potongan izin / sakit approved</span><strong>Rp {{ number_format((int) ($summary['auto_approved_leave_deduction'] ?? 0), 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Potongan telat otomatis</span><strong>Rp {{ number_format((int) $summary['auto_late_deduction'], 0, ',', '.') }}</strong></div>
                <div class="summary-row"><span>Potongan pulang cepat otomatis</span><strong>Rp {{ number_format((int) $summary['auto_early_leave_deduction'], 0, ',', '.') }}</strong></div>
                <div class="summary-row grand"><span>Potongan otomatis total</span><strong>Rp {{ number_format((int) $summary['auto_deduction_amount'], 0, ',', '.') }}</strong></div>
            </div>

            <div class="payroll-policy-note">
                <strong>Aturan aktif</strong>
                <span>FT alpa Rp {{ number_format((int) ($summary['policy']['alpha_deduction_unit_amount'] ?? 0), 0, ',', '.') }} / {{ $summary['policy']['alpha_deduction_unit_label'] ?? 'hari kerja' }}</span>
                <span>FT izin/sakit approved Rp {{ number_format((int) ($summary['policy']['approved_leave_deduction_unit_amount'] ?? 0), 0, ',', '.') }} / {{ $summary['policy']['approved_leave_deduction_unit_label'] ?? 'hari kerja' }}</span>
                <span>Perhitungan FT: gaji bulanan &divide; jumlah hari kerja terjadwal di bulan ini</span>
                <span>PT alpa Rp {{ number_format((int) ($policy['alpha_part_time'] ?? 0), 0, ',', '.') }} / shift</span>
                <span>Telat Rp {{ number_format((int) ($policy['late_per_minute'] ?? 0), 0, ',', '.') }} / menit</span>
                <span>Pulang cepat FT ikut tarif telat per menit</span>
                <span>Lembur FT Rp {{ number_format((int) ($policy['overtime_full_time'] ?? 0), 0, ',', '.') }} / jam</span>
            </div>
        </div>

        <div class="panel payroll-form-panel">
            <div class="payroll-panel-head">
                <div>
                    <h2>Simpan Slip</h2>
                    <p>Tambahkan bonus, potongan, lalu simpan sebagai draft atau final.</p>
                </div>
            </div>

            <form method="post" action="{{ route('dashboard.payroll.store', $karyawan) }}" class="form-grid">
                @csrf
                <input type="hidden" name="bulan" value="{{ $periodKey }}">
                <div class="field">
                    <label>Bonus</label>
                    <input type="number" min="0" step="1000" name="bonus_amount" value="{{ old('bonus_amount', (int) ($slip->bonus_amount ?? 0)) }}">
                    <div class="hint">Tambahan manual seperti insentif atau bonus target.</div>
                </div>
                <div class="field">
                    <label>Potongan Manual</label>
                    <input type="number" min="0" step="1000" name="deduction_amount" value="{{ old('deduction_amount', (int) ($slip->deduction_amount ?? 0)) }}">
                    <div class="hint">Potongan otomatis alpa/telat sudah dihitung terpisah di atas.</div>
                </div>
                <div class="field">
                    <label>Status Slip</label>
                    <select name="status" required>
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $slip->status ?? \App\Models\PayrollSlip::STATUS_DRAFT) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field full">
                    <label>Catatan Slip</label>
                    <textarea name="notes" rows="4" placeholder="Contoh: bonus event weekend, potongan kasbon, dll.">{{ old('notes', $slip->notes ?? '') }}</textarea>
                </div>
                @php
                    $previewBonus = (int) old('bonus_amount', (int) ($slip->bonus_amount ?? 0));
                    $previewDeduction = (int) old('deduction_amount', (int) ($slip->deduction_amount ?? 0));
                    $previewNet = max(0, (int) $summary['gross_amount'] + $previewBonus - $previewDeduction - (int) $summary['auto_deduction_amount']);
                @endphp
                <div class="payroll-net-preview full">
                    <span>Potongan otomatis alpa + telat + pulang cepat</span>
                    <strong>Rp {{ number_format((int) $summary['auto_deduction_amount'], 0, ',', '.') }}</strong>
                </div>
                <div class="payroll-net-preview full">
                    <span>Estimasi gaji bersih saat disimpan</span>
                    <strong>Rp {{ number_format($previewNet, 0, ',', '.') }}</strong>
                </div>
                <div class="actions full">
                    <button class="btn-primary" type="submit">Simpan Slip</button>
                    <a class="btn-neutral" href="{{ route('dashboard.payroll.index', ['bulan' => $periodKey]) }}">Kembali</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
