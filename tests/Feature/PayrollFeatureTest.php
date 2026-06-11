<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\PayrollSlip;
use App\Models\StrukSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_part_time_payroll_slip_based_on_attended_shifts(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0, 'Asia/Jakarta'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = $this->createKaryawan('Bani Part Time', Karyawan::EMPLOYMENT_PART_TIME, null, 20000);

        DB::table('jadwal_karyawan')->insert([
            ['tanggal' => '2026-03-01', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
            ['tanggal' => '2026-03-02', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
            ['tanggal' => '2026-03-03', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
        ]);

        DB::table('absensi')->insert([
            ['id_karyawan' => (int) $karyawan->id_karyawan, 'tanggal' => '2026-03-01', 'waktu_masuk' => '2026-03-01 07:01:00', 'waktu_pulang' => '2026-03-01 12:01:00', 'status' => 'hadir', 'shift_no' => 1],
            ['id_karyawan' => (int) $karyawan->id_karyawan, 'tanggal' => '2026-03-02', 'waktu_masuk' => '2026-03-02 07:03:00', 'waktu_pulang' => '2026-03-02 12:03:00', 'status' => 'hadir', 'shift_no' => 1],
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.payroll.store', $karyawan), [
                'bulan' => '2026-03',
                'bonus_amount' => 50000,
                'deduction_amount' => 10000,
                'status' => PayrollSlip::STATUS_FINALIZED,
                'notes' => 'Insentif weekend',
            ])
            ->assertRedirect(route('dashboard.payroll.show', ['karyawan' => $karyawan, 'bulan' => '2026-03']));

        $this->assertDatabaseHas('payroll_slips', [
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'period_month' => '2026-03-01 00:00:00',
            'salary_scheme' => 'hourly',
            'paid_minutes' => 600,
            'present_shift_count' => 2,
            'alpha_shift_count' => 1,
            'gross_amount' => 200000,
            'net_amount' => 240000,
            'status' => PayrollSlip::STATUS_FINALIZED,
        ]);

        Carbon::setTestNow();
    }

    public function test_admin_can_generate_full_time_payroll_slip_with_monthly_salary(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = $this->createKaryawan('Jaka Full Time', Karyawan::EMPLOYMENT_FULL_TIME, 3200000, null);

        DB::table('jadwal_karyawan')->insert([
            ['tanggal' => '2026-03-05', 'shift_ke' => 2, 'id_karyawan' => (int) $karyawan->id_karyawan],
            ['tanggal' => '2026-03-06', 'shift_ke' => 2, 'id_karyawan' => (int) $karyawan->id_karyawan],
        ]);

        DB::table('absensi')->insert([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'tanggal' => '2026-03-05',
            'waktu_masuk' => '2026-03-05 15:04:00',
            'waktu_pulang' => '2026-03-05 23:00:00',
            'status' => 'hadir',
            'shift_no' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.payroll.store', $karyawan), [
                'bulan' => '2026-03',
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'status' => PayrollSlip::STATUS_DRAFT,
                'notes' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_slips', [
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'period_month' => '2026-03-01 00:00:00',
            'salary_scheme' => 'monthly',
            'present_shift_count' => 1,
            'alpha_shift_count' => 1,
            'auto_alpha_deduction' => 1600000,
            'gross_amount' => 3200000,
            'net_amount' => 1600000,
            'status' => PayrollSlip::STATUS_DRAFT,
        ]);
    }

    public function test_staff_can_open_payroll_page_and_see_live_estimate(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff', Karyawan::EMPLOYMENT_PART_TIME, null, 20000);

        DB::table('jadwal_karyawan')->insert([
            ['tanggal' => '2026-03-01', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
            ['tanggal' => '2026-03-02', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
        ]);

        DB::table('absensi')->insert([
            ['id_karyawan' => (int) $karyawan->id_karyawan, 'tanggal' => '2026-03-01', 'waktu_masuk' => '2026-03-01 07:00:00', 'waktu_pulang' => '2026-03-01 12:00:00', 'status' => 'hadir', 'shift_no' => 1],
            ['id_karyawan' => (int) $karyawan->id_karyawan, 'tanggal' => '2026-03-02', 'waktu_masuk' => '2026-03-02 07:02:00', 'waktu_pulang' => '2026-03-02 12:02:00', 'status' => 'hadir', 'shift_no' => 1],
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.payroll.index', ['bulan' => '2026-03']))
            ->assertOk()
            ->assertSee('Slip Gaji Realtime')
            ->assertSee('Part Time')
            ->assertSee('Per Jam')
            ->assertSee('Rp 200.000')
            ->assertSee('10 jam')
            ->assertDontSee('type="month"', false);

        Carbon::setTestNow();
    }

    public function test_staff_payroll_page_lists_saved_past_month_slips(): void
    {
        $karyawan = $this->createKaryawan('Bani Staff', Karyawan::EMPLOYMENT_PART_TIME, null, 20000);

        PayrollSlip::query()->create([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'period_month' => '2026-02-01',
            'employment_type' => Karyawan::EMPLOYMENT_PART_TIME,
            'salary_scheme' => 'hourly',
            'base_amount' => 300000,
            'hourly_rate' => 20000,
            'paid_minutes' => 900,
            'scheduled_shift_count' => 5,
            'present_shift_count' => 5,
            'alpha_shift_count' => 0,
            'gross_amount' => 300000,
            'net_amount' => 300000,
            'status' => PayrollSlip::STATUS_FINALIZED,
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.payroll.index', ['bulan' => '2026-03']))
            ->assertOk()
            ->assertSee('Pendapatan Bulanan')
            ->assertSee('Februari 2026')
            ->assertSee('Detail');
    }

    public function test_staff_can_open_payroll_period_detail_without_saved_slip(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Detail', Karyawan::EMPLOYMENT_PART_TIME, null, 20000);

        DB::table('jadwal_karyawan')->insert([
            ['tanggal' => '2026-03-01', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
            ['tanggal' => '2026-03-02', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
        ]);

        DB::table('absensi')->insert([
            ['id_karyawan' => (int) $karyawan->id_karyawan, 'tanggal' => '2026-03-01', 'waktu_masuk' => '2026-03-01 07:00:00', 'waktu_pulang' => '2026-03-01 12:00:00', 'status' => 'hadir', 'shift_no' => 1],
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.payroll.period', ['period' => '2026-03']))
            ->assertOk()
            ->assertSee('Detail Pendapatan')
            ->assertSee('Realtime')
            ->assertSee('Maret 2026');

        Carbon::setTestNow();
    }

    public function test_payroll_applies_alpha_late_and_overtime_rules(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 29, 10, 0, 0, 'Asia/Jakarta'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = $this->createKaryawan('Jaka Payroll', Karyawan::EMPLOYMENT_FULL_TIME, 3200000, null);

        StrukSetting::current()->update([
            'payroll_alpha_deduction_full_time' => 100000,
            'payroll_late_deduction_per_minute' => 1000,
            'payroll_overtime_rate_full_time' => 25000,
            'absensi_late_tolerance_minutes' => 10,
        ]);

        DB::table('jadwal_karyawan')->insert([
            ['tanggal' => '2026-03-05', 'shift_ke' => 2, 'id_karyawan' => (int) $karyawan->id_karyawan],
            ['tanggal' => '2026-03-06', 'shift_ke' => 2, 'id_karyawan' => (int) $karyawan->id_karyawan],
        ]);

        DB::table('absensi')->insert([
            [
                'id_karyawan' => (int) $karyawan->id_karyawan,
                'tanggal' => '2026-03-05',
                'waktu_masuk' => '2026-03-05 15:20:00',
                'waktu_pulang' => '2026-03-05 23:00:00',
                'status' => 'telat',
                'shift_no' => 2,
            ],
            [
                'id_karyawan' => (int) $karyawan->id_karyawan,
                'tanggal' => '2026-03-08',
                'waktu_masuk' => '2026-03-08 07:00:00',
                'waktu_pulang' => '2026-03-08 15:00:00',
                'status' => 'hadir',
                'shift_no' => 1,
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.payroll.store', $karyawan), [
                'bulan' => '2026-03',
                'bonus_amount' => 10000,
                'deduction_amount' => 5000,
                'status' => PayrollSlip::STATUS_FINALIZED,
                'notes' => 'Slip dengan aturan auto',
            ])
            ->assertRedirect(route('dashboard.payroll.show', ['karyawan' => $karyawan, 'bulan' => '2026-03']));

        $this->assertDatabaseHas('payroll_slips', [
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'period_month' => '2026-03-01 00:00:00',
            'late_count' => 1,
            'late_minutes' => 10,
            'overtime_shift_count' => 1,
            'overtime_minutes' => 480,
            'overtime_rate' => 25000,
            'overtime_amount' => 200000,
            'auto_alpha_deduction' => 1600000,
            'auto_late_deduction' => 10000,
            'auto_deduction_amount' => 1610000,
            'gross_amount' => 3400000,
            'net_amount' => 1795000,
        ]);

        Carbon::setTestNow();
    }

    public function test_full_time_payroll_applies_early_leave_and_same_day_overtime(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 29, 10, 0, 0, 'Asia/Jakarta'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = $this->createKaryawan('Jaka Presisi', Karyawan::EMPLOYMENT_FULL_TIME, 3200000, null);

        StrukSetting::current()->update([
            'payroll_late_deduction_per_minute' => 1000,
            'payroll_overtime_rate_full_time' => 25000,
            'shift1_start_time' => '07:00',
        ]);

        DB::table('jadwal_karyawan')->insert([
            ['tanggal' => '2026-03-10', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
            ['tanggal' => '2026-03-11', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
        ]);

        DB::table('absensi')->insert([
            [
                'id_karyawan' => (int) $karyawan->id_karyawan,
                'tanggal' => '2026-03-10',
                'waktu_masuk' => '2026-03-10 07:00:00',
                'waktu_pulang' => '2026-03-10 16:30:00',
                'status' => 'hadir',
                'shift_no' => 1,
            ],
            [
                'id_karyawan' => (int) $karyawan->id_karyawan,
                'tanggal' => '2026-03-11',
                'waktu_masuk' => '2026-03-11 07:00:00',
                'waktu_pulang' => '2026-03-11 13:20:00',
                'status' => 'hadir',
                'shift_no' => 1,
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.payroll.store', $karyawan), [
                'bulan' => '2026-03',
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'status' => PayrollSlip::STATUS_FINALIZED,
                'notes' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_slips', [
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'period_month' => '2026-03-01 00:00:00',
            'early_leave_count' => 1,
            'early_leave_minutes' => 100,
            'overtime_shift_count' => 1,
            'overtime_minutes' => 90,
            'overtime_amount' => 37500,
            'auto_early_leave_deduction' => 100000,
            'auto_deduction_amount' => 100000,
            'gross_amount' => 3237500,
            'net_amount' => 3137500,
        ]);

        Carbon::setTestNow();
    }

    public function test_staff_can_print_saved_payroll_slip(): void
    {
        $karyawan = $this->createKaryawan('Bani Print', Karyawan::EMPLOYMENT_PART_TIME, null, 22000);

        $slip = PayrollSlip::query()->create([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'period_month' => '2026-03-01',
            'employment_type' => Karyawan::EMPLOYMENT_PART_TIME,
            'salary_scheme' => 'hourly',
            'base_amount' => 198000,
            'hourly_rate' => 22000,
            'paid_minutes' => 540,
            'scheduled_shift_count' => 3,
            'present_shift_count' => 2,
            'alpha_shift_count' => 1,
            'late_count' => 0,
            'late_minutes' => 0,
            'overtime_shift_count' => 0,
            'overtime_minutes' => 0,
            'overtime_rate' => 22000,
            'overtime_amount' => 0,
            'auto_alpha_deduction' => 0,
            'auto_late_deduction' => 0,
            'auto_deduction_amount' => 0,
            'bonus_amount' => 10000,
            'deduction_amount' => 5000,
            'gross_amount' => 198000,
            'net_amount' => 203000,
            'status' => PayrollSlip::STATUS_FINALIZED,
            'generated_at' => now(),
            'finalized_at' => now(),
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.payroll.print', $slip))
            ->assertOk()
            ->assertSee('Slip Gaji')
            ->assertSee('Bani Print')
            ->assertSee('Rp 203.000');
    }

    public function test_part_time_payroll_uses_clock_in_and_clock_out_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 10, 0, 0, 'Asia/Jakarta'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = $this->createKaryawan('Bani Telat', Karyawan::EMPLOYMENT_PART_TIME, null, 20000);

        StrukSetting::current()->update([
            'absensi_late_tolerance_minutes' => 10,
            'payroll_late_deduction_per_minute' => 1000,
        ]);

        DB::table('jadwal_karyawan')->insert([
            ['tanggal' => '2026-03-10', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
        ]);

        DB::table('absensi')->insert([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'tanggal' => '2026-03-10',
            'waktu_masuk' => '2026-03-10 07:15:00',
            'waktu_pulang' => '2026-03-10 12:00:00',
            'status' => 'telat',
            'shift_no' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.payroll.store', $karyawan), [
                'bulan' => '2026-03',
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'status' => PayrollSlip::STATUS_FINALIZED,
                'notes' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_slips', [
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'period_month' => '2026-03-01 00:00:00',
            'paid_minutes' => 240,
            'base_amount' => 80000,
            'gross_amount' => 80000,
            'late_count' => 1,
            'late_minutes' => 5,
            'auto_late_deduction' => 0,
            'net_amount' => 80000,
        ]);

        Carbon::setTestNow();
    }

    public function test_approved_izin_and_sakit_do_not_count_as_alpha(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 10, 0, 0, 'Asia/Jakarta'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = $this->createKaryawan('Jaka Izin', Karyawan::EMPLOYMENT_FULL_TIME, 3200000, null);

        StrukSetting::current()->update([
            'payroll_alpha_deduction_full_time' => 100000,
        ]);

        DB::table('jadwal_karyawan')->insert([
            ['tanggal' => '2026-03-10', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
            ['tanggal' => '2026-03-11', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
            ['tanggal' => '2026-03-12', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
        ]);

        DB::table('absensi')->insert([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'tanggal' => '2026-03-10',
            'waktu_masuk' => '2026-03-10 07:00:00',
            'waktu_pulang' => '2026-03-10 15:00:00',
            'status' => 'hadir',
            'shift_no' => 1,
        ]);

        DB::table('leave_requests')->insert([
            [
                'id_karyawan' => (int) $karyawan->id_karyawan,
                'jenis' => 'izin',
                'tanggal_awal' => '2026-03-11',
                'tanggal_akhir' => '2026-03-11',
                'alasan' => 'Keperluan keluarga',
                'status' => 'approved',
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_karyawan' => (int) $karyawan->id_karyawan,
                'jenis' => 'sakit',
                'tanggal_awal' => '2026-03-12',
                'tanggal_akhir' => '2026-03-12',
                'alasan' => 'Demam',
                'status' => 'approved',
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.payroll.store', $karyawan), [
                'bulan' => '2026-03',
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'status' => PayrollSlip::STATUS_FINALIZED,
                'notes' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_slips', [
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'period_month' => '2026-03-01 00:00:00',
            'scheduled_shift_count' => 1,
            'present_shift_count' => 1,
            'alpha_shift_count' => 0,
            'approved_leave_shift_count' => 2,
            'approved_leave_day_count' => 2,
            'auto_alpha_deduction' => 0,
            'auto_approved_leave_deduction' => 2133332,
            'gross_amount' => 3200000,
            'net_amount' => 1066668,
        ]);

        Carbon::setTestNow();
    }

    public function test_part_time_payroll_holds_incomplete_attendance_until_clock_out_is_filled(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 3, 10, 0, 0, 'Asia/Jakarta'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = $this->createKaryawan('Bani Pending', Karyawan::EMPLOYMENT_PART_TIME, null, 20000);

        DB::table('jadwal_karyawan')->insert([
            ['tanggal' => '2026-04-01', 'shift_ke' => 1, 'id_karyawan' => (int) $karyawan->id_karyawan],
        ]);

        DB::table('absensi')->insert([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'tanggal' => '2026-04-01',
            'waktu_masuk' => '2026-04-01 07:00:00',
            'waktu_pulang' => null,
            'status' => 'hadir',
            'shift_no' => 1,
            'verification_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.payroll.store', $karyawan), [
                'bulan' => '2026-04',
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'status' => PayrollSlip::STATUS_FINALIZED,
                'notes' => '',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payroll_slips', [
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'period_month' => '2026-04-01 00:00:00',
            'present_shift_count' => 0,
            'alpha_shift_count' => 0,
            'paid_minutes' => 0,
            'gross_amount' => 0,
            'net_amount' => 0,
        ]);

        Carbon::setTestNow();
    }

    private function createKaryawan(string $name, string $employmentType, ?int $monthlySalary, ?int $hourlyRate): Karyawan
    {
        $id = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => $name,
            'jabatan' => 'Staff',
            'no_telepon' => '0812' . random_int(100000, 999999),
            'employment_type' => $employmentType,
            'monthly_salary' => $monthlySalary,
            'hourly_rate' => $hourlyRate,
            'is_active' => 1,
        ], 'id_karyawan');

        return Karyawan::query()->findOrFail((int) $id);
    }
}
