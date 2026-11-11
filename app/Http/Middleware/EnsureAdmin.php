<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // 404 rather than 403, so the admin area is not advertised to non-admins.
        abort_unless($request->user()?->is_admin, 404);

        return $next($request);
    }
}
