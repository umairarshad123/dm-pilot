<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Webhooks: stateless (no web group → no session cookie, no CSRF).
            Route::group([], base_path('routes/webhooks.php'));

            // Public legal pages + Meta data-deletion / deauthorize callbacks (CSRF disabled per-route there).
            Route::middleware('web')->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxy headers from the tunnel (ngrok) / load balancer so HTTPS URLs are generated correctly.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Never flash tokens back into the session as old input on validation errors.
        $exceptions->dontFlash(['access_token', 'user_token']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
