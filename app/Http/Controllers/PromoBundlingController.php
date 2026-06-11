<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Produk;
use App\Models\PromoBundling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PromoBundlingController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('diskon.index');
    }

    public function create(): View
    {
        return view('promo-bundling.create', [
            'produk' => Produk::orderBy('nama_produk')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $items] = $this->validatePayload($request);

        $promo = PromoBundling::create($data);
        $promo->items()->createMany($items);
        $this->announceNewBundling($promo);

        return redirect()->route('bundling.index')->with('success', 'Promo bundling berhasil ditambahkan.');
    }

    public function edit(PromoBundling $bundling): View
    {
        $bundling->load('items');

        return view('promo-bundling.edit', [
            'bundling' => $bundling,
            'produk' => Produk::orderBy('nama_produk')->get(),
        ]);
    }

    public function update(Request $request, PromoBundling $bundling): RedirectResponse
    {
        [$data, $items] = $this->validatePayload($request);

        $wasActive = (bool) ($bundling->status_aktif ?? false);
        $bundling->update($data);
        $bundling->items()->delete();
        $bundling->items()->createMany($items);
        $nowActive = (bool) ($bundling->status_aktif ?? false);
        if ($wasActive && ! $nowActive) {
            $this->announceBundlingEnded($bundling);
        }

        return redirect()->route('bundling.index')->with('success', 'Promo bundling berhasil diperbarui.');
    }

    public function destroy(PromoBundling $bundling): RedirectResponse
    {
        $bundling->delete();
        return redirect()->route('bundling.index')->with('success', 'Promo bundling berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'nama_promo' => ['required', 'string', 'max:100'],
            'harga_bundle' => ['required', 'numeric', 'gt:0'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id_produk' => ['required', 'exists:produk,id_produk'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $data['status_aktif'] = $request->boolean('status_aktif');
        $data['keterangan'] = trim((string) ($data['keterangan'] ?? '')) ?: null;

        $items = collect((array) $data['items'])
            ->map(fn (array $item): array => [
                'id_produk' => (int) $item['id_produk'],
                'qty' => (int) $item['qty'],
            ]);

        $mergedItems = $this->mergeDuplicateItems($items)->values()->all();

        unset($data['items']);

        return [$data, $mergedItems];
    }

    private function mergeDuplicateItems(Collection $items): Collection
    {
        $map = [];
        foreach ($items as $item) {
            $idProduk = (int) ($item['id_produk'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);
            if ($idProduk <= 0 || $qty <= 0) {
                continue;
            }

            $map[$idProduk] = ($map[$idProduk] ?? 0) + $qty;
        }

        return collect($map)->map(fn (int $qty, int $idProduk): array => [
            'id_produk' => $idProduk,
            'qty' => $qty,
        ])->values();
    }

    private function announceNewBundling(PromoBundling $promo): void
    {
        $nama = trim((string) ($promo->nama_promo ?? 'Bundling'));
        $harga = (float) ($promo->harga_bundle ?? 0);
        $aktif = (bool) ($promo->status_aktif ?? false);

        $periodeMulai = $promo->tanggal_mulai?->format('Y-m-d');
        $periodeSelesai = $promo->tanggal_selesai?->format('Y-m-d');
        $periode = $periodeMulai && $periodeSelesai ? "Periode: {$periodeMulai} s/d {$periodeSelesai}" : null;

        $bodyParts = array_filter([
            "Bundling {$nama}",
            'Harga bundle: Rp ' . number_format($harga, 0, ',', '.'),
            $periode,
            $aktif ? 'Status: Aktif' : 'Status: Nonaktif',
        ]);

        Announcement::create([
            'title' => 'Promo Bundling Baru',
            'body' => implode("\n", $bodyParts),
            'target_role' => null,
            'is_active' => $aktif,
            'published_at' => now(),
        ]);
    }

    private function announceBundlingEnded(PromoBundling $promo): void
    {
        $nama = trim((string) ($promo->nama_promo ?? 'Bundling'));
        $periodeSelesai = $promo->tanggal_selesai?->format('Y-m-d');

        $bodyParts = array_filter([
            "Bundling {$nama}",
            $periodeSelesai ? "Berakhir pada: {$periodeSelesai}" : null,
            'Status: Nonaktif',
        ]);

        Announcement::create([
            'title' => 'Promo Bundling Berakhir',
            'body' => implode("\n", $bodyParts),
            'target_role' => null,
            'is_active' => false,
            'published_at' => now(),
        ]);
    }
}
