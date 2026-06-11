<?php

namespace Tests\Feature;

use App\Models\KasirShiftSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransaksiOperationalFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_operasional_today_filter_uses_configured_reset_hour(): void
    {
        config(['app.operasional_reset_hour' => 4]);
        Carbon::setTestNow(Carbon::parse('2026-02-20 02:30:00'));

        $user = User::factory()->create(['role' => 'kasir']);
        $karyawanId = $this->createKaryawan();

        $includedA = $this->createPesanan($karyawanId, '2026-02-19 05:00:00');
        $includedB = $this->createPesanan($karyawanId, '2026-02-20 03:30:00');
        $excluded = $this->createPesanan($karyawanId, '2026-02-20 04:01:00');

        $this->actingAs($user)
            ->get(route('transaksi.index', ['operasional' => 'today']))
            ->assertOk()
            ->assertSee(route('transaksi.show', ['transaksi' => $includedA]), false)
            ->assertSee(route('transaksi.show', ['transaksi' => $includedB]), false)
            ->assertDontSee(route('transaksi.show', ['transaksi' => $excluded]), false);

        Carbon::setTestNow();
    }

    public function test_date_filter_is_aligned_with_operational_day_boundary(): void
    {
        config(['app.operasional_reset_hour' => 4]);
        Carbon::setTestNow(Carbon::parse('2026-02-20 12:00:00'));

        $user = User::factory()->create(['role' => 'kasir']);
        $karyawanId = $this->createKaryawan();

        $excludedBeforeReset = $this->createPesanan($karyawanId, '2026-02-19 03:59:00');
        $includedOperationalStart = $this->createPesanan($karyawanId, '2026-02-19 04:00:00');
        $includedOperationalEnd = $this->createPesanan($karyawanId, '2026-02-20 03:59:00');
        $excludedAfterWindow = $this->createPesanan($karyawanId, '2026-02-20 04:00:00');

        $this->actingAs($user)
            ->get(route('transaksi.index', [
                'tanggal_awal' => '2026-02-19',
                'tanggal_akhir' => '2026-02-19',
            ]))
            ->assertOk()
            ->assertSee(route('transaksi.show', ['transaksi' => $includedOperationalStart]), false)
            ->assertSee(route('transaksi.show', ['transaksi' => $includedOperationalEnd]), false)
            ->assertDontSee(route('transaksi.show', ['transaksi' => $excludedBeforeReset]), false)
            ->assertDontSee(route('transaksi.show', ['transaksi' => $excludedAfterWindow]), false);

        Carbon::setTestNow();
    }

    public function test_operational_filter_automatically_follows_new_reset_hour_value(): void
    {
        config(['app.operasional_reset_hour' => 6]);
        Carbon::setTestNow(Carbon::parse('2026-02-20 05:30:00'));

        $user = User::factory()->create(['role' => 'kasir']);
        $karyawanId = $this->createKaryawan();

        $excluded = $this->createPesanan($karyawanId, '2026-02-19 05:45:00');
        $included = $this->createPesanan($karyawanId, '2026-02-19 06:15:00');

        $this->actingAs($user)
            ->get(route('transaksi.index', ['operasional' => 'today']))
            ->assertOk()
            ->assertSee(route('transaksi.show', ['transaksi' => $included]), false)
            ->assertDontSee(route('transaksi.show', ['transaksi' => $excluded]), false);

        Carbon::setTestNow();
    }

    public function test_transaction_history_displays_shift_information(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $karyawanId = $this->createKaryawan();
        $shift = KasirShiftSession::query()->create([
            'user_id' => (int) $user->id,
            'shift_ke' => 2,
            'kas_awal' => 200000,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $pesananId = $this->createPesanan($karyawanId, '2026-02-20 10:00:00', [
            'kasir_shift_session_id' => $shift->id,
            'no_urut_shift' => 7,
        ]);

        $this->actingAs($user)
            ->get(route('transaksi.index'))
            ->assertOk()
            ->assertSee(route('transaksi.show', ['transaksi' => $pesananId]), false)
            ->assertSee('Shift 2');
    }

    private function createKaryawan(): int
    {
        return DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Test',
            'jabatan' => 'Kasir',
            'no_telepon' => '08123456789',
        ], 'id_karyawan');
    }

    private function createPesanan(int $karyawanId, string $waktuPembayaran, array $overrides = []): int
    {
        return DB::table('pesanan')->insertGetId(array_merge([
            'id_pelanggan' => null,
            'id_karyawan' => $karyawanId,
            'kasir_shift_session_id' => null,
            'no_urut_shift' => null,
            'id_diskon' => null,
            'subtotal_harga' => 10000,
            'diskon_nominal' => 0,
            'diskon_nama' => null,
            'diskon_tipe' => null,
            'diskon_nilai' => null,
            'total_harga' => 10000,
            'waktu_pembayaran' => $waktuPembayaran,
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'lunas',
            'offline_ref' => null,
        ], $overrides), 'id_pesanan');
    }
}
