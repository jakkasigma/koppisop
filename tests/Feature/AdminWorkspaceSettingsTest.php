<?php

namespace Tests\Feature;

use App\Models\KasirShiftSession;
use App\Models\StrukSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminWorkspaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_workspace_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('dashboard.workspace'))
            ->assertOk()
            ->assertSee('Ruang Kerja Admin');
    }

    public function test_admin_can_update_operational_and_payment_method_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('dashboard.workspace.update'), [
                'operasional_reset_hour' => 6,
                'active_shift_count' => 3,
                'enable_keuangan_menu' => 1,
                'enable_tax' => 1,
                'tax_percent' => 10,
                'tax_mode' => 'produk',
                'enable_payment_cash' => 1,
                'enable_payment_qris' => 1,
            ])
            ->assertRedirect(route('dashboard.workspace'))
            ->assertSessionHas('success');

        $setting = StrukSetting::current();
        $this->assertSame(6, (int) $setting->operasional_reset_hour);
        $this->assertSame(3, (int) $setting->active_shift_count);
        $this->assertTrue((bool) $setting->enable_tax);
        $this->assertSame(10.0, (float) $setting->tax_percent);
        $this->assertSame('produk', (string) $setting->tax_mode);
        $this->assertTrue((bool) $setting->enable_payment_cash);
        $this->assertTrue((bool) $setting->enable_payment_qris);
        $this->assertFalse((bool) $setting->enable_payment_debit);
        $this->assertTrue((bool) $setting->enable_keuangan_menu);
    }

    public function test_disabled_payment_method_is_hidden_and_rejected_in_checkout(): void
    {
        $kasir = User::factory()->create([
            'role' => 'kasir',
            'paper_preference' => '80',
        ]);

        $setting = StrukSetting::current();
        $setting->update([
            'enable_payment_cash' => true,
            'enable_payment_qris' => true,
            'enable_payment_debit' => false,
        ]);

        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Minuman',
            'deskripsi' => 'Tes',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Americano',
            'harga' => 10000,
            'id_kategori' => $kategoriId,
            'stok' => 10,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Tes',
            'jabatan' => 'Kasir',
            'no_telepon' => '0812',
        ], 'id_karyawan');

        $this->actingAs($kasir);
        KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        $this->post(route('kasir.preview'), [
            'items' => [
                ['id_produk' => $produkId, 'qty' => 1],
            ],
        ])->assertRedirect(route('kasir.checkout_page'));

        $this->get(route('kasir.checkout_page'))
            ->assertOk()
            ->assertDontSee('value="debit"', false);

        $this->post(route('kasir.checkout_submit'), [
            'id_karyawan' => $karyawanId,
            'metode_pembayaran' => 'debit',
        ])
            ->assertSessionHasErrors('metode_pembayaran');
    }

    public function test_keuangan_menu_can_be_disabled_from_workspace(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('dashboard.workspace.update'), [
                'operasional_reset_hour' => 6,
                'active_shift_count' => 2,
                'setoran_interval_days' => 7,
                'enable_payment_cash' => 1,
                'enable_payment_qris' => 1,
                'enable_payment_debit' => 1,
                // enable_keuangan_menu intentionally omitted -> false
            ])
            ->assertRedirect(route('dashboard.workspace'));

        $setting = StrukSetting::current();
        $this->assertFalse((bool) $setting->enable_keuangan_menu);

        $this->actingAs($admin)
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertDontSee(route('dashboard.keuangan'), false);

        $this->actingAs($admin)
            ->get(route('dashboard.keuangan'))
            ->assertNotFound();
    }
}
