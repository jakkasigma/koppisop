<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class StrukSetting extends Model
{
    protected $table = 'struk_settings';

    protected $fillable = [
        'nama_toko',
        'alamat_toko',
        'header_text',
        'footer_text',
        'logo_path',
        'show_logo',
        'logo_max_width',
        'show_kode_nota',
        'show_id_pesanan',
        'show_waktu',
        'show_pelanggan',
        'show_kasir',
        'show_metode',
        'show_status',
        'auto_print_checker',
        'operasional_reset_hour',
        'active_shift_count',
        'default_cash_float',
        'setoran_interval_days',
        'enable_keuangan_menu',
        'enable_tax',
        'tax_percent',
        'tax_mode',
        'enable_payment_cash',
        'enable_payment_qris',
        'enable_payment_debit',
        'enable_payment_shopeefood',
        'enable_payment_gofood',
        'enable_payment_grabfood',
        'absensi_require_login',
        'absensi_require_selfie',
        'absensi_require_geofence',
        'absensi_geo_lat',
        'absensi_geo_lng',
        'absensi_geo_radius_m',
        'absensi_geo_max_accuracy_m',
        'shift1_start_time',
        'shift2_start_time',
        'shift3_start_time',
        'part_time_shift1_start_time',
        'part_time_shift2_start_time',
        'part_time_shift3_start_time',
        'absensi_late_tolerance_minutes',
        'absensi_checkin_before_minutes',
        'absensi_checkin_after_minutes',
        'self_schedule_enabled',
        'self_schedule_is_open',
        'self_schedule_pick_start_date',
        'self_schedule_pick_end_date',
        'self_schedule_open_start_date',
        'self_schedule_open_end_date',
        'self_schedule_capacity_shift1',
        'self_schedule_capacity_shift2',
        'self_schedule_capacity_shift3',
        'self_schedule_part_time_capacity_shift1',
        'self_schedule_part_time_capacity_shift2',
        'self_schedule_part_time_capacity_shift3',
        'self_schedule_min_per_week',
        'self_schedule_max_per_week',
        'self_schedule_min_per_month',
        'self_schedule_max_per_month',
        'self_schedule_part_time_min_per_week',
        'self_schedule_part_time_max_per_week',
        'self_schedule_part_time_min_per_month',
        'self_schedule_part_time_max_per_month',
        'self_schedule_allow_cancel',
        'self_schedule_cancel_min_days_before',
        'self_schedule_capacity_weekend_shift1',
        'self_schedule_capacity_weekend_shift2',
        'self_schedule_capacity_weekend_shift3',
        'self_schedule_part_time_capacity_weekend_shift1',
        'self_schedule_part_time_capacity_weekend_shift2',
        'self_schedule_part_time_capacity_weekend_shift3',
        'mode_template',
        'nama_toko_admin',
        'alamat_toko_admin',
        'header_text_admin',
        'footer_text_admin',
        'nama_toko_kasir',
        'alamat_toko_kasir',
        'header_text_kasir',
        'footer_text_kasir',
        'nama_cabang',
        'theme_primary',
        'theme_secondary',
        'theme_bg',
        'payroll_alpha_deduction_full_time',
        'payroll_alpha_deduction_part_time',
        'payroll_late_deduction_per_minute',
        'payroll_overtime_rate_full_time',
    ];

    protected $casts = [
        'show_logo' => 'boolean',
        'show_kode_nota' => 'boolean',
        'show_id_pesanan' => 'boolean',
        'show_waktu' => 'boolean',
        'show_pelanggan' => 'boolean',
        'show_kasir' => 'boolean',
        'show_metode' => 'boolean',
        'show_status' => 'boolean',
        'auto_print_checker' => 'boolean',
        'operasional_reset_hour' => 'integer',
        'active_shift_count' => 'integer',
        'default_cash_float' => 'float',
        'setoran_interval_days' => 'integer',
        'enable_keuangan_menu' => 'boolean',
        'enable_tax' => 'boolean',
        'tax_percent' => 'float',
        'enable_payment_cash' => 'boolean',
        'enable_payment_qris' => 'boolean',
        'enable_payment_debit' => 'boolean',
        'enable_payment_shopeefood' => 'boolean',
        'enable_payment_gofood' => 'boolean',
        'enable_payment_grabfood' => 'boolean',
        'absensi_require_login' => 'boolean',
        'absensi_require_selfie' => 'boolean',
        'absensi_require_geofence' => 'boolean',
        'absensi_geo_lat' => 'float',
        'absensi_geo_lng' => 'float',
        'absensi_geo_radius_m' => 'integer',
        'absensi_geo_max_accuracy_m' => 'integer',
        'absensi_late_tolerance_minutes' => 'integer',
        'absensi_checkin_before_minutes' => 'integer',
        'absensi_checkin_after_minutes' => 'integer',
        'self_schedule_enabled' => 'boolean',
        'self_schedule_is_open' => 'boolean',
        'self_schedule_pick_start_date' => 'date',
        'self_schedule_pick_end_date' => 'date',
        'self_schedule_open_start_date' => 'date',
        'self_schedule_open_end_date' => 'date',
        'self_schedule_capacity_shift1' => 'integer',
        'self_schedule_capacity_shift2' => 'integer',
        'self_schedule_capacity_shift3' => 'integer',
        'self_schedule_part_time_capacity_shift1' => 'integer',
        'self_schedule_part_time_capacity_shift2' => 'integer',
        'self_schedule_part_time_capacity_shift3' => 'integer',
        'self_schedule_min_per_week' => 'integer',
        'self_schedule_max_per_week' => 'integer',
        'self_schedule_min_per_month' => 'integer',
        'self_schedule_max_per_month' => 'integer',
        'self_schedule_part_time_min_per_week' => 'integer',
        'self_schedule_part_time_max_per_week' => 'integer',
        'self_schedule_part_time_min_per_month' => 'integer',
        'self_schedule_part_time_max_per_month' => 'integer',
        'self_schedule_allow_cancel' => 'boolean',
        'self_schedule_cancel_min_days_before' => 'integer',
        'self_schedule_capacity_weekend_shift1' => 'integer',
        'self_schedule_capacity_weekend_shift2' => 'integer',
        'self_schedule_capacity_weekend_shift3' => 'integer',
        'self_schedule_part_time_capacity_weekend_shift1' => 'integer',
        'self_schedule_part_time_capacity_weekend_shift2' => 'integer',
        'self_schedule_part_time_capacity_weekend_shift3' => 'integer',
        'logo_max_width' => 'integer',
        'theme_primary' => 'string',
        'theme_secondary' => 'string',
        'theme_bg' => 'string',
        'payroll_alpha_deduction_full_time' => 'integer',
        'payroll_alpha_deduction_part_time' => 'integer',
        'payroll_late_deduction_per_minute' => 'integer',
        'payroll_overtime_rate_full_time' => 'integer',
    ];

    public static function current(): self
    {
        $setting = static::query()->first();

        if ($setting) {
            return $setting;
        }

        return static::query()->create([
            'nama_toko' => 'KOPISOP',
            'alamat_toko' => null,
            'header_text' => null,
            'footer_text' => null,
            'logo_path' => null,
            'show_logo' => true,
            'logo_max_width' => 120,
            'show_kode_nota' => true,
            'show_id_pesanan' => true,
            'show_waktu' => true,
            'show_pelanggan' => true,
            'show_kasir' => true,
            'show_metode' => true,
            'show_status' => true,
            'auto_print_checker' => true,
            'operasional_reset_hour' => 3,
            'active_shift_count' => 2,
            'default_cash_float' => null,
            'setoran_interval_days' => 7,
            'enable_keuangan_menu' => true,
            'enable_tax' => false,
            'tax_percent' => 0,
            'tax_mode' => 'transaksi',
            'enable_payment_cash' => true,
            'enable_payment_qris' => true,
            'enable_payment_debit' => true,
            'enable_payment_shopeefood' => false,
            'enable_payment_gofood' => false,
            'enable_payment_grabfood' => false,
            'absensi_require_login' => false,
            'absensi_require_selfie' => false,
            'absensi_require_geofence' => false,
            'absensi_geo_lat' => null,
            'absensi_geo_lng' => null,
            'absensi_geo_radius_m' => 150,
            'absensi_geo_max_accuracy_m' => 80,
            'shift1_start_time' => '07:00',
            'shift2_start_time' => '15:00',
            'shift3_start_time' => '23:00',
            'part_time_shift1_start_time' => '07:00',
            'part_time_shift2_start_time' => '11:30',
            'part_time_shift3_start_time' => '16:00',
            'absensi_late_tolerance_minutes' => 10,
            'absensi_checkin_before_minutes' => 30,
            'absensi_checkin_after_minutes' => 60,
            'self_schedule_enabled' => false,
            'self_schedule_is_open' => false,
            'self_schedule_pick_start_date' => null,
            'self_schedule_pick_end_date' => null,
            'self_schedule_open_start_date' => null,
            'self_schedule_open_end_date' => null,
            'self_schedule_capacity_shift1' => 1,
            'self_schedule_capacity_shift2' => 1,
            'self_schedule_capacity_shift3' => 1,
            'self_schedule_part_time_capacity_shift1' => null,
            'self_schedule_part_time_capacity_shift2' => null,
            'self_schedule_part_time_capacity_shift3' => null,
            'self_schedule_min_per_week' => null,
            'self_schedule_max_per_week' => null,
            'self_schedule_min_per_month' => null,
            'self_schedule_max_per_month' => null,
            'self_schedule_part_time_min_per_week' => null,
            'self_schedule_part_time_max_per_week' => null,
            'self_schedule_part_time_min_per_month' => null,
            'self_schedule_part_time_max_per_month' => null,
            'self_schedule_allow_cancel' => false,
            'self_schedule_cancel_min_days_before' => 0,
            'self_schedule_capacity_weekend_shift1' => null,
            'self_schedule_capacity_weekend_shift2' => null,
            'self_schedule_capacity_weekend_shift3' => null,
            'self_schedule_part_time_capacity_weekend_shift1' => null,
            'self_schedule_part_time_capacity_weekend_shift2' => null,
            'self_schedule_part_time_capacity_weekend_shift3' => null,
            'mode_template' => 'global',
            'nama_toko_admin' => null,
            'alamat_toko_admin' => null,
            'header_text_admin' => null,
            'footer_text_admin' => null,
            'nama_toko_kasir' => null,
            'alamat_toko_kasir' => null,
            'header_text_kasir' => null,
            'footer_text_kasir' => null,
            'nama_cabang' => null,
            'theme_primary' => null,
            'theme_secondary' => null,
            'theme_bg' => null,
            'payroll_alpha_deduction_full_time' => 0,
            'payroll_alpha_deduction_part_time' => 0,
            'payroll_late_deduction_per_minute' => 0,
            'payroll_overtime_rate_full_time' => 0,
        ]);
    }

    public function enabledPaymentMethods(): array
    {
        $methods = [];

        if ((bool) $this->enable_payment_cash) {
            $methods[] = 'cash';
        }
        if ((bool) $this->enable_payment_qris) {
            $methods[] = 'qris';
        }
        if ((bool) $this->enable_payment_debit) {
            $methods[] = 'debit';
        }
        if ((bool) $this->enable_payment_shopeefood) {
            $methods[] = 'shopeefood';
        }
        if ((bool) $this->enable_payment_gofood) {
            $methods[] = 'gofood';
        }
        if ((bool) $this->enable_payment_grabfood) {
            $methods[] = 'grabfood';
        }

        return $methods;
    }

    public function enabledDeliveryPaymentMethods(): array
    {
        $methods = [];

        if ((bool) $this->enable_payment_shopeefood) {
            $methods[] = 'shopeefood';
        }
        if ((bool) $this->enable_payment_gofood) {
            $methods[] = 'gofood';
        }
        if ((bool) $this->enable_payment_grabfood) {
            $methods[] = 'grabfood';
        }

        return $methods;
    }

    public function usesPartTimeSlots(?string $employmentType): bool
    {
        return Karyawan::normalizeEmploymentType($employmentType) === Karyawan::EMPLOYMENT_PART_TIME;
    }

    public function shiftCodeFor(int $shiftNo, ?string $employmentType = null): string
    {
        $slotNo = max(1, min(3, $shiftNo));

        return $this->usesPartTimeSlots($employmentType)
            ? ('PT-' . $slotNo)
            : ('S' . $slotNo);
    }

    public function shiftTitleFor(int $shiftNo, ?string $employmentType = null): string
    {
        $slotNo = max(1, min(3, $shiftNo));

        return $this->usesPartTimeSlots($employmentType)
            ? ('Slot PT ' . $slotNo)
            : ('Shift ' . $slotNo);
    }

    public function shiftStartTimeFor(int $shiftNo, ?string $employmentType = null): string
    {
        if ($this->usesPartTimeSlots($employmentType)) {
            return match ($shiftNo) {
                1 => (string) ($this->part_time_shift1_start_time ?? '07:00'),
                2 => (string) ($this->part_time_shift2_start_time ?? '11:30'),
                3 => (string) ($this->part_time_shift3_start_time ?? '16:00'),
                default => '',
            };
        }

        return match ($shiftNo) {
            1 => (string) ($this->shift1_start_time ?? '07:00'),
            2 => (string) ($this->shift2_start_time ?? '15:00'),
            3 => (string) ($this->shift3_start_time ?? '23:00'),
            default => '',
        };
    }

    public function shiftDurationMinutesFor(?string $employmentType): int
    {
        return Karyawan::employmentDurationMinutesFor($employmentType);
    }

    public function shiftDurationLabelFor(?string $employmentType): string
    {
        return Karyawan::employmentDurationLabelFor($employmentType);
    }

    public function shiftRangeFor(int $shiftNo, ?string $employmentType = null, ?string $date = null): array
    {
        $startTime = $this->shiftStartTimeFor($shiftNo, $employmentType);
        if ($startTime === '') {
            return [
                'start' => '-',
                'end' => '-',
                'label' => '-',
            ];
        }

        $baseDate = $date !== null && trim($date) !== ''
            ? Carbon::parse($date)->format('Y-m-d')
            : now()->format('Y-m-d');

        $startAt = Carbon::parse($baseDate . ' ' . $startTime . ':00');
        $endAt = $startAt->copy()->addMinutes($this->shiftDurationMinutesFor($employmentType));

        return [
            'start' => $startAt->format('H:i'),
            'end' => $endAt->format('H:i'),
            'label' => $startAt->format('H:i') . ' - ' . $endAt->format('H:i'),
        ];
    }

    public function shiftRangeLabel(int $shiftNo, ?string $employmentType = null, ?string $date = null): string
    {
        return (string) ($this->shiftRangeFor($shiftNo, $employmentType, $date)['label'] ?? '-');
    }

    public function selfScheduleCapacityForShift(int $shiftNo, ?string $employmentType = null, bool $weekend = false): int
    {
        $slotNo = max(1, min(3, $shiftNo));
        $isPartTime = $this->usesPartTimeSlots($employmentType);

        $genericBaseColumn = 'self_schedule_capacity_shift' . $slotNo;
        $genericWeekendColumn = 'self_schedule_capacity_weekend_shift' . $slotNo;
        $partTimeBaseColumn = 'self_schedule_part_time_capacity_shift' . $slotNo;
        $partTimeWeekendColumn = 'self_schedule_part_time_capacity_weekend_shift' . $slotNo;

        $value = null;

        if ($isPartTime) {
            if ($weekend) {
                $value = $this->{$partTimeWeekendColumn} ?? null;
            }
            if ($value === null) {
                $value = $this->{$partTimeBaseColumn} ?? null;
            }
        } else {
            if ($weekend) {
                $value = $this->{$genericWeekendColumn} ?? null;
            }
            if ($value === null) {
                $value = $this->{$genericBaseColumn} ?? null;
            }
        }

        if ($value === null && $weekend) {
            $value = $this->{$genericWeekendColumn} ?? null;
        }

        if ($value === null) {
            $value = $this->{$genericBaseColumn} ?? null;
        }

        return max(1, (int) ($value ?? 1));
    }

    public function selfScheduleCapacityForDateAndShift(string $date, int $shiftNo, ?string $employmentType = null): int
    {
        try {
            $isWeekend = Carbon::parse($date)->isWeekend();
        } catch (\Throwable $e) {
            $isWeekend = false;
        }

        return $this->selfScheduleCapacityForShift($shiftNo, $employmentType, $isWeekend);
    }

    private function selfScheduleLimitValue(string $genericColumn, string $partTimeColumn, ?string $employmentType): ?int
    {
        $value = null;

        if ($this->usesPartTimeSlots($employmentType)) {
            $candidate = $this->{$partTimeColumn} ?? null;
            if ($candidate !== null && (int) $candidate > 0) {
                $value = (int) $candidate;
            }
        }

        if ($value === null) {
            $candidate = $this->{$genericColumn} ?? null;
            if ($candidate !== null && (int) $candidate > 0) {
                $value = (int) $candidate;
            }
        }

        return $value;
    }

    public function selfScheduleMinPerWeekFor(?string $employmentType): ?int
    {
        return $this->selfScheduleLimitValue('self_schedule_min_per_week', 'self_schedule_part_time_min_per_week', $employmentType);
    }

    public function selfScheduleMaxPerWeekFor(?string $employmentType): ?int
    {
        return $this->selfScheduleLimitValue('self_schedule_max_per_week', 'self_schedule_part_time_max_per_week', $employmentType);
    }

    public function selfScheduleMinPerMonthFor(?string $employmentType): ?int
    {
        return $this->selfScheduleLimitValue('self_schedule_min_per_month', 'self_schedule_part_time_min_per_month', $employmentType);
    }

    public function selfScheduleMaxPerMonthFor(?string $employmentType): ?int
    {
        return $this->selfScheduleLimitValue('self_schedule_max_per_month', 'self_schedule_part_time_max_per_month', $employmentType);
    }

    public function payrollAlphaDeductionFor(?string $employmentType): int
    {
        return Karyawan::normalizeEmploymentType($employmentType) === Karyawan::EMPLOYMENT_PART_TIME
            ? (int) ($this->payroll_alpha_deduction_part_time ?? 0)
            : (int) ($this->payroll_alpha_deduction_full_time ?? 0);
    }

    public function payrollLateDeductionPerMinute(): int
    {
        return (int) ($this->payroll_late_deduction_per_minute ?? 0);
    }

    public function payrollOvertimeRateFor(?string $employmentType, ?int $fallbackHourlyRate = null): int
    {
        if (Karyawan::normalizeEmploymentType($employmentType) === Karyawan::EMPLOYMENT_PART_TIME) {
            return max(0, (int) ($fallbackHourlyRate ?? 0));
        }

        return (int) ($this->payroll_overtime_rate_full_time ?? 0);
    }
}
