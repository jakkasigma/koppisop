<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\StaffActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffProfileAndActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_update_profile_and_log_activity(): void
    {
        Storage::fake('public');

        $karyawan = $this->createKaryawan('Jakka Staff');
        $imageContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==');

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->post(route('staff.profile.update'), [
                'no_telepon' => '0812 3456 7890',
                'alamat' => 'Jl. Melati No. 10, Jakarta',
                'foto_profil' => UploadedFile::fake()->createWithContent('avatar.png', (string) $imageContent),
            ])
            ->assertRedirect(route('staff.profile'));

        $karyawan->refresh();

        $this->assertSame('081234567890', (string) $karyawan->no_telepon);
        $this->assertSame('Jl. Melati No. 10, Jakarta', (string) $karyawan->alamat);
        $this->assertNotNull($karyawan->foto_profil_path);
        Storage::disk('public')->assertExists((string) $karyawan->foto_profil_path);

        $this->assertDatabaseHas('staff_activity_logs', [
            'karyawan_id' => (int) $karyawan->id_karyawan,
            'action_key' => 'staff.profile.update',
            'action_label' => 'Perbarui Profil',
        ]);
    }

    public function test_admin_can_open_staff_activity_log_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $karyawan = $this->createKaryawan('Andre Staff', Karyawan::EMPLOYMENT_PART_TIME);

        StaffActivityLog::query()->create([
            'karyawan_id' => (int) $karyawan->id_karyawan,
            'actor_name' => $karyawan->nama_karyawan,
            'actor_role' => $karyawan->jabatan,
            'employment_type' => $karyawan->employment_type,
            'action_key' => 'staff.absen.masuk',
            'action_label' => 'Absen Masuk',
            'summary' => 'Staf melakukan absen masuk untuk shift pagi.',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.staff_activity.index'))
            ->assertOk()
            ->assertSee('Aktivitas Staf')
            ->assertSee('Absen Masuk')
            ->assertSee('Andre Staff');
    }

    private function createKaryawan(string $name, string $employmentType = Karyawan::EMPLOYMENT_FULL_TIME): Karyawan
    {
        $id = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => $name,
            'jabatan' => 'Barista',
            'no_telepon' => '0812' . random_int(100000, 999999),
            'employment_type' => $employmentType,
            'is_active' => 1,
        ], 'id_karyawan');

        return Karyawan::query()->findOrFail((int) $id);
    }
}
