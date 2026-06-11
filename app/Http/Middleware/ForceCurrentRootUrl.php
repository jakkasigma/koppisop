<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ForceCurrentRootUrl
{
    public function handle(Request $request, Closure $next)
    {
        // Local dev often uses multiple hosts (127.0.0.1, LAN IP, custom .test domain).
        // If APP_URL doesn't match the current host, absolute URLs can point to a different
        // origin, causing session/CSRF cookie mismatches (419) on mobile.
        if (app()->environment('local')) {
            $forwardedProto = $request->header('X-Forwarded-Proto');
            $forwardedHost = $request->header('X-Forwarded-Host');

            $scheme = $forwardedProto ? trim(explode(',', $forwardedProto)[0]) : $request->getScheme();
            $host = $forwardedHost ? trim(explode(',', $forwardedHost)[0]) : $request->getHttpHost();

            $currentRoot = $scheme.'://'.$host;

            URL::forceRootUrl($currentRoot);
            URL::forceScheme($scheme);

            config([
                'app.url' => $currentRoot,
                'app.asset_url' => $currentRoot,
            ]);
        }

        return $next($request);
    }
}
