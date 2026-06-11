<?php

namespace Tests\Feature;

use App\Models\KasirShiftSession;
use App\Models\Pesanan;
use App\Models\StrukSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KasirTaxCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_applies_tax_when_enabled_in_workspace_setting(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        StrukSetting::current()->update([
            'enable_tax' => true,
            'tax_percent' => 10,
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Minuman',
            'deskripsi' => 'Tes',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Americano',
            'harga' => 20000,
            'id_kategori' => $kategoriId,
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Pajak',
            'jabatan' => 'Kasir',
            'no_telepon' => '08120001',
        ], 'id_karyawan');

        KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $this->actingAs($kasir)
            ->post(route('kasir.preview'), [
                'items' => [
                    ['id_produk' => $produkId, 'qty' => 1],
                ],
            ])
            ->assertRedirect(route('kasir.checkout_page'));

        $this->actingAs($kasir)
            ->post(route('kasir.checkout_submit'), [
                'id_karyawan' => $karyawanId,
                'metode_pembayaran' => 'cash',
                'jumlah_bayar' => 25000,
            ])
            ->assertRedirect();

        $pesanan = Pesanan::query()->latest('id_pesanan')->first();
        $this->assertNotNull($pesanan);
        $this->assertSame(20000.0, (float) $pesanan->subtotal_harga);
        $this->assertSame(0.0, (float) $pesanan->diskon_nominal);
        $this->assertSame(10.0, (float) $pesanan->pajak_persen);
        $this->assertSame(2000.0, (float) $pesanan->pajak_nominal);
        $this->assertSame(22000.0, (float) $pesanan->total_harga);
    }

    public function test_checkout_can_apply_tax_per_product_mode(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        StrukSetting::current()->update([
            'enable_tax' => true,
            'tax_percent' => 10,
            'tax_mode' => 'produk',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Minuman',
            'deskripsi' => 'Tes',
        ], 'id_kategori');

        $produkA = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk A',
            'harga' => 1000,
            'id_kategori' => $kategoriId,
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');
        $produkB = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk B',
            'harga' => 1000,
            'id_kategori' => $kategoriId,
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');
        $produkC = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk C',
            'harga' => 1000,
            'id_kategori' => $kategoriId,
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $diskonId = DB::table('diskon')->insertGetId([
            'nama_diskon' => 'Potongan 1',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 1,
            'minimal_belanja' => 0,
            'status_aktif' => 1,
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
            'keterangan' => null,
        ], 'id_diskon');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Pajak Produk',
            'jabatan' => 'Kasir',
            'no_telepon' => '08120002',
        ], 'id_karyawan');

        KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $this->actingAs($kasir)
            ->post(route('kasir.preview'), [
                'items' => [
                    ['id_produk' => $produkA, 'qty' => 1],
                    ['id_produk' => $produkB, 'qty' => 1],
                    ['id_produk' => $produkC, 'qty' => 1],
                ],
            ])
            ->assertRedirect(route('kasir.checkout_page'));

        $this->actingAs($kasir)
            ->post(route('kasir.checkout_submit'), [
                'id_karyawan' => $karyawanId,
                'id_diskon' => $diskonId,
                'metode_pembayaran' => 'cash',
                'jumlah_bayar' => 5000,
            ])
            ->assertRedirect();

        $pesanan = Pesanan::query()->latest('id_pesanan')->first();
        $this->assertNotNull($pesanan);
        $this->assertSame(3000.0, (float) $pesanan->subtotal_harga);
        $this->assertSame(1.0, (float) $pesanan->diskon_nominal);
        $this->assertSame(10.0, (float) $pesanan->pajak_persen);
        $this->assertSame(299.91, (float) $pesanan->pajak_nominal);
        $this->assertSame(3298.91, (float) $pesanan->total_harga);
    }

    public function test_checkout_keeps_tax_recorded_but_not_added_to_total_when_tax_auto_is_disabled(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        StrukSetting::current()->update([
            'enable_tax' => false,
            'tax_percent' => 10,
            'tax_mode' => 'transaksi',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Minuman',
            'deskripsi' => 'Tes',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Cafe Latte',
            'harga' => 20000,
            'id_kategori' => $kategoriId,
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Pajak Off',
            'jabatan' => 'Kasir',
            'no_telepon' => '08120003',
        ], 'id_karyawan');

        KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $this->actingAs($kasir)
            ->post(route('kasir.preview'), [
                'items' => [
                    ['id_produk' => $produkId, 'qty' => 1],
                ],
            ])
            ->assertRedirect(route('kasir.checkout_page'));

        $this->actingAs($kasir)
            ->post(route('kasir.checkout_submit'), [
                'id_karyawan' => $karyawanId,
                'metode_pembayaran' => 'cash',
                'jumlah_bayar' => 25000,
            ])
            ->assertRedirect();

        $pesanan = Pesanan::query()->latest('id_pesanan')->first();
        $this->assertNotNull($pesanan);
        $this->assertSame(20000.0, (float) $pesanan->subtotal_harga);
        $this->assertSame(10.0, (float) $pesanan->pajak_persen);
        $this->assertSame(2000.0, (float) $pesanan->pajak_nominal);
        $this->assertSame(20000.0, (float) $pesanan->total_harga);
    }
}
