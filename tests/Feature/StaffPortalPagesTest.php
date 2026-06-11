<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\JadwalTukarRequest;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\StaffNotification;
use App\Models\StaffMessage;
use App\Models\StrukSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffPortalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_open_schedule_page_with_mobile_list_layout(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 21, 9, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff', Karyawan::EMPLOYMENT_PART_TIME);

        DB::table('jadwal_karyawan')->insert([
            [
                'tanggal' => '2026-03-21',
                'shift_ke' => 1,
                'id_karyawan' => (int) $karyawan->id_karyawan,
            ],
            [
                'tanggal' => '2026-03-23',
                'shift_ke' => 2,
                'id_karyawan' => (int) $karyawan->id_karyawan,
            ],
        ]);

        DB::table('absensi')->insert([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'tanggal' => '2026-03-21',
            'waktu_masuk' => '2026-03-21 07:01:00',
            'waktu_pulang' => null,
            'catatan' => 'tes',
            'shift_no' => 1,
            'status' => 'hadir',
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.jadwal', ['bulan' => '2026-03']))
            ->assertOk()
            ->assertSee('Jadwal Saya')
            ->assertSee('Part Time')
            ->assertSee('07:00 - 11:30')
            ->assertSee('Jadwal Hari Ini')
            ->assertSee('Terapkan')
            ->assertSee('2 shift');

        Carbon::setTestNow();
    }

    public function test_staff_can_open_history_page_with_swap_and_leave_sections(): void
    {
        $from = $this->createKaryawan('Bani Staff');
        $to = $this->createKaryawan('Jaka Staff');

        JadwalTukarRequest::query()->create([
            'tanggal' => '2026-03-26',
            'from_tanggal' => '2026-03-26',
            'to_tanggal' => '2026-03-28',
            'from_shift' => 1,
            'to_shift' => 2,
            'from_karyawan_id' => (int) $from->id_karyawan,
            'to_karyawan_id' => (int) $to->id_karyawan,
            'status' => 'pending',
            'staff_status' => 'pending',
        ]);

        LeaveRequest::query()->create([
            'id_karyawan' => (int) $from->id_karyawan,
            'jenis' => 'izin',
            'tanggal_awal' => '2026-03-29',
            'tanggal_akhir' => '2026-03-29',
            'alasan' => 'Ada urusan keluarga',
            'status' => 'pending',
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $from->id_karyawan])
            ->get(route('staff.history'))
            ->assertOk()
            ->assertSee('Riwayat Pengajuan')
            ->assertSee('Riwayat Tukar Shift')
            ->assertSee('Riwayat Izin &amp; Sakit', false)
            ->assertSee('Pesan Admin');
    }

    public function test_staff_can_open_profile_page(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 26, 9, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff', Karyawan::EMPLOYMENT_PART_TIME);

        DB::table('jadwal_karyawan')->insert([
            'tanggal' => '2026-03-26',
            'shift_ke' => 2,
            'id_karyawan' => (int) $karyawan->id_karyawan,
        ]);

        DB::table('absensi')->insert([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'tanggal' => '2026-03-26',
            'waktu_masuk' => '2026-03-26 15:02:00',
            'waktu_pulang' => null,
            'catatan' => null,
            'shift_no' => 2,
            'status' => 'hadir',
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.profile'))
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee('Shift Hari Ini')
            ->assertSee('Hubungi Admin')
            ->assertSee('Part Time')
            ->assertSee('Status Akun')
            ->assertDontSee('Edit Profil Saya');

        Carbon::setTestNow();
    }

    public function test_staff_can_open_profile_edit_page(): void
    {
        $karyawan = $this->createKaryawan('Bani Staff', Karyawan::EMPLOYMENT_PART_TIME);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.profile.edit'))
            ->assertOk()
            ->assertSee('Edit Profil')
            ->assertSee('No. Telepon')
            ->assertSee('Foto Profil')
            ->assertSee('Simpan Profil');

        Carbon::setTestNow();
    }

    public function test_staff_home_shows_complete_promo_information_for_single_line_body(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff');

        Announcement::query()->create([
            'title' => 'Info Promo',
            'body' => 'Diskon Persen Diskontol Nilai: 15% Periode: 2026-03-27 s/d 2026-03-27 Status: Aktif',
            'target_role' => null,
            'is_active' => true,
            'published_at' => Carbon::create(2026, 3, 27, 8, 0, 0, 'Asia/Jakarta'),
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.home'))
            ->assertOk()
            ->assertSee('Diskon Persen Diskontol')
            ->assertSee('15%')
            ->assertSee('2026-03-27 s/d 2026-03-27')
            ->assertSee('Aktif')
            ->assertDontSee('Cek detail promo pada informasi di bawah.');

        Carbon::setTestNow();
    }

    public function test_staff_home_uses_computed_terjadwal_status_for_future_promo(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff');

        Announcement::query()->create([
            'title' => 'Info Promo',
            'body' => 'Bundling Weekend Nilai: 15% Periode: 2026-03-29 s/d 2026-03-31 Status: Aktif',
            'target_role' => null,
            'is_active' => true,
            'published_at' => Carbon::create(2026, 3, 27, 8, 0, 0, 'Asia/Jakarta'),
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.home'))
            ->assertOk()
            ->assertSee('Bundling Weekend')
            ->assertSee('Terjadwal')
            ->assertDontSee('Akan Berakhir');

        Carbon::setTestNow();
    }

    public function test_staff_home_keeps_recently_reactivated_or_filtered_promos_visible_in_carousel(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 7, 10, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff');

        foreach (range(1, 8) as $index) {
            Announcement::query()->create([
                'title' => 'Promo selesai ' . $index,
                'body' => 'Promo selesai Nilai: 10% Periode: 2026-04-01 s/d 2026-04-05 Status: Aktif',
                'target_role' => null,
                'is_active' => true,
                'published_at' => Carbon::create(2026, 4, 6, 8, $index, 0, 'Asia/Jakarta'),
            ]);
        }

        $announcement = Announcement::query()->create([
            'title' => 'Promo Aktif Lagi',
            'body' => 'Promo Aktif Lagi Nilai: 20% Periode: 2026-04-07 s/d 2026-04-10 Status: Aktif',
            'target_role' => null,
            'is_active' => true,
            'published_at' => Carbon::create(2026, 3, 20, 8, 0, 0, 'Asia/Jakarta'),
        ]);

        $announcement->touch();

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.home'))
            ->assertOk()
            ->assertSee('Promo Aktif Lagi')
            ->assertSee('20%')
            ->assertDontSee('Promo selesai 1');

        Carbon::setTestNow();
    }

    public function test_staff_home_shows_employment_type_status(): void
    {
        $karyawan = $this->createKaryawan('Bani Staff', Karyawan::EMPLOYMENT_PART_TIME);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.home'))
            ->assertOk()
            ->assertSee('Part Time');
    }

    public function test_staff_home_shows_profile_shortcut_and_notification_panel_items(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 8, 9, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Jakka Staff');

        StaffNotification::query()->create([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'category' => 'payroll',
            'title' => 'Slip gaji April 2026 sudah tersedia',
            'body' => 'Admin sudah memfinalkan slip gaji kamu.',
            'action_url' => route('staff.payroll.index'),
            'action_label' => 'Lihat slip',
            'event_key' => 'test-payroll:' . (int) $karyawan->id_karyawan,
            'read_at' => null,
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.home'))
            ->assertOk()
            ->assertSee('Notifikasi')
            ->assertSee('Profil Saya')
            ->assertSee('Semua')
            ->assertSee('Slip gaji April 2026 sudah tersedia')
            ->assertSee('Gaji');

        Carbon::setTestNow();
    }

    public function test_staff_opening_notification_marks_it_read(): void
    {
        $karyawan = $this->createKaryawan('Jakka Staff');

        $notification = StaffNotification::query()->create([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'category' => 'swap',
            'title' => 'Tukar shift disetujui',
            'body' => 'Cek jadwal terbaru kamu.',
            'action_url' => route('staff.swap.index'),
            'action_label' => 'Lihat swap',
            'event_key' => 'test-swap:' . (int) $karyawan->id_karyawan,
            'read_at' => null,
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.notifications.open', $notification))
            ->assertRedirect(route('staff.swap.index'));

        $this->assertNotNull($notification->fresh()?->read_at);

        Carbon::setTestNow();
    }

    public function test_staff_can_open_notification_history_page(): void
    {
        $karyawan = $this->createKaryawan('Jakka Staff');

        StaffNotification::query()->create([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'category' => 'leave',
            'title' => 'Pengajuan izin disetujui',
            'body' => 'Izin tanggal 10 Apr 2026 sudah disetujui admin.',
            'action_url' => route('staff.leave.index'),
            'action_label' => 'Lihat izin',
            'event_key' => 'test-leave:' . (int) $karyawan->id_karyawan,
            'read_at' => null,
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.notifications.index'))
            ->assertOk()
            ->assertSee('Riwayat Notifikasi')
            ->assertSee('Pengajuan izin disetujui')
            ->assertSee('Belum Dibaca');
    }

    public function test_staff_home_includes_unread_messages_in_notification_popup(): void
    {
        $karyawan = $this->createKaryawan('Jakka Staff');

        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $karyawan->id_karyawan,
            'sender_role' => 'admin',
            'message' => 'Admin sudah balas, silakan cek perubahan jadwal terbaru.',
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.home'))
            ->assertOk()
            ->assertSee('Chat Admin')
            ->assertSee('Admin sudah balas, silakan cek perubahan jadwal terbaru.')
            ->assertSee('Pesan');
    }

    public function test_staff_can_open_messages_index_with_summary_cards(): void
    {
        $karyawan = $this->createKaryawan('Bani Staff');

        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $karyawan->id_karyawan,
            'sender_role' => 'admin',
            'message' => 'Admin sudah membalas pengajuanmu.',
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.messages.index'))
            ->assertOk()
            ->assertSee('Daftar Percakapan')
            ->assertSee('Belum Dibaca')
            ->assertSee('Chat Admin');
    }

    public function test_staff_can_open_message_thread_page_with_compose_panel(): void
    {
        $karyawan = $this->createKaryawan('Bani Staff');

        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $karyawan->id_karyawan,
            'sender_role' => 'admin',
            'message' => 'Coba cek jadwal terbaru ya.',
        ]);

        StaffMessage::query()->create([
            'thread_type' => 'admin_chat',
            'thread_id' => (int) $karyawan->id_karyawan,
            'sender_role' => 'staff',
            'sender_karyawan_id' => (int) $karyawan->id_karyawan,
            'message' => 'Siap, nanti saya cek lagi.',
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.messages.show', ['type' => 'admin_chat', 'id' => (int) $karyawan->id_karyawan]))
            ->assertOk()
            ->assertSee('Balas Percakapan')
            ->assertSee('Coba cek jadwal terbaru ya.')
            ->assertSee('Siap, nanti saya cek lagi.');
    }

    public function test_staff_can_open_leave_page_with_submission_summary(): void
    {
        $karyawan = $this->createKaryawan('Bani Staff');

        DB::table('jadwal_karyawan')->insert([
            [
                'tanggal' => '2026-03-28',
                'shift_ke' => 1,
                'id_karyawan' => (int) $karyawan->id_karyawan,
            ],
            [
                'tanggal' => '2026-03-29',
                'shift_ke' => 2,
                'id_karyawan' => (int) $karyawan->id_karyawan,
            ],
        ]);

        LeaveRequest::query()->create([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'jenis' => 'izin',
            'tanggal_awal' => '2026-03-28',
            'tanggal_akhir' => '2026-03-28',
            'alasan' => 'Acara keluarga',
            'status' => 'pending',
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.leave.index'))
            ->assertOk()
            ->assertSee('Form Pengajuan')
            ->assertSee('Riwayat Pengajuan')
            ->assertSee('Pilih Jadwal Masuk');
    }

    public function test_staff_can_open_self_schedule_page_with_overview_cards(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 26, 9, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff');

        $setting = StrukSetting::current();
        $setting->update([
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => '2026-03-27',
            'self_schedule_pick_end_date' => '2026-03-29',
            'self_schedule_open_start_date' => '2026-03-26',
            'self_schedule_open_end_date' => '2026-03-29',
            'self_schedule_capacity_shift1' => 2,
            'self_schedule_capacity_shift2' => 2,
            'active_shift_count' => 2,
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.self_schedule'))
            ->assertOk()
            ->assertSee('Ambil Jadwal Mandiri')
            ->assertSee('Daftar Hari &amp; Shift', false)
            ->assertSee('Shift Sudah Diambil')
            ->assertSee('Profil Saya')
            ->assertSee('Install App')
            ->assertSee('/manifest-staff.json', false);

        Carbon::setTestNow();
    }

    public function test_part_time_self_schedule_page_shows_part_time_slots(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 26, 9, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Part Time', Karyawan::EMPLOYMENT_PART_TIME);

        $setting = StrukSetting::current();
        $setting->update([
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => '2026-03-27',
            'self_schedule_pick_end_date' => '2026-03-29',
            'self_schedule_open_start_date' => '2026-03-26',
            'self_schedule_open_end_date' => '2026-03-29',
            'self_schedule_part_time_capacity_shift1' => 2,
            'self_schedule_part_time_capacity_shift2' => 2,
            'active_shift_count' => 2,
            'part_time_shift1_start_time' => '09:00',
            'part_time_shift2_start_time' => '13:30',
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.self_schedule'))
            ->assertOk()
            ->assertSee('Part Time')
            ->assertSee('PT-1')
            ->assertSee('09:00 - 13:30')
            ->assertSee('Daftar Hari &amp; Slot PT', false);

        Carbon::setTestNow();
    }

    public function test_staff_self_schedule_page_disables_pick_buttons_outside_open_phase(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 26, 9, 0, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff');

        $setting = StrukSetting::current();
        $setting->update([
            'self_schedule_enabled' => true,
            'self_schedule_is_open' => true,
            'self_schedule_pick_start_date' => '2026-03-27',
            'self_schedule_pick_end_date' => '2026-03-29',
            'self_schedule_open_start_date' => '2026-03-28',
            'self_schedule_open_end_date' => '2026-03-29',
            'self_schedule_capacity_shift1' => 2,
            'self_schedule_capacity_shift2' => 2,
            'active_shift_count' => 2,
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $karyawan->id_karyawan])
            ->get(route('staff.self_schedule'))
            ->assertOk()
            ->assertSee('Fase pengisian belum berjalan saat ini')
            ->assertSee('Belum Masuk Fase');

        Carbon::setTestNow();
    }

    public function test_staff_can_open_swap_page_with_overview_and_history(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 26, 9, 0, 0, 'Asia/Jakarta'));

        $from = $this->createKaryawan('Bani Staff');
        $to = $this->createKaryawan('Jaka Staff');

        $setting = StrukSetting::current();
        $setting->update([
            'self_schedule_enabled' => true,
            'self_schedule_pick_start_date' => '2026-03-27',
            'self_schedule_pick_end_date' => '2026-04-05',
            'self_schedule_cancel_min_days_before' => 2,
            'active_shift_count' => 2,
        ]);

        DB::table('jadwal_karyawan')->insert([
            [
                'tanggal' => '2026-03-30',
                'shift_ke' => 1,
                'id_karyawan' => (int) $from->id_karyawan,
            ],
            [
                'tanggal' => '2026-04-01',
                'shift_ke' => 2,
                'id_karyawan' => (int) $to->id_karyawan,
            ],
        ]);

        JadwalTukarRequest::query()->create([
            'tanggal' => '2026-03-30',
            'from_tanggal' => '2026-03-30',
            'to_tanggal' => '2026-04-01',
            'from_shift' => 1,
            'to_shift' => 2,
            'from_karyawan_id' => (int) $from->id_karyawan,
            'to_karyawan_id' => (int) $to->id_karyawan,
            'status' => 'pending',
            'staff_status' => 'approved',
        ]);

        $this->withSession(['staff_karyawan_id' => (int) $from->id_karyawan])
            ->get(route('staff.swap.index'))
            ->assertOk()
            ->assertSee('Jadwal yang Bisa Ditukar')
            ->assertSee('Riwayat Permintaan Tukar')
            ->assertSee('Menunggu Admin');

        Carbon::setTestNow();
    }

    public function test_staff_can_open_absen_page_with_staff_layout_summary(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 26, 6, 45, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff');

        DB::table('jadwal_karyawan')->insert([
            'tanggal' => '2026-03-26',
            'shift_ke' => 1,
            'id_karyawan' => (int) $karyawan->id_karyawan,
        ]);

        $this->withSession([
            'staff_karyawan_id' => (int) $karyawan->id_karyawan,
            'staff_karyawan_name' => 'Bani Staff',
        ])->get(route('absen.form'))
            ->assertOk()
            ->assertSee('Absen Masuk')
            ->assertSee('Jadwal Shift')
            ->assertSee('Syarat Absen')
            ->assertSee('Absen Karyawan');

        Carbon::setTestNow();
    }

    public function test_staff_can_open_focused_absen_masuk_and_pulang_pages(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 26, 7, 15, 0, 'Asia/Jakarta'));

        $karyawan = $this->createKaryawan('Bani Staff');

        DB::table('jadwal_karyawan')->insert([
            'tanggal' => '2026-03-26',
            'shift_ke' => 1,
            'id_karyawan' => (int) $karyawan->id_karyawan,
        ]);

        $session = [
            'staff_karyawan_id' => (int) $karyawan->id_karyawan,
            'staff_karyawan_name' => 'Bani Staff',
        ];

        $this->withSession($session)
            ->get(route('absen.masuk.page'))
            ->assertOk()
            ->assertSee('Form Absen Masuk')
            ->assertSee('Kembali ke Ringkasan');

        DB::table('absensi')->insert([
            'id_karyawan' => (int) $karyawan->id_karyawan,
            'tanggal' => '2026-03-26',
            'waktu_masuk' => Carbon::parse('2026-03-26 07:12:00', 'Asia/Jakarta'),
            'status' => 'hadir',
            'verification_status' => 'pending',
            'catatan' => null,
        ]);

        $this->withSession($session)
            ->get(route('absen.pulang.page'))
            ->assertOk()
            ->assertSee('Form Absen Pulang')
            ->assertSee('Absen Pulang');

        Carbon::setTestNow();
    }

    private function createKaryawan(string $name, string $employmentType = Karyawan::EMPLOYMENT_FULL_TIME): Karyawan
    {
        $id = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => $name,
            'jabatan' => 'Staff',
            'no_telepon' => '0812' . random_int(100000, 999999),
            'employment_type' => $employmentType,
            'is_active' => 1,
        ], 'id_karyawan');

        return Karyawan::query()->findOrFail((int) $id);
    }
}
