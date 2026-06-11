<?php

namespace Tests\Feature;

use App\Models\KasirShiftSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransaksiDetailViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_detail_displays_shift_information(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Detail',
            'jabatan' => 'Kasir',
            'no_telepon' => '08130000001',
        ], 'id_karyawan');

        $shift = KasirShiftSession::query()->create([
            'user_id' => (int) $admin->id,
            'shift_ke' => 2,
            'kas_awal' => 250000,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $pesananId = DB::table('pesanan')->insertGetId([
            'id_pelanggan' => null,
            'id_karyawan' => $karyawanId,
            'kasir_shift_session_id' => $shift->id,
            'no_urut_shift' => 12,
            'id_diskon' => null,
            'subtotal_harga' => 18000,
            'diskon_nominal' => 0,
            'diskon_nama' => null,
            'diskon_tipe' => null,
            'diskon_nilai' => null,
            'total_harga' => 18000,
            'waktu_pembayaran' => '2026-03-22 11:00:00',
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'lunas',
            'offline_ref' => null,
        ], 'id_pesanan');

        $this->actingAs($admin)
            ->get(route('transaksi.show', ['transaksi' => $pesananId]))
            ->assertOk()
            ->assertSee('Shift')
            ->assertSee('Shift 2')
            ->assertSee('No. 012');
    }
}
