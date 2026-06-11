<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

$start = Carbon::create(2025, 8, 1, 0, 0, 0);
$end = Carbon::today();
$products = DB::table('produk')->select('id_produk', 'harga', 'id_kategori', 'nama_produk')->get()->values();
$karyawans = DB::table('karyawan')->pluck('id_karyawan')->values();
$pelanggans = DB::table('pelanggan')->pluck('id_pelanggan')->values();
$kasirUserId = (int) (DB::table('users')->where('role', 'kasir')->value('id') ?? DB::table('users')->value('id') ?? 0);
$setting = DB::table('struk_settings')->select('default_cash_float')->first();
$baseKas = (float) ($setting->default_cash_float ?? 200000);
if ($baseKas <= 0) {
    $baseKas = 200000;
}

if ($products->isEmpty() || $karyawans->isEmpty() || $kasirUserId <= 0) {
    echo "DATA_KURANG: produk/karyawan/user kosong" . PHP_EOL;
    exit(1);
}

DB::table('produk')->update(['stok' => 250000]);

$metodesWeighted = ['cash', 'cash', 'cash', 'qris', 'qris', 'debit'];
$inserted = 0;
$details = 0;
$expenseCount = 0;
$setoranCount = 0;
$shiftCount = 0;
$cashBox = $baseKas;
$threeDayCounter = 0;

