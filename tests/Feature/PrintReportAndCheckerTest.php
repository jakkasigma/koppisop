<?php

namespace Tests\Feature;

use App\Models\KasirShiftSession;
use App\Models\StrukSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrintReportAndCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_can_open_checker_print_page(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        [$karyawanId, $produkId] = $this->seedBasicData();

        $pesananId = $this->createPesanan($karyawanId, 'cash', 30000, '2026-02-17 11:00:00');
        DB::table('detail_pesanan')->insert([
            'id_pesanan' => $pesananId,
            'id_produk' => $produkId,
            'jumlah' => 1,
            'harga_satuan' => 30000,
            'temperature' => 'ice',
            'sugar_level' => 'normal',
        ]);

        $this->actingAsKasirWithShift($kasir)
            ->get(route('kasir.checker', ['transaksi' => $pesananId]))
            ->assertOk()
            ->assertSee('CHECKER ORDER')
            ->assertSee('Americano');
    }

    public function test_receipt_does_not_render_autoprint_script_when_disabled_in_setting(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);
        [$karyawanId, $produkId] = $this->seedBasicData();

        StrukSetting::current()->update(['auto_print_checker' => false]);

        $pesananId = $this->createPesanan($karyawanId, 'cash', 15000, '2026-02-17 12:00:00');
        DB::table('detail_pesanan')->insert([
            'id_pesanan' => $pesananId,
            'id_produk' => $produkId,
            'jumlah' => 1,
            'harga_satuan' => 15000,
        ]);

        $this->actingAsKasirWithShift($kasir)
            ->withSession(['auto_print_checker' => true])
            ->get(route('kasir.receipt', ['transaksi' => $pesananId]))
            ->assertOk()
            ->assertDontSee('checker-print-frame')
            ->assertDontSee('embedded=1', false);
    }

    public function test_receipt_renders_embedded_checker_autoprint_when_enabled(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir', 'paper_preference' => '80']);
        [$karyawanId, $produkId] = $this->seedBasicData();

        StrukSetting::current()->update(['auto_print_checker' => true]);

        $pesananId = $this->createPesanan($karyawanId, 'cash', 15000, '2026-02-17 12:30:00');
        DB::table('detail_pesanan')->insert([
            'id_pesanan' => $pesananId,
            'id_produk' => $produkId,
            'jumlah' => 1,
            'harga_satuan' => 15000,
        ]);

        $this->actingAsKasirWithShift($kasir)
            ->withSession(['auto_print_checker' => true])
            ->get(route('kasir.receipt', ['transaksi' => $pesananId]))
            ->assertOk()
            ->assertSee('checker-print-frame')
            ->assertSee('embedded=1', false);
    }

    public function test_kasir_can_open_nota_print_page(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir', 'paper_preference' => '80']);
        [$karyawanId, $produkId] = $this->seedBasicData();

        $pesananId = $this->createPesanan($karyawanId, 'cash', 30000, '2026-02-17 11:00:00');
        DB::table('detail_pesanan')->insert([
            'id_pesanan' => $pesananId,
            'id_produk' => $produkId,
            'jumlah' => 2,
            'harga_satuan' => 15000,
            'temperature' => 'ice',
            'sugar_level' => 'less',
            'selected_options' => json_encode(['extra_shot']),
        ]);

        $this->actingAsKasirWithShift($kasir)
            ->get(route('kasir.nota', ['transaksi' => $pesananId, 'autoprint' => 1]))
            ->assertOk()
            ->assertSee('Nota Pembayaran')
            ->assertSee('Americano')
            ->assertSee('Less Sugar')
            ->assertSee('Extra Shot')
            ->assertSee('window.print();', false);
    }

    public function test_admin_can_open_old_transaction_nota_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'paper_preference' => '80']);
        [$karyawanId, $produkId] = $this->seedBasicData();
        $kategoriId = (int) DB::table('produk')->where('id_produk', $produkId)->value('id_kategori');

        $diskonId = DB::table('diskon')->insertGetId([
            'nama_diskon' => 'Menu Pagi',
            'tipe_diskon' => 'nominal',
            'nilai_diskon' => 3000,
            'minimal_belanja' => 0,
            'maksimal_diskon' => null,
            'id_kategori_target' => $kategoriId,
            'harga_spesial' => null,
            'status_aktif' => 1,
            'tanggal_mulai' => '2025-07-28',
            'tanggal_selesai' => '2025-08-31',
            'keterangan' => 'Promo untuk transaksi lama',
        ], 'id_diskon');

        $pesananId = $this->createPesanan($karyawanId, 'cash', 27000, '2025-08-01 07:26:29', [
            'id_diskon' => $diskonId,
            'subtotal_harga' => 30000,
            'diskon_nominal' => 3000,
            'diskon_nama' => 'Menu Pagi',
            'diskon_tipe' => 'nominal',
            'diskon_nilai' => 3000,
        ]);

        DB::table('detail_pesanan')->insert([
            'id_pesanan' => $pesananId,
            'id_produk' => $produkId,
            'jumlah' => 2,
            'harga_satuan' => 15000,
            'temperature' => 'ice',
            'sugar_level' => 'normal',
        ]);

        $this->actingAs($admin)
            ->get(route('transaksi.nota', ['transaksi' => $pesananId, 'autoprint' => 1]))
            ->assertOk()
            ->assertSee('Nota Pembayaran')
            ->assertSee('Americano')
            ->assertSee('Promo Menu Pagi')
            ->assertSee('POTONGAN')
            ->assertSee('window.print();', false);
    }

    private function actingAsKasirWithShift(User $kasir): self
    {
        $this->actingAs($kasir);

        KasirShiftSession::query()->create([
            'user_id' => (int) $kasir->id,
            'shift_ke' => 1,
            'kas_awal' => 100000,
            'started_at' => now(),
            'ended_at' => null,
        ]);

        return $this;
    }

    private function seedBasicData(): array
    {
        $kategoriId = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Coffee',
            'deskripsi' => 'Test',
        ], 'id_kategori');

        $produkId = DB::table('produk')->insertGetId([
            'nama_produk' => 'Americano',
            'harga' => 15000,
            'id_kategori' => $kategoriId,
            'stok' => 100,
            'is_temperature_enabled' => 0,
            'is_sugar_enabled' => 0,
            'is_cup_size_enabled' => 0,
            'is_spicy_enabled' => 0,
        ], 'id_produk');

        $karyawanId = DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Print',
            'jabatan' => 'Kasir',
            'no_telepon' => '0811000222',
        ], 'id_karyawan');

        return [$karyawanId, $produkId];
    }

    private function createPesanan(int $karyawanId, string $metode, float $total, string $waktu, array $overrides = []): int
    {
        return DB::table('pesanan')->insertGetId(array_merge([
            'id_pelanggan' => null,
            'id_karyawan' => $karyawanId,
            'id_diskon' => null,
            'subtotal_harga' => $total,
            'diskon_nominal' => 0,
            'diskon_nama' => null,
            'diskon_tipe' => null,
            'diskon_nilai' => null,
            'total_harga' => $total,
            'waktu_pembayaran' => $waktu,
            'metode_pembayaran' => $metode,
            'status_pembayaran' => 'lunas',
            'offline_ref' => null,
        ], $overrides), 'id_pesanan');
    }
}
