<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PayrollSlip extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'id_karyawan',
        'period_month',
        'employment_type',
        'salary_scheme',
        'base_amount',
        'hourly_rate',
        'paid_minutes',
        'scheduled_shift_count',
        'present_shift_count',
        'alpha_shift_count',
        'approved_leave_shift_count',
        'approved_leave_day_count',
        'late_count',
        'late_minutes',
        'early_leave_count',
        'early_leave_minutes',
        'overtime_shift_count',
        'overtime_minutes',
        'overtime_rate',
        'overtime_amount',
        'auto_alpha_deduction',
        'auto_approved_leave_deduction',
        'auto_late_deduction',
        'auto_early_leave_deduction',
        'auto_deduction_amount',
        'bonus_amount',
        'deduction_amount',
        'gross_amount',
        'net_amount',
        'status',
        'notes',
        'generated_at',
        'finalized_at',
        'generated_by',
    ];

    protected $casts = [
        'id_karyawan' => 'integer',
        'period_month' => 'date',
        'employment_type' => 'string',
        'salary_scheme' => 'string',
        'base_amount' => 'integer',
        'hourly_rate' => 'integer',
        'paid_minutes' => 'integer',
        'scheduled_shift_count' => 'integer',
        'present_shift_count' => 'integer',
        'alpha_shift_count' => 'integer',
        'approved_leave_shift_count' => 'integer',
        'approved_leave_day_count' => 'integer',
        'late_count' => 'integer',
        'late_minutes' => 'integer',
        'early_leave_count' => 'integer',
        'early_leave_minutes' => 'integer',
        'overtime_shift_count' => 'integer',
        'overtime_minutes' => 'integer',
        'overtime_rate' => 'integer',
        'overtime_amount' => 'integer',
        'auto_alpha_deduction' => 'integer',
        'auto_approved_leave_deduction' => 'integer',
        'auto_late_deduction' => 'integer',
        'auto_early_leave_deduction' => 'integer',
        'auto_deduction_amount' => 'integer',
        'bonus_amount' => 'integer',
        'deduction_amount' => 'integer',
        'gross_amount' => 'integer',
        'net_amount' => 'integer',
        'status' => 'string',
        'generated_at' => 'datetime',
        'finalized_at' => 'datetime',
        'generated_by' => 'integer',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_FINALIZED => 'Final',
        ];
    }

    public static function statusLabelFor(?string $status): string
    {
        return static::statusOptions()[$status ?? ''] ?? 'Draft';
    }

    public static function formatMinutes(int $minutes): string
    {
        $hours = $minutes / 60;
        $label = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');

        return str_replace('.', ',', $label) . ' jam';
    }

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'id_karyawan', 'id_karyawan');
    }

    public function statusLabel(): string
    {
        return static::statusLabelFor($this->status);
    }

    public function paidHoursLabel(): string
    {
        return static::formatMinutes((int) ($this->paid_minutes ?? 0));
    }

    public function overtimeHoursLabel(): string
    {
        return static::formatMinutes((int) ($this->overtime_minutes ?? 0));
    }

    public function earlyLeaveHoursLabel(): string
    {
        return static::formatMinutes((int) ($this->early_leave_minutes ?? 0));
    }

    public function periodLabel(): string
    {
        $period = $this->period_month instanceof Carbon
            ? $this->period_month
            : Carbon::parse((string) $this->period_month);

        return $period->locale('id')->translatedFormat('F Y');
    }
}
