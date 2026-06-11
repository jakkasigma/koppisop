<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TelegramOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_order_can_be_stored_via_api(): void
    {
        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Minuman kopi',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Iced Latte',
            'harga' => 18000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 10,
            'is_temperature_enabled' => 1,
            'is_sugar_enabled' => 1,
            'is_cup_size_enabled' => 1,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $response = $this->postJson(route('api.telegram.orders.store'), [
            'external_order_id' => 'tg-order-001',
            'customer' => [
                'name' => 'Budi Telegram',
                'phone' => '08123456789',
            ],
            'items' => [
                [
                    'id_produk' => $produkId,
                    'qty' => 2,
                    'temperature' => 'ice',
                    'sugar_level' => 'normal',
                    'cup_size' => 'large',
                ],
            ],
            'catatan_pesanan' => 'Pickup jam 19.00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('already_exists', false)
            ->assertJsonPath('order.kasir_label', 'TELEGRAM')
            ->assertJsonPath('order.metode_pembayaran', 'qris')
            ->assertJsonPath('order.external_order_id', 'tg-order-001')
            ->assertJsonPath('order.subtotal_harga', 40000)
            ->assertJsonPath('order.total_harga', 40000)
            ->assertJsonPath('order.item_count', 2);

        $pesanan = DB::table('pesanan')->where('offline_ref', 'tg-order-001')->first();
        $detail = DB::table('detail_pesanan')->where('id_pesanan', $pesanan->id_pesanan)->first();
        $pelanggan = DB::table('pelanggan')->where('id_pelanggan', $pesanan->id_pelanggan)->first();

        $this->assertNotNull($pesanan);
        $this->assertNotNull($detail);
        $this->assertNotNull($pelanggan);
        $this->assertNull($pesanan->id_karyawan);
        $this->assertEquals('TELEGRAM', $pesanan->kasir_label);
        $this->assertEquals('qris', $pesanan->metode_pembayaran);
        $this->assertEquals('lunas', $pesanan->status_pembayaran);
        $this->assertEquals(40000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(40000.0, (float) $pesanan->total_harga);
        $this->assertEquals('Pickup jam 19.00', $pesanan->catatan_pesanan);
        $this->assertEquals('Budi Telegram', $pelanggan->nama);
        $this->assertEquals('08123456789', $pelanggan->no_telepon);
        $this->assertEquals(20000.0, (float) $detail->harga_satuan);
        $this->assertEquals('large', $detail->cup_size);
        $this->assertEquals(8, (int) DB::table('produk')->where('id_produk', $produkId)->value('stok'));
    }

    public function test_telegram_order_is_idempotent_for_same_external_order_id(): void
    {
        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Snack',
            'deskripsi' => 'Snack',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'French Fries',
            'harga' => 15000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 7,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $payload = [
            'external_order_id' => 'tg-order-002',
            'metode_pembayaran' => 'cash',
            'customer' => [
                'name' => 'Rina',
            ],
            'items' => [
                [
                    'id_produk' => $produkId,
                    'qty' => 1,
                ],
            ],
        ];

        $this->postJson(route('api.telegram.orders.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('already_exists', false);

        $this->postJson(route('api.telegram.orders.store'), $payload)
            ->assertOk()
            ->assertJsonPath('already_exists', true)
            ->assertJsonPath('order.external_order_id', 'tg-order-002');

        $this->assertEquals(1, DB::table('pesanan')->count());
        $this->assertEquals(1, DB::table('detail_pesanan')->count());
        $this->assertEquals(6, (int) DB::table('produk')->where('id_produk', $produkId)->value('stok'));
    }

    public function test_telegram_order_requires_valid_token_when_configured(): void
    {
        config()->set('services.telegram.order_token', 'secret-telegram-token');

        $response = $this->postJson(route('api.telegram.orders.store'), [
            'customer' => [
                'name' => 'Tanpa Token',
            ],
            'items' => [
                [
                    'id_produk' => 1,
                    'qty' => 1,
                ],
            ],
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Token webhook Telegram tidak valid.');
    }
}
