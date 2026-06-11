<?php

namespace App\Http\Controllers;

use App\Models\StrukSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StrukSettingController extends Controller
{
    public function edit(): View
    {
        return view('struk-setting.edit', [
            'setting' => StrukSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_toko' => ['required', 'string', 'max:120'],
            'nama_cabang' => ['nullable', 'string', 'max:120'],
            'alamat_toko' => ['nullable', 'string', 'max:255'],
            'header_text' => ['nullable', 'string', 'max:2000'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
            'mode_template' => ['nullable', 'in:global,per_role'],
            'nama_toko_admin' => ['nullable', 'string', 'max:120'],
            'alamat_toko_admin' => ['nullable', 'string', 'max:255'],
            'header_text_admin' => ['nullable', 'string', 'max:2000'],
            'footer_text_admin' => ['nullable', 'string', 'max:2000'],
            'nama_toko_kasir' => ['nullable', 'string', 'max:120'],
            'alamat_toko_kasir' => ['nullable', 'string', 'max:255'],
            'header_text_kasir' => ['nullable', 'string', 'max:2000'],
            'footer_text_kasir' => ['nullable', 'string', 'max:2000'],
            'show_logo' => ['nullable', 'boolean'],
            'logo_max_width' => ['nullable', 'integer', 'min:60', 'max:220'],
            'show_kode_nota' => ['nullable', 'boolean'],
            'show_id_pesanan' => ['nullable', 'boolean'],
            'show_waktu' => ['nullable', 'boolean'],
            'show_pelanggan' => ['nullable', 'boolean'],
            'show_kasir' => ['nullable', 'boolean'],
            'show_metode' => ['nullable', 'boolean'],
            'show_status' => ['nullable', 'boolean'],
            'auto_print_checker' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $setting = StrukSetting::current();

        if ($request->hasFile('logo')) {
            if (! empty($setting->logo_path)) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $setting->logo_path = $request->file('logo')->store('struk-logo', 'public');
        }

        $setting->nama_toko = trim((string) $data['nama_toko']);
        $setting->nama_cabang = isset($data['nama_cabang']) ? trim((string) $data['nama_cabang']) : null;
        $setting->alamat_toko = isset($data['alamat_toko']) ? trim((string) $data['alamat_toko']) : null;
        $setting->header_text = isset($data['header_text']) ? trim((string) $data['header_text']) : null;
        $setting->footer_text = isset($data['footer_text']) ? trim((string) $data['footer_text']) : null;
        $setting->mode_template = (string) ($data['mode_template'] ?? 'global');
        $setting->nama_toko_admin = isset($data['nama_toko_admin']) ? trim((string) $data['nama_toko_admin']) : null;
        $setting->alamat_toko_admin = isset($data['alamat_toko_admin']) ? trim((string) $data['alamat_toko_admin']) : null;
        $setting->header_text_admin = isset($data['header_text_admin']) ? trim((string) $data['header_text_admin']) : null;
        $setting->footer_text_admin = isset($data['footer_text_admin']) ? trim((string) $data['footer_text_admin']) : null;
        $setting->nama_toko_kasir = isset($data['nama_toko_kasir']) ? trim((string) $data['nama_toko_kasir']) : null;
        $setting->alamat_toko_kasir = isset($data['alamat_toko_kasir']) ? trim((string) $data['alamat_toko_kasir']) : null;
        $setting->header_text_kasir = isset($data['header_text_kasir']) ? trim((string) $data['header_text_kasir']) : null;
        $setting->footer_text_kasir = isset($data['footer_text_kasir']) ? trim((string) $data['footer_text_kasir']) : null;
        $setting->show_logo = $request->boolean('show_logo');
        $setting->logo_max_width = (int) ($data['logo_max_width'] ?? 120);
        $setting->show_kode_nota = $request->boolean('show_kode_nota');
        $setting->show_id_pesanan = $request->boolean('show_id_pesanan');
        $setting->show_waktu = $request->boolean('show_waktu');
        $setting->show_pelanggan = $request->boolean('show_pelanggan');
        $setting->show_kasir = $request->boolean('show_kasir');
        $setting->show_metode = $request->boolean('show_metode');
        $setting->show_status = $request->boolean('show_status');
        $setting->auto_print_checker = $request->boolean('auto_print_checker');
        $setting->save();

        return redirect()
            ->route('struk_setting.edit')
            ->with('success', 'Pengaturan struk berhasil diperbarui.');
    }

    public function editTheme(): View
    {
        return view('theme-setting.edit', [
            'setting' => StrukSetting::current(),
        ]);
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme_primary' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'theme_secondary' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'theme_bg' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ]);

        $setting = StrukSetting::current();
        $setting->theme_primary = isset($data['theme_primary']) ? strtoupper(trim((string) $data['theme_primary'])) : null;
        $setting->theme_secondary = isset($data['theme_secondary']) ? strtoupper(trim((string) $data['theme_secondary'])) : null;
        $setting->theme_bg = isset($data['theme_bg']) ? strtoupper(trim((string) $data['theme_bg'])) : null;
        $setting->save();

        return redirect()
            ->route('theme_setting.edit')
            ->with('success', 'Tema aplikasi berhasil diperbarui.');
    }
}
