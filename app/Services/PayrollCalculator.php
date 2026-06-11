<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\JadwalKaryawan;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\StrukSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PayrollCalculator
{
    public function calculate(Karyawan $karyawan, Carbon|string|null $periodMonth = null): array
    {
        $period = $this->resolvePeriodMonth($periodMonth);
        $start = $period->copy()->startOfMonth();
        $end = $period->copy()->endOfMonth();
        $setting = StrukSetting::current();
        $employmentType = $karyawan->employmentTypeValue();
        $salaryScheme = $karyawan->salaryScheme();
        $salarySchemeLabel = $salaryScheme === 'hourly' ? 'Per Jam' : 'Bulanan';

        $scheduledRows = JadwalKaryawan::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->orderBy('tanggal')
            ->orderBy('shift_ke')
            ->get();

        $scheduledByDate = $scheduledRows->groupBy(fn ($row) => $row->tanggal?->format('Y-m-d') ?? '-');

        $approvedLeaveDates = $this->approvedLeaveDatesFor($karyawan, $start, $end);

        $absensiRows = Absensi::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString())
            ->orderBy('tanggal')
            ->get();

        $absensiByDate = $absensiRows->keyBy(fn ($row) => $row->tanggal?->format('Y-m-d') ?? '-');

        $scheduledShiftCountRaw = (int) $scheduledRows->count();
        $scheduledDayCountRaw = (int) $scheduledByDate->count();
        $presentShiftCount = 0;
        $presentDayCount = 0;
        $alphaShiftCount = 0;
        $approvedLeaveShiftCount = 0;
        $approvedLeaveDayCount = 0;
        $alphaDayCount = 0;
        $lateCount = 0;
        $lateMinutes = 0;
        $earlyLeaveCount = 0;
        $earlyLeaveMinutes = 0;
        $regularPaidMinutes = 0;
        $sameDayOvertimeShiftCount = 0;
        $sameDayOvertimeMinutes = 0;

        foreach ($scheduledByDate as $date => $rows) {
            $scheduledCount = (int) $rows->count();
            $absensi = $absensiByDate->get($date);

            if ($approvedLeaveDates->has($date)) {
                $approvedLeaveShiftCount += $scheduledCount;
                $approvedLeaveDayCount++;

                continue;
            }

            if ($this->shouldHoldPayrollForIncompleteAttendance($absensi)) {
                continue;
            }

            if ($this->countsAsPresent($absensi)) {
                $presentShiftCount += $scheduledCount;
                $presentDayCount++;
                if ($salaryScheme === 'hourly') {
                    $regularPaidMinutes += $this->payableMinutesForAttendance($absensi, $rows, $karyawan, $setting);
                } else {
                    $workedSummary = $this->fullTimeWorkedSummaryForAttendance($absensi, $rows, $karyawan, $setting);
                    $regularPaidMinutes += $workedSummary['regular_minutes'];
                    $earlyLeaveMinutes += $workedSummary['early_leave_minutes'];
                    $sameDayOvertimeMinutes += $workedSummary['overtime_minutes'];

                    if (($workedSummary['early_leave_minutes'] ?? 0) > 0) {
                        $earlyLeaveCount++;
                    }
                    if (($workedSummary['overtime_minutes'] ?? 0) > 0) {
                        $sameDayOvertimeShiftCount++;
                    }
                }

                $late = $this->lateSummaryForAttendance($absensi, $rows, $karyawan, $setting);
                if ($late['minutes'] > 0) {
                    $lateCount++;
                    $lateMinutes += $late['minutes'];
                }
            } else {
                $alphaShiftCount += $scheduledCount;
                $alphaDayCount++;
            }
        }

        $scheduledShiftCount = max(0, $scheduledShiftCountRaw - $approvedLeaveShiftCount);
        $scheduledDayCount = max(0, $scheduledDayCountRaw - $approvedLeaveDayCount);

        $extraPresentRows = $absensiRows
            ->filter(fn (Absensi $absensi) => $this->countsAsPresent($absensi) && ! $this->shouldHoldPayrollForIncompleteAttendance($absensi))
            ->reject(function (Absensi $absensi) use ($scheduledByDate): bool {
                $date = $absensi->tanggal?->format('Y-m-d') ?? '-';

                return $scheduledByDate->has($date);
            })
            ->values();

        $extraPresentDayCount = (int) $extraPresentRows->count();
        $overtimeShiftCount = $sameDayOvertimeShiftCount + $extraPresentDayCount;
        $overtimeMinutes = $salaryScheme === 'hourly'
            ? (int) $extraPresentRows->sum(fn (Absensi $absensi) => $this->clockedHourlyMinutes($absensi, $karyawan->employmentDurationMinutes()))
            : $sameDayOvertimeMinutes + (int) $extraPresentRows->sum(fn (Absensi $absensi) => $this->clockedWorkedMinutes($absensi, $karyawan->employmentDurationMinutes()));
        $paidMinutes = $regularPaidMinutes + $overtimeMinutes;

        $baseAmount = $salaryScheme === 'hourly'
            ? (int) round(((int) ($karyawan->hourly_rate ?? 0) * $regularPaidMinutes) / 60)
            : (int) ($karyawan->monthly_salary ?? 0);

        $overtimeRate = $setting->payrollOvertimeRateFor($employmentType, (int) ($karyawan->hourly_rate ?? 0));
        $overtimeAmount = $overtimeRate > 0
            ? (int) round(($overtimeRate * $overtimeMinutes) / 60)
            : 0;

        $grossAmount = $baseAmount + $overtimeAmount;
        $fullTimeDailyRate = $salaryScheme === 'hourly'
            ? 0
            : $this->fullTimeDailyWorkdayAmount((int) ($karyawan->monthly_salary ?? 0), $scheduledDayCountRaw);
        $alphaUnitDeduction = $salaryScheme === 'hourly'
            ? $setting->payrollAlphaDeductionFor($employmentType)
            : $fullTimeDailyRate;
        $autoAlphaDeduction = $salaryScheme === 'hourly'
            ? ($alphaShiftCount * $alphaUnitDeduction)
            : ($alphaDayCount * $alphaUnitDeduction);
        $autoApprovedLeaveDeduction = $salaryScheme === 'hourly'
            ? 0
            : ($approvedLeaveDayCount * $fullTimeDailyRate);
        $autoLateDeduction = $salaryScheme === 'hourly'
            ? 0
            : $lateMinutes * $setting->payrollLateDeductionPerMinute();
        $autoEarlyLeaveDeduction = $salaryScheme === 'hourly'
            ? 0
            : $earlyLeaveMinutes * $setting->payrollLateDeductionPerMinute();
        $autoDeductionAmount = $autoAlphaDeduction + $autoApprovedLeaveDeduction + $autoLateDeduction + $autoEarlyLeaveDeduction;

        return [
            'period_key' => $period->format('Y-m'),
            'period_start' => $start,
            'period_end' => $end,
            'period_label' => $period->locale('id')->translatedFormat('F Y'),
            'employment_type' => $employmentType,
            'employment_label' => $karyawan->employmentTypeLabel(),
            'salary_scheme' => $salaryScheme,
            'salary_scheme_label' => $salarySchemeLabel,
            'monthly_salary' => (int) ($karyawan->monthly_salary ?? 0),
            'hourly_rate' => (int) ($karyawan->hourly_rate ?? 0),
            'scheduled_shift_count' => $scheduledShiftCount,
            'scheduled_day_count' => $scheduledDayCount,
            'present_shift_count' => $presentShiftCount,
            'present_day_count' => $presentDayCount,
            'alpha_shift_count' => $alphaShiftCount,
            'alpha_day_count' => $alphaDayCount,
            'approved_leave_shift_count' => $approvedLeaveShiftCount,
            'approved_leave_day_count' => $approvedLeaveDayCount,
            'extra_present_day_count' => $extraPresentDayCount,
            'late_count' => $lateCount,
            'late_minutes' => $lateMinutes,
            'early_leave_count' => $earlyLeaveCount,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'early_leave_hours_label' => $this->formatMinutes($earlyLeaveMinutes),
            'regular_paid_minutes' => $regularPaidMinutes,
            'regular_paid_hours_label' => $this->formatMinutes($regularPaidMinutes),
            'overtime_shift_count' => $overtimeShiftCount,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_hours_label' => $this->formatMinutes($overtimeMinutes),
            'overtime_rate' => $overtimeRate,
            'overtime_amount' => $overtimeAmount,
            'paid_minutes' => $paidMinutes,
            'paid_hours_label' => $this->formatMinutes($paidMinutes),
            'work_duration_label' => $karyawan->employmentDurationLabel(),
            'base_amount' => $baseAmount,
            'gross_amount' => $grossAmount,
            'auto_alpha_deduction' => $autoAlphaDeduction,
            'auto_approved_leave_deduction' => $autoApprovedLeaveDeduction,
            'auto_late_deduction' => $autoLateDeduction,
            'auto_early_leave_deduction' => $autoEarlyLeaveDeduction,
            'auto_deduction_amount' => $autoDeductionAmount,
            'estimated_net_amount' => $this->calculateNetAmount($grossAmount, 0, 0, $autoDeductionAmount),
            'policy' => [
                'alpha_deduction_unit_amount' => $alphaUnitDeduction,
                'alpha_deduction_unit_label' => $salaryScheme === 'hourly' ? 'per shift' : 'per hari kerja',
                'alpha_deduction_mode' => $salaryScheme === 'hourly' ? 'flat_shift' : 'prorata_day',
                'approved_leave_deduction_unit_amount' => $salaryScheme === 'hourly' ? 0 : $fullTimeDailyRate,
                'approved_leave_deduction_unit_label' => $salaryScheme === 'hourly' ? 'tidak dipotong' : 'per hari kerja',
                'late_deduction_per_minute' => $setting->payrollLateDeductionPerMinute(),
                'early_leave_deduction_per_minute' => $setting->payrollLateDeductionPerMinute(),
                'overtime_rate' => $overtimeRate,
            ],
        ];
    }

    public function calculateNetAmount(
        int $grossAmount,
        int $bonusAmount = 0,
        int $deductionAmount = 0,
        int $autoDeductionAmount = 0,
    ): int {
        return max(0, $grossAmount + $bonusAmount - $deductionAmount - $autoDeductionAmount);
    }

    public function resolvePeriodMonth(Carbon|string|null $periodMonth = null): Carbon
    {
        if ($periodMonth instanceof Carbon) {
            return $periodMonth->copy()->startOfMonth();
        }

        $value = trim((string) ($periodMonth ?? ''));
        if ($value === '') {
            return now()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
        } catch (\Throwable $e) {
            return now()->startOfMonth();
        }
    }

    public function formatMinutes(int $minutes): string
    {
        $hours = $minutes / 60;
        $label = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');

        return str_replace('.', ',', $label) . ' jam';
    }

    private function fullTimeDailyWorkdayAmount(int $monthlySalary, int $scheduledDayCount): int
    {
        if ($monthlySalary <= 0 || $scheduledDayCount <= 0) {
            return 0;
        }

        return (int) floor($monthlySalary / $scheduledDayCount);
    }

    private function countsAsPresent(?Absensi $absensi): bool
    {
        if (! $absensi) {
            return false;
        }

        $status = trim((string) ($absensi->status ?? ''));
        if ($status === 'alpa') {
            return false;
        }

        return $absensi->waktu_masuk !== null
            || in_array($status, ['hadir', 'telat'], true);
    }

    private function shouldHoldPayrollForIncompleteAttendance(?Absensi $absensi): bool
    {
        if (! $absensi) {
            return false;
        }

        return $absensi->waktu_masuk !== null
            && $absensi->waktu_pulang === null;
    }

    private function approvedLeaveDatesFor(Karyawan $karyawan, Carbon $start, Carbon $end): Collection
    {
        if (! Schema::hasTable('leave_requests')) {
            return collect();
        }

        $rows = LeaveRequest::query()
            ->where('id_karyawan', (int) $karyawan->id_karyawan)
            ->where('status', 'approved')
            ->whereDate('tanggal_awal', '<=', $end->toDateString())
            ->whereDate('tanggal_akhir', '>=', $start->toDateString())
            ->orderBy('tanggal_awal')
            ->get(['tanggal_awal', 'tanggal_akhir', 'jenis']);

        $dates = collect();

        foreach ($rows as $row) {
            $rangeStart = $row->tanggal_awal?->copy()?->startOfDay();
            $rangeEnd = $row->tanggal_akhir?->copy()?->startOfDay();
            if (! $rangeStart || ! $rangeEnd) {
                continue;
            }

            $cursor = $rangeStart->greaterThan($start) ? $rangeStart->copy() : $start->copy();
            $until = $rangeEnd->lessThan($end) ? $rangeEnd->copy() : $end->copy();

            while ($cursor->lte($until)) {
                $dates->put($cursor->toDateString(), [
                    'jenis' => strtolower((string) ($row->jenis ?? 'izin')),
                ]);
                $cursor->addDay();
            }
        }

        return $dates;
    }

    private function lateSummaryForAttendance(
        ?Absensi $absensi,
        Collection $scheduledRows,
        Karyawan $karyawan,
        StrukSetting $setting,
    ): array {
        if (! $this->countsAsPresent($absensi) || ! $absensi?->waktu_masuk || $scheduledRows->isEmpty()) {
            return ['minutes' => 0];
        }

        $shiftNo = (int) ($absensi->shift_no ?: ($scheduledRows->min('shift_ke') ?? 0));
        if ($shiftNo < 1) {
            $shiftNo = (int) ($scheduledRows->min('shift_ke') ?? 1);
        }

        $date = $absensi->tanggal?->format('Y-m-d') ?: now()->format('Y-m-d');
        $range = $setting->shiftRangeFor($shiftNo, $karyawan->employmentTypeValue(), $date);
        $start = trim((string) ($range['start'] ?? ''));
        if ($start === '' || $start === '-') {
            return ['minutes' => 0];
        }

        $startAt = Carbon::parse($date . ' ' . $start . ':00');
        $delay = max(0, $absensi->waktu_masuk->diffInMinutes($startAt, false) * -1);
        $tolerance = max(0, (int) ($setting->absensi_late_tolerance_minutes ?? 0));
        $effectiveMinutes = max(0, $delay - $tolerance);

        return ['minutes' => $effectiveMinutes];
    }

    private function payableMinutesForAttendance(
        ?Absensi $absensi,
        Collection $scheduledRows,
        Karyawan $karyawan,
        StrukSetting $setting,
    ): int {
        if (
            ! $this->countsAsPresent($absensi)
            || $scheduledRows->isEmpty()
            || $this->shouldHoldPayrollForIncompleteAttendance($absensi)
        ) {
            return 0;
        }

        $clockedMinutes = $this->clockedHourlyMinutes($absensi);
        if ($clockedMinutes > 0) {
            return $clockedMinutes;
        }

        $total = 0;

        foreach ($scheduledRows as $row) {
            $shiftNo = (int) ($row->shift_ke ?? 0);
            if ($shiftNo < 1) {
                continue;
            }

            $date = $row->tanggal?->format('Y-m-d') ?? $absensi?->tanggal?->format('Y-m-d') ?? now()->format('Y-m-d');
            $range = $setting->shiftRangeFor($shiftNo, $karyawan->employmentTypeValue(), $date);
            $start = trim((string) ($range['start'] ?? ''));
            if ($start === '' || $start === '-') {
                continue;
            }

            $startAt = Carbon::parse($date . ' ' . $start . ':00');
            $endAt = $startAt->copy()->addMinutes($karyawan->employmentDurationMinutes());
            $paidStartAt = $startAt->copy();

            if ($absensi?->waktu_masuk && $absensi->waktu_masuk->greaterThan($startAt)) {
                $late = $this->lateSummaryForAttendance($absensi, collect([$row]), $karyawan, $setting);
                if (($late['minutes'] ?? 0) > 0) {
                    $paidStartAt = $this->roundUpToHour($absensi->waktu_masuk->copy());
                    if ($paidStartAt->lt($startAt)) {
                        $paidStartAt = $startAt->copy();
                    }
                }
            }

            if ($paidStartAt->gte($endAt)) {
                continue;
            }

            $total += $paidStartAt->diffInMinutes($endAt);
        }

        return $total;
    }

    private function fullTimeWorkedSummaryForAttendance(
        ?Absensi $absensi,
        Collection $scheduledRows,
        Karyawan $karyawan,
        StrukSetting $setting,
    ): array {
        $scheduledMinutes = (int) $scheduledRows
            ->filter(fn ($row) => (int) ($row->shift_ke ?? 0) > 0)
            ->count() * $karyawan->employmentDurationMinutes();

        if ($scheduledMinutes <= 0) {
            return [
                'regular_minutes' => 0,
                'early_leave_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        }

        if ($this->shouldHoldPayrollForIncompleteAttendance($absensi)) {
            return [
                'regular_minutes' => 0,
                'early_leave_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        }

        $scheduledStartAt = null;
        $scheduledEndAt = null;

        foreach ($scheduledRows as $row) {
            $shiftNo = (int) ($row->shift_ke ?? 0);
            if ($shiftNo < 1) {
                continue;
            }

            $date = $row->tanggal?->format('Y-m-d') ?? $absensi?->tanggal?->format('Y-m-d') ?? now()->format('Y-m-d');
            $range = $setting->shiftRangeFor($shiftNo, $karyawan->employmentTypeValue(), $date);
            $start = trim((string) ($range['start'] ?? ''));
            if ($start === '' || $start === '-') {
                continue;
            }

            $startAt = Carbon::parse($date . ' ' . $start . ':00');
            $endAt = $startAt->copy()->addMinutes($karyawan->employmentDurationMinutes());

            if (! $scheduledStartAt || $startAt->lt($scheduledStartAt)) {
                $scheduledStartAt = $startAt;
            }
            if (! $scheduledEndAt || $endAt->gt($scheduledEndAt)) {
                $scheduledEndAt = $endAt;
            }
        }

        if (! $scheduledEndAt || ! $absensi?->waktu_pulang) {
            $workedMinutes = $this->clockedWorkedMinutes($absensi, $scheduledMinutes);
            $regularMinutes = min($workedMinutes, $scheduledMinutes);
            $earlyLeaveMinutes = max(0, $scheduledMinutes - $workedMinutes);
            $overtimeMinutes = max(0, $workedMinutes - $scheduledMinutes);

            return [
                'regular_minutes' => $regularMinutes,
                'early_leave_minutes' => $earlyLeaveMinutes,
                'overtime_minutes' => $overtimeMinutes,
            ];
        }

        $earlyLeaveMinutes = $absensi->waktu_pulang->lt($scheduledEndAt)
            ? (int) $absensi->waktu_pulang->diffInMinutes($scheduledEndAt)
            : 0;
        $overtimeMinutes = $absensi->waktu_pulang->gt($scheduledEndAt)
            ? (int) $scheduledEndAt->diffInMinutes($absensi->waktu_pulang)
            : 0;
        $regularMinutes = max(0, $scheduledMinutes - $earlyLeaveMinutes);

        return [
            'regular_minutes' => $regularMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
        ];
    }

    private function clockedHourlyMinutes(?Absensi $absensi, int $fallbackMinutes = 0): int
    {
        if ($this->shouldHoldPayrollForIncompleteAttendance($absensi)) {
            return 0;
        }

        $workedMinutes = $this->clockedWorkedMinutes($absensi, $fallbackMinutes);
        $workedHours = intdiv(max(0, $workedMinutes), 60);

        return $workedHours * 60;
    }

    private function clockedWorkedMinutes(?Absensi $absensi, int $fallbackMinutes = 0): int
    {
        if ($this->shouldHoldPayrollForIncompleteAttendance($absensi)) {
            return 0;
        }

        if (! $absensi?->waktu_masuk || ! $absensi?->waktu_pulang || $absensi->waktu_pulang->lte($absensi->waktu_masuk)) {
            return max(0, $fallbackMinutes);
        }

        return max(0, (int) $absensi->waktu_masuk->diffInMinutes($absensi->waktu_pulang));
    }

    private function roundUpToHour(Carbon $time): Carbon
    {
        if ((int) $time->format('i') === 0 && (int) $time->format('s') === 0) {
            return $time->copy()->startOfMinute();
        }

        return $time->copy()->addHour()->startOfHour();
    }
}
