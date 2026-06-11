<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\DetailPesanan;
use App\Models\Diskon;
use App\Models\Karyawan;
use App\Models\KasirShiftSession;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\PromoBundling;
use App\Models\StrukSetting;
use App\Services\NotaViewDataBuilder;
use App\Services\PromoAutoExpire;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KasirController extends Controller
{
    private const DISCOUNT_TYPES = ['persen', 'nominal', 'harga_kategori'];
    private const LARGE_CUP_SURCHARGE = 2000;

    private function isAdminKasir(Request $request): bool
    {
        return $request->routeIs('admin.kasir.*');
    }

    private function resolveKasirRoute(string $suffix, bool $adminKasir): string
    {
        return $adminKasir ? 'admin.kasir.' . $suffix : 'kasir.' . $suffix;
    }

    public function index(Request $request): View
    {
        $isAdminKasir = $this->isAdminKasir($request);
        PromoAutoExpire::run();
        $selectedLines = $this->normalizeRequestedItemsFromInput((array) session('checkout.items', []))
            ->values()
            ->all();
        $promoBundling = PromoBundling::activeForDate(now())
            ->with('items.produk')
            ->orderBy('nama_promo')
            ->get();
        $selectedBundlingId = (int) session('checkout.id_promo_bundling', 0);
        $selectedBundling = $selectedBundlingId > 0
            ? $promoBundling->firstWhere('id_promo_bundling', $selectedBundlingId)
            : null;
        $activeShift = KasirShiftSession::query()
            ->forUser((int) auth()->id())
            ->active()
            ->latest('started_at')
            ->first();
        $kasSekarang = null;
        if ($activeShift) {
            $totalCash = (float) Pesanan::query()
                ->where('status_pembayaran', 'lunas')
                ->where('metode_pembayaran', 'cash')
                ->where('waktu_pembayaran', '>=', $activeShift->started_at)
                ->sum('total_harga');
            $totalPengeluaran = (float) \App\Models\KasirShiftPengeluaran::query()
                ->where('kasir_shift_session_id', (int) $activeShift->id)
                ->where('pengeluaran_at', '>=', $activeShift->started_at)
                ->sum('nominal');
            $kasSekarang = ((float) $activeShift->kas_awal + $totalCash) - $totalPengeluaran;
        }

        $promoAnnouncements = collect();
        if (Schema::hasTable('announcements')) {
            $announcementNow = now();
            $announcementCutoff = $announcementNow->copy()->subDays(3);
            $promoAnnouncements = Announcement::query()
                ->where(function ($q): void {
                    $q->where('title', 'like', '%Promo%')
                        ->orWhere('body', 'like', '%Diskon%')
                        ->orWhere('body', 'like', '%Bundling%');
                })
                ->where(function ($q) use ($announcementNow): void {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', $announcementNow);
                })
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get()
                ->map(function (Announcement $announcement) use ($announcementNow) {
                    $promoInfo = $announcement->resolvePromoStatus($announcementNow);
                    $announcement->promo_status = $promoInfo['status'] === 'Akan Mulai' ? 'Terjadwal' : $promoInfo['status'];
                    $announcement->promo_end_at = $promoInfo['end_at'];
                    return $announcement;
                })
                ->filter(function (Announcement $announcement) use ($announcementCutoff) {
                    if (! empty($announcement->promo_end_at) && $announcement->promo_end_at instanceof Carbon) {
                        return $announcement->promo_end_at->greaterThanOrEqualTo($announcementCutoff);
                    }
                    return true;
                })
                ->values()
                ->take(6);
        }

        return view('kasir.index', [
            'produk' => Produk::with('kategori')->orderBy('nama_produk')->get(),
            'promoBundling' => $promoBundling,
            'selectedBundlingId' => $selectedBundling?->id_promo_bundling,
            'selectedBundlingName' => $selectedBundling?->nama_promo,
            'selectedLines' => $selectedLines,
            'activeShift' => $activeShift,
            'kasSekarang' => $kasSekarang,
            'promoAnnouncements' => $promoAnnouncements,
            'kasirRoutePrefix' => $isAdminKasir ? 'admin.kasir' : 'kasir',
            'isAdminKasir' => $isAdminKasir,
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $isAdminKasir = $this->isAdminKasir($request);
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.qty' => ['nullable', 'integer', 'min:0'],
            'items.*.temperature' => ['nullable', 'string', 'max:30'],
            'items.*.sugar_level' => ['nullable', 'string', 'max:30'],
            'items.*.cup_size' => ['nullable', 'string', 'max:30'],
            'items.*.spicy_level' => ['nullable', 'string', 'max:30'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'items.*.custom_options' => ['nullable', 'array'],
            'id_promo_bundling' => ['nullable', 'integer', 'min:1'],
        ]);

        $requestedItems = $this->normalizeRequestedItemsFromInput((array) $request->input('items', []));

        if ($requestedItems->isEmpty()) {
            return back()->withErrors(['items' => 'Pilih minimal 1 produk.'])->withInput();
        }

        [$validatedItems, $produkById] = $this->resolveAndValidateItems($requestedItems, false);
        $this->ensureStocksAvailable($validatedItems, false);

        $request->session()->put('checkout.items', $validatedItems->all());
        $selectedBundlingId = (int) $request->input('id_promo_bundling', 0);
        if ($selectedBundlingId > 0) {
            $bundling = PromoBundling::activeForDate(now())
                ->with('items')
                ->where('id_promo_bundling', $selectedBundlingId)
                ->first();
            if (! $bundling) {
                $request->session()->forget('checkout.id_promo_bundling');
                return redirect()->route($this->resolveKasirRoute('checkout_page', $isAdminKasir));
            }

            $calc = $this->calculateBundlingDiscount($bundling, $validatedItems, $produkById);
            if ($calc['applies_count'] <= 0) {
                return back()->withErrors(['id_promo_bundling' => 'Keranjang belum memenuhi syarat bundling yang dipilih.'])->withInput();
            }

            $request->session()->put('checkout.id_promo_bundling', $selectedBundlingId);
        } else {
            $request->session()->forget('checkout.id_promo_bundling');
        }

        return redirect()->route($this->resolveKasirRoute('checkout_page', $isAdminKasir));
    }

    public function checkoutPage(Request $request): View|RedirectResponse
    {
        $isAdminKasir = $this->isAdminKasir($request);
        $requestedItems = $this->normalizeRequestedItemsFromInput((array) $request->session()->get('checkout.items', []));

        if ($requestedItems->isEmpty()) {
            return redirect()->route($this->resolveKasirRoute('index', $isAdminKasir))->withErrors(['items' => 'Pilih produk dulu sebelum checkout.']);
        }

        [$validatedItems, $produkById] = $this->resolveAndValidateItems($requestedItems, false);

        $ringkasan = [];
        $total = 0.0;
        foreach ($validatedItems as $line) {
            $produk = $produkById->get((int) $line['id_produk']);
            if (! $produk) {
                continue;
            }

            $qty = (int) $line['qty'];
            $hargaSatuan = $this->resolveLinePrice($produk, $line, false);
            $subtotal = $hargaSatuan * $qty;
            $total += $subtotal;

            $ringkasan[] = [
                'produk' => $produk,
                'qty' => $qty,
                'harga_satuan' => $hargaSatuan,
                'subtotal' => $subtotal,
                'temperature' => $line['temperature'],
                'sugar_level' => $line['sugar_level'],
                'cup_size' => $line['cup_size'],
                'spicy_level' => $line['spicy_level'],
                'note' => $line['note'],
                'options_label' => $this->buildOptionsLabel(
                    $line['temperature'],
                    $line['sugar_level'],
                    $line['cup_size'],
                    $line['spicy_level'],
                    $produk,
                    $line['custom_options'] ?? [],
                    $line['note'] ?? null
                ),
            ];
        }

        $selectedBundlingId = (int) $request->session()->get('checkout.id_promo_bundling', 0);
        $selectedBundling = null;
        $selectedBundlingNominal = 0.0;
        $selectedBundlingApplies = 0;
        if ($selectedBundlingId > 0) {
            $selectedBundling = PromoBundling::activeForDate(now())
                ->with('items')
                ->where('id_promo_bundling', $selectedBundlingId)
                ->first();
            if ($selectedBundling) {
                $calc = $this->calculateBundlingDiscount($selectedBundling, $validatedItems, $produkById);
                $selectedBundlingNominal = (float) $calc['diskon_nominal'];
                $selectedBundlingApplies = (int) $calc['applies_count'];
            }
        }
        $enabledPaymentMethods = $this->resolveEnabledPaymentMethods();
        $enabledDeliveryMethods = StrukSetting::current()->enabledDeliveryPaymentMethods();
        if ($enabledPaymentMethods === []) {
            $enabledPaymentMethods = ['cash'];
        }
        [$taxEnabled, $taxPercent, $taxMode] = $this->resolveTaxConfig();

        return view('kasir.checkout', [
            'ringkasan' => $ringkasan,
            'total' => $total,
            'offlineItems' => $validatedItems->map(fn (array $line): array => [
                'id_produk' => (int) $line['id_produk'],
                'qty' => (int) $line['qty'],
                'harga_satuan' => $this->resolveLinePrice(
                    $produkById->get((int) $line['id_produk']),
                    $line,
                    false
                ),
                'temperature' => $line['temperature'],
                'sugar_level' => $line['sugar_level'],
                'cup_size' => $line['cup_size'],
                'spicy_level' => $line['spicy_level'],
                'note' => $line['note'],
                'custom_options' => $line['custom_options'] ?? [],
            ])->values()->all(),
            'pelanggan' => Pelanggan::orderBy('nama')->get(),
            'karyawan' => Karyawan::query()
                ->when(\Illuminate\Support\Facades\Schema::hasColumn('karyawan', 'is_active'), fn ($q) => $q->where('is_active', true))
                ->orderBy('nama_karyawan')
                ->get(),
            'diskon' => Diskon::with('kategoriTarget')->activeForDate(now())->orderBy('nama_diskon')->get(),
            'discountLines' => collect($ringkasan)->map(fn (array $row): array => [
                'id_produk' => (int) $row['produk']->id_produk,
                'id_kategori' => (int) ($row['produk']->id_kategori ?? 0),
                'qty' => (int) $row['qty'],
                'harga_satuan' => (float) $row['harga_satuan'],
            ])->values()->all(),
            'selectedBundling' => $selectedBundling,
            'selectedBundlingNominal' => $selectedBundlingNominal,
            'selectedBundlingApplies' => $selectedBundlingApplies,
            'enabledPaymentMethods' => $enabledPaymentMethods,
            'enabledDeliveryMethods' => $enabledDeliveryMethods,
            'taxEnabled' => $taxEnabled,
            'taxPercent' => $taxPercent,
            'taxMode' => $taxMode,
            'kasirRoutePrefix' => $isAdminKasir ? 'admin.kasir' : 'kasir',
            'isAdminKasir' => $isAdminKasir,
        ]);
    }

    public function submitCheckout(Request $request): RedirectResponse
    {
        $isAdminKasir = $this->isAdminKasir($request);
        $data = $this->validateCheckoutData($request, false, $isAdminKasir);
        if (empty($data['id_promo_bundling'])) {
            $data['id_promo_bundling'] = (int) $request->session()->get('checkout.id_promo_bundling', 0) ?: null;
        }
        $requestedItems = $this->normalizeRequestedItemsFromInput((array) $request->session()->get('checkout.items', []));

        if ($requestedItems->isEmpty()) {
            return redirect()->route($this->resolveKasirRoute('index', $isAdminKasir))->withErrors(['items' => 'Sesi checkout kosong. Pilih produk lagi.']);
        }

        [$idPesananBaru, $jumlahBayarCash] = $this->processCheckout(
            $data,
            $requestedItems,
            $request,
            true,
            $isAdminKasir
        );

        return redirect()
            ->route('kasir.receipt', ['transaksi' => $idPesananBaru])
            ->with('cash_paid', $jumlahBayarCash)
            ->with('auto_print_checker', true);
    }

    public function syncOfflineTransaction(Request $request): JsonResponse
    {
        $data = $this->validateCheckoutData($request, true, false);
        $requestedItems = $this->normalizeRequestedItemsFromInput((array) ($data['items'] ?? []));

        if ($requestedItems->isEmpty()) {
            return response()->json([
                'message' => 'Item transaksi offline kosong.',
                'errors' => ['items' => ['Pilih minimal 1 produk.']],
            ], 422);
        }

        $offlineRef = trim((string) ($data['offline_ref'] ?? ''));
        if ($offlineRef !== '') {
            $existing = Pesanan::where('offline_ref', $offlineRef)->first();
            if ($existing) {
                return response()->json([
                    'message' => 'Transaksi offline sudah pernah disinkronkan.',
                    'already_synced' => true,
                    'id_pesanan' => $existing->id_pesanan,
                    'receipt_url' => route('kasir.receipt', ['transaksi' => $existing]),
                ]);
            }
        }

        try {
            [$idPesananBaru] = $this->processCheckout(
                $data,
                $requestedItems,
                $request,
                false
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Validasi transaksi offline gagal.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (QueryException $exception) {
            if ($this->isDuplicateOfflineRef($exception) && $offlineRef !== '') {
                $existing = Pesanan::where('offline_ref', $offlineRef)->first();
                if ($existing) {
                    return response()->json([
                        'message' => 'Transaksi offline sudah pernah disinkronkan.',
                        'already_synced' => true,
                        'id_pesanan' => $existing->id_pesanan,
                        'receipt_url' => route('kasir.receipt', ['transaksi' => $existing]),
                    ]);
                }
            }

            throw $exception;
        }

        $pesanan = Pesanan::findOrFail($idPesananBaru);

        return response()->json([
            'message' => 'Transaksi offline berhasil disinkronkan.',
            'id_pesanan' => $idPesananBaru,
            'receipt_url' => route('kasir.receipt', ['transaksi' => $pesanan]),
        ]);
    }

    public function receipt(Pesanan $transaksi, Request $request): View
    {
        $transaksi->load(['pelanggan', 'karyawan', 'detail.produk', 'diskon.kategoriTarget', 'shift']);
        $jumlahBayarCash = $request->session()->get('cash_paid');
        $autoPrintChecker = (bool) $request->session()->pull('auto_print_checker', false);

        if ($transaksi->metode_pembayaran === 'cash') {
            $jumlahBayarCash = $jumlahBayarCash !== null ? (float) $jumlahBayarCash : (float) $transaksi->total_harga;
        } else {
            $jumlahBayarCash = null;
        }

        return view('kasir.receipt', [
            'transaksi' => $transaksi,
            'kodeNota' => $this->buildPublicKodeNota($transaksi),
            'jumlahBayarCash' => $jumlahBayarCash,
            'kembalian' => $jumlahBayarCash !== null ? max(0, $jumlahBayarCash - (float) $transaksi->total_harga) : null,
            'strukSetting' => StrukSetting::current(),
            'autoPrintChecker' => $autoPrintChecker,
            'kasirRoutePrefix' => $transaksi->kasir_label ? 'admin.kasir' : 'kasir',
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
            'notaRouteName' => 'kasir.nota',
            'strukSetting' => $setting,
            'nota' => app(NotaViewDataBuilder::class)->build(
                $transaksi,
                $setting,
                (string) ($request->user()?->role ?? 'kasir')
            ),
        ]);
    }

    public function checker(Request $request, Pesanan $transaksi): View
    {
        $transaksi->load(['detail.produk', 'pelanggan', 'karyawan', 'shift']);
        $paper = $this->resolvePaperPreference($request);
        $checkerDate = date('Ymd', strtotime((string) $transaksi->waktu_pembayaran));
        $publicOrderId = $this->buildPublicOrderId($transaksi);

        return view('kasir.checker', [
            'transaksi' => $transaksi,
            'paper' => $paper,
            'autoprint' => (bool) $request->boolean('autoprint'),
            'embedded' => (bool) $request->boolean('embedded'),
            'kodeChecker' => 'CHK-' . $checkerDate . '-' . $publicOrderId,
        ]);
    }

    private function validateCheckoutData(Request $request, bool $includeItems, bool $adminKasir = false): array
    {
        $enabledPaymentMethods = $this->resolveEnabledPaymentMethods();
        if ($enabledPaymentMethods === []) {
            throw ValidationException::withMessages([
                'metode_pembayaran' => 'Tidak ada metode pembayaran yang aktif. Hubungi admin.',
            ]);
        }

        $kasirRule = $adminKasir
            ? ['nullable', 'exists:karyawan,id_karyawan']
            : ['required', 'exists:karyawan,id_karyawan'];

        $rules = [
            'id_pelanggan' => ['nullable', 'exists:pelanggan,id_pelanggan'],
            'pelanggan_baru_nama' => ['nullable', 'string', 'max:100'],
            'pelanggan_baru_username_ig' => ['nullable', 'string', 'max:100'],
            'pelanggan_baru_no_telepon' => ['nullable', 'string', 'max:20'],
            'id_karyawan' => $kasirRule,
            'id_diskon' => ['nullable', 'exists:diskon,id_diskon'],
            'id_promo_bundling' => ['nullable', 'exists:promo_bundling,id_promo_bundling'],
            'diskon_nominal_snapshot' => ['nullable', 'numeric', 'min:0'],
            'diskon_nama_snapshot' => ['nullable', 'string', 'max:100'],
            'diskon_tipe_snapshot' => ['nullable', 'in:persen,nominal,harga_kategori'],
            'diskon_nilai_snapshot' => ['nullable', 'numeric', 'min:0'],
            'metode_pembayaran' => ['required', 'in:' . implode(',', $enabledPaymentMethods)],
            'jumlah_bayar' => ['nullable', 'numeric', 'min:0'],
            'catatan_pesanan' => ['nullable', 'string', 'max:255'],
            'offline_ref' => ['nullable', 'string', 'max:64'],
        ];

        if ($includeItems) {
            $rules['items'] = ['required', 'array'];
            $rules['items.*.id_produk'] = ['nullable', 'integer', 'min:1'];
            $rules['items.*.qty'] = ['nullable', 'integer', 'min:0'];
            $rules['items.*.harga_satuan'] = ['nullable', 'numeric', 'min:0'];
            $rules['items.*.temperature'] = ['nullable', 'string', 'max:30'];
            $rules['items.*.sugar_level'] = ['nullable', 'string', 'max:30'];
            $rules['items.*.cup_size'] = ['nullable', 'string', 'max:30'];
            $rules['items.*.spicy_level'] = ['nullable', 'string', 'max:30'];
            $rules['items.*.note'] = ['nullable', 'string', 'max:255'];
            $rules['items.*.custom_options'] = ['nullable', 'array'];
        }

        return $request->validate($rules);
    }

    private function resolveEnabledPaymentMethods(): array
    {
        return StrukSetting::current()->enabledPaymentMethods();
    }

    private function resolveTaxConfig(): array
    {
        $setting = StrukSetting::current();
        $chargeToCustomer = (bool) ($setting->enable_tax ?? false);
        $percent = (float) ($setting->tax_percent ?? 0);
        $percent = max(0, min(100, $percent));
        $mode = (string) ($setting->tax_mode ?? 'transaksi');
        $mode = in_array($mode, ['transaksi', 'produk'], true) ? $mode : 'transaksi';

        if ($percent <= 0) {
            return [$chargeToCustomer, 0.0, 'transaksi'];
        }

        return [$chargeToCustomer, $percent, $mode];
    }

    private function calculateTaxNominal(
        float $taxPercent,
        string $taxMode,
        float $subtotal,
        float $diskonNominal,
        Collection $validatedItems,
        Collection $produkById,
        bool $useSnapshotHarga
    ): float {
        if ($taxPercent <= 0) {
            return 0.0;
        }

        $taxMode = in_array($taxMode, ['transaksi', 'produk'], true) ? $taxMode : 'transaksi';
        $base = max(0, $subtotal - $diskonNominal);

        if ($taxMode === 'transaksi') {
            return round(($base * $taxPercent) / 100, 2);
        }

        $lineSubtotals = [];
        $subtotalLines = 0.0;
        foreach ($validatedItems as $line) {
            $produk = $produkById->get((int) $line['id_produk']);
            if (! $produk) {
                continue;
            }
            $hargaSatuan = $this->resolveLinePrice($produk, $line, $useSnapshotHarga);
            $lineSubtotal = max(0.0, (float) $hargaSatuan * (int) $line['qty']);
            $lineSubtotals[] = $lineSubtotal;
            $subtotalLines += $lineSubtotal;
        }

        if ($subtotalLines <= 0 || $lineSubtotals === []) {
            return 0.0;
        }

        $diskonTerpakai = min(max(0.0, $diskonNominal), $subtotalLines);
        $taxTotal = 0.0;
        $remainingDiscount = $diskonTerpakai;
        $lastIndex = count($lineSubtotals) - 1;

        foreach ($lineSubtotals as $index => $lineSubtotal) {
            if ($lineSubtotal <= 0) {
                continue;
            }
            if ($index === $lastIndex) {
                $lineDiscount = $remainingDiscount;
            } else {
                $ratio = $subtotalLines > 0 ? ($lineSubtotal / $subtotalLines) : 0.0;
                $lineDiscount = round($diskonTerpakai * $ratio, 2);
                $lineDiscount = min($lineDiscount, $remainingDiscount);
            }
            $remainingDiscount = max(0.0, $remainingDiscount - $lineDiscount);
            $lineBase = max(0.0, $lineSubtotal - $lineDiscount);
            $taxTotal += round(($lineBase * $taxPercent) / 100, 2);
        }

        return round($taxTotal, 2);
    }

    private function normalizeRequestedItemsFromInput(array $items): Collection
    {
        $normalized = [];

        foreach ($items as $key => $value) {
            $row = is_array($value) ? $value : ['qty' => $value];

            $idProduk = (int) ($row['id_produk'] ?? $key);
            $qty = (int) ($row['qty'] ?? 0);
            $hargaSatuan = isset($row['harga_satuan']) ? (float) $row['harga_satuan'] : null;

            if ($idProduk <= 0 || $qty <= 0) {
                continue;
            }

            $temperature = strtolower(trim((string) ($row['temperature'] ?? '')));
            $sugarLevel = strtolower(trim((string) ($row['sugar_level'] ?? '')));
            $cupSize = strtolower(trim((string) ($row['cup_size'] ?? '')));
            $spicyLevel = strtolower(trim((string) ($row['spicy_level'] ?? '')));
            $note = trim((string) ($row['note'] ?? ''));
            $note = preg_replace('/\s+/', ' ', $note);
            $note = $note !== '' ? $note : null;
            $customOptions = $this->normalizeCustomOptions($row['custom_options'] ?? []);

            $normalized[] = [
                'id_produk' => $idProduk,
                'qty' => $qty,
                'harga_satuan' => ($hargaSatuan !== null && $hargaSatuan >= 0) ? $hargaSatuan : null,
                'temperature' => $temperature !== '' ? $temperature : null,
                'sugar_level' => $sugarLevel !== '' ? $sugarLevel : null,
                'cup_size' => $cupSize !== '' ? $cupSize : null,
                'spicy_level' => $spicyLevel !== '' ? $spicyLevel : null,
                'note' => $note,
                'custom_options' => $customOptions,
            ];
        }

        return collect($normalized)->values();
    }

    private function resolveAndValidateItems(Collection $requestedItems, bool $lockProducts): array
    {
        $productIds = $requestedItems->pluck('id_produk')->unique()->values()->all();

        $productQuery = Produk::whereIn('id_produk', $productIds);
        if ($lockProducts) {
            $productQuery->lockForUpdate();
        }

        $produkById = $productQuery->get()->keyBy('id_produk');
        $validated = [];

        foreach ($requestedItems as $line) {
            $item = $produkById->get((int) $line['id_produk']);

            if (! $item) {
                throw ValidationException::withMessages(['items' => 'Produk tidak ditemukan.']);
            }

            $temperature = $line['temperature'];
            $sugarLevel = $line['sugar_level'];
            $cupSize = $line['cup_size'];
            $spicyLevel = $line['spicy_level'];
            $note = $line['note'] ?? null;
            $selectedCustomOptions = $line['custom_options'] ?? [];
            $temperatureOptions = $item->resolvedTemperatureOptions();
            $sugarOptions = $item->resolvedSugarOptions();
            $cupOptions = $item->resolvedCupSizeOptions();
            $spicyOptions = $item->resolvedSpicyOptions();
            $customGroups = $item->resolvedCustomOptionGroups();

            if ((bool) $item->is_temperature_enabled) {
                if (! $this->hasOptionValue($temperatureOptions, $temperature)) {
                    throw ValidationException::withMessages([
                        'items' => 'Produk ' . $item->nama_produk . ' wajib memilih opsi suhu.',
                    ]);
                }

                if ($temperature === 'hot') {
                    $sugarLevel = null;
                    $cupSize = $this->resolveDefaultOptionValue($cupOptions, 'regular') ?? 'regular';
                } else {
                    if ((bool) $item->is_sugar_enabled) {
                        if (! $this->hasOptionValue($sugarOptions, $sugarLevel)) {
                            $sugarLevel = $this->resolveDefaultOptionValue($sugarOptions, 'normal');
                            if (! $this->hasOptionValue($sugarOptions, $sugarLevel)) {
                                throw ValidationException::withMessages([
                                    'items' => 'Produk ' . $item->nama_produk . ' wajib memilih level gula.',
                                ]);
                            }
                        }
                    } else {
                        $sugarLevel = null;
                    }

                    if ((bool) $item->is_cup_size_enabled) {
                        if (! $this->hasOptionValue($cupOptions, $cupSize)) {
                            $cupSize = $this->resolveDefaultOptionValue($cupOptions, 'regular');
                            if (! $this->hasOptionValue($cupOptions, $cupSize)) {
                                throw ValidationException::withMessages([
                                    'items' => 'Produk ' . $item->nama_produk . ' wajib memilih ukuran cup.',
                                ]);
                            }
                        }
                    } else {
                        $cupSize = null;
                    }
                }
            } else {
                $temperature = null;

                if ((bool) $item->is_sugar_enabled) {
                    if (! $this->hasOptionValue($sugarOptions, $sugarLevel)) {
                        $sugarLevel = $this->resolveDefaultOptionValue($sugarOptions, 'normal');
                        if (! $this->hasOptionValue($sugarOptions, $sugarLevel)) {
                            throw ValidationException::withMessages([
                                'items' => 'Produk ' . $item->nama_produk . ' wajib memilih level gula.',
                            ]);
                        }
                    }
                } else {
                    $sugarLevel = null;
                }

                if ((bool) $item->is_cup_size_enabled) {
                    if (! $this->hasOptionValue($cupOptions, $cupSize)) {
                        $cupSize = $this->resolveDefaultOptionValue($cupOptions, 'regular');
                        if (! $this->hasOptionValue($cupOptions, $cupSize)) {
                            throw ValidationException::withMessages([
                                'items' => 'Produk ' . $item->nama_produk . ' wajib memilih ukuran cup.',
                            ]);
                        }
                    }
                } else {
                    $cupSize = null;
                }
            }

            if ((bool) $item->is_spicy_enabled) {
                if (! $this->hasOptionValue($spicyOptions, $spicyLevel)) {
                    throw ValidationException::withMessages([
                        'items' => 'Produk ' . $item->nama_produk . ' wajib memilih level pedas.',
                    ]);
                }
            } else {
                $spicyLevel = null;
            }

            $selectedCustomOptions = $this->validateCustomOptions($item, $customGroups, $selectedCustomOptions);

            $validated[] = [
                'id_produk' => (int) $line['id_produk'],
                'qty' => (int) $line['qty'],
                'harga_satuan' => $line['harga_satuan'] !== null ? (float) $line['harga_satuan'] : null,
                'temperature' => $temperature,
                'sugar_level' => $sugarLevel,
                'cup_size' => $cupSize,
                'spicy_level' => $spicyLevel,
                'note' => $note,
                'custom_options' => $selectedCustomOptions,
            ];
        }

        return [collect($validated), $produkById];
    }

    private function ensureStocksAvailable(Collection $validatedItems, bool $strict): void
    {
        $stokByProduk = [];
        foreach ($validatedItems as $line) {
            $idProduk = (int) $line['id_produk'];
            $stokByProduk[$idProduk] = ($stokByProduk[$idProduk] ?? 0) + (int) $line['qty'];
        }

        $produk = Produk::whereIn('id_produk', array_keys($stokByProduk))->get()->keyBy('id_produk');

        foreach ($stokByProduk as $idProduk => $qty) {
            $item = $produk->get($idProduk);

            if (! $item) {
                throw ValidationException::withMessages(['items' => 'Produk tidak ditemukan.']);
            }

            if ((int) $item->stok < $qty) {
                $message = $strict
                    ? 'Stok produk ' . $item->nama_produk . ' tidak mencukupi.'
                    : 'Qty produk ' . $item->nama_produk . ' melebihi stok yang tersedia.';

                throw ValidationException::withMessages(['items' => $message]);
            }
        }
    }

    private function processCheckout(
        array $data,
        Collection $requestedItems,
        Request $request,
        bool $clearSessionCheckout,
        bool $adminKasir = false
    ): array {
        $idPesananBaru = null;
        $jumlahBayarCash = null;

        DB::transaction(function () use ($data, $requestedItems, $request, $clearSessionCheckout, $adminKasir, &$idPesananBaru, &$jumlahBayarCash): void {
            [$validatedItems, $produkById] = $this->resolveAndValidateItems($requestedItems, true);

            $stokByProduk = [];
            $subtotal = 0.0;
            $useSnapshotHarga = ! $clearSessionCheckout;
            foreach ($validatedItems as $line) {
                $idProduk = (int) $line['id_produk'];
                $qty = (int) $line['qty'];
                $stokByProduk[$idProduk] = ($stokByProduk[$idProduk] ?? 0) + $qty;

                $item = $produkById->get($idProduk);
                $hargaSatuan = $this->resolveLinePrice($item, $line, $useSnapshotHarga);
                $subtotal += ($hargaSatuan * $qty);
            }

            foreach ($stokByProduk as $idProduk => $qty) {
                $item = $produkById->get($idProduk);
                if (! $item || (int) $item->stok < $qty) {
                    throw ValidationException::withMessages([
                        'items' => 'Stok produk ' . ($item?->nama_produk ?? ('#' . $idProduk)) . ' tidak mencukupi.',
                    ]);
                }
            }

            $selectedDiskon = null;
            $diskonNominal = 0.0;
            $diskonNama = null;
            $diskonTipe = null;
            $diskonNilai = null;
            $isOfflineSync = ! $clearSessionCheckout;

            if (! empty($data['id_diskon']) && ! empty($data['id_promo_bundling'])) {
                throw ValidationException::withMessages([
                    'id_diskon' => 'Pilih salah satu: diskon atau promo bundling.',
                ]);
            }

            if (! empty($data['id_diskon'])) {
                $selectedDiskon = Diskon::where('id_diskon', $data['id_diskon'])->lockForUpdate()->first();

                if (! $selectedDiskon) {
                    throw ValidationException::withMessages([
                        'id_diskon' => 'Diskon tidak ditemukan.',
                    ]);
                }

                $snapshotNominal = isset($data['diskon_nominal_snapshot']) ? (float) $data['diskon_nominal_snapshot'] : null;
                $snapshotNama = isset($data['diskon_nama_snapshot']) ? trim((string) $data['diskon_nama_snapshot']) : null;
                $snapshotTipe = isset($data['diskon_tipe_snapshot']) ? trim((string) $data['diskon_tipe_snapshot']) : null;
                $snapshotNilai = isset($data['diskon_nilai_snapshot']) ? (float) $data['diskon_nilai_snapshot'] : null;

                $isDiskonAktifSaatIni = $selectedDiskon->isAktifPada(now());
                $subtotalScope = $this->calculateDiscountBaseSubtotal($selectedDiskon, $validatedItems, $produkById, $useSnapshotHarga);
                $minimalTerpenuhiSaatIni = (float) $subtotalScope >= (float) $selectedDiskon->minimal_belanja;

                if ($isDiskonAktifSaatIni && $minimalTerpenuhiSaatIni) {
                    if ($selectedDiskon->tipe_diskon === 'persen') {
                        $diskonNominal = round(((float) $subtotalScope * (float) $selectedDiskon->nilai_diskon) / 100, 2);
                        if ((float) ($selectedDiskon->maksimal_diskon ?? 0) > 0) {
                            $diskonNominal = min($diskonNominal, (float) $selectedDiskon->maksimal_diskon);
                        }
                    } elseif ($selectedDiskon->tipe_diskon === 'harga_kategori') {
                        $diskonNominal = $this->calculateSpecialCategoryDiscount($selectedDiskon, $validatedItems, $produkById, $useSnapshotHarga);
                    } else {
                        $diskonNominal = min((float) $subtotalScope, (float) $selectedDiskon->nilai_diskon);
                    }

                    $diskonNama = (string) $selectedDiskon->nama_diskon;
                    $diskonTipe = (string) $selectedDiskon->tipe_diskon;
                    $diskonNilai = (float) $selectedDiskon->nilai_diskon;
                } elseif ($isOfflineSync && $snapshotNominal !== null) {
                    $diskonNominal = min((float) $subtotal, max(0.0, $snapshotNominal));
                    $diskonNama = ! empty($snapshotNama) ? $snapshotNama : (string) $selectedDiskon->nama_diskon;
                    $diskonTipe = in_array($snapshotTipe, self::DISCOUNT_TYPES, true)
                        ? $snapshotTipe
                        : (string) $selectedDiskon->tipe_diskon;
                    $diskonNilai = $snapshotNilai !== null ? max(0.0, $snapshotNilai) : (float) $selectedDiskon->nilai_diskon;
                } else {
                    $message = ! $isDiskonAktifSaatIni
                        ? 'Diskon tidak aktif atau tidak ditemukan.'
                        : 'Minimal belanja untuk diskon ini belum terpenuhi.';
                    throw ValidationException::withMessages([
                        'id_diskon' => $message,
                    ]);
                }
            }

            if (! empty($data['id_promo_bundling'])) {
                $selectedBundling = PromoBundling::where('id_promo_bundling', $data['id_promo_bundling'])
                    ->with('items')
                    ->lockForUpdate()
                    ->first();

                if (! $selectedBundling || ! $selectedBundling->isAktifPada(now())) {
                    throw ValidationException::withMessages([
                        'id_promo_bundling' => 'Promo bundling tidak aktif atau tidak ditemukan.',
                    ]);
                }

                $calcBundling = $this->calculateBundlingDiscount($selectedBundling, $validatedItems, $produkById);
                if ($calcBundling['applies_count'] <= 0 || $calcBundling['diskon_nominal'] <= 0) {
                    throw ValidationException::withMessages([
                        'id_promo_bundling' => 'Keranjang belanja belum memenuhi syarat bundling.',
                    ]);
                }

                $diskonNominal = $calcBundling['diskon_nominal'];
                $diskonNama = $selectedBundling->nama_promo . ' x' . $calcBundling['applies_count'];
                $diskonTipe = 'bundling';
                $diskonNilai = (float) $selectedBundling->harga_bundle;
                $selectedDiskon = null;
            }

            $totalSebelumPajak = max(0, (float) $subtotal - (float) $diskonNominal);
            [$taxChargeToCustomer, $taxPercent, $taxMode] = $this->resolveTaxConfig();
            $pajakNominal = $this->calculateTaxNominal(
                $taxPercent,
                $taxMode,
                $subtotal,
                $diskonNominal,
                $validatedItems,
                $produkById,
                $useSnapshotHarga
            );
            $totalAkhir = $taxChargeToCustomer
                ? ($totalSebelumPajak + $pajakNominal)
                : $totalSebelumPajak;

            $idPelanggan = $data['id_pelanggan'] ?? null;
            if ($idPelanggan === '') {
                $idPelanggan = null;
            }

            if ($data['metode_pembayaran'] === 'cash') {
                $jumlahBayar = (float) ($data['jumlah_bayar'] ?? 0);
                if ($jumlahBayar < $totalAkhir) {
                    throw ValidationException::withMessages([
                        'jumlah_bayar' => 'Jumlah bayar cash kurang dari total belanja.',
                    ]);
                }
                $jumlahBayarCash = $jumlahBayar;
            }

            if ($idPelanggan === null && ! empty(trim((string) ($data['pelanggan_baru_nama'] ?? '')))) {
                $pelangganBaru = Pelanggan::create([
                    'nama' => trim((string) $data['pelanggan_baru_nama']),
                    'username_ig' => trim((string) ($data['pelanggan_baru_username_ig'] ?? '')) ?: null,
                    'no_telepon' => trim((string) ($data['pelanggan_baru_no_telepon'] ?? '')) ?: null,
                ]);

                $idPelanggan = $pelangganBaru->id_pelanggan;
            }

            if ($adminKasir && $idPelanggan === null) {
                $pelangganAdmin = Pelanggan::query()
                    ->whereRaw("LOWER(TRIM(nama)) = 'admin'")
                    ->first();
                if (! $pelangganAdmin) {
                    $pelangganAdmin = Pelanggan::create([
                        'nama' => 'Admin',
                        'username_ig' => null,
                        'no_telepon' => null,
                    ]);
                }
                $idPelanggan = $pelangganAdmin->id_pelanggan;
            }

            $offlineRef = trim((string) ($data['offline_ref'] ?? ''));
            $catatanPesanan = trim((string) ($data['catatan_pesanan'] ?? ''));
            $catatanPesanan = $catatanPesanan !== '' ? $catatanPesanan : null;
            $kasirLabel = $adminKasir ? 'ADMIN' : null;
            $idKaryawan = $adminKasir ? null : $data['id_karyawan'];
            [$shiftSessionId, $shiftOrderNo] = $this->resolveShiftOrderSequence((int) ($request->user()?->id ?? 0));

            $pesanan = Pesanan::create([
                'id_pelanggan' => $idPelanggan,
                'id_karyawan' => $idKaryawan,
                'kasir_label' => $kasirLabel,
                'kasir_shift_session_id' => $shiftSessionId,
                'no_urut_shift' => $shiftOrderNo,
                'id_diskon' => $selectedDiskon?->id_diskon,
                'subtotal_harga' => $subtotal,
                'diskon_nominal' => $diskonNominal,
                'diskon_nama' => $diskonNama,
                'diskon_tipe' => $diskonTipe,
                'diskon_nilai' => $diskonNilai,
                'pajak_persen' => $taxPercent,
                'pajak_nominal' => $pajakNominal,
                'total_harga' => $totalAkhir,
                'waktu_pembayaran' => now(),
                'metode_pembayaran' => $data['metode_pembayaran'],
                'status_pembayaran' => 'lunas',
                'catatan_pesanan' => $catatanPesanan,
                'offline_ref' => $offlineRef !== '' ? $offlineRef : null,
            ]);

            foreach ($validatedItems as $line) {
                $item = $produkById->get((int) $line['id_produk']);
                $hargaSatuan = $this->resolveLinePrice($item, $line, $useSnapshotHarga);
                $selectedOptions = $line['custom_options'] ?? [];
                $note = preg_replace('/\s+/', ' ', trim((string) ($line['note'] ?? ''))) ?? '';
                if ($note !== '') {
                    $selectedOptions['note'] = $note;
                }

                DetailPesanan::create([
                    'id_pesanan' => $pesanan->id_pesanan,
                    'id_produk' => $item->id_produk,
                    'jumlah' => (int) $line['qty'],
                    'harga_satuan' => $hargaSatuan,
                    'temperature' => $line['temperature'],
                    'sugar_level' => $line['sugar_level'],
                    'cup_size' => $line['cup_size'],
                    'spicy_level' => $line['spicy_level'],
                    'selected_options' => $selectedOptions,
                ]);
            }

            foreach ($stokByProduk as $idProduk => $qty) {
                $item = $produkById->get((int) $idProduk);
                if ($item && $qty > 0) {
                    $item->decrement('stok', (int) $qty);
                }
            }

            $idPesananBaru = $pesanan->id_pesanan;

            if ($clearSessionCheckout) {
                $request->session()->forget('checkout.items');
                $request->session()->forget('checkout.id_promo_bundling');
            }
        });

        return [$idPesananBaru, $jumlahBayarCash];
    }

    private function resolveShiftOrderSequence(int $userId): array
    {
        $activeShift = null;
        if ($userId > 0) {
            $activeShift = KasirShiftSession::query()
                ->forUser($userId)
                ->active()
                ->lockForUpdate()
                ->latest('started_at')
                ->first();
        }

        if ($activeShift) {
            $lastNo = DB::table('pesanan')
                ->where('kasir_shift_session_id', (int) $activeShift->id)
                ->orderByDesc('no_urut_shift')
                ->lockForUpdate()
                ->value('no_urut_shift');

            return [(int) $activeShift->id, max(1, ((int) $lastNo) + 1)];
        }

        // Fallback: jika transaksi dibuat di luar shift aktif, tetap pakai nomor publik non-global.
        $today = now()->toDateString();
        $lastNoFallback = DB::table('pesanan')
            ->whereNull('kasir_shift_session_id')
            ->whereDate('waktu_pembayaran', $today)
            ->orderByDesc('no_urut_shift')
            ->lockForUpdate()
            ->value('no_urut_shift');

        return [null, max(1, ((int) $lastNoFallback) + 1)];
    }

    private function isDuplicateOfflineRef(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && str_contains(strtolower($exception->getMessage()), 'offline_ref');
    }

    private function buildOptionsLabel(
        ?string $temperature,
        ?string $sugarLevel,
        ?string $cupSize,
        ?string $spicyLevel,
        ?Produk $produk = null,
        array $customOptions = [],
        ?string $note = null
    ): string
    {
        $parts = [];
        $temperatureMap = $produk ? $this->optionMap($produk->resolvedTemperatureOptions()) : [];
        $sugarMap = $produk ? $this->optionMap($produk->resolvedSugarOptions()) : [];
        $cupMap = $produk ? $this->optionMap($produk->resolvedCupSizeOptions()) : [];
        $spicyMap = $produk ? $this->optionMap($produk->resolvedSpicyOptions()) : [];

        if ($temperature !== null) {
            $parts[] = $temperatureMap[$temperature] ?? $this->humanizeValue($temperature);
        }

        if ($sugarLevel !== null) {
            $parts[] = $sugarMap[$sugarLevel] ?? $this->humanizeValue($sugarLevel);
        }

        if ($cupSize !== null) {
            $parts[] = $cupMap[$cupSize] ?? $this->humanizeValue($cupSize);
        }

        if ($spicyLevel !== null) {
            $parts[] = $spicyMap[$spicyLevel] ?? $this->humanizeValue($spicyLevel);
        }

        if ($produk) {
            foreach ($produk->resolvedCustomOptionGroups() as $group) {
                $groupId = (string) ($group['id'] ?? '');
                if ($groupId === '') {
                    continue;
                }
                $value = $customOptions[$groupId] ?? null;
                if (! is_string($value) || $value === '') {
                    continue;
                }
                $option = collect($group['options'] ?? [])->firstWhere('value', $value);
                if (! is_array($option)) {
                    continue;
                }
                $extra = (int) ($option['extra_price'] ?? 0);
                $label = (string) ($option['label'] ?? $this->humanizeValue($value));
                $parts[] = $extra > 0 ? ($label . ' (+' . number_format($extra, 0, ',', '.') . ')') : $label;
            }
        }

        $note = preg_replace('/\s+/', ' ', trim((string) $note)) ?? '';
        if ($note !== '') {
            $parts[] = 'Catatan: ' . $note;
        }

        return implode(' | ', $parts);
    }

    private function hasOptionValue(array $options, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        foreach ($options as $option) {
            if (($option['value'] ?? null) === $value) {
                return true;
            }
        }

        return false;
    }

    private function resolveDefaultOptionValue(array $options, string $preferred): ?string
    {
        foreach ($options as $option) {
            if (($option['value'] ?? null) === $preferred) {
                return $preferred;
            }
        }

        foreach ($options as $option) {
            $value = (string) ($option['value'] ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function optionMap(array $options): array
    {
        $map = [];
        foreach ($options as $option) {
            $value = (string) ($option['value'] ?? '');
            $label = (string) ($option['label'] ?? '');
            $extraPrice = max(0, (int) ($option['extra_price'] ?? 0));
            if ($value === '' || $label === '') {
                continue;
            }
            $map[$value] = $extraPrice > 0
                ? ($label . ' (+' . number_format($extraPrice, 0, ',', '.') . ')')
                : $label;
        }

        return $map;
    }

    private function humanizeValue(string $value): string
    {
        $value = trim(str_replace('_', ' ', $value));
        if ($value === '') {
            return '-';
        }

        return ucwords($value);
    }

    private function resolveHargaSatuan(Produk $produk, ?string $cupSize): float
    {
        $hargaSatuan = (float) $produk->harga;
        $hargaSatuan += $this->resolveBaseOptionExtra($produk->resolvedCupSizeOptions(), $cupSize);
        if ($cupSize === 'large' && $this->resolveBaseOptionExtra($produk->resolvedCupSizeOptions(), $cupSize) <= 0) {
            $hargaSatuan += self::LARGE_CUP_SURCHARGE;
        }

        return $hargaSatuan;
    }

    private function resolveLinePrice(Produk $produk, array $line, bool $allowSnapshot): float
    {
        if ($allowSnapshot && isset($line['harga_satuan']) && $line['harga_satuan'] !== null) {
            return max(0, (float) $line['harga_satuan']);
        }

        $base = $this->resolveHargaSatuan($produk, $line['cup_size'] ?? null);
        $base += $this->resolveBaseOptionExtra($produk->resolvedTemperatureOptions(), $line['temperature'] ?? null);
        $base += $this->resolveBaseOptionExtra($produk->resolvedSugarOptions(), $line['sugar_level'] ?? null);
        $base += $this->resolveBaseOptionExtra($produk->resolvedSpicyOptions(), $line['spicy_level'] ?? null);
        return $base + $this->resolveCustomOptionsExtraPrice($produk, $line['custom_options'] ?? []);
    }

    private function resolveBaseOptionExtra(array $options, ?string $selectedValue): float
    {
        if (! is_string($selectedValue) || $selectedValue === '') {
            return 0.0;
        }

        foreach ($options as $option) {
            if (($option['value'] ?? null) === $selectedValue) {
                return max(0, (float) ($option['extra_price'] ?? 0));
            }
        }

        return 0.0;
    }

    private function normalizeCustomOptions(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $groupId => $value) {
            $groupId = strtolower(trim((string) $groupId));
            $value = strtolower(trim((string) $value));
            if ($groupId === '' || $value === '') {
                continue;
            }
            $result[$groupId] = $value;
        }

        return $result;
    }

    private function validateCustomOptions(Produk $produk, array $customGroups, array $selected): array
    {
        $allowedGroupIds = [];
        $validated = [];

        foreach ($customGroups as $group) {
            $groupId = (string) ($group['id'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $allowedGroupIds[$groupId] = true;
            $required = (bool) ($group['required'] ?? false);
            $value = $selected[$groupId] ?? null;
            $options = is_array($group['options'] ?? null) ? $group['options'] : [];
            $optionMap = [];
            foreach ($options as $option) {
                $optValue = (string) ($option['value'] ?? '');
                if ($optValue !== '') {
                    $optionMap[$optValue] = true;
                }
            }

            if ($required && (! is_string($value) || $value === '')) {
                throw ValidationException::withMessages([
                    'items' => 'Produk ' . $produk->nama_produk . ' wajib memilih opsi ' . ($group['label'] ?? $groupId) . '.',
                ]);
            }

            if (is_string($value) && $value !== '') {
                if (! isset($optionMap[$value])) {
                    throw ValidationException::withMessages([
                        'items' => 'Pilihan opsi ' . ($group['label'] ?? $groupId) . ' untuk produk ' . $produk->nama_produk . ' tidak valid.',
                    ]);
                }
                $validated[$groupId] = $value;
            }
        }

        foreach ($selected as $groupId => $value) {
            if (! isset($allowedGroupIds[$groupId])) {
                continue;
            }
            $validated[$groupId] = $value;
        }

        return $validated;
    }

    private function resolveCustomOptionsExtraPrice(Produk $produk, array $selected): float
    {
        if ($selected === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($produk->resolvedCustomOptionGroups() as $group) {
            $groupId = (string) ($group['id'] ?? '');
            if ($groupId === '' || ! isset($selected[$groupId])) {
                continue;
            }
            $value = (string) $selected[$groupId];
            foreach (($group['options'] ?? []) as $option) {
                if (($option['value'] ?? null) === $value) {
                    $total += max(0, (float) ($option['extra_price'] ?? 0));
                    break;
                }
            }
        }

        return $total;
    }

    private function calculateSpecialCategoryDiscount(
        Diskon $diskon,
        Collection $validatedItems,
        Collection $produkById,
        bool $allowSnapshot
    ): float {
        $targetKategoriId = (int) ($diskon->id_kategori_target ?? 0);
        $specialPrice = (float) ($diskon->harga_spesial ?? 0);
        if ($targetKategoriId <= 0 || $specialPrice <= 0) {
            return 0.0;
        }

        $discount = 0.0;
        foreach ($validatedItems as $line) {
            $produk = $produkById->get((int) ($line['id_produk'] ?? 0));
            if (! $produk || (int) $produk->id_kategori !== $targetKategoriId) {
                continue;
            }

            $qty = max(0, (int) ($line['qty'] ?? 0));
            if ($qty <= 0) {
                continue;
            }

            $normalUnitPrice = $this->resolveLinePrice($produk, $line, $allowSnapshot);
            $discountPerUnit = max(0, $normalUnitPrice - $specialPrice);
            $discount += ($discountPerUnit * $qty);
        }

        return round($discount, 2);
    }

    private function calculateDiscountBaseSubtotal(
        Diskon $diskon,
        Collection $validatedItems,
        Collection $produkById,
        bool $allowSnapshot
    ): float {
        $targetKategoriId = (int) ($diskon->id_kategori_target ?? 0);
        $subtotal = 0.0;

        foreach ($validatedItems as $line) {
            $produk = $produkById->get((int) ($line['id_produk'] ?? 0));
            if (! $produk) {
                continue;
            }

            if ($targetKategoriId > 0 && (int) $produk->id_kategori !== $targetKategoriId) {
                continue;
            }

            $qty = max(0, (int) ($line['qty'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $subtotal += ($this->resolveLinePrice($produk, $line, $allowSnapshot) * $qty);
        }

        return round($subtotal, 2);
    }

    private function calculateBundlingDiscount(PromoBundling $promo, Collection $validatedItems, Collection $produkById): array
    {
        $requirements = $promo->items->mapWithKeys(fn ($item) => [(int) $item->id_produk => max(1, (int) $item->qty)]);
        if ($requirements->isEmpty()) {
            return ['applies_count' => 0, 'diskon_nominal' => 0.0];
        }

        $qtyByProduk = $validatedItems->groupBy('id_produk')->map(fn (Collection $rows) => (int) $rows->sum('qty'));
        $bundleCount = null;

        foreach ($requirements as $idProduk => $requiredQty) {
            $available = (int) ($qtyByProduk->get((int) $idProduk) ?? 0);
            $possible = intdiv($available, (int) $requiredQty);
            $bundleCount = $bundleCount === null ? $possible : min($bundleCount, $possible);
        }

        $bundleCount = max(0, (int) $bundleCount);
        if ($bundleCount <= 0) {
            return ['applies_count' => 0, 'diskon_nominal' => 0.0];
        }

        $normalBundlePrice = 0.0;
        foreach ($requirements as $idProduk => $requiredQty) {
            $produk = $produkById->get((int) $idProduk);
            if (! $produk) {
                return ['applies_count' => 0, 'diskon_nominal' => 0.0];
            }
            $normalBundlePrice += ((float) $produk->harga * (int) $requiredQty);
        }

        $discountPerBundle = max(0.0, $normalBundlePrice - (float) $promo->harga_bundle);

        return [
            'applies_count' => $bundleCount,
            'diskon_nominal' => round($discountPerBundle * $bundleCount, 2),
        ];
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
}
