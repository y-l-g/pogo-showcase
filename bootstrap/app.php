<?php

use App\Http\Middleware\DisableSsr;
use App\Http\Middleware\EnsureUserHasPassword;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Env;
use Symfony\Component\HttpFoundation\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies();
        $middleware->preventRequestForgery([
            'pogo/auth',
            'pogo/user-auth',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->alias([
            'has.password' => EnsureUserHasPassword::class,
            'nossr' => DisableSsr::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$storagePath = ($_SERVER['LARAVEL_STORAGE_PATH'] ?? null)
    ?? Env::get('LARAVEL_STORAGE_PATH', getenv('LARAVEL_STORAGE_PATH'))
    ?: null;

if (is_string($storagePath) && $storagePath !== '') {
    $app->useStoragePath($storagePath);
}

return $app;
