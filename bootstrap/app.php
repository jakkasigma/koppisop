<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\EnsureKasirShiftStarted;
use App\Http\Middleware\EnsureStaffPortalAuthenticated;
use App\Http\Middleware\ForceCurrentRootUrl;
use App\Support\AuthRedirects;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'kasir.shift' => EnsureKasirShiftStarted::class,
            'staff.portal' => EnsureStaffPortalAuthenticated::class,
        ]);

        $middleware->web(append: [
            ForceCurrentRootUrl::class,
        ]);

        $middleware->redirectUsersTo(fn ($request) => AuthRedirects::urlFor($request->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
