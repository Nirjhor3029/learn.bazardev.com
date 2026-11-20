<?php

namespace App\Http\Middleware;

use App\Services\Entitlements;
use App\Services\Learning;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a chapter page behind payment. Chapter 1 of every course stays a free
 * preview (so a reader can judge quality before buying) — everything after
 * that needs the course to be free, an active subscription, or an entitlement.
 * The course overview page itself is NOT behind this middleware; people need
 * to see what a course covers and its price before deciding to buy it.
 */
class EnsureCourseEntitlement
{
    public function __construct(private Learning $learning, private Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        $course = $request->route('course');
        $chapter = $request->route('chapter');

        $chapters = $this->learning->catalog()[$course] ?? [];
        $isFreePreview = array_key_first($chapters) === $chapter;

        if ($isFreePreview) {
            return $next($request);
        }

        if ($this->entitlements->canAccessCourse($request->user(), $course)) {
            return $next($request);
        }

        return redirect()->route('course.show', $course)
            ->with('status', 'This chapter needs the course to be purchased first.');
    }
}
