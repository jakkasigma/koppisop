<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji {{ $payrollSlip->periodLabel() }}</title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #dbe5ea;
            --panel: #ffffff;
            --bg: #f8fafc;
            --accent: #0f766e;
            --accent-soft: #ecfdf5;
            --danger-soft: #fef2f2;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--ink);
            background: var(--bg);
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 10px;
            justify-content: center;
            padding: 14px;
            background: rgba(248, 250, 252, .95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--line);
        }
        .toolbar a,
        .toolbar button {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .toolbar .print { background: var(--accent); color: #fff; }
        .toolbar .back { background: #fff; color: var(--ink); border: 1px solid var(--line); }
        .page {
            max-width: 860px;
            margin: 24px auto;
            padding: 24px;
        }
        .sheet {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .08);
        }
        .hero {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 22px;
        }
        .eyebrow { color: var(--accent); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
        h1 { margin: 8px 0 6px; font-size: 30px; }
        .sub { color: var(--muted); font-size: 14px; }
        .status {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-weight: 800;
            border: 1px solid #bbf7d0;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }
        .card {
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 16px;
            background: #fff;
        }
        .card .label { color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .card strong { display: block; margin-top: 8px; font-size: 24px; }
        .card p { margin: 8px 0 0; color: var(--muted); font-size: 13px; }
        .section { margin-top: 22px; }
        .section h2 { margin: 0 0 12px; font-size: 18px; }
        .rows {
            display: grid;
            gap: 10px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            border-bottom: 1px dashed var(--line);
            padding-bottom: 10px;
            font-size: 14px;
        }
        .row strong { text-align: right; }
        .row.muted strong { color: var(--muted); }
        .row.total {
            border-bottom: 0;
            padding-top: 8px;
            font-size: 18px;
            font-weight: 800;
        }
        .row.total strong { color: var(--accent); }
        .note {
            margin-top: 18px;
            border-radius: 16px;
            padding: 14px 16px;
            background: var(--danger-soft);
            border: 1px solid #fecaca;
        }
        .note span {
            display: block;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #991b1b;
            margin-bottom: 6px;
        }
        .footer {
            margin-top: 18px;
            font-size: 12px;
            color: var(--muted);
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; max-width: none; padding: 0; }
            .sheet { box-shadow: none; border: 0; border-radius: 0; padding: 0; }
        }
        @media (max-width: 720px) {
            .page { padding: 14px; }
            .sheet { padding: 18px; border-radius: 18px; }
            .hero { flex-direction: column; }
            .grid { grid-template-columns: 1fr; }
            .row { flex-direction: column; }
            .row strong { text-align: left; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="print" onclick="window.print()">Cetak Slip</button>
        <a class="back" href="{{ $viewer === 'admin' ? route('dashboard.payroll.show', ['karyawan' => $karyawan, 'bulan' => $payrollSlip->period_month?->format('Y-m')]) : route('staff.payroll.show', $payrollSlip) }}">Kembali</a>
    </div>

    <div class="page">
        <div class="sheet">
            <div class="hero">
                <div>
                    <div class="eyebrow">Slip Gaji {{ $viewer === 'admin' ? 'Admin' : 'Karyawan' }}</div>
                    <h1>{{ $payrollSlip->periodLabel() }}</h1>
                    <div class="sub">{{ $karyawan->nama_karyawan }} • {{ $karyawan->jabatan ?: 'Staf' }} • {{ $karyawan->employmentTypeLabel() }}</div>
                </div>
                <span class="status">{{ $payrollSlip->statusLabel() }}</span>
            </div>

            <div class="grid">
                <div class="card">
                    <div class="label">Gaji Bersih</div>
                    <strong>Rp {{ number_format((int) $payrollSlip->net_amount, 0, ',', '.') }}</strong>
                    <p>Nilai akhir slip setelah bonus dan semua potongan.</p>
                </div>
                <div class="card">
                    <div class="label">Jam Dibayar</div>
                    <strong>{{ $payrollSlip->paidHoursLabel() }}</strong>
                    <p>{{ (int) $payrollSlip->present_shift_count }} shift hadir • {{ (int) $payrollSlip->alpha_shift_count }} shift alpa • {{ (int) ($payrollSlip->late_count ?? 0) }} kali telat</p>
                </div>
            </div>

            <div class="section">
                <h2>Rincian Perhitungan</h2>
                <div class="rows">
                    <div class="row"><span>Skema gaji</span><strong>{{ $payrollSlip->salary_scheme === 'hourly' ? 'Per Jam' : 'Bulanan' }}</strong></div>
                    <div class="row"><span>Gaji dasar</span><strong>Rp {{ number_format((int) $payrollSlip->base_amount, 0, ',', '.') }}</strong></div>
                    <div class="row"><span>Lembur</span><strong>{{ $payrollSlip->overtimeHoursLabel() }} • Rp {{ number_format((int) ($payrollSlip->overtime_amount ?? 0), 0, ',', '.') }}</strong></div>
                    <div class="row"><span>Bonus</span><strong>Rp {{ number_format((int) $payrollSlip->bonus_amount, 0, ',', '.') }}</strong></div>
                    <div class="row muted"><span>Potongan alpa otomatis</span><strong>Rp {{ number_format((int) ($payrollSlip->auto_alpha_deduction ?? 0), 0, ',', '.') }}</strong></div>
                    <div class="row muted"><span>Potongan izin/sakit approved</span><strong>Rp {{ number_format((int) ($payrollSlip->auto_approved_leave_deduction ?? 0), 0, ',', '.') }}</strong></div>
                    <div class="row muted"><span>Potongan telat otomatis</span><strong>Rp {{ number_format((int) ($payrollSlip->auto_late_deduction ?? 0), 0, ',', '.') }}</strong></div>
                    <div class="row muted"><span>Potongan pulang cepat otomatis</span><strong>Rp {{ number_format((int) ($payrollSlip->auto_early_leave_deduction ?? 0), 0, ',', '.') }}</strong></div>
                    <div class="row muted"><span>Potongan manual admin</span><strong>Rp {{ number_format((int) $payrollSlip->deduction_amount, 0, ',', '.') }}</strong></div>
                    <div class="row total"><span>Total diterima</span><strong>Rp {{ number_format((int) $payrollSlip->net_amount, 0, ',', '.') }}</strong></div>
                </div>
            </div>

            <div class="section">
                <h2>Ringkasan Kehadiran</h2>
                <div class="rows">
                    <div class="row"><span>Shift terjadwal</span><strong>{{ (int) $payrollSlip->scheduled_shift_count }} shift</strong></div>
                    <div class="row"><span>Shift hadir</span><strong>{{ (int) $payrollSlip->present_shift_count }} shift</strong></div>
                    @if((int) ($payrollSlip->approved_leave_shift_count ?? 0) > 0)
                        <div class="row"><span>Izin / sakit approved</span><strong>{{ (int) ($payrollSlip->approved_leave_shift_count ?? 0) }} shift • {{ (int) ($payrollSlip->approved_leave_day_count ?? 0) }} hari</strong></div>
                    @endif
                    <div class="row"><span>Shift alpa</span><strong>{{ (int) $payrollSlip->alpha_shift_count }} shift</strong></div>
                    <div class="row"><span>Telat</span><strong>{{ (int) ($payrollSlip->late_count ?? 0) }} kali • {{ (int) ($payrollSlip->late_minutes ?? 0) }} menit</strong></div>
                    <div class="row"><span>Pulang cepat</span><strong>{{ (int) ($payrollSlip->early_leave_count ?? 0) }} kali • {{ $payrollSlip->earlyLeaveHoursLabel() }}</strong></div>
                </div>
            </div>

            @if($payrollSlip->notes)
                <div class="note">
                    <span>Catatan Admin</span>
                    <div>{{ $payrollSlip->notes }}</div>
                </div>
            @endif

            <div class="footer">
                Slip dibuat {{ optional($payrollSlip->generated_at)->format('d M Y H:i') ?: '-' }} @if($payrollSlip->finalized_at)• difinalkan {{ $payrollSlip->finalized_at->format('d M Y H:i') }}@endif
            </div>
        </div>
    </div>

    @if(!empty($autoprint))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();
                }, 200);
            });
        </script>
    @endif
</body>
</html>
