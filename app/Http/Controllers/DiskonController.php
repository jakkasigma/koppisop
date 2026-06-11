<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Diskon;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\PromoBundling;
use App\Services\PromoAutoExpire;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DiskonController extends Controller
{
    public function index(): View
    {
        PromoAutoExpire::run();
        $today = now()->toDateString();

        $diskon = Diskon::with('kategoriTarget')->get()
            ->sortBy(function (Diskon $item) use ($today): string {
                if (! $item->status_aktif) {
                    $rank = 4;
                } elseif ($item->isAktifPada(now())) {
                    $rank = 1;
                } else {
                    $mulai = $item->tanggal_mulai?->toDateString();
                    $rank = ($mulai !== null && $mulai > $today) ? 2 : 3;
                }

                return sprintf('%d|%s', $rank, mb_strtolower((string) $item->nama_diskon));
            })
            ->values();
        $promoBundling = PromoBundling::with('items.produk')
            ->orderByDesc('status_aktif')
            ->orderBy('nama_promo')
            ->get();

        return view('diskon.index', [
            'diskon' => $diskon,
            'promoBundling' => $promoBundling,
            'kategori' => Kategori::orderBy('nama_kategori')->get(),
            'produk' => Produk::orderBy('nama_produk')->get(),
        ]);
    }

    public function create(): View
    {
        return view('diskon.create', [
            'kategori' => Kategori::orderBy('nama_kategori')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $diskon = Diskon::create($data);
        $this->announceNewDiskon($diskon);

        return redirect()->route('diskon.index')->with('success', 'Diskon berhasil ditambahkan.');
    }

    public function edit(Diskon $diskon): View
    {
        return view('diskon.edit', [
            'diskon' => $diskon,
            'kategori' => Kategori::orderBy('nama_kategori')->get(),
        ]);
    }

    public function update(Request $request, Diskon $diskon): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $wasActive = (bool) ($diskon->status_aktif ?? false);
        $diskon->update($data);
        $nowActive = (bool) ($diskon->status_aktif ?? false);
        if ($wasActive && ! $nowActive) {
            $this->announceDiskonEnded($diskon);
        }

        return redirect()->route('diskon.index')->with('success', 'Diskon berhasil diperbarui.');
    }

    public function destroy(Diskon $diskon): RedirectResponse
    {
        if ($diskon->pesanan()->exists()) {
            return back()->withErrors(['diskon' => 'Diskon sudah dipakai pada transaksi, jadi tidak bisa dihapus.']);
        }

        $diskon->delete();

        return redirect()->route('diskon.index')->with('success', 'Diskon berhasil dihapus.');
    }

    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'nama_diskon' => ['required', 'string', 'max:100'],
            'tipe_diskon' => ['required', 'in:persen,nominal,harga_kategori'],
            'nilai_diskon' => ['nullable', 'numeric', 'gt:0'],
            'minimal_belanja' => ['nullable', 'numeric', 'min:0'],
            'maksimal_diskon' => ['nullable', 'numeric', 'min:0'],
            'id_kategori_target' => ['nullable', 'exists:kategori,id_kategori'],
            'harga_spesial' => ['nullable', 'numeric', 'gt:0'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['tipe_diskon'] === 'persen' && (float) ($data['nilai_diskon'] ?? 0) > 100) {
            throw ValidationException::withMessages([
                'nilai_diskon' => 'Diskon persen maksimal 100%.',
            ]);
        }

        $data['status_aktif'] = $request->boolean('status_aktif');
        $data['minimal_belanja'] = (float) ($data['minimal_belanja'] ?? 0);
        if ($data['tipe_diskon'] !== 'harga_kategori' && (! isset($data['nilai_diskon']) || (float) $data['nilai_diskon'] <= 0)) {
            throw ValidationException::withMessages([
                'nilai_diskon' => 'Nilai diskon wajib diisi.',
            ]);
        }
        $data['nilai_diskon'] = (float) ($data['nilai_diskon'] ?? 0);
        $data['maksimal_diskon'] = $data['tipe_diskon'] === 'persen'
            ? ((isset($data['maksimal_diskon']) && $data['maksimal_diskon'] !== '') ? (float) $data['maksimal_diskon'] : null)
            : null;
        $data['id_kategori_target'] = ! empty($data['id_kategori_target'])
            ? (int) $data['id_kategori_target']
            : null;

        if ($data['tipe_diskon'] === 'harga_kategori') {
            if (empty($data['id_kategori_target'])) {
                throw ValidationException::withMessages([
                    'id_kategori_target' => 'Pilih kategori target untuk promo harga khusus.',
                ]);
            }
            if (! isset($data['harga_spesial']) || (float) $data['harga_spesial'] <= 0) {
                throw ValidationException::withMessages([
                    'harga_spesial' => 'Isi harga khusus untuk kategori target.',
                ]);
            }
            $data['harga_spesial'] = (float) $data['harga_spesial'];
            $data['nilai_diskon'] = $data['harga_spesial'];
        } else {
            $data['harga_spesial'] = null;
        }
        $data['keterangan'] = trim((string) ($data['keterangan'] ?? '')) ?: null;

        return $data;
    }

    private function announceNewDiskon(Diskon $diskon): void
    {
        $tipe = (string) ($diskon->tipe_diskon ?? '');
        $nilai = (float) ($diskon->nilai_diskon ?? 0);
        $nama = trim((string) ($diskon->nama_diskon ?? 'Promo'));
        $aktif = (bool) ($diskon->status_aktif ?? false);

        $tipeLabel = match ($tipe) {
            'persen' => 'Diskon Persen',
            'nominal' => 'Diskon Nominal',
            'harga_kategori' => 'Harga Spesial Kategori',
            default => 'Promo',
        };

        $nilaiLabel = $tipe === 'persen'
            ? rtrim(rtrim(number_format($nilai, 2, '.', ''), '0'), '.') . '%'
            : 'Rp ' . number_format($nilai, 0, ',', '.');

        $periodeMulai = $diskon->tanggal_mulai?->format('Y-m-d');
        $periodeSelesai = $diskon->tanggal_selesai?->format('Y-m-d');
        $periode = $periodeMulai && $periodeSelesai ? "Periode: {$periodeMulai} s/d {$periodeSelesai}" : null;

        $minBelanja = (float) ($diskon->minimal_belanja ?? 0);
        $minBelanjaLabel = $minBelanja > 0 ? 'Min belanja: Rp ' . number_format($minBelanja, 0, ',', '.') : null;

        $bodyParts = array_filter([
            "{$tipeLabel} {$nama}",
            "Nilai: {$nilaiLabel}",
            $periode,
            $minBelanjaLabel,
            $aktif ? 'Status: Aktif' : 'Status: Nonaktif',
        ]);

        Announcement::create([
            'title' => 'Promo Baru',
            'body' => implode("\n", $bodyParts),
            'target_role' => null,
            'is_active' => $aktif,
            'published_at' => now(),
        ]);
    }

    private function announceDiskonEnded(Diskon $diskon): void
    {
        $tipe = (string) ($diskon->tipe_diskon ?? '');
        $nama = trim((string) ($diskon->nama_diskon ?? 'Promo'));
        $tipeLabel = match ($tipe) {
            'persen' => 'Diskon Persen',
            'nominal' => 'Diskon Nominal',
            'harga_kategori' => 'Harga Spesial Kategori',
            default => 'Promo',
        };
        $periodeSelesai = $diskon->tanggal_selesai?->format('Y-m-d');

        $bodyParts = array_filter([
            "{$tipeLabel} {$nama}",
            $periodeSelesai ? "Berakhir pada: {$periodeSelesai}" : null,
            'Status: Nonaktif',
        ]);

        Announcement::create([
            'title' => 'Promo Berakhir',
            'body' => implode("\n", $bodyParts),
            'target_role' => null,
            'is_active' => false,
            'published_at' => now(),
        ]);
    }
}
