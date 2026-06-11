<?php

namespace App\Support;

use App\Models\KasirShiftSession;
use App\Models\User;

class AuthRedirects
{
    public static function routeNameFor(?User $user): string
    {
        if (! $user) {
            return 'login';
        }

        if ((string) $user->role === 'admin') {
            return 'dashboard.index';
        }

        if ((string) $user->role === 'kasir') {
            $hasActiveShift = KasirShiftSession::query()
                ->forUser((int) $user->id)
                ->active()
                ->exists();

            return $hasActiveShift ? 'kasir.index' : 'kasir.shift.start';
        }

        return 'login';
    }

    public static function urlFor(?User $user): string
    {
        return route(self::routeNameFor($user));
    }

    public static function sanitizeIntendedFor(User $user, ?string $intended): ?string
    {
        if (! is_string($intended) || trim($intended) === '') {
            return null;
        }

        $path = parse_url($intended, PHP_URL_PATH);
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = '/'.ltrim($path, '/');

        if ((string) $user->role === 'kasir') {
            foreach (['/kasir', '/transaksi'] as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return $intended;
                }
            }
        }

        return null;
    }
}
