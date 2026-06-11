<?php

namespace Tests\Feature;

use App\Models\KasirShiftSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KasirCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_applies_large_cup_surcharge_to_unit_price_and_total(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Minuman kopi',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Americano',
            'harga' => 10000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 10,
            'is_temperature_enabled' => 1,
            'is_sugar_enabled' => 1,
            'is_cup_size_enabled' => 1,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Test',
            'jabatan' => 'Kasir',
            'no_telepon' => '0812',
        ], 'id_karyawan');

        $this->actingAsKasirWithShift($user);

        $this->post(route('kasir.preview'), [
            'items' => [
                [
                    'id_produk' => $produkId,
                    'qty' => 2,
                    'temperature' => 'ice',
                    'sugar_level' => 'normal',
                    'cup_size' => 'large',
                ],
            ],
        ])->assertRedirect(route('kasir.checkout_page'));

        $this->post(route('kasir.checkout_submit'), [
            'id_karyawan' => $karyawanId,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 50000,
        ])->assertRedirectContains('/kasir/receipt/');

        $pesanan = DB::table('pesanan')->first();
        $detail = DB::table('detail_pesanan')->first();

        $this->assertNotNull($pesanan);
        $this->assertNotNull($detail);
        $this->assertEquals(24000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(24000.0, (float) $pesanan->total_harga);
        $this->assertEquals(12000.0, (float) $detail->harga_satuan);
        $this->assertEquals('large', $detail->cup_size);
        $this->assertEquals(2, (int) $detail->jumlah);
        $this->assertEquals(8, (int) DB::table('produk')->where('id_produk', $produkId)->value('stok'));
    }

    public function test_checkout_page_includes_custom_option_surcharge_in_summary(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Food',
            'deskripsi' => 'Makanan',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Potato Wedges',
            'harga' => 22000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
            'custom_option_groups' => json_encode([
                [
                    'id' => 'pedas',
                    'label' => 'Pedas',
                    'required' => true,
                    'options' => [
                        ['value' => 'sauce', 'label' => 'Sauce', 'extra_price' => 0],
                        ['value' => 'lombok kecap', 'label' => 'Lombok Kecap', 'extra_price' => 2000],
                    ],
                ],
            ]),
        ], 'id_produk');

        $this->actingAsKasirWithShift($user);

        $this->post(route('kasir.preview'), [
            'items' => [
                [
                    'id_produk' => $produkId,
                    'qty' => 1,
                    'custom_options' => [
                        'pedas' => 'lombok kecap',
                    ],
                ],
            ],
        ])->assertRedirect(route('kasir.checkout_page'));

        $this->get(route('kasir.checkout_page'))
            ->assertOk()
            ->assertViewHas('total', 24000.0)
            ->assertViewHas('ringkasan', function (array $ringkasan): bool {
                return isset($ringkasan[0])
                    && (float) ($ringkasan[0]['harga_satuan'] ?? 0) === 24000.0
                    && (float) ($ringkasan[0]['subtotal'] ?? 0) === 24000.0;
            });
    }

    public function test_admin_can_cancel_and_restore_transaction_stock(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Minuman kopi',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Latte',
            'harga' => 12000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 8,
            'is_temperature_enabled' => 1,
            'is_sugar_enabled' => 1,
            'is_cup_size_enabled' => 1,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Admin Kasir',
            'jabatan' => 'Kasir',
            'no_telepon' => '0813',
        ], 'id_karyawan');

        $pesananId = DB::table('pesanan')->insertGetId([
            'id_pelanggan' => null,
            'id_karyawan' => $karyawanId,
            'total_harga' => 24000,
            'subtotal_harga' => 24000,
            'diskon_nominal' => 0,
            'diskon_nama' => null,
            'diskon_tipe' => null,
            'diskon_nilai' => null,
            'waktu_pembayaran' => now(),
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'lunas',
            'offline_ref' => null,
        ], 'id_pesanan');

        DB::table('detail_pesanan')->insert([
            'id_pesanan' => $pesananId,
            'id_produk' => $produkId,
            'jumlah' => 2,
            'harga_satuan' => 12000,
            'temperature' => 'ice',
            'sugar_level' => 'normal',
            'cup_size' => 'regular',
        ]);

        $this->actingAs($admin);

        $this->post(route('transaksi.batal', ['transaksi' => $pesananId]))
            ->assertRedirect(route('transaksi.show', ['transaksi' => $pesananId]));

        $this->assertEquals('dibatalkan', DB::table('pesanan')->where('id_pesanan', $pesananId)->value('status_pembayaran'));
        $this->assertEquals(10, (int) DB::table('produk')->where('id_produk', $produkId)->value('stok'));

        $this->post(route('transaksi.restore', ['transaksi' => $pesananId]))
            ->assertRedirect(route('transaksi.show', ['transaksi' => $pesananId]));

        $this->assertEquals('lunas', DB::table('pesanan')->where('id_pesanan', $pesananId)->value('status_pembayaran'));
        $this->assertEquals(8, (int) DB::table('produk')->where('id_produk', $produkId)->value('stok'));
    }

    public function test_offline_sync_uses_snapshot_unit_price_when_product_price_changes(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Minuman kopi',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Cappuccino',
            'harga' => 10000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 20,
            'is_temperature_enabled' => 1,
            'is_sugar_enabled' => 1,
            'is_cup_size_enabled' => 1,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Offline',
            'jabatan' => 'Kasir',
            'no_telepon' => '0814',
        ], 'id_karyawan');

        DB::table('produk')->where('id_produk', $produkId)->update(['harga' => 15000]);

        $this->actingAsKasirWithShift($user);

        $response = $this->postJson(route('kasir.offline_sync'), [
            'offline_ref' => 'offline-test-001',
            'id_karyawan' => $karyawanId,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 50000,
            'items' => [
                [
                    'id_produk' => $produkId,
                    'qty' => 2,
                    'harga_satuan' => 12000,
                    'temperature' => 'ice',
                    'sugar_level' => 'normal',
                    'cup_size' => 'large',
                ],
            ],
        ]);

        $response->assertOk();

        $pesanan = DB::table('pesanan')->where('offline_ref', 'offline-test-001')->first();
        $detail = DB::table('detail_pesanan')->where('id_pesanan', $pesanan->id_pesanan)->first();

        $this->assertEquals(24000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(24000.0, (float) $pesanan->total_harga);
        $this->assertEquals(12000.0, (float) $detail->harga_satuan);
    }

    public function test_offline_sync_accepts_discount_snapshot_when_discount_is_no_longer_active(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Minuman kopi',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Mocha',
            'harga' => 12000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 20,
            'is_temperature_enabled' => 1,
            'is_sugar_enabled' => 1,
            'is_cup_size_enabled' => 1,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Offline 2',
            'jabatan' => 'Kasir',
            'no_telepon' => '0815',
        ], 'id_karyawan');

        $diskonId = DB::table('diskon')->insertGetId([
            'nama_diskon' => 'Promo Lama',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 2000,
            'minimal_belanja' => 10000,
            'status_aktif' => 0,
            'tanggal_mulai' => now()->subDays(7)->toDateString(),
            'tanggal_selesai' => now()->subDay()->toDateString(),
            'keterangan' => 'Expired',
        ], 'id_diskon');

        $this->actingAsKasirWithShift($user);

        $response = $this->postJson(route('kasir.offline_sync'), [
            'offline_ref' => 'offline-test-002',
            'id_karyawan' => $karyawanId,
            'id_diskon' => $diskonId,
            'diskon_nominal_snapshot' => 2000,
            'diskon_nama_snapshot' => 'Promo Lama',
            'diskon_tipe_snapshot' => 'nominal',
            'diskon_nilai_snapshot' => 2000,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 50000,
            'items' => [
                [
                    'id_produk' => $produkId,
                    'qty' => 2,
                    'harga_satuan' => 12000,
                    'temperature' => 'ice',
                    'sugar_level' => 'normal',
                    'cup_size' => 'regular',
                ],
            ],
        ]);

        $response->assertOk();

        $pesanan = DB::table('pesanan')->where('offline_ref', 'offline-test-002')->first();
        $this->assertNotNull($pesanan);
        $this->assertEquals(24000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(2000.0, (float) $pesanan->diskon_nominal);
        $this->assertEquals(22000.0, (float) $pesanan->total_harga);
        $this->assertEquals('Promo Lama', $pesanan->diskon_nama);
    }

    public function test_percentage_discount_is_limited_by_maximum_cap(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Minuman kopi',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Americano',
            'harga' => 10000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 20,
            'is_temperature_enabled' => 1,
            'is_sugar_enabled' => 1,
            'is_cup_size_enabled' => 1,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Diskon',
            'jabatan' => 'Kasir',
            'no_telepon' => '0816',
        ], 'id_karyawan');

        $diskonId = DB::table('diskon')->insertGetId([
            'nama_diskon' => 'Promo 50 Cap',
            'tipe_diskon' => 'persen',
            'nilai_diskon' => 50,
            'minimal_belanja' => 0,
            'maksimal_diskon' => 10000,
            'status_aktif' => 1,
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
            'keterangan' => null,
        ], 'id_diskon');

        $this->actingAsKasirWithShift($user);

        $this->post(route('kasir.preview'), [
            'items' => [
                [
                    'id_produk' => $produkId,
                    'qty' => 3,
                    'temperature' => 'ice',
                    'sugar_level' => 'normal',
                    'cup_size' => 'regular',
                ],
            ],
        ])->assertRedirect(route('kasir.checkout_page'));

        $this->post(route('kasir.checkout_submit'), [
            'id_karyawan' => $karyawanId,
            'id_diskon' => $diskonId,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 30000,
        ])->assertRedirectContains('/kasir/receipt/');

        $pesanan = DB::table('pesanan')->orderByDesc('id_pesanan')->first();
        $this->assertEquals(30000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(10000.0, (float) $pesanan->diskon_nominal);
        $this->assertEquals(20000.0, (float) $pesanan->total_harga);
    }

    public function test_checkout_applies_multiple_custom_options_surcharge(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Food',
            'deskripsi' => 'Makanan',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Rice Bowl',
            'harga' => 20000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 20,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
            'custom_option_groups' => json_encode([
                [
                    'id' => 'pedas',
                    'label' => 'Level Pedas',
                    'required' => true,
                    'options' => [
                        ['value' => 'normal', 'label' => 'Normal', 'extra_price' => 0],
                        ['value' => 'extra', 'label' => 'Extra Pedas', 'extra_price' => 2000],
                    ],
                ],
                [
                    'id' => 'topping',
                    'label' => 'Topping',
                    'required' => true,
                    'options' => [
                        ['value' => 'none', 'label' => 'Tanpa Topping', 'extra_price' => 0],
                        ['value' => 'egg', 'label' => 'Telur', 'extra_price' => 3000],
                    ],
                ],
            ]),
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Multi Opsi',
            'jabatan' => 'Kasir',
            'no_telepon' => '0817',
        ], 'id_karyawan');

        $this->actingAsKasirWithShift($user);

        $this->post(route('kasir.preview'), [
            'items' => [
                [
                    'id_produk' => $produkId,
                    'qty' => 1,
                    'custom_options' => [
                        'pedas' => 'extra',
                        'topping' => 'egg',
                    ],
                ],
            ],
        ])->assertRedirect(route('kasir.checkout_page'));

        $this->post(route('kasir.checkout_submit'), [
            'id_karyawan' => $karyawanId,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 50000,
        ])->assertRedirectContains('/kasir/receipt/');

        $pesanan = DB::table('pesanan')->latest('id_pesanan')->first();
        $detail = DB::table('detail_pesanan')->where('id_pesanan', $pesanan->id_pesanan)->first();

        $this->assertEquals(25000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(25000.0, (float) $pesanan->total_harga);
        $this->assertEquals(25000.0, (float) $detail->harga_satuan);
        $this->assertStringContainsString('"pedas":"extra"', (string) $detail->selected_options);
        $this->assertStringContainsString('"topping":"egg"', (string) $detail->selected_options);
    }

    public function test_preview_fails_when_required_custom_option_is_missing(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Food',
            'deskripsi' => 'Makanan',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Chicken Wrap',
            'harga' => 18000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
            'custom_option_groups' => json_encode([
                [
                    'id' => 'saos',
                    'label' => 'Saos',
                    'required' => true,
                    'options' => [
                        ['value' => 'original', 'label' => 'Original', 'extra_price' => 0],
                        ['value' => 'extra', 'label' => 'Extra Saos', 'extra_price' => 2000],
                    ],
                ],
            ]),
        ], 'id_produk');

        $this->actingAsKasirWithShift($user);

        $this->from(route('kasir.index'))
            ->post(route('kasir.preview'), [
                'items' => [
                    [
                        'id_produk' => $produkId,
                        'qty' => 1,
                        'custom_options' => [],
                    ],
                ],
            ])
            ->assertRedirect(route('kasir.index'))
            ->assertSessionHasErrors('items');
    }

    public function test_transaction_detail_page_still_works_after_product_options_change(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Food',
            'deskripsi' => 'Makanan',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Potato Wedges',
            'harga' => 22000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 15,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
            'custom_option_groups' => json_encode([
                [
                    'id' => 'pedas',
                    'label' => 'Pedas',
                    'required' => true,
                    'options' => [
                        ['value' => 'sauce', 'label' => 'Sauce', 'extra_price' => 0],
                        ['value' => 'lombok_kecap', 'label' => 'Lombok Kecap', 'extra_price' => 2000],
                    ],
                ],
            ]),
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Riwayat',
            'jabatan' => 'Kasir',
            'no_telepon' => '0818',
        ], 'id_karyawan');

        $pesananId = DB::table('pesanan')->insertGetId([
            'id_pelanggan' => null,
            'id_karyawan' => $karyawanId,
            'total_harga' => 24000,
            'subtotal_harga' => 24000,
            'diskon_nominal' => 0,
            'diskon_nama' => null,
            'diskon_tipe' => null,
            'diskon_nilai' => null,
            'waktu_pembayaran' => now(),
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'lunas',
            'offline_ref' => null,
        ], 'id_pesanan');

        DB::table('detail_pesanan')->insert([
            'id_pesanan' => $pesananId,
            'id_produk' => $produkId,
            'jumlah' => 1,
            'harga_satuan' => 24000,
            'temperature' => null,
            'sugar_level' => null,
            'cup_size' => null,
            'spicy_level' => null,
            'selected_options' => json_encode(['pedas' => 'lombok_kecap']),
        ]);

        DB::table('produk')->where('id_produk', $produkId)->update([
            'custom_option_groups' => json_encode([
                [
                    'id' => 'pedas',
                    'label' => 'Pedas',
                    'required' => true,
                    'options' => [
                        ['value' => 'sauce', 'label' => 'Sauce', 'extra_price' => 0],
                    ],
                ],
            ]),
        ]);

        $this->actingAsKasirWithShift($user);

        $this->get(route('transaksi.show', ['transaksi' => $pesananId]))
            ->assertOk()
            ->assertSee('Lombok Kecap');
    }

    public function test_kasir_can_export_excel_report(): void
    {
        $kasir = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $this->actingAsKasirWithShift($kasir);

        $this->get(route('transaksi.export_excel'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
    }

    public function test_checkout_can_apply_promo_bundling_discount(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Bundling',
            'deskripsi' => 'Bundling test',
        ], 'id_kategori');

        $produkA = DB::table('produk')->insertGetId([
            'nama_produk' => 'Paket A',
            'harga' => 10000,
            'id_kategori' => $kategoriId,
            'stok' => 20,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $produkB = DB::table('produk')->insertGetId([
            'nama_produk' => 'Paket B',
            'harga' => 8000,
            'id_kategori' => $kategoriId,
            'stok' => 20,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $bundlingId = DB::table('promo_bundling')->insertGetId([
            'nama_promo' => 'Paket Hemat AB',
            'harga_bundle' => 15000,
            'status_aktif' => 1,
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
            'keterangan' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_promo_bundling');

        DB::table('promo_bundling_item')->insert([
            [
                'id_promo_bundling' => $bundlingId,
                'id_produk' => $produkA,
                'qty' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_promo_bundling' => $bundlingId,
                'id_produk' => $produkB,
                'qty' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Bundling',
            'jabatan' => 'Kasir',
            'no_telepon' => '0821',
        ], 'id_karyawan');

        $this->actingAsKasirWithShift($user);

        $this->post(route('kasir.preview'), [
            'items' => [
                ['id_produk' => $produkA, 'qty' => 2],
                ['id_produk' => $produkB, 'qty' => 2],
            ],
        ])->assertRedirect(route('kasir.checkout_page'));

        $this->post(route('kasir.checkout_submit'), [
            'id_karyawan' => $karyawanId,
            'id_promo_bundling' => $bundlingId,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 50000,
        ])->assertRedirectContains('/kasir/receipt/');

        $pesanan = DB::table('pesanan')->latest('id_pesanan')->first();

        $this->assertNotNull($pesanan);
        $this->assertEquals(36000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(6000.0, (float) $pesanan->diskon_nominal);
        $this->assertEquals(30000.0, (float) $pesanan->total_harga);
        $this->assertEquals('bundling', $pesanan->diskon_tipe);
        $this->assertEquals(18, (int) DB::table('produk')->where('id_produk', $produkA)->value('stok'));
        $this->assertEquals(18, (int) DB::table('produk')->where('id_produk', $produkB)->value('stok'));
    }

    public function test_bundling_selected_in_phase_one_is_applied_in_checkout_submit(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Bundling Flow',
            'deskripsi' => 'Bundling flow test',
        ], 'id_kategori');

        $produkA = DB::table('produk')->insertGetId([
            'nama_produk' => 'Flow A',
            'harga' => 10000,
            'id_kategori' => $kategoriId,
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $produkB = DB::table('produk')->insertGetId([
            'nama_produk' => 'Flow B',
            'harga' => 8000,
            'id_kategori' => $kategoriId,
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $bundlingId = DB::table('promo_bundling')->insertGetId([
            'nama_promo' => 'Flow Bundle AB',
            'harga_bundle' => 15000,
            'status_aktif' => 1,
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
            'keterangan' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_promo_bundling');

        DB::table('promo_bundling_item')->insert([
            [
                'id_promo_bundling' => $bundlingId,
                'id_produk' => $produkA,
                'qty' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_promo_bundling' => $bundlingId,
                'id_produk' => $produkB,
                'qty' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Flow Bundling',
            'jabatan' => 'Kasir',
            'no_telepon' => '0822',
        ], 'id_karyawan');

        $this->actingAsKasirWithShift($user);

        $this->post(route('kasir.preview'), [
            'items' => [
                ['id_produk' => $produkA, 'qty' => 1],
                ['id_produk' => $produkB, 'qty' => 1],
            ],
            'id_promo_bundling' => $bundlingId,
        ])->assertRedirect(route('kasir.checkout_page'));

        $this->assertEquals($bundlingId, session('checkout.id_promo_bundling'));

        $this->get(route('kasir.checkout_page'))
            ->assertOk()
            ->assertViewHas('selectedBundling', fn ($bundle) => (int) ($bundle?->id_promo_bundling ?? 0) === (int) $bundlingId);

        $this->post(route('kasir.checkout_submit'), [
            'id_karyawan' => $karyawanId,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 50000,
        ])->assertRedirectContains('/kasir/receipt/');

        $pesanan = DB::table('pesanan')->latest('id_pesanan')->first();

        $this->assertNotNull($pesanan);
        $this->assertEquals('bundling', $pesanan->diskon_tipe);
        $this->assertEquals(18000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(3000.0, (float) $pesanan->diskon_nominal);
        $this->assertEquals(15000.0, (float) $pesanan->total_harga);
        $this->assertEquals(9, (int) DB::table('produk')->where('id_produk', $produkA)->value('stok'));
        $this->assertEquals(9, (int) DB::table('produk')->where('id_produk', $produkB)->value('stok'));
    }

    public function test_checkout_can_apply_special_category_price_discount(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite enum check constraint belum mendukung tipe diskon harga_kategori.');
        }

        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriEspresso = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Espresso Based',
            'deskripsi' => 'Espresso based drinks',
        ], 'id_kategori');

        $kategoriLain = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Tea',
            'deskripsi' => 'Tea drinks',
        ], 'id_kategori');

        $produkEspresso = DB::table('produk')->insertGetId([
            'nama_produk' => 'Latte',
            'harga' => 15000,
            'id_kategori' => $kategoriEspresso,
            'stok' => 20,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $produkLain = DB::table('produk')->insertGetId([
            'nama_produk' => 'Lemon Tea',
            'harga' => 10000,
            'id_kategori' => $kategoriLain,
            'stok' => 20,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $diskonId = DB::table('diskon')->insertGetId([
            'nama_diskon' => '12.12 Espresso 12rb',
            'tipe_diskon' => 'harga_kategori',
            'nilai_diskon' => 12000,
            'minimal_belanja' => 0,
            'maksimal_diskon' => null,
            'id_kategori_target' => $kategoriEspresso,
            'harga_spesial' => 12000,
            'status_aktif' => 1,
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
            'keterangan' => null,
        ], 'id_diskon');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Harga Khusus',
            'jabatan' => 'Kasir',
            'no_telepon' => '0823',
        ], 'id_karyawan');

        $this->actingAsKasirWithShift($user);

        $this->post(route('kasir.preview'), [
            'items' => [
                ['id_produk' => $produkEspresso, 'qty' => 2],
                ['id_produk' => $produkLain, 'qty' => 1],
            ],
        ])->assertRedirect(route('kasir.checkout_page'));

        $this->post(route('kasir.checkout_submit'), [
            'id_karyawan' => $karyawanId,
            'id_diskon' => $diskonId,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 50000,
        ])->assertRedirectContains('/kasir/receipt/');

        $pesanan = DB::table('pesanan')->latest('id_pesanan')->first();

        $this->assertNotNull($pesanan);
        $this->assertEquals(40000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(6000.0, (float) $pesanan->diskon_nominal);
        $this->assertEquals(34000.0, (float) $pesanan->total_harga);
        $this->assertEquals('harga_kategori', $pesanan->diskon_tipe);
    }

    public function test_checkout_nominal_discount_can_target_specific_category_only(): void
    {
        $user = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $kategoriMakanan = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Makanan',
            'deskripsi' => 'Food',
        ], 'id_kategori');

        $kategoriMinuman = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Minuman',
            'deskripsi' => 'Drink',
        ], 'id_kategori');

        $produkMakanan = DB::table('produk')->insertGetId([
            'nama_produk' => 'Potato Wedges',
            'harga' => 20000,
            'id_kategori' => $kategoriMakanan,
            'stok' => 20,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $produkMinuman = DB::table('produk')->insertGetId([
            'nama_produk' => 'Americano',
            'harga' => 15000,
            'id_kategori' => $kategoriMinuman,
            'stok' => 20,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $diskonId = DB::table('diskon')->insertGetId([
            'nama_diskon' => 'Potongan Makanan',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 5000,
            'minimal_belanja' => 0,
            'maksimal_diskon' => null,
            'id_kategori_target' => $kategoriMakanan,
            'harga_spesial' => null,
            'status_aktif' => 1,
            'tanggal_mulai' => null,
            'tanggal_selesai' => null,
            'keterangan' => null,
        ], 'id_diskon');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Kategori Nominal',
            'jabatan' => 'Kasir',
            'no_telepon' => '0824',
        ], 'id_karyawan');

        $this->actingAsKasirWithShift($user);

        $this->post(route('kasir.preview'), [
            'items' => [
                ['id_produk' => $produkMakanan, 'qty' => 1],
                ['id_produk' => $produkMinuman, 'qty' => 1],
            ],
        ])->assertRedirect(route('kasir.checkout_page'));

        $this->post(route('kasir.checkout_submit'), [
            'id_karyawan' => $karyawanId,
            'id_diskon' => $diskonId,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 50000,
        ])->assertRedirectContains('/kasir/receipt/');

        $pesanan = DB::table('pesanan')->latest('id_pesanan')->first();

        $this->assertNotNull($pesanan);
        $this->assertEquals(35000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(5000.0, (float) $pesanan->diskon_nominal);
        $this->assertEquals(30000.0, (float) $pesanan->total_harga);
        $this->assertEquals('nominal', $pesanan->diskon_tipe);
    }

    public function test_admin_kasir_page_renders_product_picker_script(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Minuman kopi',
        ], 'id_kategori');

        DB::table('produk')->insert([
            'nama_produk' => 'Latte Admin',
            'harga' => 18000,
            'id_kategori' => $kategoriId,
            'stok' => 12,
            'is_temperature_enabled' => 1,
            'is_sugar_enabled' => 1,
            'is_cup_size_enabled' => 1,
            'is_spicy_enabled' => 0,
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.kasir.index'))
            ->assertOk()
            ->assertSee("const cards = Array.from(document.querySelectorAll('.js-product-card'));", false)
            ->assertSee("const card = document.querySelector('.shift-flip-card');", false)
            ->assertSee(route('admin.kasir.preview'), false);
    }

    public function test_admin_can_checkout_from_admin_kasir_flow(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'paper_preference' => '80',
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Minuman kopi',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Manual Brew Admin',
            'harga' => 22000,
            'id_kategori' => $kategoriId,
            'deskripsi' => 'Test',
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $this->actingAs($admin);

        $this->post(route('admin.kasir.preview'), [
            'items' => [
                [
                    'id_produk' => $produkId,
                    'qty' => 2,
                ],
            ],
        ])->assertRedirect(route('admin.kasir.checkout_page'));

        $this->get(route('admin.kasir.checkout_page'))
            ->assertOk()
            ->assertSee('ADMIN (otomatis)')
            ->assertSee('Manual Brew Admin');

        $this->post(route('admin.kasir.checkout_submit'), [
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => 50000,
            'catatan_pesanan' => 'Tes admin kasir',
        ])->assertRedirectContains('/kasir/receipt/');

        $pesanan = DB::table('pesanan')->latest('id_pesanan')->first();
        $detail = DB::table('detail_pesanan')->where('id_pesanan', $pesanan->id_pesanan)->first();
        $pelanggan = DB::table('pelanggan')->where('id_pelanggan', $pesanan->id_pelanggan)->first();

        $this->assertNotNull($pesanan);
        $this->assertNotNull($detail);
        $this->assertNotNull($pelanggan);
        $this->assertNull($pesanan->id_karyawan);
        $this->assertEquals('ADMIN', $pesanan->kasir_label);
        $this->assertEquals('Admin', $pelanggan->nama);
        $this->assertEquals('cash', $pesanan->metode_pembayaran);
        $this->assertEquals('lunas', $pesanan->status_pembayaran);
        $this->assertEquals(44000.0, (float) $pesanan->subtotal_harga);
        $this->assertEquals(44000.0, (float) $pesanan->total_harga);
        $this->assertEquals(8, (int) DB::table('produk')->where('id_produk', $produkId)->value('stok'));
    }

    private function actingAsKasirWithShift(User $user): void
    {
        $this->actingAs($user);

        KasirShiftSession::query()->create([
            'user_id' => (int) $user->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }
}

