<?php

namespace Tests\Feature;

use App\Models\KasirShiftSession;
use App\Models\Pesanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KasirShiftOrderNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_assigns_incremental_order_number_per_shift(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Minuman',
            'deskripsi' => 'Tes',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Americano',
            'harga' => 15000,
            'id_kategori' => $kategoriId,
            'stok' => 50,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Urut',
            'jabatan' => 'Kasir',
            'no_telepon' => '08129999',
        ], 'id_karyawan');

        $shift = KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $this->actingAs($kasir)
            ->post(route('kasir.preview'), [
                'items' => [['id_produk' => $produkId, 'qty' => 1]],
            ])
            ->assertRedirect(route('kasir.checkout_page'));
        $this->actingAs($kasir)
            ->post(route('kasir.checkout_submit'), [
                'id_karyawan' => $karyawanId,
                'metode_pembayaran' => 'cash',
                'jumlah_bayar' => 20000,
            ])
            ->assertRedirect();

        $this->actingAs($kasir)
            ->post(route('kasir.preview'), [
                'items' => [['id_produk' => $produkId, 'qty' => 1]],
            ])
            ->assertRedirect(route('kasir.checkout_page'));
        $this->actingAs($kasir)
            ->post(route('kasir.checkout_submit'), [
                'id_karyawan' => $karyawanId,
                'metode_pembayaran' => 'cash',
                'jumlah_bayar' => 20000,
            ])
            ->assertRedirect();

        $orders = Pesanan::query()
            ->where('kasir_shift_session_id', (int) $shift->id)
            ->orderBy('id_pesanan')
            ->get(['no_urut_shift']);

        $this->assertCount(2, $orders);
        $this->assertSame(1, (int) $orders[0]->no_urut_shift);
        $this->assertSame(2, (int) $orders[1]->no_urut_shift);
    }
}

