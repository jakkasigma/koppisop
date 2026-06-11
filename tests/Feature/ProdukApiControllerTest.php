<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProdukApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_can_list_products_with_category_and_options(): void
    {
        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Minuman kopi',
        ], 'id_kategori');

        DB::table('produk')->insert([
            'nama_produk' => 'Iced Latte',
            'harga' => 18000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Latte dingin',
            'stok' => 5,
            'is_temperature_enabled' => 1,
            'temperature_options' => json_encode([
                ['value' => 'hot', 'label' => 'Hot', 'extra_price' => 0],
                ['value' => 'ice', 'label' => 'Es', 'extra_price' => 0],
            ]),
            'is_sugar_enabled' => 1,
            'sugar_options' => json_encode([
                ['value' => 'normal', 'label' => 'Normal Sugar', 'extra_price' => 0],
            ]),
            'is_cup_size_enabled' => 1,
            'cup_size_options' => json_encode([
                ['value' => 'regular', 'label' => 'Regular', 'extra_price' => 0],
                ['value' => 'large', 'label' => 'Large', 'extra_price' => 2000],
            ]),
            'is_spicy_enabled' => 0,
            'spicy_options' => null,
            'custom_option_groups' => json_encode([
                [
                    'id' => 'topping',
                    'label' => 'Topping',
                    'required' => false,
                    'options' => [
                        ['value' => 'boba', 'label' => 'Boba', 'extra_price' => 3000],
                    ],
                ],
            ]),
        ]);

        $response = $this->getJson(route('api.produk.index'));

        $response->assertOk()
            ->assertJsonPath('message', 'Daftar produk berhasil diambil.')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nama_produk', 'Iced Latte')
            ->assertJsonPath('data.0.kategori.nama_kategori', 'Coffee')
            ->assertJsonPath('data.0.tersedia', true)
            ->assertJsonPath('data.0.opsi.temperature_enabled', true)
            ->assertJsonPath('data.0.opsi.cup_size_options.1.value', 'large')
            ->assertJsonPath('data.0.opsi.custom_option_groups.0.id', 'topping');
    }

    public function test_api_can_filter_available_products_only(): void
    {
        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Tea',
            'deskripsi' => 'Minuman teh',
        ], 'id_kategori');

        DB::table('produk')->insert([
            [
                'nama_produk' => 'Lemon Tea',
                'harga' => 12000,
                'id_kategori' => $kategoriId,
                'deskripsi' => 'Tersedia',
                'stok' => 3,
                'is_temperature_enabled' => 0,
                'is_sugar_enabled' => 0,
                'is_cup_size_enabled' => 0,
                'is_spicy_enabled' => 0,
            ],
            [
                'nama_produk' => 'Black Tea',
                'harga' => 10000,
                'id_kategori' => $kategoriId,
                'deskripsi' => 'Habis',
                'stok' => 0,
                'is_temperature_enabled' => 0,
                'is_sugar_enabled' => 0,
                'is_cup_size_enabled' => 0,
                'is_spicy_enabled' => 0,
            ],
        ]);

        $response = $this->getJson(route('api.produk.index', ['tersedia' => 1]));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_produk', 'Lemon Tea');
    }
}
