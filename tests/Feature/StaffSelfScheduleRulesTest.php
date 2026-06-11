<?php

namespace Tests\Feature;

use App\Models\StrukSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffSelfScheduleRulesTest extends TestCase
{
    use RefreshDatabase;

    private function createKaryawan(string $name, string $employmentType = \App\Models\Karyawan::EMPLOYMENT_FULL_TIME): int
    {
        return (int) DB::table('karyawan')->insertGetId([
            'nama_karyawan' => $name,
            'jabatan' => 'Staff',
            'no_telepon' => '0812' . random_int(100000, 999999),
            'employment_type' => $employmentType,
        ], 'id_karyawan');
    }

    public function test_max_per_week_is_enforced(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 11, 10, 0, 0, 'Asia/Jakarta'));

        $karyawanId = $this->createKaryawan('A');

        $setting = StrukSetting::current();
        $setting->update([
            'active_shift_count' => 3,
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => now()->toDateString(),
            'self_schedule_pick_end_date' => now()->addDays(20)->toDateString(),
            'self_schedule_capacity_shift1' => 10,
            'self_schedule_capacity_shift2' => 10,
            'self_schedule_capacity_shift3' => 10,
            'self_schedule_max_per_week' => 2,
        ]);

        $this->withSession(['staff_karyawan_id' => $karyawanId])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => now()->addDays(1)->toDateString(),
                'shift_ke' => 1,
            ])
            ->assertSessionHas('success');

        $this->withSession(['staff_karyawan_id' => $karyawanId])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => now()->addDays(2)->toDateString(),
                'shift_ke' => 2,
            ])
            ->assertSessionHas('success');

        $this->withSession(['staff_karyawan_id' => $karyawanId])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => now()->addDays(3)->toDateString(),
                'shift_ke' => 3,
            ])
            ->assertSessionHasErrors('pick');
    }

    public function test_weekend_capacity_override_is_applied(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 11, 10, 0, 0, 'Asia/Jakarta'));

        $a = $this->createKaryawan('A');
        $b = $this->createKaryawan('B');
        $c = $this->createKaryawan('C');

        $setting = StrukSetting::current();
        $setting->update([
            'active_shift_count' => 3,
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => now()->toDateString(),
            'self_schedule_pick_end_date' => now()->addDays(20)->toDateString(),
            'self_schedule_capacity_shift1' => 1,
            'self_schedule_capacity_shift2' => 1,
            'self_schedule_capacity_shift3' => 1,
            'self_schedule_capacity_weekend_shift1' => 2,
        ]);

        $weekendDate = Carbon::parse('2026-03-14', 'Asia/Jakarta')->toDateString(); // Saturday

        $this->withSession(['staff_karyawan_id' => $a])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => $weekendDate,
                'shift_ke' => 1,
            ])
            ->assertSessionHas('success');

        $this->withSession(['staff_karyawan_id' => $b])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => $weekendDate,
                'shift_ke' => 1,
            ])
            ->assertSessionHas('success');

        $this->withSession(['staff_karyawan_id' => $c])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => $weekendDate,
                'shift_ke' => 1,
            ])
            ->assertSessionHasErrors('pick');
    }

    public function test_staff_can_cancel_future_schedule_when_allowed(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 11, 10, 0, 0, 'Asia/Jakarta'));

        $karyawanId = $this->createKaryawan('A');

        $setting = StrukSetting::current();
        $setting->update([
            'active_shift_count' => 3,
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => now()->toDateString(),
            'self_schedule_pick_end_date' => now()->addDays(20)->toDateString(),
            'self_schedule_capacity_shift1' => 10,
            'self_schedule_capacity_shift2' => 10,
            'self_schedule_capacity_shift3' => 10,
            'self_schedule_allow_cancel' => true,
            'self_schedule_cancel_min_days_before' => 1,
        ]);

        $target = now()->addDays(3)->toDateString();

        $this->withSession(['staff_karyawan_id' => $karyawanId])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => $target,
                'shift_ke' => 1,
            ])
            ->assertSessionHas('success');

        $this->withSession(['staff_karyawan_id' => $karyawanId])
            ->post(route('staff.self_schedule.cancel'), [
                'tanggal' => $target,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('jadwal_karyawan', [
            'id_karyawan' => $karyawanId,
            'tanggal' => $target,
        ]);
    }

    public function test_pick_is_blocked_when_outside_open_phase(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 11, 10, 0, 0, 'Asia/Jakarta'));

        $karyawanId = $this->createKaryawan('A');

        $setting = StrukSetting::current();
        $setting->update([
            'active_shift_count' => 3,
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => now()->addDays(1)->toDateString(),
            'self_schedule_pick_end_date' => now()->addDays(10)->toDateString(),
            'self_schedule_open_start_date' => now()->addDays(2)->toDateString(),
            'self_schedule_open_end_date' => now()->addDays(5)->toDateString(),
            'self_schedule_capacity_shift1' => 10,
            'self_schedule_capacity_shift2' => 10,
            'self_schedule_capacity_shift3' => 10,
        ]);

        $this->withSession(['staff_karyawan_id' => $karyawanId])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => now()->addDays(3)->toDateString(),
                'shift_ke' => 1,
            ])
            ->assertSessionHasErrors('pick');
    }

    public function test_part_time_capacity_is_separate_from_full_time_capacity(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 11, 10, 0, 0, 'Asia/Jakarta'));

        $fullTime = $this->createKaryawan('FT A');
        $partTimeA = $this->createKaryawan('PT A', \App\Models\Karyawan::EMPLOYMENT_PART_TIME);
        $partTimeB = $this->createKaryawan('PT B', \App\Models\Karyawan::EMPLOYMENT_PART_TIME);
        $partTimeC = $this->createKaryawan('PT C', \App\Models\Karyawan::EMPLOYMENT_PART_TIME);

        $setting = StrukSetting::current();
        $setting->update([
            'active_shift_count' => 2,
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => now()->toDateString(),
            'self_schedule_pick_end_date' => now()->addDays(10)->toDateString(),
            'self_schedule_capacity_shift1' => 1,
            'self_schedule_capacity_shift2' => 1,
            'self_schedule_part_time_capacity_shift1' => 2,
            'part_time_shift1_start_time' => '09:00',
            'part_time_shift2_start_time' => '13:30',
        ]);

        $target = now()->addDays(1)->toDateString();

        $this->withSession(['staff_karyawan_id' => $fullTime])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => $target,
                'shift_ke' => 1,
            ])
            ->assertSessionHas('success');

        $this->withSession(['staff_karyawan_id' => $partTimeA])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => $target,
                'shift_ke' => 1,
            ])
            ->assertSessionHas('success');

        $this->withSession(['staff_karyawan_id' => $partTimeB])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => $target,
                'shift_ke' => 1,
            ])
            ->assertSessionHas('success');

        $this->withSession(['staff_karyawan_id' => $partTimeC])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => $target,
                'shift_ke' => 1,
            ])
            ->assertSessionHasErrors('pick');
    }

    public function test_part_time_weekly_limit_uses_part_time_rule(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 11, 10, 0, 0, 'Asia/Jakarta'));

        $karyawanId = $this->createKaryawan('PT A', \App\Models\Karyawan::EMPLOYMENT_PART_TIME);

        $setting = StrukSetting::current();
        $setting->update([
            'active_shift_count' => 2,
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => now()->toDateString(),
            'self_schedule_pick_end_date' => now()->addDays(20)->toDateString(),
            'self_schedule_capacity_shift1' => 10,
            'self_schedule_capacity_shift2' => 10,
            'self_schedule_max_per_week' => 5,
            'self_schedule_part_time_max_per_week' => 1,
            'part_time_shift1_start_time' => '09:00',
            'part_time_shift2_start_time' => '13:30',
        ]);

        $this->withSession(['staff_karyawan_id' => $karyawanId])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => now()->addDays(1)->toDateString(),
                'shift_ke' => 1,
            ])
            ->assertSessionHas('success');

        $this->withSession(['staff_karyawan_id' => $karyawanId])
            ->post(route('staff.self_schedule.pick'), [
                'tanggal' => now()->addDays(2)->toDateString(),
                'shift_ke' => 2,
            ])
            ->assertSessionHasErrors('pick');
    }
}
