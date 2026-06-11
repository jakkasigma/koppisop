<?php

namespace App\Http\Controllers;

use App\Models\MasterOpsiKasir;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MasterOpsiKasirController extends Controller
{
    public function index(): View
    {
        return view('master-opsi-kasir.index', [
            'items' => MasterOpsiKasir::orderBy('urutan')->orderBy('nama_opsi')->get(),
        ]);
    }

    public function create(): View
    {
        return view('master-opsi-kasir.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_opsi' => ['required', 'string', 'max:50'],
            'kode_opsi' => ['nullable', 'string', 'max:30', 'unique:master_opsi_kasir,kode_opsi'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'opsi_text' => ['required', 'string', 'max:4000'],
        ]);

        $kode = trim((string) ($data['kode_opsi'] ?? ''));
        if ($kode === '') {
            $kode = Str::of($data['nama_opsi'])->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
        }

        MasterOpsiKasir::create([
            'nama_opsi' => trim((string) $data['nama_opsi']),
            'kode_opsi' => Str::limit(strtolower($kode), 30, ''),
            'urutan' => (int) ($data['urutan'] ?? 0),
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active', true),
            'opsi' => $this->parseOptionsText($data['opsi_text']),
        ]);

        return redirect()->route('master_opsi_kasir.index')->with('success', 'Master opsi kasir berhasil ditambahkan.');
    }

    public function edit(MasterOpsiKasir $masterOpsiKasir): View
    {
        return view('master-opsi-kasir.edit', [
            'item' => $masterOpsiKasir,
        ]);
    }

    public function update(Request $request, MasterOpsiKasir $masterOpsiKasir): RedirectResponse
    {
        $data = $request->validate([
            'nama_opsi' => ['required', 'string', 'max:50'],
            'kode_opsi' => ['required', 'string', 'max:30', 'unique:master_opsi_kasir,kode_opsi,' . $masterOpsiKasir->id],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'opsi_text' => ['required', 'string', 'max:4000'],
        ]);

        $masterOpsiKasir->update([
            'nama_opsi' => trim((string) $data['nama_opsi']),
            'kode_opsi' => Str::limit(strtolower(trim((string) $data['kode_opsi'])), 30, ''),
            'urutan' => (int) ($data['urutan'] ?? 0),
            'is_required' => $request->boolean('is_required'),
            'is_active' => $request->boolean('is_active'),
            'opsi' => $this->parseOptionsText($data['opsi_text']),
        ]);

        return redirect()->route('master_opsi_kasir.index')->with('success', 'Master opsi kasir berhasil diperbarui.');
    }

    public function destroy(MasterOpsiKasir $masterOpsiKasir): RedirectResponse
    {
        $masterOpsiKasir->delete();
        return redirect()->route('master_opsi_kasir.index')->with('success', 'Master opsi kasir berhasil dihapus.');
    }

    private function parseOptionsText(string $text): array
    {
        $rows = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $result = [];
        $used = [];

        foreach ($rows as $row) {
            $line = trim((string) $row);
            if ($line === '') {
                continue;
            }

            [$rawValue, $rawLabel, $rawExtra] = array_pad(explode('|', $line, 3), 3, '');
            $value = strtolower(trim($rawValue));
            $label = trim($rawLabel);
            $extra = max(0, (int) trim($rawExtra));

            if ($value === '' && $label !== '') {
                $value = Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
            }
            if ($label === '' && $value !== '') {
                $label = Str::of($value)->replace('_', ' ')->title()->value();
            }

            if ($value === '' || $label === '' || isset($used[$value])) {
                continue;
            }

            $result[] = [
                'value' => Str::limit($value, 30, ''),
                'label' => Str::limit($label, 40, ''),
                'extra_price' => $extra,
            ];
            $used[$value] = true;
        }

        return $result;
    }
}
