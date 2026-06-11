<?php

namespace Database\Seeders;

use App\Models\MasterOpsiKasir;
use Illuminate\Database\Seeder;

class MasterOpsiKasirSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'kode_opsi' => 'saos',
                'nama_opsi' => 'Saos',
                'is_required' => false,
                'is_active' => true,
                'urutan' => 100,
                'opsi' => [
                    ['value' => 'tanpa_saos', 'label' => 'Tanpa Saos', 'extra_price' => 0],
                    ['value' => 'extra_saos', 'label' => 'Extra Saos', 'extra_price' => 2000],
                ],
            ],
            [
                'kode_opsi' => 'topping',
                'nama_opsi' => 'Topping',
                'is_required' => false,
                'is_active' => true,
                'urutan' => 110,
                'opsi' => [
                    ['value' => 'tanpa_topping', 'label' => 'Tanpa Topping', 'extra_price' => 0],
                    ['value' => 'boba', 'label' => 'Boba', 'extra_price' => 4000],
                    ['value' => 'jelly', 'label' => 'Jelly', 'extra_price' => 3000],
                ],
            ],
            [
                'kode_opsi' => 'addon_shot',
                'nama_opsi' => 'Addon Shot',
                'is_required' => false,
                'is_active' => true,
                'urutan' => 120,
                'opsi' => [
                    ['value' => 'tanpa_shot', 'label' => 'Tanpa Extra Shot', 'extra_price' => 0],
                    ['value' => 'extra_1_shot', 'label' => 'Extra 1 Shot', 'extra_price' => 5000],
                    ['value' => 'extra_2_shot', 'label' => 'Extra 2 Shot', 'extra_price' => 9000],
                ],
            ],
        ];

        $allowedCodes = collect($items)->pluck('kode_opsi')->all();
        MasterOpsiKasir::whereNotIn('kode_opsi', $allowedCodes)->delete();

        foreach ($items as $item) {
            MasterOpsiKasir::updateOrCreate(
                ['kode_opsi' => $item['kode_opsi']],
                $item
            );
        }
    }
}
