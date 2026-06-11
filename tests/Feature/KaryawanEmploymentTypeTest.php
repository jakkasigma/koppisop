<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\StrukSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KaryawanEmploymentTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_part_time_karyawan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('karyawan.store'), [
                'nama_karyawan' => 'Bani Part Time',
                'jabatan' => 'Barista',
                'no_telepon' => '081234567890',
                'employment_type' => Karyawan::EMPLOYMENT_PART_TIME,
                'hourly_rate' => 20000,
                'pin' => '1234',
                'is_active' => 1,
            ])
            ->assertRedirect(route('karyawan.index'));

        $this->assertDatabaseHas('karyawan', [
            'nama_karyawan' => 'Bani Part Time',
            'employment_type' => Karyawan::EMPLOYMENT_PART_TIME,
        ]);
    }

    public function test_shift_range_label_uses_employee_type_duration(): void
    {
        $setting = StrukSetting::current();

        $this->assertSame(
            '07:00 - 15:00',
            $setting->shiftRangeLabel(1, Karyawan::EMPLOYMENT_FULL_TIME, '2026-03-27')
        );

        $this->assertSame(
            '07:00 - 11:30',
            $setting->shiftRangeLabel(1, Karyawan::EMPLOYMENT_PART_TIME, '2026-03-27')
        );
    }

    public function test_shift_range_label_uses_part_time_slot_setting_when_available(): void
    {
        $setting = StrukSetting::current();
        $setting->update([
            'part_time_shift2_start_time' => '12:00',
        ]);

        $this->assertSame(
            '12:00 - 16:30',
            $setting->shiftRangeLabel(2, Karyawan::EMPLOYMENT_PART_TIME, '2026-03-27')
        );
    }

    public function test_admin_schedule_page_shows_full_time_and_part_time_labels_in_one_screen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $setting = StrukSetting::current();
        $setting->update([
            'active_shift_count' => 2,
            'shift1_start_time' => '07:00',
            'part_time_shift1_start_time' => '09:00',
        ]);

        $fullTimeId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Faris Full',
            'jabatan' => 'Barista',
            'no_telepon' => '081200000001',
            'employment_type' => Karyawan::EMPLOYMENT_FULL_TIME,
        ], 'id_karyawan');

        $partTimeId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Prita Part',
            'jabatan' => 'Runner',
            'no_telepon' => '081200000002',
            'employment_type' => Karyawan::EMPLOYMENT_PART_TIME,
        ], 'id_karyawan');

        DB::table('jadwal_karyawan')->insert([
            [
                'tanggal' => '2026-04-10',
                'shift_ke' => 1,
                'id_karyawan' => $fullTimeId,
            ],
            [
                'tanggal' => '2026-04-10',
                'shift_ke' => 1,
                'id_karyawan' => $partTimeId,
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.jadwal.index', ['bulan' => '2026-04']))
            ->assertOk()
            ->assertSee('Full Time')
            ->assertSee('Part Time')
            ->assertSee('PT-1')
            ->assertSee('09:00 - 13:30');
    }

    public function test_admin_self_schedule_settings_page_separates_full_time_and_part_time_sections(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        StrukSetting::current()->update([
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => '2026-04-10',
            'self_schedule_pick_end_date' => '2026-04-20',
            'part_time_shift1_start_time' => '09:00',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.jadwal.self_schedule'))
            ->assertOk()
            ->assertSee('Aturan Umum')
            ->assertSee('Pengaturan Full Time')
            ->assertSee('Pengaturan Part Time')
            ->assertSee('PT-1')
            ->assertSee('09:00 - 13:30');
    }
}