DB::beginTransaction();
try {
    // Reset data simulasi sebelumnya.
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    DB::table('detail_pesanan')->delete();
    DB::table('kasir_shift_pengeluaran')->delete();
    DB::table('pesanan')->delete();
    DB::table('kas_setoran_audits')->delete();
    DB::table('kas_setoran')->delete();
    DB::table('kasir_shift_sessions')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
        $trx = rand(18, 34);
        $trxShift1 = (int) floor($trx * 0.42);
        $trxShift2 = $trx - $trxShift1;

        $shift1Start = $d->copy()->setTime(7, 0, 0);
        $shift1End = $d->copy()->setTime(15, 0, 0);
        $shift2Start = $d->copy()->setTime(15, 0, 0);
        $shift2End = $d->copy()->setTime(23, 0, 0);

        $shift1Id = (int) DB::table('kasir_shift_sessions')->insertGetId([
            'user_id' => $kasirUserId,
            'shift_ke' => 1,
            'kas_awal' => round($cashBox, 2),
            'started_at' => $shift1Start->format('Y-m-d H:i:s'),
            'ended_at' => $shift1End->format('Y-m-d H:i:s'),
            'total_trx' => 0,
            'total_omzet' => 0,
            'total_cash' => 0,
            'total_qris' => 0,
            'total_debit' => 0,
            'total_pengeluaran' => 0,
            'estimasi_kas_akhir' => round($cashBox, 2),
            'kas_akhir_input' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id');
        $shiftCount++;

        $shiftStat = [
            1 => ['trx' => 0, 'omzet' => 0.0, 'cash' => 0.0, 'qris' => 0.0, 'debit' => 0.0, 'expense' => 0.0, 'next_order' => 1],
            2 => ['trx' => 0, 'omzet' => 0.0, 'cash' => 0.0, 'qris' => 0.0, 'debit' => 0.0, 'expense' => 0.0, 'next_order' => 1],
        ];

        $insertShiftTransactions = function (int $shiftNo, int $count, int $shiftId, Carbon $minTime, Carbon $maxTime) use (
            &$products,
            &$pelanggans,
            &$karyawans,
            &$metodesWeighted,
            &$inserted,
            &$details,
            &$shiftStat
        ): void {
            for ($i = 0; $i < $count; $i++) {
                $maxMinute = (int) $minTime->diffInMinutes($maxTime);
                $randMinute = rand(0, max(1, $maxMinute - 1));
                $waktu = $minTime->copy()->addMinutes($randMinute)->setSecond(rand(0, 59));
                $lineCount = rand(1, 3);
                $subtotal = 0.0;
                $rows = [];

                for ($l = 0; $l < $lineCount; $l++) {
                    $useEspressoBias = rand(1, 100) <= 62;
                    $pool = $products;
                    if ($useEspressoBias) {
                        $espresso = $products->filter(function ($p) {
                            $name = strtolower((string) ($p->nama_produk ?? ''));
                            return str_contains($name, 'espresso')
                                || str_contains($name, 'latte')
                                || str_contains($name, 'americano')
                                || str_contains($name, 'cappuccino');
                        })->values();
                        if ($espresso->isNotEmpty()) {
                            $pool = $espresso;
                        }
                    }
                    $p = $pool[rand(0, $pool->count() - 1)];
                    $qty = rand(1, 3);
                    $harga = (float) $p->harga;
                    $rows[] = [
                        'id_produk' => (int) $p->id_produk,
                        'jumlah' => $qty,
                        'harga_satuan' => $harga,
                    ];
                    $subtotal += ($harga * $qty);
                }

                $diskonNominal = 0.0;
                if (rand(1, 100) <= 18) {
                    $diskonNominal = round($subtotal * (rand(5, 15) / 100), 2);
                    if ($diskonNominal > $subtotal) {
                        $diskonNominal = $subtotal;
                    }
                }

                $afterDiskon = max(0, $subtotal - $diskonNominal);
                $pajakPersen = 0.0;
                if (rand(1, 100) <= 30) {
                    $pajakPersen = [10.0, 11.0][rand(0, 1)];
                }
                $pajakNominal = round($afterDiskon * $pajakPersen / 100, 2);
                $total = $afterDiskon + $pajakNominal;

                $idPelanggan = null;
                if (!$pelanggans->isEmpty() && rand(1, 100) <= 50) {
                    $idPelanggan = (int) $pelanggans[rand(0, $pelanggans->count() - 1)];
                }

                $idKaryawan = (int) $karyawans[rand(0, $karyawans->count() - 1)];
                $metode = $metodesWeighted[rand(0, count($metodesWeighted) - 1)];

                $pesananId = DB::table('pesanan')->insertGetId([
                    'id_pelanggan' => $idPelanggan,
                    'id_karyawan' => $idKaryawan,
                    'kasir_shift_session_id' => $shiftId,
                    'no_urut_shift' => $shiftStat[$shiftNo]['next_order'],
                    'id_diskon' => null,
                    'subtotal_harga' => round($subtotal, 2),
                    'diskon_nominal' => round($diskonNominal, 2),
                    'diskon_nama' => $diskonNominal > 0 ? 'Promo Random' : null,
                    'diskon_tipe' => $diskonNominal > 0 ? 'nominal' : null,
                    'diskon_nilai' => $diskonNominal > 0 ? round($diskonNominal, 2) : null,
                    'pajak_persen' => $pajakPersen,
                    'pajak_nominal' => round($pajakNominal, 2),
                    'total_harga' => round($total, 2),
                    'waktu_pembayaran' => $waktu->format('Y-m-d H:i:s'),
                    'metode_pembayaran' => $metode,
                    'status_pembayaran' => 'lunas',
                    'offline_ref' => null,
                ], 'id_pesanan');

                foreach ($rows as $r) {
                    DB::table('detail_pesanan')->insert([
                        'id_pesanan' => (int) $pesananId,
                        'id_produk' => (int) $r['id_produk'],
                        'jumlah' => (int) $r['jumlah'],
                        'harga_satuan' => (float) $r['harga_satuan'],
                        'temperature' => null,
                        'sugar_level' => null,
                        'cup_size' => null,
                        'spicy_level' => null,
                        'selected_options' => null,
                    ]);
                    $details++;
                }

                $shiftStat[$shiftNo]['trx']++;
                $shiftStat[$shiftNo]['omzet'] += $total;
                $shiftStat[$shiftNo][$metode] += $total;
                $shiftStat[$shiftNo]['next_order']++;

                $inserted++;
                if ($inserted % 500 === 0) {
                    echo "progress={$inserted}" . PHP_EOL;
                }
            }
        };

        $insertShiftTransactions(1, $trxShift1, $shift1Id, $shift1Start, $shift1End);

        $shift1EstimasiAkhir = round($cashBox + $shiftStat[1]['cash'] - $shiftStat[1]['expense'], 2);
        DB::table('kasir_shift_sessions')->where('id', $shift1Id)->update([
            'total_trx' => $shiftStat[1]['trx'],
            'total_omzet' => round($shiftStat[1]['omzet'], 2),
            'total_cash' => round($shiftStat[1]['cash'], 2),
            'total_qris' => round($shiftStat[1]['qris'], 2),
            'total_debit' => round($shiftStat[1]['debit'], 2),
            'total_pengeluaran' => round($shiftStat[1]['expense'], 2),
            'estimasi_kas_akhir' => $shift1EstimasiAkhir,
            'updated_at' => now(),
        ]);
        $cashBox = $shift1EstimasiAkhir;

        $shift2Id = (int) DB::table('kasir_shift_sessions')->insertGetId([
            'user_id' => $kasirUserId,
            'shift_ke' => 2,
            'kas_awal' => round($cashBox, 2),
            'started_at' => $shift2Start->format('Y-m-d H:i:s'),
            'ended_at' => $shift2End->format('Y-m-d H:i:s'),
            'total_trx' => 0,
            'total_omzet' => 0,
            'total_cash' => 0,
            'total_qris' => 0,
            'total_debit' => 0,
            'total_pengeluaran' => 0,
            'estimasi_kas_akhir' => round($cashBox, 2),
            'kas_akhir_input' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id');
        $shiftCount++;

        $insertShiftTransactions(2, $trxShift2, $shift2Id, $shift2Start, $shift2End);

        // Pengeluaran rata-rata per 3 hari sekitar Rp100.000.
        if ($threeDayCounter % 3 === 0) {
            $expenseNominal = (float) rand(85000, 115000);
            DB::table('kasir_shift_pengeluaran')->insert([
                'kasir_shift_session_id' => $shift2Id,
                'user_id' => $kasirUserId,
                'nominal' => $expenseNominal,
                'keterangan' => 'Pengeluaran operasional simulasi',
                'pengeluaran_at' => $d->copy()->setTime(21, rand(0, 59), rand(0, 59))->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $shiftStat[2]['expense'] += $expenseNominal;
            $expenseCount++;
        }
        $threeDayCounter++;

        $shift2KasAwal = (float) DB::table('kasir_shift_sessions')->where('id', $shift2Id)->value('kas_awal');
        $shift2EstimasiAkhir = round($shift2KasAwal + $shiftStat[2]['cash'] - $shiftStat[2]['expense'], 2);
        DB::table('kasir_shift_sessions')->where('id', $shift2Id)->update([
            'total_trx' => $shiftStat[2]['trx'],
            'total_omzet' => round($shiftStat[2]['omzet'], 2),
            'total_cash' => round($shiftStat[2]['cash'], 2),
            'total_qris' => round($shiftStat[2]['qris'], 2),
            'total_debit' => round($shiftStat[2]['debit'], 2),
            'total_pengeluaran' => round($shiftStat[2]['expense'], 2),
            'estimasi_kas_akhir' => $shift2EstimasiAkhir,
            'updated_at' => now(),
        ]);
        $cashBox = $shift2EstimasiAkhir;

        // Isi laporan setoran setiap hari Minggu.
        if ($d->dayOfWeek === Carbon::SUNDAY) {
            $nominalSetor = max(0.0, $cashBox - $baseKas);
            if ($nominalSetor > 0) {
                $setoranId = (int) DB::table('kas_setoran')->insertGetId([
                    'tanggal_setor' => $d->copy()->setTime(23, 10, 0)->format('Y-m-d H:i:s'),
                    'nominal' => round($nominalSetor, 2),
                    'catatan' => 'Setoran mingguan otomatis (simulasi)',
                    'user_id' => $kasirUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'id');

                DB::table('kas_setoran_audits')->insert([
                    'setoran_id' => $setoranId,
                    'aksi' => 'buat_setoran',
                    'nominal_lama' => null,
                    'nominal_baru' => round($nominalSetor, 2),
                    'catatan_lama' => null,
                    'catatan_baru' => 'Setoran mingguan otomatis (simulasi)',
                    'meta' => json_encode(['sumber' => 'script_generate_dummy_transactions'], JSON_UNESCAPED_UNICODE),
                    'user_id' => $kasirUserId,
                    'dibuat_pada' => now(),
                ]);

                $cashBox = round($cashBox - $nominalSetor, 2);
                $setoranCount++;
            }
        }
    }

    DB::commit();
    echo "OK inserted={$inserted} details={$details} shifts={$shiftCount} expenses={$expenseCount} setoran_minggu={$setoranCount} kas_akhir=" . number_format($cashBox, 2, '.', '') . PHP_EOL;
} catch (Throwable $e) {
    DB::rollBack();
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
