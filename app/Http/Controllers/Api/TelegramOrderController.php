<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetailPesanan;
use App\Models\KasirShiftSession;
use App\Models\Pelanggan;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\StrukSetting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelegramOrderController extends Controller
{
    private const LARGE_CUP_SURCHARGE = 2000;

    public function store(Request $request): JsonResponse
    {
        $this->authorizeRequest($request);

        $validated = $request->validate([
            'external_order_id' => ['nullable', 'string', 'max:64'],
            'metode_pembayaran' => ['nullable', 'string', 'max:50'],
            'catatan_pesanan' => ['nullable', 'string', 'max:255'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:100'],
            'customer.phone' => ['nullable', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_produk' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.temperature' => ['nullable', 'string', 'max:30'],
            'items.*.sugar_level' => ['nullable', 'string', 'max:30'],
            'items.*.cup_size' => ['nullable', 'string', 'max:30'],
            'items.*.spicy_level' => ['nullable', 'string', 'max:30'],
            'items.*.custom_options' => ['nullable', 'array'],
        ]);

        $metodePembayaran = $this->resolveRequestedPaymentMethod(
            isset($validated['metode_pembayaran'])
                ? strtolower(trim((string) $validated['metode_pembayaran']))
                : null
        );

        $externalOrderId = trim((string) ($validated['external_order_id'] ?? ''));
        if ($externalOrderId !== '') {
            $existing = Pesanan::query()
                ->with(['pelanggan', 'detail'])
                ->where('offline_ref', $externalOrderId)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Pesanan Telegram sudah pernah diproses.',
                    'already_exists' => true,
                    'order' => $this->transformOrder($existing),
                ]);
            }
        }

        $requestedItems = $this->normalizeRequestedItemsFromInput((array) ($validated['items'] ?? []));
        if ($requestedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal 1 produk.',
            ]);
        }

        try {
            $pesanan = DB::transaction(function () use ($validated, $requestedItems, $metodePembayaran, $externalOrderId): Pesanan {
                [$validatedItems, $produkById] = $this->resolveAndValidateItems($requestedItems, true);

                $stokByProduk = [];
                $subtotal = 0.0;

                foreach ($validatedItems as $line) {
                    $idProduk = (int) $line['id_produk'];
                    $qty = (int) $line['qty'];
                    $stokByProduk[$idProduk] = ($stokByProduk[$idProduk] ?? 0) + $qty;

                    $produk = $produkById->get($idProduk);
                    $subtotal += ($this->resolveLinePrice($produk, $line) * $qty);
                }

                foreach ($stokByProduk as $idProduk => $qty) {
                    $produk = $produkById->get($idProduk);
                    if (! $produk || (int) $produk->stok < $qty) {
                        throw ValidationException::withMessages([
                            'items' => 'Stok produk ' . ($produk?->nama_produk ?? ('#' . $idProduk)) . ' tidak mencukupi.',
                        ]);
                    }
                }

                [$taxChargeToCustomer, $taxPercent, $taxMode] = $this->resolveTaxConfig();
                $pajakNominal = $this->calculateTaxNominal(
                    $taxPercent,
                    $taxMode,
                    $subtotal,
                    $validatedItems,
                    $produkById
                );
                $totalAkhir = $taxChargeToCustomer
                    ? ($subtotal + $pajakNominal)
                    : $subtotal;

                $pelanggan = $this->resolveOrCreatePelanggan((array) ($validated['customer'] ?? []));
                [$shiftSessionId, $shiftOrderNo] = $this->resolveShiftOrderSequence(0);

                $pesanan = Pesanan::query()->create([
                    'id_pelanggan' => $pelanggan?->id_pelanggan,
                    'id_karyawan' => null,
                    'kasir_label' => 'TELEGRAM',
                    'kasir_shift_session_id' => $shiftSessionId,
                    'no_urut_shift' => $shiftOrderNo,
                    'id_diskon' => null,
                    'subtotal_harga' => $subtotal,
                    'diskon_nominal' => 0,
                    'diskon_nama' => null,
                    'diskon_tipe' => null,
                    'diskon_nilai' => null,
                    'pajak_persen' => $taxPercent,
                    'pajak_nominal' => $pajakNominal,
                    'total_harga' => $totalAkhir,
                    'waktu_pembayaran' => now(),
                    'metode_pembayaran' => $metodePembayaran,
                    'status_pembayaran' => 'lunas',
                    'catatan_pesanan' => $this->resolveOrderNote($validated),
                    'offline_ref' => $externalOrderId !== '' ? $externalOrderId : null,
                ]);

                foreach ($validatedItems as $line) {
                    $produk = $produkById->get((int) $line['id_produk']);

                    DetailPesanan::query()->create([
                        'id_pesanan' => $pesanan->id_pesanan,
                        'id_produk' => $produk->id_produk,
                        'jumlah' => (int) $line['qty'],
                        'harga_satuan' => $this->resolveLinePrice($produk, $line),
                        'temperature' => $line['temperature'],
                        'sugar_level' => $line['sugar_level'],
                        'cup_size' => $line['cup_size'],
                        'spicy_level' => $line['spicy_level'],
                        'selected_options' => $line['custom_options'] ?? [],
                    ]);
                }

                foreach ($stokByProduk as $idProduk => $qty) {
                    $produkById->get((int) $idProduk)?->decrement('stok', (int) $qty);
                }

                return $pesanan->load(['pelanggan', 'detail.produk']);
            });
        } catch (QueryException $exception) {
            if ($externalOrderId !== '' && $this->isDuplicateExternalOrderRef($exception)) {
                $existing = Pesanan::query()
                    ->with(['pelanggan', 'detail'])
                    ->where('offline_ref', $externalOrderId)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'message' => 'Pesanan Telegram sudah pernah diproses.',
                        'already_exists' => true,
                        'order' => $this->transformOrder($existing),
                    ]);
                }
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Pesanan Telegram berhasil disimpan.',
            'already_exists' => false,
            'order' => $this->transformOrder($pesanan),
        ], 201);
    }

    private function authorizeRequest(Request $request): void
    {
        $configuredToken = trim((string) config('services.telegram.order_token', ''));
        if ($configuredToken === '') {
            return;
        }

        $providedToken = trim((string) ($request->bearerToken() ?: $request->header('X-Telegram-Order-Token', '')));
        if ($providedToken !== '' && hash_equals($configuredToken, $providedToken)) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Token webhook Telegram tidak valid.',
        ], 401));
    }

    private function transformOrder(Pesanan $pesanan): array
    {
        return [
            'id_pesanan' => (int) $pesanan->id_pesanan,
            'id_pelanggan' => $pesanan->id_pelanggan !== null ? (int) $pesanan->id_pelanggan : null,
            'pelanggan' => $pesanan->pelanggan?->nama,
            'kasir_label' => $pesanan->kasir_label,
            'metode_pembayaran' => $pesanan->metode_pembayaran,
            'status_pembayaran' => $pesanan->status_pembayaran,
            'subtotal_harga' => (float) ($pesanan->subtotal_harga ?? 0),
            'pajak_nominal' => (float) ($pesanan->pajak_nominal ?? 0),
            'total_harga' => (float) ($pesanan->total_harga ?? 0),
            'item_count' => (int) $pesanan->detail->sum('jumlah'),
            'external_order_id' => $pesanan->offline_ref,
            'waktu_pembayaran' => optional($pesanan->waktu_pembayaran)->toDateTimeString()
                ?? (string) $pesanan->waktu_pembayaran,
        ];
    }

    private function resolveRequestedPaymentMethod(?string $requestedMethod): string
    {
        $enabledPaymentMethods = StrukSetting::current()->enabledPaymentMethods();
        if ($enabledPaymentMethods === []) {
            throw ValidationException::withMessages([
                'metode_pembayaran' => 'Tidak ada metode pembayaran yang aktif. Hubungi admin.',
            ]);
        }

        if ($requestedMethod !== null && $requestedMethod !== '') {
            if (! in_array($requestedMethod, $enabledPaymentMethods, true)) {
                throw ValidationException::withMessages([
                    'metode_pembayaran' => 'Metode pembayaran tidak tersedia untuk order Telegram.',
                ]);
            }

            return $requestedMethod;
        }

        if (in_array('qris', $enabledPaymentMethods, true)) {
            return 'qris';
        }

        return (string) $enabledPaymentMethods[0];
    }

    private function resolveTaxConfig(): array
    {
        $setting = StrukSetting::current();
        $chargeToCustomer = (bool) ($setting->enable_tax ?? false);
        $percent = max(0, min(100, (float) ($setting->tax_percent ?? 0)));
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
        Collection $validatedItems,
        Collection $produkById
    ): float {
        if ($taxPercent <= 0) {
            return 0.0;
        }

        if ($taxMode === 'transaksi') {
            return round(($subtotal * $taxPercent) / 100, 2);
        }

        $taxTotal = 0.0;
        foreach ($validatedItems as $line) {
            $produk = $produkById->get((int) $line['id_produk']);
            if (! $produk) {
                continue;
            }

            $lineSubtotal = $this->resolveLinePrice($produk, $line) * (int) $line['qty'];
            $taxTotal += round(($lineSubtotal * $taxPercent) / 100, 2);
        }

        return round($taxTotal, 2);
    }

    private function normalizeRequestedItemsFromInput(array $items): Collection
    {
        $normalized = [];

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $idProduk = (int) ($row['id_produk'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            if ($idProduk <= 0 || $qty <= 0) {
                continue;
            }

            $normalized[] = [
                'id_produk' => $idProduk,
                'qty' => $qty,
                'temperature' => $this->normalizeNullableOption($row['temperature'] ?? null),
                'sugar_level' => $this->normalizeNullableOption($row['sugar_level'] ?? null),
                'cup_size' => $this->normalizeNullableOption($row['cup_size'] ?? null),
                'spicy_level' => $this->normalizeNullableOption($row['spicy_level'] ?? null),
                'custom_options' => $this->normalizeCustomOptions($row['custom_options'] ?? []),
            ];
        }

        return collect($normalized)->values();
    }

    private function resolveAndValidateItems(Collection $requestedItems, bool $lockProducts): array
    {
        $productIds = $requestedItems->pluck('id_produk')->unique()->values()->all();

        $productQuery = Produk::query()->whereIn('id_produk', $productIds);
        if ($lockProducts) {
            $productQuery->lockForUpdate();
        }

        $produkById = $productQuery->get()->keyBy('id_produk');
        $validated = [];

        foreach ($requestedItems as $line) {
            $produk = $produkById->get((int) $line['id_produk']);
            if (! $produk) {
                throw ValidationException::withMessages([
                    'items' => 'Produk tidak ditemukan.',
                ]);
            }

            $temperature = $line['temperature'];
            $sugarLevel = $line['sugar_level'];
            $cupSize = $line['cup_size'];
            $spicyLevel = $line['spicy_level'];
            $selectedCustomOptions = $line['custom_options'] ?? [];

            $temperatureOptions = $produk->resolvedTemperatureOptions();
            $sugarOptions = $produk->resolvedSugarOptions();
            $cupOptions = $produk->resolvedCupSizeOptions();
            $spicyOptions = $produk->resolvedSpicyOptions();
            $customGroups = $produk->resolvedCustomOptionGroups();

            if ((bool) $produk->is_temperature_enabled) {
                if (! $this->hasOptionValue($temperatureOptions, $temperature)) {
                    throw ValidationException::withMessages([
                        'items' => 'Produk ' . $produk->nama_produk . ' wajib memilih opsi suhu.',
                    ]);
                }

                if ($temperature === 'hot') {
                    $sugarLevel = null;
                    $cupSize = $this->resolveDefaultOptionValue($cupOptions, 'regular') ?? 'regular';
                } else {
                    $sugarLevel = $this->resolveValidatedOptionValue(
                        (bool) $produk->is_sugar_enabled,
                        $sugarOptions,
                        $sugarLevel,
                        'normal',
                        'level gula',
                        $produk->nama_produk
                    );

                    $cupSize = $this->resolveValidatedOptionValue(
                        (bool) $produk->is_cup_size_enabled,
                        $cupOptions,
                        $cupSize,
                        'regular',
                        'ukuran cup',
                        $produk->nama_produk
                    );
                }
            } else {
                $temperature = null;
                $sugarLevel = $this->resolveValidatedOptionValue(
                    (bool) $produk->is_sugar_enabled,
                    $sugarOptions,
                    $sugarLevel,
                    'normal',
                    'level gula',
                    $produk->nama_produk
                );
                $cupSize = $this->resolveValidatedOptionValue(
                    (bool) $produk->is_cup_size_enabled,
                    $cupOptions,
                    $cupSize,
                    'regular',
                    'ukuran cup',
                    $produk->nama_produk
                );
            }

            if ((bool) $produk->is_spicy_enabled) {
                if (! $this->hasOptionValue($spicyOptions, $spicyLevel)) {
                    throw ValidationException::withMessages([
                        'items' => 'Produk ' . $produk->nama_produk . ' wajib memilih level pedas.',
                    ]);
                }
            } else {
                $spicyLevel = null;
            }

            $validated[] = [
                'id_produk' => (int) $line['id_produk'],
                'qty' => (int) $line['qty'],
                'temperature' => $temperature,
                'sugar_level' => $sugarLevel,
                'cup_size' => $cupSize,
                'spicy_level' => $spicyLevel,
                'custom_options' => $this->validateCustomOptions($produk, $customGroups, $selectedCustomOptions),
            ];
        }

        return [collect($validated), $produkById];
    }

    private function resolveValidatedOptionValue(
        bool $enabled,
        array $options,
        ?string $value,
        string $preferredDefault,
        string $label,
        string $productName
    ): ?string {
        if (! $enabled) {
            return null;
        }

        if ($this->hasOptionValue($options, $value)) {
            return $value;
        }

        $resolved = $this->resolveDefaultOptionValue($options, $preferredDefault);
        if ($this->hasOptionValue($options, $resolved)) {
            return $resolved;
        }

        throw ValidationException::withMessages([
            'items' => 'Produk ' . $productName . ' wajib memilih ' . $label . '.',
        ]);
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

    private function normalizeNullableOption(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeCustomOptions(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $groupId => $value) {
            $groupId = strtolower(trim((string) $groupId));
            $value = strtolower(trim((string) $value));
            if ($groupId === '' || $value === '') {
                continue;
            }

            $normalized[$groupId] = $value;
        }

        return $normalized;
    }

    private function validateCustomOptions(Produk $produk, array $customGroups, array $selected): array
    {
        $validated = [];
        $allowedGroupIds = [];

        foreach ($customGroups as $group) {
            $groupId = (string) ($group['id'] ?? '');
            if ($groupId === '') {
                continue;
            }

            $allowedGroupIds[$groupId] = true;
            $options = is_array($group['options'] ?? null) ? $group['options'] : [];
            $required = (bool) ($group['required'] ?? false);
            $value = $selected[$groupId] ?? null;

            if ($required && (! is_string($value) || $value === '')) {
                throw ValidationException::withMessages([
                    'items' => 'Produk ' . $produk->nama_produk . ' wajib memilih opsi ' . ($group['label'] ?? $groupId) . '.',
                ]);
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $isAllowed = false;
            foreach ($options as $option) {
                if (($option['value'] ?? null) === $value) {
                    $isAllowed = true;
                    break;
                }
            }

            if (! $isAllowed) {
                throw ValidationException::withMessages([
                    'items' => 'Pilihan opsi ' . ($group['label'] ?? $groupId) . ' untuk produk ' . $produk->nama_produk . ' tidak valid.',
                ]);
            }

            $validated[$groupId] = $value;
        }

        foreach ($selected as $groupId => $value) {
            if (isset($allowedGroupIds[$groupId]) && is_string($value) && $value !== '') {
                $validated[$groupId] = $value;
            }
        }

        return $validated;
    }

    private function resolveOrCreatePelanggan(array $customer): Pelanggan
    {
        $name = trim((string) ($customer['name'] ?? ''));
        $phone = trim((string) ($customer['phone'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'customer.name' => 'Nama pelanggan Telegram wajib diisi.',
            ]);
        }

        $pelanggan = null;
        if ($phone !== '') {
            $pelanggan = Pelanggan::query()->where('no_telepon', $phone)->first();
        }

        if (! $pelanggan && $phone === '') {
            $pelanggan = Pelanggan::query()
                ->whereRaw('LOWER(TRIM(nama)) = ?', [Str::lower($name)])
                ->first();
        }

        if (! $pelanggan) {
            return Pelanggan::query()->create([
                'nama' => $name,
                'username_ig' => null,
                'no_telepon' => $phone !== '' ? $phone : null,
            ]);
        }

        $updates = [];
        if (trim((string) $pelanggan->nama) === '') {
            $updates['nama'] = $name;
        }
        if ($phone !== '' && trim((string) $pelanggan->no_telepon) === '') {
            $updates['no_telepon'] = $phone;
        }
        if ($updates !== []) {
            $pelanggan->fill($updates)->save();
        }

        return $pelanggan;
    }

    private function isDuplicateExternalOrderRef(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && str_contains(strtolower($exception->getMessage()), 'offline_ref');
    }

    private function resolveOrderNote(array $validated): ?string
    {
        $note = trim((string) ($validated['catatan_pesanan'] ?? ''));

        return $note !== '' ? $note : null;
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

        $today = now()->toDateString();
        $lastNoFallback = DB::table('pesanan')
            ->whereNull('kasir_shift_session_id')
            ->whereDate('waktu_pembayaran', $today)
            ->orderByDesc('no_urut_shift')
            ->lockForUpdate()
            ->value('no_urut_shift');

        return [null, max(1, ((int) $lastNoFallback) + 1)];
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

    private function resolveLinePrice(Produk $produk, array $line): float
    {
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

            foreach (($group['options'] ?? []) as $option) {
                if (($option['value'] ?? null) === $selected[$groupId]) {
                    $total += max(0, (float) ($option['extra_price'] ?? 0));
                    break;
                }
            }
        }

        return $total;
    }
}
