<?php

namespace App\Http\Middleware;

use App\Models\KasirShiftSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKasirShiftStarted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role !== 'kasir') {
            return $next($request);
        }

        if ($request->routeIs('kasir.shift.*')) {
            return $next($request);
        }

        $hasActiveShift = KasirShiftSession::query()
            ->forUser((int) $user->id)
            ->active()
            ->exists();

        if (! $hasActiveShift) {
            return redirect()
                ->route('kasir.shift.start')
                ->withErrors(['shift' => 'Mulai shift dulu (pilih sesi shift aktif + kas awal sistem) sebelum menggunakan kasir.']);
        }

        return $next($request);
    }
}
