<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterOpsiKasir extends Model
{
    protected $table = 'master_opsi_kasir';

    protected $fillable = [
        'kode_opsi',
        'nama_opsi',
        'is_required',
        'is_active',
        'urutan',
        'opsi',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'opsi' => 'array',
    ];

    public function resolvedOptions(): array
    {
        $raw = $this->opsi;
        if (! is_array($raw)) {
            return [];
        }

        $result = [];
        $used = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = strtolower(trim((string) ($row['value'] ?? '')));
            $label = trim((string) ($row['label'] ?? ''));
            $extraPrice = max(0, (int) ($row['extra_price'] ?? 0));

            if ($value === '' || $label === '' || isset($used[$value])) {
                continue;
            }

            $result[] = [
                'value' => $value,
                'label' => $label,
                'extra_price' => $extraPrice,
            ];
            $used[$value] = true;
        }

        return $result;
    }
}
