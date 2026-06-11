<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KaryawanController extends Controller
{
    public function index(): View
    {
        return view('karyawan.index', [
            'karyawan' => Karyawan::orderBy('id_karyawan')->get(),
        ]);
    }

    public function create(): View
    {
        return view('karyawan.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_karyawan' => ['required', 'string', 'max:100'],
            'jabatan' => ['nullable', 'string', 'max:50'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'employment_type' => ['required', Rule::in(array_keys(Karyawan::employmentTypeOptions()))],
            'monthly_salary' => ['nullable', 'integer', 'min:0'],
            'hourly_rate' => ['nullable', 'integer', 'min:0'],
            'pin' => ['required', 'string', 'regex:/^[0-9]{4,8}$/'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $pin = trim((string) ($data['pin'] ?? ''));
        unset($data['pin']);

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['employment_type'] = Karyawan::normalizeEmploymentType((string) ($data['employment_type'] ?? null));
        $data['monthly_salary'] = array_key_exists('monthly_salary', $data) && $data['monthly_salary'] !== null
            ? (int) $data['monthly_salary']
            : null;
        $data['hourly_rate'] = array_key_exists('hourly_rate', $data) && $data['hourly_rate'] !== null
            ? (int) $data['hourly_rate']
            : null;

        if ($data['employment_type'] === Karyawan::EMPLOYMENT_FULL_TIME && ($data['monthly_salary'] ?? null) === null) {
            return back()->withErrors(['monthly_salary' => 'Gaji bulanan wajib diisi untuk karyawan Full Time.'])->withInput();
        }

        if ($data['employment_type'] === Karyawan::EMPLOYMENT_PART_TIME && ($data['hourly_rate'] ?? null) === null) {
            return back()->withErrors(['hourly_rate' => 'Tarif per jam wajib diisi untuk karyawan Part Time.'])->withInput();
        }

        $digest = Karyawan::pinDigest($pin);
        if (Karyawan::query()->where('pin_digest', $digest)->exists()) {
            return back()->withErrors(['pin' => 'PIN sudah dipakai karyawan lain.'])->withInput();
        }
        $data['pin_digest'] = $digest;
        $data['pin_encrypted'] = Crypt::encryptString($pin);

        Karyawan::create($data);

        return redirect()->route('karyawan.index')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function edit(Karyawan $karyawan): View
    {
        return view('karyawan.edit', compact('karyawan'));
    }

    public function show(Karyawan $karyawan): View
    {
        return view('karyawan.show', compact('karyawan'));
    }

    public function update(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $data = $request->validate([
            'nama_karyawan' => ['required', 'string', 'max:100'],
            'jabatan' => ['nullable', 'string', 'max:50'],
            'no_telepon' => ['nullable', 'string', 'max:20'],
            'employment_type' => ['required', Rule::in(array_keys(Karyawan::employmentTypeOptions()))],
            'monthly_salary' => ['nullable', 'integer', 'min:0'],
            'hourly_rate' => ['nullable', 'integer', 'min:0'],
            'pin' => [
                Rule::requiredIf(!is_string($karyawan->pin_digest ?? null) || trim((string) $karyawan->pin_digest) === ''),
                'nullable',
                'string',
                'regex:/^[0-9]{4,8}$/',
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $pin = trim((string) ($data['pin'] ?? ''));
        unset($data['pin']);
        $data['employment_type'] = Karyawan::normalizeEmploymentType((string) ($data['employment_type'] ?? null));
        $data['monthly_salary'] = array_key_exists('monthly_salary', $data) && $data['monthly_salary'] !== null
            ? (int) $data['monthly_salary']
            : null;
        $data['hourly_rate'] = array_key_exists('hourly_rate', $data) && $data['hourly_rate'] !== null
            ? (int) $data['hourly_rate']
            : null;

        if ($data['employment_type'] === Karyawan::EMPLOYMENT_FULL_TIME && ($data['monthly_salary'] ?? null) === null) {
            return back()->withErrors(['monthly_salary' => 'Gaji bulanan wajib diisi untuk karyawan Full Time.'])->withInput();
        }

        if ($data['employment_type'] === Karyawan::EMPLOYMENT_PART_TIME && ($data['hourly_rate'] ?? null) === null) {
            return back()->withErrors(['hourly_rate' => 'Tarif per jam wajib diisi untuk karyawan Part Time.'])->withInput();
        }

        if ($pin !== '') {
            $digest = Karyawan::pinDigest($pin);
            $exists = Karyawan::query()
                ->where('pin_digest', $digest)
                ->where('id_karyawan', '!=', $karyawan->id_karyawan)
                ->exists();
            if ($exists) {
                return back()->withErrors(['pin' => 'PIN sudah dipakai karyawan lain.'])->withInput();
            }
            $data['pin_digest'] = $digest;
            $data['pin_encrypted'] = Crypt::encryptString($pin);
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }

        $karyawan->update($data);

        return redirect()->route('karyawan.index')->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function updateActive(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $data['is_active'];
        $karyawan->update(['is_active' => $isActive]);

        return redirect()
            ->route('karyawan.index')
            ->with('success', 'Status karyawan berhasil diperbarui.');
    }

    public function showPin(Request $request, Karyawan $karyawan)
    {
        // Admin-only endpoint to reveal PIN temporarily in UI.
        // NOTE: Old rows may have pin_digest but no pin_encrypted (cannot be revealed).
        $enc = (string) ($karyawan->pin_encrypted ?? '');
        if (trim($enc) === '') {
            return response()->json([
                'ok' => false,
                'message' => 'PIN tidak dapat ditampilkan karena dibuat sebelum fitur ini. Silakan set ulang PIN di halaman Edit.',
            ], 422);
        }

        try {
            $pin = Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'PIN tidak dapat ditampilkan (data terenkripsi tidak valid). Silakan set ulang PIN.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'pin' => (string) $pin,
        ]);
    }

    public function destroy(Karyawan $karyawan): RedirectResponse
    {
        if ($karyawan->pesanan()->exists()) {
            return back()->withErrors(['karyawan' => 'Karyawan tidak bisa dihapus karena sudah ada transaksi.']);
        }

        $karyawan->delete();

        return redirect()->route('karyawan.index')->with('success', 'Karyawan berhasil dihapus.');
    }
}
