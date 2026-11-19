<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

// Server-to-server auth for the terminal orchestrator's callback — not a browser
// session. 404s rather than 403s so the endpoint isn't advertised by its error.
class EnsureTerminalSharedSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = Config::get('services.terminal.shared_secret');

        abort_unless(
            $expected && hash_equals($expected, (string) $request->header('X-Terminal-Secret')),
            404
        );

        return $next($request);
    }
}
