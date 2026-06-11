<?php

namespace Tests\Feature;

use App\Models\KasirShiftSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KasirShiftClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_can_close_shift_and_store_summary_snapshot(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $shift = KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        DB::table('pesanan')->insert([
            [
                'id_pelanggan' => null,
                'id_karyawan' => null,
                'id_diskon' => null,
                'subtotal_harga' => 20000,
                'diskon_nominal' => 0,
                'diskon_nama' => null,
                'diskon_tipe' => null,
                'diskon_nilai' => null,
                'total_harga' => 20000,
                'waktu_pembayaran' => now()->subMinutes(30),
                'metode_pembayaran' => 'cash',
                'status_pembayaran' => 'lunas',
                'offline_ref' => null,
            ],
            [
                'id_pelanggan' => null,
                'id_karyawan' => null,
                'id_diskon' => null,
                'subtotal_harga' => 15000,
                'diskon_nominal' => 0,
                'diskon_nama' => null,
                'diskon_tipe' => null,
                'diskon_nilai' => null,
                'total_harga' => 15000,
                'waktu_pembayaran' => now()->subMinutes(20),
                'metode_pembayaran' => 'qris',
                'status_pembayaran' => 'lunas',
                'offline_ref' => null,
            ],
        ]);

        $this->actingAs($kasir)
            ->post(route('kasir.shift.close.submit'), [
            ])
            ->assertRedirectContains('/kasir/shift/' . $shift->id . '/struk');

        $shift = $shift->fresh();
        $this->assertNotNull($shift->ended_at);
        $this->assertSame(2, (int) $shift->total_trx);
        $this->assertEquals(35000.0, (float) $shift->total_omzet);
        $this->assertEquals(20000.0, (float) $shift->total_cash);
        $this->assertEquals(15000.0, (float) $shift->total_qris);
        $this->assertEquals(0.0, (float) $shift->total_debit);
        $this->assertEquals(120000.0, (float) $shift->estimasi_kas_akhir);
        $this->assertNull($shift->kas_akhir_input);
    }

    public function test_kasir_index_requires_new_shift_after_shift_closed(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 2,
            'kas_awal' => 90000,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinute(),
        ]);

        $this->actingAs($kasir)
            ->get(route('kasir.index'))
            ->assertRedirect(route('kasir.shift.start'));
    }

    public function test_admin_can_open_shift_history_and_shift_struk(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kasir = User::factory()->create(['role' => 'kasir']);

        $shift = KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now()->subHours(4),
            'ended_at' => now()->subHours(1),
            'total_trx' => 5,
            'total_omzet' => 125000,
            'total_cash' => 70000,
            'total_qris' => 30000,
            'total_debit' => 25000,
            'estimasi_kas_akhir' => 170000,
            'kas_akhir_input' => 169000,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.shift_history'))
            ->assertOk()
            ->assertSee('Riwayat Shift Kasir')
            ->assertSee((string) $shift->id);

        $this->actingAs($admin)
            ->get(route('kasir.shift.struk', ['shift' => $shift->id]))
            ->assertOk()
            ->assertSee('LAPORAN TUTUP SHIFT');
    }

    public function test_shift_expense_reduces_estimated_cash_end(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        $shift = KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now()->subHour(),
            'ended_at' => null,
        ]);

        DB::table('pesanan')->insert([
            'id_pelanggan' => null,
            'id_karyawan' => null,
            'id_diskon' => null,
            'subtotal_harga' => 20000,
            'diskon_nominal' => 0,
            'diskon_nama' => null,
            'diskon_tipe' => null,
            'diskon_nilai' => null,
            'total_harga' => 20000,
            'waktu_pembayaran' => now()->subMinutes(20),
            'metode_pembayaran' => 'cash',
            'status_pembayaran' => 'lunas',
            'offline_ref' => null,
        ]);

        $this->actingAs($kasir)
            ->post(route('kasir.shift.expense.store'), [
                'nominal' => 5000,
                'keterangan' => 'Beli es batu',
            ])
            ->assertRedirect(route('kasir.shift.report'));

        $this->actingAs($kasir)
            ->post(route('kasir.shift.close.submit'))
            ->assertRedirectContains('/kasir/shift/' . $shift->id . '/struk');

        $shift = $shift->fresh();
        $this->assertEquals(5000.0, (float) $shift->total_pengeluaran);
        $this->assertEquals(115000.0, (float) $shift->estimasi_kas_akhir);
    }
}
