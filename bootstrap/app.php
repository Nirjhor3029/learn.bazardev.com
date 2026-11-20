<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureCourseEntitlement;
use App\Http\Middleware\EnsureTerminalSharedSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'terminal.secret' => EnsureTerminalSharedSecret::class,
            'course.entitled' => EnsureCourseEntitlement::class,
        ]);

        // Server-to-server call from the terminal orchestrator — no browser session,
        // so no CSRF token to present. Guarded instead by the shared-secret middleware.
        $middleware->validateCsrfTokens(except: [
            'internal/terminal/verify-ticket',
        ]);

        // Not logged in -> login page; already logged in and visiting /login -> home.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
