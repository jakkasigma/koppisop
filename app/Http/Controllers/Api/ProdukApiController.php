<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProdukApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kategori_id' => ['nullable', 'integer', 'exists:kategori,id_kategori'],
            'tersedia' => ['nullable', 'boolean'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $kategoriId = isset($validated['kategori_id']) ? (int) $validated['kategori_id'] : null;
        $onlyAvailable = filter_var($request->query('tersedia'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $produk = Produk::query()
            ->with('kategori')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('nama_produk', 'like', '%' . $search . '%')
                        ->orWhere('deskripsi', 'like', '%' . $search . '%');
                });
            })
            ->when($kategoriId !== null, fn ($query) => $query->where('id_kategori', $kategoriId))
            ->when($onlyAvailable === true, fn ($query) => $query->where('stok', '>', 0))
            ->orderBy('nama_produk')
            ->get();

        return response()->json([
            'message' => 'Daftar produk berhasil diambil.',
            'data' => $produk->map(fn (Produk $item): array => [
                'id_produk' => (int) $item->id_produk,
                'nama_produk' => (string) $item->nama_produk,
                'harga' => (float) $item->harga,
                'deskripsi' => $item->deskripsi,
                'stok' => (int) $item->stok,
                'tersedia' => (int) $item->stok > 0,
                'kategori' => [
                    'id_kategori' => (int) ($item->kategori?->id_kategori ?? 0),
                    'nama_kategori' => $item->kategori?->nama_kategori,
                ],
                'opsi' => [
                    'temperature_enabled' => (bool) $item->is_temperature_enabled,
                    'temperature_options' => $item->is_temperature_enabled ? $item->resolvedTemperatureOptions() : [],
                    'sugar_enabled' => (bool) $item->is_sugar_enabled,
                    'sugar_options' => $item->is_sugar_enabled ? $item->resolvedSugarOptions() : [],
                    'cup_size_enabled' => (bool) $item->is_cup_size_enabled,
                    'cup_size_options' => $item->is_cup_size_enabled ? $item->resolvedCupSizeOptions() : [],
                    'spicy_enabled' => (bool) $item->is_spicy_enabled,
                    'spicy_options' => $item->is_spicy_enabled ? $item->resolvedSpicyOptions() : [],
                    'custom_option_groups' => $item->resolvedCustomOptionGroups(),
                ],
            ])->values()->all(),
            'meta' => [
                'total' => $produk->count(),
                'filters' => [
                    'search' => $search !== '' ? $search : null,
                    'kategori_id' => $kategoriId,
                    'tersedia' => $onlyAvailable,
                ],
            ],
        ]);
    }
}
