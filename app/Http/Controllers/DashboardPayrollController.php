<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Models\PayrollSlip;
use App\Models\StaffNotification;
use App\Models\StrukSetting;
use App\Services\PayrollCalculator;
use App\Services\StaffNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardPayrollController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
    ) {
    }

    public function index(Request $request): View
    {
        $period = $this->calculator->resolvePeriodMonth((string) $request->query('bulan', now()->format('Y-m')));
        $setting = StrukSetting::current();
        $employees = Karyawan::query()
            ->orderByDesc('is_active')
            ->orderBy('nama_karyawan')
            ->get();

        $slips = PayrollSlip::query()
            ->whereDate('period_month', $period->toDateString())
            ->get()
            ->keyBy('id_karyawan');

        $rows = $employees->map(function (Karyawan $employee) use ($period, $slips): array {
            $summary = $this->calculator->calculate($employee, $period);
            $slip = $slips->get((int) $employee->id_karyawan);

            return [
                'employee' => $employee,
                'summary' => $summary,
                'slip' => $slip,
                'net_amount' => (int) ($slip->net_amount ?? $summary['estimated_net_amount']),
            ];
        });

        return view('dashboard.payroll.index', [
            'period' => $period,
            'periodKey' => $period->format('Y-m'),
            'periodLabel' => $period->locale('id')->translatedFormat('F Y'),
            'rows' => $rows,
            'summaryCards' => [
                'totalEmployees' => $rows->count(),
                'fullTime' => $rows->filter(fn (array $row) => $row['employee']->employmentTypeValue() === Karyawan::EMPLOYMENT_FULL_TIME)->count(),
                'partTime' => $rows->filter(fn (array $row) => $row['employee']->employmentTypeValue() === Karyawan::EMPLOYMENT_PART_TIME)->count(),
                'estimatedPayroll' => (int) $rows->sum('net_amount'),
                'finalizedCount' => $rows->filter(fn (array $row) => ($row['slip']?->status ?? null) === PayrollSlip::STATUS_FINALIZED)->count(),
            ],
            'policy' => [
                'alpha_full_time' => (int) ($setting->payroll_alpha_deduction_full_time ?? 0),
                'alpha_part_time' => (int) ($setting->payroll_alpha_deduction_part_time ?? 0),
                'late_per_minute' => (int) ($setting->payroll_late_deduction_per_minute ?? 0),
                'overtime_full_time' => (int) ($setting->payroll_overtime_rate_full_time ?? 0),
            ],
        ]);
    }

    public function show(Request $request, Karyawan $karyawan): View
    {
        $period = $this->calculator->resolvePeriodMonth((string) $request->query('bulan', now()->format('Y-m')));
        $summary = $this->calculator->calculate($karyawan, $period);
        $setting = StrukSetting::current();
        $slip = PayrollSlip::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('period_month', $period->toDateString())
            ->first();

        return view('dashboard.payroll.show', [
            'karyawan' => $karyawan,
            'period' => $period,
            'periodKey' => $period->format('Y-m'),
            'periodLabel' => $period->locale('id')->translatedFormat('F Y'),
            'summary' => $summary,
            'slip' => $slip,
            'statusOptions' => PayrollSlip::statusOptions(),
            'policy' => [
                'alpha_full_time' => (int) ($setting->payroll_alpha_deduction_full_time ?? 0),
                'alpha_part_time' => (int) ($setting->payroll_alpha_deduction_part_time ?? 0),
                'late_per_minute' => (int) ($setting->payroll_late_deduction_per_minute ?? 0),
                'overtime_full_time' => (int) ($setting->payroll_overtime_rate_full_time ?? 0),
            ],
        ]);
    }

    public function store(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $data = $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
            'bonus_amount' => ['nullable', 'integer', 'min:0'],
            'deduction_amount' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:' . implode(',', array_keys(PayrollSlip::statusOptions()))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $period = $this->calculator->resolvePeriodMonth((string) $data['bulan']);
        $summary = $this->calculator->calculate($karyawan, $period);
        $bonusAmount = (int) ($data['bonus_amount'] ?? 0);
        $deductionAmount = (int) ($data['deduction_amount'] ?? 0);
        $grossAmount = (int) ($summary['gross_amount'] ?? 0);
        $status = (string) $data['status'];
        $autoDeductionAmount = (int) ($summary['auto_deduction_amount'] ?? 0);

        $existingSlip = PayrollSlip::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('period_month', $period->toDateString())
            ->first();

        $slip = PayrollSlip::query()->updateOrCreate(
            [
                'id_karyawan' => (int) $karyawan->id_karyawan,
                'period_month' => $period->toDateString(),
            ],
            [
                'employment_type' => $summary['employment_type'],
                'salary_scheme' => $summary['salary_scheme'],
                'base_amount' => $summary['base_amount'],
                'hourly_rate' => $summary['hourly_rate'] ?: null,
                'paid_minutes' => $summary['paid_minutes'],
                'scheduled_shift_count' => $summary['scheduled_shift_count'],
                'present_shift_count' => $summary['present_shift_count'],
                'alpha_shift_count' => $summary['alpha_shift_count'],
                'approved_leave_shift_count' => $summary['approved_leave_shift_count'],
                'approved_leave_day_count' => $summary['approved_leave_day_count'],
                'late_count' => $summary['late_count'],
                'late_minutes' => $summary['late_minutes'],
                'early_leave_count' => $summary['early_leave_count'],
                'early_leave_minutes' => $summary['early_leave_minutes'],
                'overtime_shift_count' => $summary['overtime_shift_count'],
                'overtime_minutes' => $summary['overtime_minutes'],
                'overtime_rate' => $summary['overtime_rate'],
                'overtime_amount' => $summary['overtime_amount'],
                'auto_alpha_deduction' => $summary['auto_alpha_deduction'],
                'auto_approved_leave_deduction' => $summary['auto_approved_leave_deduction'],
                'auto_late_deduction' => $summary['auto_late_deduction'],
                'auto_early_leave_deduction' => $summary['auto_early_leave_deduction'],
                'auto_deduction_amount' => $autoDeductionAmount,
                'bonus_amount' => $bonusAmount,
                'deduction_amount' => $deductionAmount,
                'gross_amount' => $grossAmount,
                'net_amount' => $this->calculator->calculateNetAmount($grossAmount, $bonusAmount, $deductionAmount, $autoDeductionAmount),
                'status' => $status,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'generated_at' => now(),
                'finalized_at' => $status === PayrollSlip::STATUS_FINALIZED ? now() : null,
                'generated_by' => $request->user()?->id,
            ]
        );

        if (
            $status === PayrollSlip::STATUS_FINALIZED
            && (($existingSlip?->status ?? null) !== PayrollSlip::STATUS_FINALIZED || ! $existingSlip)
        ) {
            app(StaffNotificationService::class)->notify(
                (int) $karyawan->id_karyawan,
                StaffNotification::CATEGORY_PAYROLL,
                'Slip gaji ' . $period->locale('id')->translatedFormat('F Y') . ' sudah tersedia',
                'Admin sudah memfinalkan slip gaji bulan ini. Total diterima Rp ' . number_format((int) $slip->net_amount, 0, ',', '.') . '.',
                route('staff.payroll.show', ['payrollSlip' => $slip->getKey()]),
                'Lihat slip',
                'payroll-finalized:' . (int) $slip->getKey(),
                [
                    'type' => 'payroll',
                    'payroll_slip_id' => (int) $slip->getKey(),
                    'period' => $period->format('Y-m'),
                ]
            );
        }

        return redirect()
            ->route('dashboard.payroll.show', ['karyawan' => $karyawan, 'bulan' => $period->format('Y-m')])
            ->with('success', $status === PayrollSlip::STATUS_FINALIZED
                ? 'Slip gaji berhasil difinalkan.'
                : 'Draft slip gaji berhasil disimpan.');
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'payroll_alpha_deduction_full_time' => ['nullable', 'integer', 'min:0'],
            'payroll_alpha_deduction_part_time' => ['nullable', 'integer', 'min:0'],
            'payroll_late_deduction_per_minute' => ['nullable', 'integer', 'min:0'],
            'payroll_overtime_rate_full_time' => ['nullable', 'integer', 'min:0'],
        ]);

        $setting = StrukSetting::current();
        $payload = [
            'payroll_alpha_deduction_part_time' => (int) ($data['payroll_alpha_deduction_part_time'] ?? ($setting->payroll_alpha_deduction_part_time ?? 0)),
            'payroll_late_deduction_per_minute' => (int) ($data['payroll_late_deduction_per_minute'] ?? ($setting->payroll_late_deduction_per_minute ?? 0)),
            'payroll_overtime_rate_full_time' => (int) ($data['payroll_overtime_rate_full_time'] ?? ($setting->payroll_overtime_rate_full_time ?? 0)),
        ];

        if (array_key_exists('payroll_alpha_deduction_full_time', $data)) {
            $payload['payroll_alpha_deduction_full_time'] = (int) ($data['payroll_alpha_deduction_full_time'] ?? 0);
        }

        $setting->fill($payload)->save();

        $params = [];
        if (! empty($data['bulan'])) {
            $params['bulan'] = $data['bulan'];
        }

        return redirect()
            ->route('dashboard.payroll.index', $params)
            ->with('success', 'Aturan payroll berhasil disimpan.');
    }

    public function print(Request $request, PayrollSlip $payrollSlip): View
    {
        $payrollSlip->loadMissing('karyawan');

        return view('payroll.print', [
            'payrollSlip' => $payrollSlip,
            'karyawan' => $payrollSlip->karyawan,
            'viewer' => 'admin',
            'autoprint' => (bool) $request->boolean('autoprint'),
        ]);
    }
}
