<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\JadwalKaryawan;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\PayrollSlip;
use App\Models\StaffMessage;
use App\Models\StaffMessageRead;
use App\Models\StrukSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoWorkforceSeeder
{
    private const DEMO_STAFF = [
        [
            'key' => 'ft_nabila',
            'nama_karyawan' => 'Demo FT Nabila',
            'jabatan' => 'Supervisor Shift',
            'no_telepon' => '0899003101',
            'employment_type' => Karyawan::EMPLOYMENT_FULL_TIME,
            'monthly_salary' => 4200000,
            'hourly_rate' => null,
            'pin' => '61011',
        ],
        [
            'key' => 'ft_fajar',
            'nama_karyawan' => 'Demo FT Fajar',
            'jabatan' => 'Kasir Senior',
            'no_telepon' => '0899003102',
            'employment_type' => Karyawan::EMPLOYMENT_FULL_TIME,
            'monthly_salary' => 3800000,
            'hourly_rate' => null,
            'pin' => '61022',
        ],
        [
            'key' => 'pt_salsa',
            'nama_karyawan' => 'Demo PT Salsa',
            'jabatan' => 'Crew Part Time',
            'no_telepon' => '0899003201',
            'employment_type' => Karyawan::EMPLOYMENT_PART_TIME,
            'monthly_salary' => null,
            'hourly_rate' => 24000,
            'pin' => '62033',
        ],
        [
            'key' => 'pt_rian',
            'nama_karyawan' => 'Demo PT Rian',
            'jabatan' => 'Crew Part Time',
            'no_telepon' => '0899003202',
            'employment_type' => Karyawan::EMPLOYMENT_PART_TIME,
            'monthly_salary' => null,
            'hourly_rate' => 22000,
            'pin' => '62044',
        ],
    ];

    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
    ) {
    }

    public function seed(bool $resetDemo = true): array
    {
        return DB::transaction(function () use ($resetDemo): array {
            $now = now();
            $currentMonth = $now->copy()->startOfMonth();
            $previousMonth = $currentMonth->copy()->subMonthNoOverflow()->startOfMonth();
            $setting = StrukSetting::current();

            $this->applyDemoSettings($setting, $now);

            if ($resetDemo) {
                $this->purgeDemoData();
            }

            $employees = $this->upsertDemoEmployees();
            $adminUserId = $this->resolveAdminUserId();

            $previousSummary = $this->seedPreviousMonthDataset($employees, $previousMonth, $setting, $adminUserId);
            $currentSummary = $this->seedCurrentMonthDataset($employees, $currentMonth, $setting, $adminUserId, $now);
            $messageCount = $this->seedStaffMessages($employees, $currentSummary['threads'], $adminUserId);

            return [
                'periods' => [
                    'current' => $currentMonth->format('Y-m'),
                    'previous' => $previousMonth->format('Y-m'),
                ],
                'employees' => $employees
                    ->map(fn (array $item): array => [
                        'name' => $item['model']->nama_karyawan,
                        'employment' => $item['model']->employmentTypeLabel(),
                        'phone' => (string) $item['model']->no_telepon,
                        'pin' => (string) $item['pin'],
                        'salary_label' => $item['model']->baseSalaryLabel(),
                    ])
                    ->values()
                    ->all(),
                'counts' => [
                    'employees' => $employees->count(),
                    'jadwal' => (int) ($previousSummary['jadwal_count'] + $currentSummary['jadwal_count']),
                    'absensi' => (int) ($previousSummary['absensi_count'] + $currentSummary['absensi_count']),
                    'leave_requests' => (int) $previousSummary['leave_count'],
                    'payroll_slips' => (int) $previousSummary['slip_count'],
                    'staff_messages' => $messageCount,
                ],
                'scenarios' => [
                    'slips' => 'Slip gaji bulan ' . $previousMonth->locale('id')->translatedFormat('F Y') . ' sudah dibuat untuk semua karyawan demo.',
                    'part_time_long_shift' => 'Demo PT Salsa punya absensi part time dengan jam kerja panjang supaya perhitungan per jam bisa dites.',
                    'requested_correction' => 'Demo PT Rian punya kasus koreksi absen pulang yang sedang menunggu persetujuan admin.',
                    'expired_correction' => 'Demo FT Fajar punya kasus lupa absen pulang yang sudah lewat batas koreksi mandiri dan butuh koreksi admin.',
                    'verified_records' => 'Demo FT Nabila punya data hadir, telat, pulang cepat, dan izin supaya breakdown payroll lebih terasa.',
                ],
            ];
        });
    }

    private function applyDemoSettings(StrukSetting $setting, Carbon $now): void
    {
        $start = $now->copy()->startOfMonth()->toDateString();
        $end = $now->copy()->endOfMonth()->toDateString();

        $setting->fill([
            'active_shift_count' => max(2, (int) ($setting->active_shift_count ?? 2)),
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => $start,
            'self_schedule_pick_end_date' => $end,
            'self_schedule_open_start_date' => $start,
            'self_schedule_open_end_date' => $end,
            'self_schedule_capacity_shift1' => 6,
            'self_schedule_capacity_shift2' => 6,
            'self_schedule_capacity_shift3' => 4,
            'self_schedule_part_time_capacity_shift1' => 6,
            'self_schedule_part_time_capacity_shift2' => 6,
            'self_schedule_part_time_capacity_shift3' => 4,
            'self_schedule_capacity_weekend_shift1' => 8,
            'self_schedule_capacity_weekend_shift2' => 8,
            'self_schedule_capacity_weekend_shift3' => 6,
            'self_schedule_part_time_capacity_weekend_shift1' => 8,
            'self_schedule_part_time_capacity_weekend_shift2' => 8,
            'self_schedule_part_time_capacity_weekend_shift3' => 6,
            'self_schedule_min_per_week' => 4,
            'self_schedule_max_per_week' => 6,
            'self_schedule_min_per_month' => 18,
            'self_schedule_max_per_month' => 26,
            'self_schedule_part_time_min_per_week' => 2,
            'self_schedule_part_time_max_per_week' => 5,
            'self_schedule_part_time_min_per_month' => 8,
            'self_schedule_part_time_max_per_month' => 18,
            'self_schedule_allow_cancel' => true,
            'self_schedule_cancel_min_days_before' => 1,
            'absensi_require_selfie' => false,
            'absensi_require_geofence' => false,
            'payroll_alpha_deduction_full_time' => 125000,
            'payroll_alpha_deduction_part_time' => 45000,
            'payroll_late_deduction_per_minute' => 1000,
            'payroll_overtime_rate_full_time' => 30000,
        ])->save();
    }

    private function purgeDemoData(): void
    {
        $demoPhones = collect(self::DEMO_STAFF)->pluck('no_telepon')->all();
        $demoIds = Karyawan::query()
            ->whereIn('no_telepon', $demoPhones)
            ->pluck('id_karyawan')
            ->map(fn ($value) => (int) $value)
            ->values();

        if ($demoIds->isEmpty()) {
            return;
        }

        $absensiIds = Absensi::query()
            ->whereIn('id_karyawan', $demoIds->all())
            ->pluck('id_absensi')
            ->map(fn ($value) => (int) $value)
            ->values();

        if (Schema::hasTable('staff_message_reads')) {
            StaffMessageRead::query()
                ->when($absensiIds->isNotEmpty(), function ($query) use ($absensiIds) {
                    $query->where(function ($nested) use ($absensiIds): void {
                        $nested->where('thread_type', 'absensi')
                            ->whereIn('thread_id', $absensiIds->all());
                    });
                }, function ($query) {
                    $query->whereRaw('1 = 0');
                })
                ->orWhere(function ($query) use ($demoIds): void {
                    $query->where('thread_type', 'admin_chat')
                        ->whereIn('thread_id', $demoIds->all());
                })
                ->orWhereIn('reader_karyawan_id', $demoIds->all())
                ->delete();
        }

        if (Schema::hasTable('staff_messages')) {
            StaffMessage::query()
                ->when($absensiIds->isNotEmpty(), function ($query) use ($absensiIds) {
                    $query->where(function ($nested) use ($absensiIds): void {
                        $nested->where('thread_type', 'absensi')
                            ->whereIn('thread_id', $absensiIds->all());
                    });
                }, function ($query) {
                    $query->whereRaw('1 = 0');
                })
                ->orWhere(function ($query) use ($demoIds): void {
                    $query->where('thread_type', 'admin_chat')
                        ->whereIn('thread_id', $demoIds->all());
                })
                ->orWhereIn('sender_karyawan_id', $demoIds->all())
                ->delete();
        }

        if (Schema::hasTable('payroll_slips')) {
            PayrollSlip::query()->whereIn('id_karyawan', $demoIds->all())->delete();
        }

        if (Schema::hasTable('leave_requests')) {
            LeaveRequest::query()->whereIn('id_karyawan', $demoIds->all())->delete();
        }

        JadwalKaryawan::query()->whereIn('id_karyawan', $demoIds->all())->delete();
        Absensi::query()->whereIn('id_karyawan', $demoIds->all())->delete();
        Karyawan::query()->whereIn('id_karyawan', $demoIds->all())->delete();
    }

    /**
     * @return Collection<string, array{model: Karyawan, pin: string}>
     */
    private function upsertDemoEmployees(): Collection
    {
        return collect(self::DEMO_STAFF)->mapWithKeys(function (array $definition): array {
            $pin = $this->resolveAvailablePin((string) $definition['pin']);
            $digest = Karyawan::pinDigest($pin);

            $karyawan = Karyawan::query()->create([
                'nama_karyawan' => $definition['nama_karyawan'],
                'jabatan' => $definition['jabatan'],
                'no_telepon' => $definition['no_telepon'],
                'employment_type' => $definition['employment_type'],
                'monthly_salary' => $definition['monthly_salary'],
                'hourly_rate' => $definition['hourly_rate'],
                'pin_digest' => $digest,
                'pin_encrypted' => Crypt::encryptString($pin),
                'is_active' => true,
            ]);

            return [
                $definition['key'] => [
                    'model' => $karyawan,
                    'pin' => $pin,
                ],
            ];
        });
    }

    private function resolveAvailablePin(string $preferredPin): string
    {
        $candidate = $preferredPin;

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $digest = Karyawan::pinDigest($candidate);
            $exists = Karyawan::query()->where('pin_digest', $digest)->exists();

            if (! $exists) {
                return $candidate;
            }

            $candidate = (string) ((int) $preferredPin + (($attempt + 1) * 37));
        }

        return $preferredPin . '9';
    }

    /**
     * @param  Collection<string, array{model: Karyawan, pin: string}>  $employees
     */
    private function seedPreviousMonthDataset(
        Collection $employees,
        Carbon $period,
        StrukSetting $setting,
        ?int $adminUserId,
    ): array {
        $scheduleMap = [
            'ft_nabila' => $this->buildMonthlySchedules($period, 1, [1, 2, 3, 4, 5]),
            'ft_fajar' => $this->buildMonthlySchedules($period, 2, [1, 2, 3, 4, 5]),
            'pt_salsa' => $this->buildMonthlySchedules($period, 1, [1, 3, 5]),
            'pt_rian' => $this->buildMonthlySchedules($period, 2, [2, 4]),
        ];

        $jadwalRows = [];
        foreach ($scheduleMap as $key => $items) {
            foreach ($items as $item) {
                $jadwalRows[] = [
                    'tanggal' => $item['date'],
                    'shift_ke' => $item['shift'],
                    'id_karyawan' => (int) $employees[$key]['model']->id_karyawan,
                ];
            }
        }
        if ($jadwalRows !== []) {
            JadwalKaryawan::query()->insert($jadwalRows);
        }

        $ftDates = collect($scheduleMap['ft_nabila'])->pluck('date')->values();
        $ptSalsaDates = collect($scheduleMap['pt_salsa'])->pluck('date')->values();
        $ptRianDates = collect($scheduleMap['pt_rian'])->pluck('date')->values();

        $nabilaLeave = $this->pickDateValue($ftDates, 3);
        $nabilaAlpha = $this->pickDateValue($ftDates, 7);
        $nabilaLate = $this->pickDateValue($ftDates, 10);
        $nabilaEarly = $this->pickDateValue($ftDates, 14);
        $fajarOvertime = $this->pickDateValue($ftDates, 5);
        $fajarLate = $this->pickDateValue($ftDates, 11);
        $fajarLeave = $this->pickDateValue($ftDates, 15);
        $salsaLeave = $this->pickDateValue($ptSalsaDates, 3);
        $salsaLong = $this->pickDateValue($ptSalsaDates, 5);
        $salsaFiveHours = $this->pickDateValue($ptSalsaDates, 1);
        $rianLong = $this->pickDateValue($ptRianDates, 2);
        $rianFiveHours = $this->pickDateValue($ptRianDates, 4);

        $leaveRows = array_filter([
            $this->buildLeaveRequest($employees['ft_nabila']['model'], $nabilaLeave, 'izin', 'Izin keluarga (demo)', $adminUserId),
            $this->buildLeaveRequest($employees['ft_fajar']['model'], $fajarLeave, 'sakit', 'Sakit ringan (demo)', $adminUserId),
            $this->buildLeaveRequest($employees['pt_salsa']['model'], $salsaLeave, 'izin', 'Kuliah / part time (demo)', $adminUserId),
        ]);
        if ($leaveRows !== [] && Schema::hasTable('leave_requests')) {
            LeaveRequest::query()->insert($leaveRows);
        }

        $absensiCount = 0;
        foreach ($scheduleMap['ft_nabila'] as $item) {
            if (in_array($item['date'], [$nabilaLeave, $nabilaAlpha], true)) {
                continue;
            }

            $scenario = [
                'in_offset' => -5,
                'out_offset' => 0,
                'status' => 'hadir',
                'catatan' => 'Demo FT Nabila',
            ];

            if ($item['date'] === $nabilaLate) {
                $scenario['in_offset'] = 24;
                $scenario['status'] = 'telat';
                $scenario['catatan'] = 'Telat terverifikasi (demo)';
            } elseif ($item['date'] === $nabilaEarly) {
                $scenario['in_offset'] = 0;
                $scenario['out_offset'] = -70;
                $scenario['catatan'] = 'Pulang cepat (demo)';
            }

            $this->createCompletedAbsensi($employees['ft_nabila']['model'], $item['date'], $item['shift'], $setting, $adminUserId, $scenario);
            $absensiCount++;
        }

        foreach ($scheduleMap['ft_fajar'] as $item) {
            if ($item['date'] === $fajarLeave) {
                continue;
            }

            $scenario = [
                'in_offset' => 0,
                'out_offset' => 0,
                'status' => 'hadir',
                'catatan' => 'Demo FT Fajar',
            ];

            if ($item['date'] === $fajarOvertime) {
                $scenario['out_offset'] = 95;
                $scenario['catatan'] = 'Lembur shift yang sama (demo)';
            } elseif ($item['date'] === $fajarLate) {
                $scenario['in_offset'] = 18;
                $scenario['status'] = 'telat';
                $scenario['catatan'] = 'Telat FT (demo)';
            }

            $this->createCompletedAbsensi($employees['ft_fajar']['model'], $item['date'], $item['shift'], $setting, $adminUserId, $scenario);
            $absensiCount++;
        }

        foreach ($scheduleMap['pt_salsa'] as $item) {
            if ($item['date'] === $salsaLeave) {
                continue;
            }

            $scenario = [
                'in_offset' => 5,
                'duration_minutes' => 285,
                'status' => 'hadir',
                'catatan' => 'Demo PT Salsa',
            ];

            if ($item['date'] === $salsaLong) {
                $scenario['in_offset'] = 0;
                $scenario['duration_minutes'] = 485;
                $scenario['catatan'] = 'Shift part time panjang 8 jam (demo)';
            } elseif ($item['date'] === $salsaFiveHours) {
                $scenario['duration_minutes'] = 315;
                $scenario['catatan'] = 'Shift part time 5 jam (demo)';
            }

            $this->createCompletedAbsensi($employees['pt_salsa']['model'], $item['date'], $item['shift'], $setting, $adminUserId, $scenario);
            $absensiCount++;
        }

        foreach ($scheduleMap['pt_rian'] as $item) {
            $scenario = [
                'in_offset' => 3,
                'duration_minutes' => 270,
                'status' => 'hadir',
                'catatan' => 'Demo PT Rian',
            ];

            if ($item['date'] === $rianLong) {
                $scenario['duration_minutes'] = 495;
                $scenario['catatan'] = 'Shift part time panjang 8 jam (demo)';
            } elseif ($item['date'] === $rianFiveHours) {
                $scenario['duration_minutes'] = 300;
                $scenario['catatan'] = 'Shift part time 5 jam (demo)';
            }

            $this->createCompletedAbsensi($employees['pt_rian']['model'], $item['date'], $item['shift'], $setting, $adminUserId, $scenario);
            $absensiCount++;
        }

        $slipCount = 0;
        foreach ($employees as $key => $employee) {
            $summary = $this->payrollCalculator->calculate($employee['model'], $period);
            $status = in_array($key, ['ft_nabila', 'pt_salsa'], true)
                ? PayrollSlip::STATUS_FINALIZED
                : PayrollSlip::STATUS_DRAFT;
            $bonus = match ($key) {
                'ft_nabila' => 150000,
                'pt_salsa' => 50000,
                default => 0,
            };
            $deduction = match ($key) {
                'ft_fajar' => 50000,
                'pt_rian' => 25000,
                default => 0,
            };
            $gross = (int) ($summary['gross_amount'] ?? 0);
            $autoDeduction = (int) ($summary['auto_deduction_amount'] ?? 0);

            PayrollSlip::query()->updateOrCreate(
                [
                    'id_karyawan' => (int) $employee['model']->id_karyawan,
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
                    'auto_deduction_amount' => $autoDeduction,
                    'bonus_amount' => $bonus,
                    'deduction_amount' => $deduction,
                    'gross_amount' => $gross,
                    'net_amount' => $this->payrollCalculator->calculateNetAmount($gross, $bonus, $deduction, $autoDeduction),
                    'status' => $status,
                    'notes' => 'Slip gaji demo untuk pengujian FT/PT.',
                    'generated_at' => now()->subDays(3),
                    'finalized_at' => $status === PayrollSlip::STATUS_FINALIZED ? now()->subDays(2) : null,
                    'generated_by' => $adminUserId,
                ]
            );
            $slipCount++;
        }

        return [
            'jadwal_count' => count($jadwalRows),
            'absensi_count' => $absensiCount,
            'leave_count' => count($leaveRows),
            'slip_count' => $slipCount,
        ];
    }

    /**
     * @param  Collection<string, array{model: Karyawan, pin: string}>  $employees
     */
    private function seedCurrentMonthDataset(
        Collection $employees,
        Carbon $period,
        StrukSetting $setting,
        ?int $adminUserId,
        Carbon $now,
    ): array {
        $windowEnd = $now->copy()->addDays(7)->endOfDay();
        if ($windowEnd->month !== $period->month || $windowEnd->year !== $period->year) {
            $windowEnd = $period->copy()->endOfMonth();
        }

        $scheduleMap = [
            'ft_nabila' => $this->buildDateRangeSchedules($period->copy()->startOfMonth(), $windowEnd, 1, [1, 2, 3, 4, 5]),
            'ft_fajar' => $this->buildDateRangeSchedules($period->copy()->startOfMonth(), $windowEnd, 2, [1, 2, 3, 4, 5]),
            'pt_salsa' => $this->buildDateRangeSchedules($period->copy()->startOfMonth(), $windowEnd, 1, [1, 3, 5]),
            'pt_rian' => $this->buildDateRangeSchedules($period->copy()->startOfMonth(), $windowEnd, 2, [2, 4]),
        ];

        $jadwalRows = [];
        foreach ($scheduleMap as $key => $items) {
            foreach ($items as $item) {
                $jadwalRows[] = [
                    'tanggal' => $item['date'],
                    'shift_ke' => $item['shift'],
                    'id_karyawan' => (int) $employees[$key]['model']->id_karyawan,
                ];
            }
        }
        if ($jadwalRows !== []) {
            JadwalKaryawan::query()->insert($jadwalRows);
        }

        $ftNabilaDates = collect($scheduleMap['ft_nabila'])->pluck('date')->values();
        $ftFajarDates = collect($scheduleMap['ft_fajar'])->pluck('date')->values();
        $ptSalsaDates = collect($scheduleMap['pt_salsa'])->pluck('date')->values();
        $ptRianDates = collect($scheduleMap['pt_rian'])->pluck('date')->values();

        $nabilaVerifiedDate = $this->latestDateBeforeOrEqual($ftNabilaDates, $now->copy()->subDay());
        $fajarExpiredDate = $this->latestDateBeforeOrEqual($ftFajarDates, $now->copy()->subDays(2));
        $salsaLongShiftDate = $this->latestDateBeforeOrEqual($ptSalsaDates, $now->copy()->subDay());
        $rianRequestedDate = $this->latestDateBeforeOrEqual($ptRianDates, $now->copy()->subDay());

        $threads = [];
        $absensiCount = 0;

        if ($nabilaVerifiedDate) {
            $this->createCompletedAbsensi(
                $employees['ft_nabila']['model'],
                $nabilaVerifiedDate,
                1,
                $setting,
                $adminUserId,
                [
                    'in_offset' => -4,
                    'out_offset' => 0,
                    'status' => 'hadir',
                    'catatan' => 'Absensi lengkap bulan berjalan (demo)',
                ]
            );
            $absensiCount++;
        }

        if ($salsaLongShiftDate) {
            $this->createCompletedAbsensi(
                $employees['pt_salsa']['model'],
                $salsaLongShiftDate,
                1,
                $setting,
                $adminUserId,
                [
                    'in_offset' => 0,
                    'duration_minutes' => 485,
                    'status' => 'hadir',
                    'catatan' => 'Part time kerja 8 jam untuk tes payroll real-time',
                ]
            );
            $absensiCount++;
        }

        if ($fajarExpiredDate) {
            $threads['ft_fajar'] = $this->createCorrectionCase(
                $employees['ft_fajar']['model'],
                $fajarExpiredDate,
                2,
                $setting,
                'expired',
                $adminUserId,
                [
                    'masuk_offset' => 6,
                    'catatan' => 'Lupa absen pulang - perlu koreksi admin',
                ]
            );
            $absensiCount++;
        }

        if ($rianRequestedDate) {
            $threads['pt_rian'] = $this->createCorrectionCase(
                $employees['pt_rian']['model'],
                $rianRequestedDate,
                2,
                $setting,
                'requested',
                $adminUserId,
                [
                    'masuk_offset' => 3,
                    'requested_offset' => 300,
                    'catatan' => 'Ajukan koreksi pulang - menunggu admin',
                ]
            );
            $absensiCount++;
        }

        return [
            'jadwal_count' => count($jadwalRows),
            'absensi_count' => $absensiCount,
            'threads' => array_filter($threads),
        ];
    }

    /**
     * @param  Collection<string, array{model: Karyawan, pin: string}>  $employees
     * @param  array<string, Absensi>  $correctionThreads
     */
    private function seedStaffMessages(Collection $employees, array $correctionThreads, ?int $adminUserId): int
    {
        $count = 0;

        foreach ($employees as $key => $employee) {
            StaffMessage::query()->create([
                'thread_type' => 'admin_chat',
                'thread_id' => (int) $employee['model']->id_karyawan,
                'sender_role' => 'admin',
                'sender_karyawan_id' => null,
                'sender_user_id' => $adminUserId,
                'message' => 'Halo ' . $employee['model']->nama_karyawan . ', data demo kamu sudah siap untuk dites di portal staf.',
                'meta' => [
                    'seed' => 'demo_workforce',
                    'employment_type' => $employee['model']->employmentTypeValue(),
                ],
            ]);
            $count++;

            if ($key === 'pt_rian' && isset($correctionThreads[$key])) {
                $absensi = $correctionThreads[$key];

                StaffMessage::query()->create([
                    'thread_type' => 'absensi',
                    'thread_id' => (int) $absensi->id_absensi,
                    'sender_role' => 'staff',
                    'sender_karyawan_id' => (int) $employee['model']->id_karyawan,
                    'sender_user_id' => null,
                    'message' => 'Ajukan koreksi absen pulang untuk data demo.',
                    'meta' => [
                        'seed' => 'demo_workforce',
                    ],
                ]);
                $count++;

                StaffMessage::query()->create([
                    'thread_type' => 'absensi',
                    'thread_id' => (int) $absensi->id_absensi,
                    'sender_role' => 'admin',
                    'sender_karyawan_id' => null,
                    'sender_user_id' => $adminUserId,
                    'message' => 'Admin menerima usulan koreksi dan akan meninjaunya.',
                    'meta' => [
                        'seed' => 'demo_workforce',
                    ],
                ]);
                $count++;
            }

            if ($key === 'ft_fajar' && isset($correctionThreads[$key])) {
                $absensi = $correctionThreads[$key];

                StaffMessage::query()->create([
                    'thread_type' => 'absensi',
                    'thread_id' => (int) $absensi->id_absensi,
                    'sender_role' => 'admin',
                    'sender_karyawan_id' => null,
                    'sender_user_id' => $adminUserId,
                    'message' => 'Absensi demo ini sengaja dibiarkan lupa pulang supaya admin bisa tes koreksi manual.',
                    'meta' => [
                        'seed' => 'demo_workforce',
                    ],
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int, array{date: string, shift: int}>
     */
    private function buildMonthlySchedules(Carbon $period, int $shift, array $dayOfWeekIso): array
    {
        return $this->buildDateRangeSchedules($period->copy()->startOfMonth(), $period->copy()->endOfMonth(), $shift, $dayOfWeekIso);
    }

    /**
     * @return array<int, array{date: string, shift: int}>
     */
    private function buildDateRangeSchedules(Carbon $start, Carbon $end, int $shift, array $dayOfWeekIso): array
    {
        $rows = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (! in_array($date->dayOfWeekIso, $dayOfWeekIso, true)) {
                continue;
            }

            $rows[] = [
                'date' => $date->toDateString(),
                'shift' => $shift,
            ];
        }

        return $rows;
    }

    private function pickDateValue(Collection $dates, int $index): ?string
    {
        $value = $dates->get($index);

        return is_string($value) ? $value : null;
    }

    private function latestDateBeforeOrEqual(Collection $dates, Carbon $limit): ?string
    {
        return $dates
            ->map(fn ($date) => Carbon::parse((string) $date))
            ->filter(fn (Carbon $date) => $date->lte($limit))
            ->sort()
            ->last()?->toDateString();
    }

    private function buildLeaveRequest(
        Karyawan $karyawan,
        ?string $date,
        string $jenis,
        string $alasan,
        ?int $adminUserId,
    ): ?array {
        if (! $date || ! Schema::hasTable('leave_requests')) {
            return null;
        }

        return [
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'jenis' => $jenis,
            'tanggal_awal' => $date,
            'tanggal_akhir' => $date,
            'alasan' => $alasan,
            'bukti_path' => null,
            'status' => 'approved',
            'approved_by' => $adminUserId,
            'approved_at' => Carbon::parse($date . ' 09:00:00'),
            'note' => 'Disetujui otomatis untuk data demo.',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function createCompletedAbsensi(
        Karyawan $karyawan,
        string $date,
        int $shiftNo,
        StrukSetting $setting,
        ?int $adminUserId,
        array $scenario = [],
    ): Absensi {
        $bounds = $this->shiftBounds($date, $shiftNo, $karyawan, $setting);
        $waktuMasuk = isset($scenario['waktu_masuk'])
            ? Carbon::parse((string) $scenario['waktu_masuk'])
            : $bounds['start']->copy()->addMinutes((int) ($scenario['in_offset'] ?? 0));

        if (array_key_exists('duration_minutes', $scenario)) {
            $waktuPulang = $waktuMasuk->copy()->addMinutes((int) $scenario['duration_minutes']);
        } else {
            $waktuPulang = $bounds['end']->copy()->addMinutes((int) ($scenario['out_offset'] ?? 0));
        }

        return Absensi::query()->create([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'tanggal' => $date,
            'waktu_masuk' => $waktuMasuk,
            'waktu_pulang' => $waktuPulang,
            'catatan' => $scenario['catatan'] ?? 'Data demo absensi',
            'status' => $scenario['status'] ?? 'hadir',
            'verification_status' => $scenario['verification_status'] ?? 'verified',
            'verification_note' => $scenario['verification_note'] ?? 'Terverifikasi otomatis untuk data demo.',
            'verified_by' => $adminUserId,
            'verified_at' => ($scenario['verification_status'] ?? 'verified') === 'verified' ? $waktuPulang->copy()->addMinutes(20) : null,
            'checkout_correction_status' => null,
            'checkout_requested_pulang' => null,
            'checkout_requested_at' => null,
            'checkout_request_note' => null,
            'checkout_review_note' => null,
            'checkout_reviewed_by' => null,
            'checkout_reviewed_at' => null,
            'absensi_source' => 'portal',
            'shift_no' => $shiftNo,
            'selfie_path' => null,
            'geo_lat' => null,
            'geo_lng' => null,
            'geo_accuracy_m' => null,
        ]);
    }

    private function createCorrectionCase(
        Karyawan $karyawan,
        string $date,
        int $shiftNo,
        StrukSetting $setting,
        string $mode,
        ?int $adminUserId,
        array $scenario = [],
    ): Absensi {
        $bounds = $this->shiftBounds($date, $shiftNo, $karyawan, $setting);
        $waktuMasuk = $bounds['start']->copy()->addMinutes((int) ($scenario['masuk_offset'] ?? 0));

        $attributes = [
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'tanggal' => $date,
            'waktu_masuk' => $waktuMasuk,
            'waktu_pulang' => null,
            'catatan' => $scenario['catatan'] ?? 'Data demo koreksi absen pulang',
            'status' => $scenario['status'] ?? 'hadir',
            'verification_status' => 'pending',
            'verification_note' => null,
            'verified_by' => null,
            'verified_at' => null,
            'checkout_correction_status' => null,
            'checkout_requested_pulang' => null,
            'checkout_requested_at' => null,
            'checkout_request_note' => null,
            'checkout_review_note' => null,
            'checkout_reviewed_by' => null,
            'checkout_reviewed_at' => null,
            'absensi_source' => 'portal',
            'shift_no' => $shiftNo,
            'selfie_path' => null,
            'geo_lat' => null,
            'geo_lng' => null,
            'geo_accuracy_m' => null,
        ];

        if ($mode === 'requested') {
            $requestedPulang = $bounds['start']->copy()->addMinutes((int) ($scenario['requested_offset'] ?? 300));
            $attributes['checkout_correction_status'] = Absensi::CHECKOUT_CORRECTION_REQUESTED;
            $attributes['checkout_requested_pulang'] = $requestedPulang;
            $attributes['checkout_requested_at'] = $requestedPulang->copy()->subMinutes(10);
            $attributes['checkout_request_note'] = 'Lupa klik absen pulang, mohon dicek.';
        }

        if ($mode === 'expired') {
            $attributes['checkout_review_note'] = 'Butuh koreksi admin manual.';
            $attributes['checkout_reviewed_by'] = $adminUserId;
            $attributes['checkout_reviewed_at'] = Carbon::parse($date . ' 23:30:00')->addDay();
        }

        return Absensi::query()->create($attributes);
    }

    private function shiftBounds(string $date, int $shiftNo, Karyawan $karyawan, StrukSetting $setting): array
    {
        $range = $setting->shiftRangeFor($shiftNo, $karyawan->employmentTypeValue(), $date);
        $start = Carbon::parse($date . ' ' . (string) ($range['start'] ?? '07:00') . ':00');
        $end = Carbon::parse($date . ' ' . (string) ($range['end'] ?? '15:00') . ':00');

        if ($end->lte($start)) {
            $end = $start->copy()->addMinutes($karyawan->employmentDurationMinutes());
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function resolveAdminUserId(): ?int
    {
        $adminId = User::query()->where('role', 'admin')->value('id');

        if ($adminId) {
            return (int) $adminId;
        }

        $fallback = User::query()->value('id');

        return $fallback ? (int) $fallback : null;
    }
}
