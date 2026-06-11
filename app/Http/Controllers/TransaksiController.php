<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\StrukSetting;
use App\Services\NotaViewDataBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransaksiController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validateFilters($request);

        $query = Pesanan::with(['pelanggan', 'karyawan', 'shift'])
            ->orderByDesc('id_pesanan');

        $this->applyFilters($query, $filters);

        $pesanan = $query->paginate(20)->onEachSide(1)->withQueryString();

        return view('transaksi.index', [
            'pesanan' => $pesanan,
            'karyawan' => Karyawan::orderBy('nama_karyawan')->get(),
            'filters' => $filters,
            'operasionalQuickFilters' => [
                'today' => $this->buildOperationalQuickFilter('today'),
                'yesterday' => $this->buildOperationalQuickFilter('yesterday'),
            ],
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $filters = $this->validateFilters($request);
        $totalsQuery = DB::table('pesanan as p')
            ->selectRaw('COALESCE(SUM(p.total_harga),0) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'cash' THEN p.total_harga ELSE 0 END),0) as total_cash")
            ->selectRaw("COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'qris' THEN p.total_harga ELSE 0 END),0) as total_qris")
            ->selectRaw("COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'debit' THEN p.total_harga ELSE 0 END),0) as total_debit")
            ->selectRaw("COALESCE(SUM(CASE WHEN p.metode_pembayaran IN ('shopeefood','gofood','grabfood') THEN p.total_harga ELSE 0 END),0) as total_delivery")
            ->selectRaw("COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'shopeefood' THEN p.total_harga ELSE 0 END),0) as total_shopeefood")
            ->selectRaw("COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'gofood' THEN p.total_harga ELSE 0 END),0) as total_gofood")
            ->selectRaw("COALESCE(SUM(CASE WHEN p.metode_pembayaran = 'grabfood' THEN p.total_harga ELSE 0 END),0) as total_grabfood");
        $this->applyFiltersToTableQuery($totalsQuery, $filters, 'p.waktu_pembayaran', 'p.id_karyawan', 'p.kasir_label');
        $totals = $totalsQuery->first();

        $filename = 'laporan-transaksi-' . now()->format('Ymd-His') . '.xls';

        return response()->streamDownload(function () use ($filters, $totals): void {
            $total = (float) ($totals->total ?? 0);
            $tanggalAwal = $filters['tanggal_awal'] ?? '-';
            $tanggalAkhir = $filters['tanggal_akhir'] ?? '-';
            $kasir = '-';

            if (! empty($filters['id_karyawan'])) {
                $kasir = Karyawan::where('id_karyawan', $filters['id_karyawan'])->value('nama_karyawan') ?? '-';
            }

            echo '<html><head><meta charset="UTF-8">';
            echo '<style>';
            echo 'body{font-family:Arial,sans-serif;font-size:12px;}';
            echo 'h2{margin:0 0 8px 0;}';
            echo 'h3{margin:18px 0 8px 0;}';
            echo '.meta{margin:0 0 12px 0;line-height:1.6;}';
            echo 'table{border-collapse:collapse;width:100%;margin-bottom:14px;}';
            echo 'th,td{border:1px solid #444;padding:6px;}';
            echo 'th{background:#eaeaea;text-align:left;}';
            echo '.num{text-align:right;}';
            echo '.foot td{font-weight:bold;background:#f7f7f7;}';
            echo '</style></head><body>';

            echo '<h2>Laporan Transaksi</h2>';
            echo '<div class="meta">';
            echo 'Tanggal awal: ' . $this->e($tanggalAwal) . '<br>';
            echo 'Tanggal akhir: ' . $this->e($tanggalAkhir) . '<br>';
            echo 'Kasir: ' . $this->e($kasir) . '<br>';
            echo 'Dicetak: ' . $this->e(now()->format('Y-m-d H:i:s'));
            echo '</div>';

            echo '<h3>Ringkasan</h3>';
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>ID Pesanan</th>';
            echo '<th>Waktu</th>';
            echo '<th>Pelanggan</th>';
            echo '<th>Kasir</th>';
            echo '<th>Metode</th>';
            echo '<th>Status</th>';
            echo '<th>Total</th>';
            echo '</tr></thead><tbody>';

            $summaryRows = DB::table('pesanan as p')
                ->leftJoin('pelanggan as pl', 'pl.id_pelanggan', '=', 'p.id_pelanggan')
                ->leftJoin('karyawan as k', 'k.id_karyawan', '=', 'p.id_karyawan')
                ->select(
                    'p.id_pesanan',
                    'p.waktu_pembayaran',
                    'p.metode_pembayaran',
                    'p.status_pembayaran',
                    'p.total_harga',
                    'pl.nama as pelanggan_nama',
                    DB::raw("COALESCE(NULLIF(TRIM(p.kasir_label), ''), k.nama_karyawan) as kasir_nama")
                )
                ->orderBy('p.id_pesanan');
            $this->applyFiltersToTableQuery($summaryRows, $filters, 'p.waktu_pembayaran', 'p.id_karyawan', 'p.kasir_label');

            foreach ($summaryRows->cursor() as $item) {
                echo '<tr>';
                echo '<td>' . $this->e($item->id_pesanan) . '</td>';
                echo '<td>' . $this->e($item->waktu_pembayaran) . '</td>';
                echo '<td>' . $this->e($item->pelanggan_nama ?? 'Umum') . '</td>';
                echo '<td>' . $this->e($item->kasir_nama ?? '-') . '</td>';
                echo '<td>' . $this->e($item->metode_pembayaran) . '</td>';
                echo '<td>' . $this->e($item->status_pembayaran) . '</td>';
                echo '<td class="num">' . $this->e(number_format((float) $item->total_harga, 0, ',', '.')) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '<tfoot><tr class="foot">';
            echo '<td colspan="6">TOTAL</td>';
            echo '<td class="num">' . $this->e(number_format((float) $total, 0, ',', '.')) . '</td>';
            echo '</tr></tfoot>';
            echo '</table>';

            $isAppChannel = ($filters['channel'] ?? null) === 'app';
            $totalCash = (float) ($totals->total_cash ?? 0);
            $totalQris = (float) ($totals->total_qris ?? 0);
            $totalDebit = (float) ($totals->total_debit ?? 0);
            $totalShopee = (float) ($totals->total_shopeefood ?? 0);
            $totalGofood = (float) ($totals->total_gofood ?? 0);
            $totalGrab = (float) ($totals->total_grabfood ?? 0);
            $totalDelivery = (float) ($totals->total_delivery ?? 0);

            if ($isAppChannel) {
                $totalMetode = $totalShopee + $totalGofood + $totalGrab;
                $pctShopee = $totalMetode > 0 ? round(($totalShopee / $totalMetode) * 100, 2) : 0.0;
                $pctGofood = $totalMetode > 0 ? round(($totalGofood / $totalMetode) * 100, 2) : 0.0;
                $pctGrab = $totalMetode > 0 ? round(($totalGrab / $totalMetode) * 100, 2) : 0.0;

                echo '<h3>Pendapatan Per Metode Pembayaran (Aplikasi)</h3>';
                echo '<table>';
                echo '<thead><tr><th>Metode</th><th>Total Pendapatan</th><th>Kontribusi</th></tr></thead>';
                echo '<tbody>';
                echo '<tr><td>SHOPEEFOOD</td><td class="num">' . $this->e(number_format($totalShopee, 0, ',', '.')) . '</td><td class="num">' . $this->e(number_format($pctShopee, 2, ',', '.')) . '%</td></tr>';
                echo '<tr><td>GOFOOD</td><td class="num">' . $this->e(number_format($totalGofood, 0, ',', '.')) . '</td><td class="num">' . $this->e(number_format($pctGofood, 2, ',', '.')) . '%</td></tr>';
                echo '<tr><td>GRABFOOD</td><td class="num">' . $this->e(number_format($totalGrab, 0, ',', '.')) . '</td><td class="num">' . $this->e(number_format($pctGrab, 2, ',', '.')) . '%</td></tr>';
                echo '</tbody>';
                echo '<tfoot><tr class="foot"><td>TOTAL</td><td class="num">' . $this->e(number_format($totalMetode, 0, ',', '.')) . '</td><td class="num">100,00%</td></tr></tfoot>';
                echo '</table>';
            } else {
                $totalMetode = $totalCash + $totalQris + $totalDebit + $totalDelivery;
                $pctCash = $totalMetode > 0 ? round(($totalCash / $totalMetode) * 100, 2) : 0.0;
                $pctQris = $totalMetode > 0 ? round(($totalQris / $totalMetode) * 100, 2) : 0.0;
                $pctDebit = $totalMetode > 0 ? round(($totalDebit / $totalMetode) * 100, 2) : 0.0;
                $pctDelivery = $totalMetode > 0 ? round(($totalDelivery / $totalMetode) * 100, 2) : 0.0;

                echo '<h3>Pendapatan Per Metode Pembayaran</h3>';
                echo '<table>';
                echo '<thead><tr><th>Metode</th><th>Total Pendapatan</th><th>Kontribusi</th></tr></thead>';
                echo '<tbody>';
                echo '<tr><td>CASH</td><td class="num">' . $this->e(number_format($totalCash, 0, ',', '.')) . '</td><td class="num">' . $this->e(number_format($pctCash, 2, ',', '.')) . '%</td></tr>';
                echo '<tr><td>QRIS</td><td class="num">' . $this->e(number_format($totalQris, 0, ',', '.')) . '</td><td class="num">' . $this->e(number_format($pctQris, 2, ',', '.')) . '%</td></tr>';
                echo '<tr><td>DEBIT</td><td class="num">' . $this->e(number_format($totalDebit, 0, ',', '.')) . '</td><td class="num">' . $this->e(number_format($pctDebit, 2, ',', '.')) . '%</td></tr>';
                echo '</tbody>';
                echo '<tfoot><tr class="foot"><td>TOTAL</td><td class="num">' . $this->e(number_format($totalMetode, 0, ',', '.')) . '</td><td class="num">100,00%</td></tr></tfoot>';
                echo '</table>';
            }

            echo '<h3>Detail Item</h3>';
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>ID Pesanan</th>';
            echo '<th>Waktu</th>';
            echo '<th>Pelanggan</th>';
            echo '<th>Kasir</th>';
            echo '<th>Produk</th>';
            echo '<th>Opsi</th>';
            echo '<th>Qty</th>';
            echo '<th>Harga Satuan</th>';
            echo '<th>Subtotal</th>';
            echo '</tr></thead><tbody>';

            $detailRows = DB::table('detail_pesanan as dp')
                ->join('pesanan as p', 'p.id_pesanan', '=', 'dp.id_pesanan')
                ->leftJoin('produk as pr', 'pr.id_produk', '=', 'dp.id_produk')
                ->leftJoin('pelanggan as pl', 'pl.id_pelanggan', '=', 'p.id_pelanggan')
                ->leftJoin('karyawan as k', 'k.id_karyawan', '=', 'p.id_karyawan')
                ->select(
                    'p.id_pesanan',
                    'p.waktu_pembayaran',
                    'pl.nama as pelanggan_nama',
                    DB::raw("COALESCE(NULLIF(TRIM(p.kasir_label), ''), k.nama_karyawan) as kasir_nama"),
                    'pr.nama_produk as produk_nama',
                    'dp.temperature',
                    'dp.sugar_level',
                    'dp.cup_size',
                    'dp.spicy_level',
                    'dp.selected_options',
                    'dp.jumlah',
                    'dp.harga_satuan'
                )
                ->orderBy('p.id_pesanan')
                ->orderBy('dp.id_produk');
            $this->applyFiltersToTableQuery($detailRows, $filters, 'p.waktu_pembayaran', 'p.id_karyawan', 'p.kasir_label');

            foreach ($detailRows->cursor() as $detail) {
                $subtotalDetail = (float) $detail->harga_satuan * (int) $detail->jumlah;
                echo '<tr>';
                echo '<td>' . $this->e($detail->id_pesanan) . '</td>';
                echo '<td>' . $this->e($detail->waktu_pembayaran) . '</td>';
                echo '<td>' . $this->e($detail->pelanggan_nama ?? 'Umum') . '</td>';
                echo '<td>' . $this->e($detail->kasir_nama ?? '-') . '</td>';
                echo '<td>' . $this->e($detail->produk_nama ?? 'Produk dihapus') . '</td>';
                echo '<td>' . $this->e($this->buildDetailOptionsLabel($detail)) . '</td>';
                echo '<td class="num">' . $this->e((int) $detail->jumlah) . '</td>';
                echo '<td class="num">' . $this->e(number_format((float) $detail->harga_satuan, 0, ',', '.')) . '</td>';
                echo '<td class="num">' . $this->e(number_format($subtotalDetail, 0, ',', '.')) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            echo '</body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function show(Pesanan $transaksi): View
    {
        $transaksi->load(['pelanggan', 'karyawan', 'detail.produk', 'diskon.kategoriTarget', 'shift']);

        return view('transaksi.show', [
            'transaksi' => $transaksi,
        ]);
    }

    public function nota(Request $request, Pesanan $transaksi): View
    {
        $transaksi->load(['pelanggan', 'karyawan', 'detail.produk', 'diskon.kategoriTarget', 'shift']);
        $paper = $this->resolvePaperPreference($request);
        $setting = StrukSetting::current();

        return view('transaksi.nota', [
            'transaksi' => $transaksi,
            'kodeNota' => $this->buildPublicKodeNota($transaksi),
            'paper' => $paper,
            'autoprint' => (bool) $request->boolean('autoprint'),
            'paperDefault' => in_array((string) ($request->user()?->paper_preference ?? ''), ['58', '80'], true)
                ? (string) $request->user()->paper_preference
                : $paper,
            'strukSetting' => $setting,
            'nota' => app(NotaViewDataBuilder::class)->build(
                $transaksi,
                $setting,
                (string) ($request->user()?->role ?? 'kasir')
            ),
        ]);
    }

    public function batal(Pesanan $transaksi): RedirectResponse
    {
        if ($transaksi->status_pembayaran === 'dibatalkan') {
            return back()->withErrors(['transaksi' => 'Transaksi ini sudah dibatalkan sebelumnya.']);
        }

        DB::transaction(function () use ($transaksi): void {
            $locked = Pesanan::where('id_pesanan', $transaksi->id_pesanan)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status_pembayaran === 'dibatalkan') {
                return;
            }

            $details = $locked->detail()->get();

            $stokByProduk = [];
            foreach ($details as $detail) {
                $idProduk = (int) $detail->id_produk;
                $stokByProduk[$idProduk] = ($stokByProduk[$idProduk] ?? 0) + (int) $detail->jumlah;
            }

            if (! empty($stokByProduk)) {
                $produk = Produk::whereIn('id_produk', array_keys($stokByProduk))->lockForUpdate()->get()->keyBy('id_produk');

                foreach ($stokByProduk as $idProduk => $qty) {
                    $item = $produk->get($idProduk);
                    if ($item) {
                        $item->increment('stok', $qty);
                    }
                }
            }

            $locked->status_pembayaran = 'dibatalkan';
            $locked->save();
        });

        return redirect()->route('transaksi.show', $transaksi)->with('success', 'Transaksi berhasil dibatalkan dan stok dikembalikan.');
    }

    public function restore(Pesanan $transaksi): RedirectResponse
    {
        if ($transaksi->status_pembayaran !== 'dibatalkan') {
            return back()->withErrors(['transaksi' => 'Transaksi ini tidak dalam status dibatalkan.']);
        }

        DB::transaction(function () use ($transaksi): void {
            $locked = Pesanan::where('id_pesanan', $transaksi->id_pesanan)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status_pembayaran !== 'dibatalkan') {
                return;
            }

            $details = $locked->detail()->get();

            $stokByProduk = [];
            foreach ($details as $detail) {
                $idProduk = (int) $detail->id_produk;
                $stokByProduk[$idProduk] = ($stokByProduk[$idProduk] ?? 0) + (int) $detail->jumlah;
            }

            if (! empty($stokByProduk)) {
                $produk = Produk::whereIn('id_produk', array_keys($stokByProduk))->lockForUpdate()->get()->keyBy('id_produk');

                foreach ($stokByProduk as $idProduk => $qty) {
                    $item = $produk->get($idProduk);

                    if (! $item) {
                        throw ValidationException::withMessages([
                            'transaksi' => 'Produk transaksi tidak ditemukan.',
                        ]);
                    }

                    if ((int) $item->stok < $qty) {
                        throw ValidationException::withMessages([
                            'transaksi' => 'Stok produk ' . $item->nama_produk . ' tidak cukup untuk restore transaksi.',
                        ]);
                    }
                }

                foreach ($stokByProduk as $idProduk => $qty) {
                    $produk[$idProduk]->decrement('stok', $qty);
                }
            }

            $locked->status_pembayaran = 'lunas';
            $locked->save();
        });

        return redirect()->route('transaksi.show', $transaksi)->with('success', 'Transaksi berhasil direstore dan stok dikurangi kembali.');
    }

    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'tanggal_awal' => ['nullable', 'date'],
            'tanggal_akhir' => ['nullable', 'date'],
            'id_karyawan' => ['nullable'],
            'operasional' => ['nullable', 'in:today,yesterday'],
            'channel' => ['nullable', 'in:app'],
        ]);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $this->applyFiltersToTableQuery($query, $filters, 'waktu_pembayaran', 'id_karyawan');
    }

    private function applyFiltersToTableQuery(
        QueryBuilder|Builder $query,
        array $filters,
        string $dateColumn,
        string $karyawanColumn,
        string $kasirLabelColumn = 'kasir_label'
    ): void
    {
        $hasTanggalFilter = ! empty($filters['tanggal_awal']) || ! empty($filters['tanggal_akhir']);

        if ($hasTanggalFilter) {
            $tanggalAwal = (string) ($filters['tanggal_awal'] ?? $filters['tanggal_akhir']);
            $tanggalAkhir = (string) ($filters['tanggal_akhir'] ?? $filters['tanggal_awal']);
            [$start, $end] = $this->resolveOperationalRangeFromDates($tanggalAwal, $tanggalAkhir);
            $query->where($dateColumn, '>=', $start)
                ->where($dateColumn, '<', $end);
        } elseif (! empty($filters['operasional'])) {
            [$start, $end] = $this->resolveOperationalRange((string) $filters['operasional']);
            $query->where($dateColumn, '>=', $start)
                ->where($dateColumn, '<', $end);
        }

        if (! empty($filters['id_karyawan'])) {
            if ($filters['id_karyawan'] === 'admin') {
                $query->where(function ($subQuery) use ($kasirLabelColumn): void {
                    $subQuery->whereNotNull($kasirLabelColumn)
                        ->where($kasirLabelColumn, '<>', '');
                });
            } else {
                $query->where($karyawanColumn, $filters['id_karyawan']);
            }
        }

        if (! $hasTanggalFilter && ! empty($filters['operasional'])) {
            $query->where(function ($subQuery) use ($kasirLabelColumn): void {
                $subQuery->whereNull($kasirLabelColumn)
                    ->orWhere($kasirLabelColumn, '');
            });
        }

        $deliveryMethods = ['shopeefood', 'gofood', 'grabfood'];
        if (($filters['channel'] ?? null) === 'app') {
            $query->whereIn('metode_pembayaran', $deliveryMethods);
        } else {
            $query->whereNotIn('metode_pembayaran', $deliveryMethods);
        }
    }

    private function resolveOperationalRange(string $preset): array
    {
        $resetHour = $this->resolveOperationalResetHour();
        $base = now()->subHours($resetHour)->startOfDay();

        if ($preset === 'yesterday') {
            $base->subDay();
        }

        $start = $base->copy()->addHours($resetHour);
        $end = $start->copy()->addDay();

        return [$start, $end];
    }

    private function resolveOperationalRangeFromDates(string $tanggalAwal, string $tanggalAkhir): array
    {
        $resetHour = $this->resolveOperationalResetHour();
        $startDay = Carbon::parse($tanggalAwal)->startOfDay();
        $endDay = Carbon::parse($tanggalAkhir)->startOfDay();

        if ($endDay->lt($startDay)) {
            [$startDay, $endDay] = [$endDay, $startDay];
        }

        $start = $startDay->copy()->addHours($resetHour);
        $end = $endDay->copy()->addDay()->addHours($resetHour);

        return [$start, $end];
    }

    private function buildOperationalQuickFilter(string $preset): array
    {
        return [
            'query' => ['operasional' => $preset],
            'label' => $preset === 'today' ? 'Transaksi Shift Ini' : 'Transaksi Shift Sebelumnya',
        ];
    }

    private function resolveOperationalResetHour(): int
    {
        $configHour = (int) config('app.operasional_reset_hour', 3);
        if (app()->environment('testing') && $configHour !== 3) {
            return max(0, min(23, $configHour));
        }

        $settingHour = (int) (StrukSetting::query()->value('operasional_reset_hour') ?? -1);
        if ($settingHour >= 0 && $settingHour <= 23) {
            return $settingHour;
        }

        return max(0, min(23, $configHour));
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function buildDetailOptionsLabel(mixed $detail): string
    {
        $parts = [];
        $temperature = $detail->temperature ?? null;
        $sugarLevel = $detail->sugar_level ?? null;
        $cupSize = $detail->cup_size ?? null;
        $spicyLevel = $detail->spicy_level ?? null;
        $selectedOptions = $detail->selected_options ?? null;

        if ($temperature) {
            $parts[] = match ($temperature) {
                'hot' => 'Hot',
                'less_ice' => 'Less Es',
                'ice' => 'Es',
                default => $this->humanizeOptionValue((string) $temperature),
            };
        }

        if ($sugarLevel) {
            $parts[] = match ($sugarLevel) {
                'none' => 'No Sugar',
                'less' => 'Less Sugar',
                'normal' => 'Normal Sugar',
                default => $this->humanizeOptionValue((string) $sugarLevel),
            };
        }

        if ($cupSize) {
            $parts[] = match ($cupSize) {
                'large' => 'Cup Large',
                'regular' => 'Cup Regular',
                default => $this->humanizeOptionValue((string) $cupSize),
            };
        }

        if ($spicyLevel) {
            $parts[] = match ($spicyLevel) {
                'extra_spicy' => 'Extra Spicy',
                'spicy' => 'Spicy',
                'non_spicy' => 'Non Spicy',
                default => $this->humanizeOptionValue((string) $spicyLevel),
            };
        }

        if (is_string($selectedOptions) && $selectedOptions !== '') {
            $selectedOptions = json_decode($selectedOptions, true);
        }
        if (is_array($selectedOptions)) {
            foreach ($selectedOptions as $selectedValue) {
                if (! is_string($selectedValue) || trim($selectedValue) === '') {
                    continue;
                }
                $parts[] = $this->humanizeOptionValue($selectedValue);
            }
        }

        return ! empty($parts) ? implode(' | ', $parts) : '-';
    }

    private function humanizeOptionValue(string $value): string
    {
        $value = trim(str_replace('_', ' ', $value));
        return $value !== '' ? ucwords($value) : '-';
    }

    private function buildPublicKodeNota(Pesanan $transaksi): string
    {
        $prefixDate = date('Ymd', strtotime((string) $transaksi->waktu_pembayaran));
        $publicOrderId = $this->buildPublicOrderId($transaksi);

        return 'INV-' . $prefixDate . '-' . $publicOrderId;
    }

    private function buildPublicOrderId(Pesanan $transaksi): string
    {
        if (! empty($transaksi->no_urut_shift)) {
            $shiftKe = (int) ($transaksi->shift?->shift_ke ?? 0);
            $urut = str_pad((string) ((int) $transaksi->no_urut_shift), 3, '0', STR_PAD_LEFT);
            return 'S' . $shiftKe . '-' . $urut;
        }

        $hash = strtoupper(base_convert(sprintf('%u', crc32((string) $transaksi->id_pesanan)), 10, 36));
        return 'S0-' . str_pad(substr($hash, -6), 6, '0', STR_PAD_LEFT);
    }

    private function resolvePaperPreference(Request $request): string
    {
        $requestedPaper = $request->query('paper');
        $user = $request->user();

        if (in_array($requestedPaper, ['58', '80'], true)) {
            if ($user && $user->paper_preference !== $requestedPaper) {
                $user->forceFill(['paper_preference' => $requestedPaper])->save();
            }

            return $requestedPaper;
        }

        if ($user && in_array((string) $user->paper_preference, ['58', '80'], true)) {
            return (string) $user->paper_preference;
        }

        return '80';
    }
}
