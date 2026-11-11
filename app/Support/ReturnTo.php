<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * "Log in to track this chapter" links carry ?next=/docker/01-what-is-docker so the
 * reader lands back on the chapter after logging in or signing up.
 * Only real chapter paths are accepted — never an arbitrary URL (no open redirect).
 */
class ReturnTo
{
    public static function remember(Request $request): void
    {
        $next = (string) $request->query('next');

        if (! preg_match('#^/([a-z0-9-]+)/([a-z0-9-]+)(\?lang=bn)?$#', $next, $match)) {
            return;
        }

        if (! isset(config('course.courses')[$match[1]]['chapters'][$match[2]])) {
            return;
        }

        $request->session()->put('url.intended', url($next));
    }
}
