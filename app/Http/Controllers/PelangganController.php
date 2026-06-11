<?php

namespace App\Http\Controllers;

use App\Models\Pelanggan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PelangganController extends Controller
{
    public function index(): View
    {
        return view('pelanggan.index', [
            'pelanggan' => Pelanggan::orderBy('id_pelanggan')->get(),
        ]);
    }

    public function create(): View
    {
        return view('pelanggan.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'username_ig' => ['nullable', 'string', 'max:100'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
        ]);

        Pelanggan::create($data);

        return redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function edit(Pelanggan $pelanggan): View
    {
        return view('pelanggan.edit', compact('pelanggan'));
    }

    public function update(Request $request, Pelanggan $pelanggan): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'username_ig' => ['nullable', 'string', 'max:100'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
        ]);

        $pelanggan->update($data);

        return redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Pelanggan $pelanggan): RedirectResponse
    {
        if ($pelanggan->pesanan()->exists()) {
            return back()->withErrors(['pelanggan' => 'Pelanggan tidak bisa dihapus karena sudah ada transaksi.']);
        }

        $pelanggan->delete();

        return redirect()->route('pelanggan.index')->with('success', 'Pelanggan berhasil dihapus.');
    }
}
