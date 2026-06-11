<?php

namespace App\Http\Controllers;

use App\Models\KasirShiftSession;
use App\Support\AuthRedirects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Email atau password salah.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $intended = (string) ($request->session()->pull('url.intended') ?? '');
        $safeIntended = $user ? AuthRedirects::sanitizeIntendedFor($user, $intended) : null;

        if ($safeIntended) {
            return redirect()->to($safeIntended);
        }

        return redirect()->to(AuthRedirects::urlFor($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->role === 'kasir') {
            KasirShiftSession::query()
                ->forUser((int) $user->id)
                ->active()
                ->update(['ended_at' => now()]);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
