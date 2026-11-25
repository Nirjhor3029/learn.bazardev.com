<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoursePricing;
use App\Services\Learning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Set a course's price, mark it free, toggle subscription eligibility, or pick its free preview chapters. */
class CoursePricingController extends Controller
{
    public function __construct(private Learning $learning) {}

    public function index(): View
    {
        $pricing = CoursePricing::query()->get()->keyBy('course_slug');
        $catalog = $this->learning->catalog();

        $courses = collect(config('course.courses'))
            ->map(fn (array $course, string $slug) => [
                'slug' => $slug,
                'title' => $course['title'],
                'section' => $course['section'],
                'pricing' => $pricing->get($slug),
                'chapters' => $catalog[$slug] ?? [],
            ])
            ->values();

        return view('admin.pricing', ['courses' => $courses]);
    }

    /**
     * One course's row on the pricing screen saves itself over AJAX (fetch,
     * same route and verb, Accept: application/json) so that editing many
     * rows and saving them one at a time never wipes out the others via a
     * full-page reload. The plain-form fallback (no JS) still works the same
     * as before, just with a page reload.
     */
    public function update(Request $request, string $course): RedirectResponse|JsonResponse
    {
        abort_unless(array_key_exists($course, config('course.courses')), 404);

        $data = $request->validate([
            'is_free' => ['sometimes', 'boolean'],
            'price_amount' => ['required', 'integer', 'min:0', 'max:1000000'],
            'subscription_eligible' => ['sometimes', 'boolean'],
            'free_chapter_slugs' => ['sometimes', 'array'],
            'free_chapter_slugs.*' => ['string'],
        ]);

        // The pricing screen always sends this field (even as an empty array), so only
        // a caller that omits it entirely keeps whatever free-chapter list already
        // existed (or the default "first chapter only" rule, if none did) — a plain
        // price/free-flag update never silently locks chapters the admin never touched.
        if ($request->has('free_chapter_slugs')) {
            // Only chapters that actually belong to this course, so a tampered list
            // can't free-preview-unlock something that doesn't exist.
            $validChapterSlugs = array_keys($this->learning->catalog()[$course] ?? []);
            $freeChapterSlugs = array_values(array_intersect($data['free_chapter_slugs'] ?? [], $validChapterSlugs));
        } else {
            $freeChapterSlugs = CoursePricing::query()->where('course_slug', $course)->first()?->free_chapter_slugs;
        }

        $pricing = CoursePricing::query()->updateOrCreate(
            ['course_slug' => $course],
            [
                'is_free' => $request->boolean('is_free'),
                'price_amount' => $data['price_amount'],
                'subscription_eligible' => $request->boolean('subscription_eligible'),
                'free_chapter_slugs' => $freeChapterSlugs,
                'updated_by' => $request->user()->id,
            ],
        );

        $message = config("course.courses.{$course}.title").'’s pricing was updated.';

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'updated_at' => $pricing->updated_at->diffForHumans(),
                'updated_by' => $request->user()->name,
            ]);
        }

        return back()->with('status', $message);
    }
}
