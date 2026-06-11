<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Karyawan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AbsensiFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_absen_masuk_after_login(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 07:15:00'));
        $pin = '1234';
        $karyawanId = $this->createKaryawan('Kasir Pagi', $pin);

        $this->post(route('staff.login.submit'), [
            'no_telepon' => '0812345678',
            'pin' => $pin,
        ])->assertRedirect(route('staff.home'));

        $this->get(route('absen.form'))
            ->assertOk()
            ->assertSee('Absen Karyawan');

        $this->post(route('absen.masuk'), [
            'catatan' => 'datang tepat waktu',
        ])->assertRedirect(route('absen.form'));

        // Second attempt should be blocked with a clear message.
        $this->post(route('absen.masuk'), [
            'catatan' => 'coba absen lagi',
        ])->assertRedirect(route('absen.form'))
            ->assertSessionHasErrors(['absensi']);

        $row = DB::table('absensi')->where('id_karyawan', $karyawanId)->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-03-10', Carbon::parse((string) $row->tanggal)->toDateString());
        $this->assertNotNull($row->waktu_masuk);
        $this->assertNull($row->waktu_pulang);
        $this->assertSame(1, (int) DB::table('absensi')->where('id_karyawan', $karyawanId)->count());

        Carbon::setTestNow();
    }

    public function test_staff_can_absen_pulang_after_absen_masuk(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 07:05:00'));
        $pin = '1234';
        $karyawanId = $this->createKaryawan('Kasir Pulang', $pin);

        $this->post(route('staff.login.submit'), [
            'no_telepon' => '0812345678',
            'pin' => $pin,
        ])->assertRedirect(route('staff.home'));

        $this->post(route('absen.masuk'), [
            'catatan' => 'mulai kerja',
        ])->assertRedirect(route('absen.form'));

        Carbon::setTestNow(Carbon::parse('2026-03-10 12:10:00'));

        $this->post(route('absen.pulang'))
            ->assertRedirect(route('absen.form'));

        $row = DB::table('absensi')->where('id_karyawan', $karyawanId)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->waktu_masuk);
        $this->assertNotNull($row->waktu_pulang);
        $this->assertSame('pending', $row->verification_status);

        Carbon::setTestNow();
    }

    public function test_admin_can_monitor_absensi(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawanId = $this->createKaryawan('Kasir Monitor', '2345');

        DB::table('absensi')->insert([
            'id_karyawan' => $karyawanId,
            'tanggal' => '2026-03-10',
            'waktu_masuk' => '2026-03-10 07:00:00',
            'waktu_pulang' => null,
            'catatan' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.absensi', [
                'tanggal_awal' => '2026-03-10',
                'tanggal_akhir' => '2026-03-10',
            ]))
            ->assertOk()
            ->assertSee('Absensi Karyawan')
            ->assertSee('Masuk')
            ->assertSee('Pulang')
            ->assertSee('Durasi')
            ->assertSee('Verifikasi');

        Carbon::setTestNow();
    }

    public function test_admin_can_filter_absensi_that_need_checkout_correction(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-11 10:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $needsCorrectionId = $this->createKaryawan('Kasir Koreksi Admin', '3333');
        $cleanId = $this->createKaryawan('Kasir Lengkap', '4444');

        DB::table('absensi')->insert([
            [
                'id_karyawan' => $needsCorrectionId,
                'tanggal' => '2026-03-10',
                'waktu_masuk' => '2026-03-10 07:00:00',
                'waktu_pulang' => null,
                'status' => 'hadir',
                'shift_no' => 1,
                'verification_status' => 'pending',
                'checkout_correction_status' => 'requested',
                'checkout_requested_pulang' => '2026-03-10 12:00:00',
                'checkout_requested_at' => '2026-03-11 09:00:00',
                'checkout_request_note' => 'Lupa klik pulang',
            ],
            [
                'id_karyawan' => $cleanId,
                'tanggal' => '2026-03-10',
                'waktu_masuk' => '2026-03-10 07:00:00',
                'waktu_pulang' => '2026-03-10 15:00:00',
                'status' => 'hadir',
                'shift_no' => 1,
                'verification_status' => 'verified',
                'checkout_correction_status' => null,
                'checkout_requested_pulang' => null,
                'checkout_requested_at' => null,
                'checkout_request_note' => null,
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.absensi', [
                'tanggal_awal' => '2026-03-10',
                'tanggal_akhir' => '2026-03-11',
                'correction_state' => 'needs_attention',
            ]))
            ->assertOk()
            ->assertSee('Kasir Koreksi Admin')
            ->assertSee('Perlu Koreksi')
            ->assertSee('1 data')
            ->assertDontSee('data-id="2"', false);

        Carbon::setTestNow();
    }

    public function test_admin_cannot_verify_attendance_before_absen_pulang(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawanId = $this->createKaryawan('Kasir Belum Pulang', '8888');

        DB::table('absensi')->insert([
            'id_karyawan' => $karyawanId,
            'tanggal' => '2026-03-10',
            'waktu_masuk' => '2026-03-10 07:00:00',
            'waktu_pulang' => null,
            'status' => 'hadir',
            'verification_status' => 'pending',
            'catatan' => null,
        ]);

        $absensiId = (int) DB::table('absensi')->where('id_karyawan', $karyawanId)->value('id_absensi');

        $this->actingAs($admin)
            ->post(route('dashboard.absensi.verify', ['absensi' => $absensiId]), [
                'note' => '',
            ])
            ->assertSessionHasErrors(['absensi']);

        $this->assertDatabaseHas('absensi', [
            'id_absensi' => $absensiId,
            'verification_status' => 'pending',
        ]);

        Carbon::setTestNow();
    }

    public function test_admin_can_export_absensi_excel(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawanId = $this->createKaryawan('Kasir Export', '4567');

        DB::table('absensi')->insert([
            'id_karyawan' => $karyawanId,
            'tanggal' => '2026-03-10',
            'waktu_masuk' => '2026-03-10 07:00:00',
            'waktu_pulang' => null,
            'catatan' => 'test',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.absensi.export_excel', [
            'tanggal_awal' => '2026-03-10',
            'tanggal_akhir' => '2026-03-10',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Laporan Absensi', $content);
        $this->assertStringContainsString('Kasir Export', $content);

        Carbon::setTestNow();
    }

    public function test_staff_can_request_checkout_correction_for_previous_open_absensi(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-11 09:00:00'));
        $pin = '9999';
        $karyawanId = $this->createKaryawan('Kasir Koreksi', $pin);

        DB::table('absensi')->insert([
            'id_karyawan' => $karyawanId,
            'tanggal' => '2026-03-10',
            'waktu_masuk' => '2026-03-10 07:00:00',
            'waktu_pulang' => null,
            'status' => 'hadir',
            'shift_no' => 1,
            'verification_status' => 'pending',
        ]);

        $this->post(route('staff.login.submit'), [
            'no_telepon' => '0812345678',
            'pin' => $pin,
        ])->assertRedirect(route('staff.home'));

        $this->get(route('absen.form'))
            ->assertOk()
            ->assertSee('Koreksi Jam Pulang')
            ->assertSee('Ajukan Koreksi');

        $absensiId = (int) DB::table('absensi')->where('id_karyawan', $karyawanId)->value('id_absensi');

        $this->post(route('absen.checkout_correction.request', ['absensi' => $absensiId]), [
            'requested_pulang' => '2026-03-10T12:00',
            'request_note' => 'Lupa klik absen pulang saat tutup toko',
        ])->assertRedirect(route('absen.form'));

        $this->assertDatabaseHas('absensi', [
            'id_absensi' => $absensiId,
            'checkout_correction_status' => 'requested',
            'checkout_request_note' => 'Lupa klik absen pulang saat tutup toko',
        ]);

        $this->assertDatabaseHas('staff_messages', [
            'thread_type' => 'absensi',
            'thread_id' => $absensiId,
            'sender_role' => 'staff',
        ]);

        Carbon::setTestNow();
    }

    public function test_admin_can_approve_checkout_correction_and_verify_afterwards(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-11 10:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawanId = $this->createKaryawan('Kasir Approval', '5555');

        DB::table('absensi')->insert([
            'id_karyawan' => $karyawanId,
            'tanggal' => '2026-03-10',
            'waktu_masuk' => '2026-03-10 07:00:00',
            'waktu_pulang' => null,
            'status' => 'hadir',
            'shift_no' => 1,
            'verification_status' => 'pending',
            'checkout_correction_status' => 'requested',
            'checkout_requested_pulang' => '2026-03-10 12:00:00',
            'checkout_requested_at' => '2026-03-11 09:00:00',
            'checkout_request_note' => 'Lupa klik pulang',
        ]);

        $absensiId = (int) DB::table('absensi')->where('id_karyawan', $karyawanId)->value('id_absensi');

        $this->actingAs($admin)
            ->post(route('dashboard.absensi.checkout_correction.approve', ['absensi' => $absensiId]), [
                'note' => 'Disetujui admin',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('absensi', [
            'id_absensi' => $absensiId,
            'checkout_correction_status' => 'approved',
            'checkout_review_note' => 'Disetujui admin',
            'waktu_pulang' => '2026-03-10 12:00:00',
        ]);

        $this->actingAs($admin)
            ->post(route('dashboard.absensi.verify', ['absensi' => $absensiId]), [
                'note' => 'Lengkap',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('absensi', [
            'id_absensi' => $absensiId,
            'verification_status' => 'verified',
            'verification_note' => 'Lengkap',
        ]);

        Carbon::setTestNow();
    }

    public function test_admin_dashboard_shows_absensi_correction_notice_card(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-11 10:00:00'));

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawanId = $this->createKaryawan('Kasir Dashboard Koreksi', '6666');

        DB::table('absensi')->insert([
            'id_karyawan' => $karyawanId,
            'tanggal' => '2026-03-10',
            'waktu_masuk' => '2026-03-10 07:00:00',
            'waktu_pulang' => null,
            'status' => 'hadir',
            'shift_no' => 1,
            'verification_status' => 'pending',
            'checkout_correction_status' => 'requested',
            'checkout_requested_pulang' => '2026-03-10 12:00:00',
            'checkout_requested_at' => '2026-03-11 09:00:00',
            'checkout_request_note' => 'Lupa klik pulang',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('Koreksi Absensi')
            ->assertSee('menunggu admin');

        Carbon::setTestNow();
    }

    private function createKaryawan(string $nama, string $pin): int
    {
        $digest = Karyawan::pinDigest($pin);
        return DB::table('karyawan')->insertGetId([
            'nama_karyawan' => $nama,
            'jabatan' => 'Kasir',
            'no_telepon' => '0812345678',
            'pin_digest' => $digest,
            'is_active' => 1,
        ], 'id_karyawan');
    }
}
