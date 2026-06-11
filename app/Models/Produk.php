<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    protected $table = 'produk';
    protected $primaryKey = 'id_produk';
    public $timestamps = false;

    protected $fillable = [
        'nama_produk',
        'harga',
        'id_kategori',
        'deskripsi',
        'stok',
        'is_temperature_enabled',
        'temperature_options',
        'is_sugar_enabled',
        'sugar_options',
        'is_cup_size_enabled',
        'cup_size_options',
        'is_spicy_enabled',
        'spicy_options',
        'custom_option_groups',
    ];

    protected $casts = [
        'is_temperature_enabled' => 'boolean',
        'temperature_options' => 'array',
        'is_sugar_enabled' => 'boolean',
        'sugar_options' => 'array',
        'is_cup_size_enabled' => 'boolean',
        'cup_size_options' => 'array',
        'is_spicy_enabled' => 'boolean',
        'spicy_options' => 'array',
        'custom_option_groups' => 'array',
    ];

    public const DEFAULT_TEMPERATURE_OPTIONS = [
        ['value' => 'hot', 'label' => 'Hot', 'extra_price' => 0],
        ['value' => 'ice', 'label' => 'Es', 'extra_price' => 0],
        ['value' => 'less_ice', 'label' => 'Less Es', 'extra_price' => 0],
    ];

    public const DEFAULT_SUGAR_OPTIONS = [
        ['value' => 'normal', 'label' => 'Normal Sugar', 'extra_price' => 0],
        ['value' => 'less', 'label' => 'Less Sugar', 'extra_price' => 0],
        ['value' => 'none', 'label' => 'No Sugar', 'extra_price' => 0],
    ];

    public const DEFAULT_CUP_SIZE_OPTIONS = [
        ['value' => 'regular', 'label' => 'Regular', 'extra_price' => 0],
        ['value' => 'large', 'label' => 'Large', 'extra_price' => 2000],
    ];

    public const DEFAULT_SPICY_OPTIONS = [
        ['value' => 'non_spicy', 'label' => 'Non Spicy', 'extra_price' => 0],
        ['value' => 'spicy', 'label' => 'Spicy', 'extra_price' => 0],
        ['value' => 'extra_spicy', 'label' => 'Extra Spicy', 'extra_price' => 0],
    ];

    public function resolvedTemperatureOptions(): array
    {
        return $this->resolveOptionGroup($this->temperature_options, self::DEFAULT_TEMPERATURE_OPTIONS);
    }

    public function resolvedSugarOptions(): array
    {
        return $this->resolveOptionGroup($this->sugar_options, self::DEFAULT_SUGAR_OPTIONS);
    }

    public function resolvedCupSizeOptions(): array
    {
        return $this->resolveOptionGroup($this->cup_size_options, self::DEFAULT_CUP_SIZE_OPTIONS);
    }

    public function resolvedSpicyOptions(): array
    {
        return $this->resolveOptionGroup($this->spicy_options, self::DEFAULT_SPICY_OPTIONS);
    }

    private function resolveOptionGroup(mixed $rawOptions, array $fallback): array
    {
        if (! is_array($rawOptions) || $rawOptions === []) {
            return $fallback;
        }

        $resolved = [];
        $usedValues = [];

        foreach ($rawOptions as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = strtolower(trim((string) ($row['value'] ?? '')));
            $label = trim((string) ($row['label'] ?? ''));
            $extraPrice = max(0, (int) ($row['extra_price'] ?? 0));

            if ($value === '' || $label === '' || isset($usedValues[$value])) {
                continue;
            }

            $resolved[] = ['value' => $value, 'label' => $label, 'extra_price' => $extraPrice];
            $usedValues[$value] = true;
        }

        return $resolved !== [] ? $resolved : $fallback;
    }

    public function resolvedCustomOptionGroups(): array
    {
        $rawGroups = $this->custom_option_groups;
        if (! is_array($rawGroups) || $rawGroups === []) {
            return [];
        }

        $groups = [];
        $usedIds = [];
        foreach ($rawGroups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $id = strtolower(trim((string) ($group['id'] ?? '')));
            $label = trim((string) ($group['label'] ?? ''));
            $required = (bool) ($group['required'] ?? false);
            $options = is_array($group['options'] ?? null) ? $group['options'] : [];

            if ($id === '' || $label === '' || isset($usedIds[$id])) {
                continue;
            }

            $cleanedOptions = [];
            $usedValues = [];
            foreach ($options as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $value = strtolower(trim((string) ($option['value'] ?? '')));
                $optionLabel = trim((string) ($option['label'] ?? ''));
                $extraPrice = max(0, (int) ($option['extra_price'] ?? 0));

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
                'label' => $label,
                'required' => $required,
                'options' => $cleanedOptions,
            ];
            $usedIds[$id] = true;
        }

        return $groups;
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'id_kategori', 'id_kategori');
    }

    public function detailPesanan()
    {
        return $this->hasMany(DetailPesanan::class, 'id_produk', 'id_produk');
    }
}
