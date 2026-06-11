<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardKeuanganTest extends TestCase
{
    use RefreshDatabase;

    public function test_setoran_actions_are_logged_to_audit_table(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('dashboard.setoran.store'), [
                'nominal' => 120000,
                'catatan' => 'Setor awal',
            ])
            ->assertRedirect();

        $setoranId = (int) DB::table('kas_setoran')->value('id');
        $this->assertGreaterThan(0, $setoranId);

        $this->actingAs($admin)
            ->post(route('dashboard.setoran.catatan.update', ['setoran' => $setoranId]), [
                'catatan' => 'Setor revisi',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('dashboard.setoran.nominal.correct', ['setoran' => $setoranId]), [
                'nominal_baru' => 100000,
                'catatan' => 'Selisih hitung',
            ])
            ->assertRedirect();

        $auditRows = DB::table('kas_setoran_audits')
            ->select('aksi')
            ->orderBy('id')
            ->pluck('aksi')
            ->all();

        $this->assertContains('buat_setoran', $auditRows);
        $this->assertContains('ubah_catatan', $auditRows);
        $this->assertContains('koreksi_nominal', $auditRows);
    }

    public function test_admin_can_export_keuangan_excel_with_date_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'kasir', 'name' => 'Kasir Satu']);

        DB::table('kas_setoran')->insert([
            [
                'tanggal_setor' => '2026-02-10 10:00:00',
                'nominal' => 500000,
                'catatan' => 'Setor mingguan',
                'user_id' => (int) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tanggal_setor' => '2026-02-11 12:00:00',
                'nominal' => -20000,
                'catatan' => 'Koreksi setoran #1',
                'user_id' => (int) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tanggal_setor' => '2026-02-13 09:00:00',
                'nominal' => 300000,
                'catatan' => 'Setor tambahan',
                'user_id' => (int) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.keuangan.export_excel', [
                'tanggal_awal' => '2026-02-10',
                'tanggal_akhir' => '2026-02-11',
            ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Laporan Keuangan - Histori Setoran', $content);
        $this->assertStringContainsString('Setor mingguan', $content);
        $this->assertStringContainsString('Koreksi', $content);
        $this->assertStringNotContainsString('Setor tambahan', $content);
    }

    public function test_keuangan_page_shows_setoran_audit_log_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $setoranId = DB::table('kas_setoran')->insertGetId([
            'tanggal_setor' => '2026-02-18 10:00:00',
            'nominal' => 150000,
            'catatan' => 'Setor kas',
            'user_id' => (int) $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('kas_setoran_audits')->insert([
            'setoran_id' => $setoranId,
            'aksi' => 'buat_setoran',
            'nominal_lama' => null,
            'nominal_baru' => 150000,
            'catatan_lama' => null,
            'catatan_baru' => 'Setor kas',
            'meta' => null,
            'user_id' => (int) $admin->id,
            'dibuat_pada' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.keuangan'))
            ->assertOk()
            ->assertSee('Log Audit Setoran')
            ->assertSee('Buat Setoran');
    }
}
