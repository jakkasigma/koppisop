<?php

namespace App\Http\Controllers;

use App\Models\CafeProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CafeProfileController extends Controller
{
    public function edit(): View
    {
        $profile = CafeProfile::firstOrCreate(
            ['id' => 1],
            ['nama_cafe' => 'Cafe']
        );

        return view('cafe-profile.edit', [
            'profile' => $profile,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = CafeProfile::firstOrCreate(
            ['id' => 1],
            ['nama_cafe' => 'Cafe']
        );

        $data = $request->validate([
            'nama_cafe' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'kota' => ['nullable', 'string', 'max:80'],
            'telepon' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'instagram' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if (! empty($profile->logo_path)) {
                Storage::disk('public')->delete($profile->logo_path);
            }
            $profile->logo_path = $request->file('logo')->store('cafe-logo', 'public');
        }

        $profile->fill([
            'nama_cafe' => $data['nama_cafe'],
            'tagline' => $data['tagline'] ?? null,
            'alamat' => $data['alamat'] ?? null,
            'kota' => $data['kota'] ?? null,
            'telepon' => $data['telepon'] ?? null,
            'email' => $data['email'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'website' => $data['website'] ?? null,
            'deskripsi' => $data['deskripsi'] ?? null,
        ]);
        $profile->save();

        return back()->with('success', 'Profil cafe berhasil disimpan.');
    }
}
