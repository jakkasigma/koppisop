<?php

namespace Tests\Feature;

use App\Models\StrukSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardStatistikTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistik_uses_operational_day_boundary_for_date_filter(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-20 12:00:00'));
        StrukSetting::current()->update(['operasional_reset_hour' => 4]);

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawanId = $this->createKaryawan();

        // Keluar periode operasional 19/02 (sebelum jam reset).
        $this->createPesanan($karyawanId, 10000, 'cash', '2026-02-19 03:59:00');
        // Masuk periode operasional 19/02.
        $this->createPesanan($karyawanId, 12000, 'cash', '2026-02-19 04:00:00');
        $this->createPesanan($karyawanId, 18000, 'qris', '2026-02-20 03:59:00');
        // Keluar periode operasional 19/02 (hari berikutnya setelah reset).
        $this->createPesanan($karyawanId, 25000, 'debit', '2026-02-20 04:00:00');

        $this->actingAs($admin)
            ->get(route('dashboard.statistik', [
                'tanggal_awal' => '2026-02-19',
                'tanggal_akhir' => '2026-02-19',
            ]))
            ->assertOk()
            ->assertViewHas('transaksiTotal', 2)
            ->assertViewHas('omzetTotal', 30000.0)
            ->assertViewHas('paymentBreakdown', function ($breakdown): bool {
                return (float) ($breakdown['cash']['total'] ?? 0) === 12000.0
                    && (float) ($breakdown['qris']['total'] ?? 0) === 18000.0
                    && (float) ($breakdown['debit']['total'] ?? 0) === 0.0;
            });

        Carbon::setTestNow();
    }

    public function test_statistik_daily_series_contains_payment_method_values(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-17 10:00:00'));
        StrukSetting::current()->update(['operasional_reset_hour' => 3]);

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawanId = $this->createKaryawan();

        $this->createPesanan($karyawanId, 15000, 'cash', '2026-02-16 12:00:00');
        $this->createPesanan($karyawanId, 9000, 'qris', '2026-02-16 13:00:00');
        $this->createPesanan($karyawanId, 21000, 'debit', '2026-02-17 01:00:00');

        $this->actingAs($admin)
            ->get(route('dashboard.statistik'))
            ->assertOk()
            ->assertViewHas('dailySeries', function ($series): bool {
                $cashNonZero = collect($series['cash'] ?? [])->where('omzet', '>', 0)->count();
                $qrisNonZero = collect($series['qris'] ?? [])->where('omzet', '>', 0)->count();
                $debitNonZero = collect($series['debit'] ?? [])->where('omzet', '>', 0)->count();

                return $cashNonZero > 0 && $qrisNonZero > 0 && $debitNonZero > 0;
            });

        Carbon::setTestNow();
    }

    public function test_statistik_export_excel_returns_expected_sections(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-17 11:00:00'));
        StrukSetting::current()->update(['operasional_reset_hour' => 3]);

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawanId = $this->createKaryawan();

        $this->createPesanan($karyawanId, 20000, 'cash', '2026-02-17 08:30:00', [
            'diskon_nominal' => 5000,
            'diskon_nama' => 'Promo Pagi',
            'diskon_tipe' => 'nominal',
            'subtotal_harga' => 25000,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.statistik.export_excel', [
                'tanggal_awal' => '2026-02-17',
                'tanggal_akhir' => '2026-02-17',
            ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Laporan Statistik Cafe', $content);
        $this->assertStringContainsString('KPI Ringkasan', $content);
        $this->assertStringContainsString('Performa Promo', $content);
        $this->assertStringContainsString('Promo Pagi', $content);

        Carbon::setTestNow();
    }

    private function createKaryawan(): int
    {
        return DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Kasir Statistik',
            'jabatan' => 'Kasir',
            'no_telepon' => '0812345678',
        ], 'id_karyawan');
    }

    private function createPesanan(int $karyawanId, float $total, string $metode, string $waktu, array $overrides = []): int
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

