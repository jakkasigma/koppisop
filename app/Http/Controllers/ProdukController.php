<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\MasterOpsiKasir;
use App\Models\Produk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProdukController extends Controller
{
    public function index(): View
    {
        $produk = Produk::with('kategori')->get()
            ->sortBy(fn (Produk $item) => Kategori::normalizedKey($item->kategori?->nama_kategori).'|'.Str::lower($item->nama_produk))
            ->values();

        $kategori = Kategori::all()
            ->sortBy([
                fn (Kategori $item) => Kategori::normalizedKey($item->nama_kategori),
                fn (Kategori $item) => Str::lower($item->nama_kategori ?? ''),
            ])
            ->values();

        return view('produk.index', [
            'produk' => $produk,
            'kategori' => $kategori,
            'masterOpsiKasir' => MasterOpsiKasir::where('is_active', true)->orderBy('urutan')->orderBy('nama_opsi')->get(),
        ]);
    }

    public function create(): View
    {
        $kategori = Kategori::all()
            ->sortBy([
                fn (Kategori $item) => Kategori::normalizedKey($item->nama_kategori),
                fn (Kategori $item) => Str::lower($item->nama_kategori ?? ''),
            ])
            ->values();

        return view('produk.create', [
            'kategori' => $kategori,
            'masterOpsiKasir' => MasterOpsiKasir::where('is_active', true)->orderBy('urutan')->orderBy('nama_opsi')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_produk' => ['required', 'string', 'max:100'],
            'harga' => ['required', 'numeric', 'min:0'],
            'id_kategori' => ['required', 'exists:kategori,id_kategori'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'stok' => ['required', 'integer', 'min:0'],
            'master_opsi' => ['nullable', 'array'],
            'master_opsi.*' => ['string', 'max:30'],
        ]);

        $data['is_temperature_enabled'] = $request->boolean('is_temperature_enabled');
        $data['is_sugar_enabled'] = $request->boolean('is_sugar_enabled');
        $data['is_cup_size_enabled'] = $request->boolean('is_cup_size_enabled');
        $data['is_spicy_enabled'] = $request->boolean('is_spicy_enabled');
        $data['temperature_options'] = $data['is_temperature_enabled'] ? Produk::DEFAULT_TEMPERATURE_OPTIONS : null;
        $data['sugar_options'] = $data['is_sugar_enabled'] ? Produk::DEFAULT_SUGAR_OPTIONS : null;
        $data['cup_size_options'] = $data['is_cup_size_enabled'] ? Produk::DEFAULT_CUP_SIZE_OPTIONS : null;
        $data['spicy_options'] = $data['is_spicy_enabled'] ? Produk::DEFAULT_SPICY_OPTIONS : null;
        $data['custom_option_groups'] = $this->resolveSelectedMasterOpsiGroups((array) $request->input('master_opsi', []));

        Produk::create($data);

        return redirect()->route('produk.index')->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Produk $produk): View
    {
        $kategori = Kategori::all()
            ->sortBy([
                fn (Kategori $item) => Kategori::normalizedKey($item->nama_kategori),
                fn (Kategori $item) => Str::lower($item->nama_kategori ?? ''),
            ])
            ->values();

        return view('produk.edit', [
            'produk' => $produk,
            'kategori' => $kategori,
            'masterOpsiKasir' => MasterOpsiKasir::where('is_active', true)->orderBy('urutan')->orderBy('nama_opsi')->get(),
        ]);
    }

    public function update(Request $request, Produk $produk): RedirectResponse
    {
        $data = $request->validate([
            'nama_produk' => ['required', 'string', 'max:100'],
            'harga' => ['required', 'numeric', 'min:0'],
            'id_kategori' => ['required', 'exists:kategori,id_kategori'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'stok' => ['required', 'integer', 'min:0'],
            'master_opsi' => ['nullable', 'array'],
            'master_opsi.*' => ['string', 'max:30'],
        ]);

        $data['is_temperature_enabled'] = $request->boolean('is_temperature_enabled');
        $data['is_sugar_enabled'] = $request->boolean('is_sugar_enabled');
        $data['is_cup_size_enabled'] = $request->boolean('is_cup_size_enabled');
        $data['is_spicy_enabled'] = $request->boolean('is_spicy_enabled');
        $data['temperature_options'] = $data['is_temperature_enabled']
            ? ($produk->temperature_options ?: Produk::DEFAULT_TEMPERATURE_OPTIONS)
            : null;
        $data['sugar_options'] = $data['is_sugar_enabled']
            ? ($produk->sugar_options ?: Produk::DEFAULT_SUGAR_OPTIONS)
            : null;
        $data['cup_size_options'] = $data['is_cup_size_enabled']
            ? ($produk->cup_size_options ?: Produk::DEFAULT_CUP_SIZE_OPTIONS)
            : null;
        $data['spicy_options'] = $data['is_spicy_enabled']
            ? ($produk->spicy_options ?: Produk::DEFAULT_SPICY_OPTIONS)
            : null;
        $data['custom_option_groups'] = $this->resolveSelectedMasterOpsiGroups((array) $request->input('master_opsi', []));

        $produk->update($data);

        return redirect()->route('produk.index')->with('success', 'Produk berhasil diperbarui.');
    }

    public function optionsIndex(): View
    {
        $produk = Produk::with('kategori')->get()
            ->sortBy(fn (Produk $item) => Kategori::normalizedKey($item->kategori?->nama_kategori).'|'.Str::lower($item->nama_produk))
            ->values();

        return view('produk.options-index', [
            'produk' => $produk,
        ]);
    }

    public function editOptions(Produk $produk): View
    {
        return view('produk.options-edit', [
            'produk' => $produk,
        ]);
    }

    public function updateOptions(Request $request, Produk $produk): RedirectResponse
    {
        $data = $request->validate([
            'temperature_options_text' => ['nullable', 'string', 'max:2000'],
            'sugar_options_text' => ['nullable', 'string', 'max:2000'],
            'cup_size_options_text' => ['nullable', 'string', 'max:2000'],
            'spicy_options_text' => ['nullable', 'string', 'max:2000'],
            'custom_option_groups_json' => ['nullable', 'string', 'max:12000'],
        ]);

        $update = [];
        if ($produk->is_temperature_enabled) {
            $update['temperature_options'] = $this->parseOptionsText($data['temperature_options_text'] ?? null, Produk::DEFAULT_TEMPERATURE_OPTIONS);
        }
        if ($produk->is_sugar_enabled) {
            $update['sugar_options'] = $this->parseOptionsText($data['sugar_options_text'] ?? null, Produk::DEFAULT_SUGAR_OPTIONS);
        }
        if ($produk->is_cup_size_enabled) {
            $update['cup_size_options'] = $this->parseOptionsText($data['cup_size_options_text'] ?? null, Produk::DEFAULT_CUP_SIZE_OPTIONS);
        }
        if ($produk->is_spicy_enabled) {
            $update['spicy_options'] = $this->parseOptionsText($data['spicy_options_text'] ?? null, Produk::DEFAULT_SPICY_OPTIONS);
        }

        $update['custom_option_groups'] = $this->parseCustomOptionGroups($data['custom_option_groups_json'] ?? null);
        $produk->update($update);

        return redirect()->route('produk.options.edit', $produk)->with('success', 'Pengaturan opsi kasir berhasil diperbarui.');
    }

    public function destroy(Produk $produk): RedirectResponse
    {
        if ($produk->detailPesanan()->exists()) {
            return back()->withErrors(['produk' => 'Produk tidak bisa dihapus karena sudah ada di transaksi.']);
        }

        $produk->delete();

        return redirect()->route('produk.index')->with('success', 'Produk berhasil dihapus.');
    }

    private function parseOptionsText(?string $text, array $fallback): array
    {
        $text = trim((string) $text);
        if ($text === '') {
            return $fallback;
        }

        $rows = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $result = [];
        $usedValues = [];

        foreach ($rows as $row) {
            $line = trim((string) $row);
            if ($line === '') {
                continue;
            }

            [$rawValue, $rawLabel, $rawExtraPrice] = array_pad(explode('|', $line, 3), 3, '');
            $value = strtolower(trim($rawValue));
            $label = trim($rawLabel);
            $extraPrice = max(0, (int) trim($rawExtraPrice));

            if ($value === '') {
                $value = Str::of($line)
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '_')
                    ->trim('_')
                    ->value();
            }

            if ($label === '') {
                $label = trim($line);
            }

            if ($value === '' || $label === '' || isset($usedValues[$value])) {
                continue;
            }

            $result[] = [
                'value' => Str::limit($value, 20, ''),
                'label' => Str::limit($label, 30, ''),
                'extra_price' => $extraPrice,
            ];
            $usedValues[$value] = true;
        }

        return $result !== [] ? $result : $fallback;
    }

    private function parseCustomOptionGroups(?string $rawJson): ?array
    {
        $rawJson = trim((string) $rawJson);
        if ($rawJson === '') {
            return null;
        }

        $decoded = json_decode($rawJson, true);
        if (! is_array($decoded)) {
            return null;
        }

        $groups = [];
        $usedGroupIds = [];
        foreach ($decoded as $group) {
            if (! is_array($group)) {
                continue;
            }

            $label = trim((string) ($group['label'] ?? ''));
            $id = trim((string) ($group['id'] ?? ''));
            $required = (bool) ($group['required'] ?? false);

            if ($label === '') {
                continue;
            }

            if ($id === '') {
                $id = Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
            }
            $id = Str::limit(strtolower($id), 30, '');
            if ($id === '' || isset($usedGroupIds[$id])) {
                continue;
            }

            $options = is_array($group['options'] ?? null) ? $group['options'] : [];
            $cleanedOptions = [];
            $usedValues = [];
            foreach ($options as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $value = trim((string) ($option['value'] ?? ''));
                $optionLabel = trim((string) ($option['label'] ?? ''));
                $extraPrice = max(0, (int) ($option['extra_price'] ?? 0));

                if ($value === '' && $optionLabel !== '') {
                    $value = Str::of($optionLabel)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
                }

                $value = Str::limit(strtolower($value), 30, '');
                $optionLabel = Str::limit($optionLabel, 40, '');

                if ($value === '' || $optionLabel === '' || isset($usedValues[$value])) {
                    continue;
                }

                $cleanedOptions[] = [
                    'value' => $value,
                    'label' => $optionLabel,
                    'extra_price' => $extraPrice,
                ];
                $usedValues[$value] = true;
            }

            if ($cleanedOptions === []) {
                continue;
            }

            $groups[] = [
                'id' => $id,
                'label' => Str::limit($label, 40, ''),
                'required' => $required,
                'options' => $cleanedOptions,
            ];
            $usedGroupIds[$id] = true;
        }

        return $groups !== [] ? $groups : null;
    }

    private function resolveSelectedMasterOpsiGroups(array $selectedCodes): ?array
    {
        $codes = collect($selectedCodes)
            ->map(fn ($code) => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($codes === []) {
            return null;
        }

        $templates = MasterOpsiKasir::whereIn('kode_opsi', $codes)
            ->where('is_active', true)
            ->orderBy('urutan')
            ->orderBy('nama_opsi')
            ->get();

        $groups = $templates
            ->map(fn (MasterOpsiKasir $item): array => [
                'id' => $item->kode_opsi,
                'label' => $item->nama_opsi,
                'required' => (bool) $item->is_required,
                'options' => $item->resolvedOptions(),
            ])
            ->filter(fn (array $group): bool => $group['options'] !== [])
            ->values()
            ->all();

        return $groups !== [] ? $groups : null;
    }
}
