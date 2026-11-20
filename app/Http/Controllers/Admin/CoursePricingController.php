<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoursePricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Set a course's price, mark it free, or toggle subscription eligibility. */
class CoursePricingController extends Controller
{
    public function index(): View
    {
        $pricing = CoursePricing::query()->get()->keyBy('course_slug');

        $courses = collect(config('course.courses'))
            ->map(fn (array $course, string $slug) => [
                'slug' => $slug,
                'title' => $course['title'],
                'section' => $course['section'],
                'pricing' => $pricing->get($slug),
            ])
            ->values();

        return view('admin.pricing', ['courses' => $courses]);
    }

    public function update(Request $request, string $course): RedirectResponse
    {
        abort_unless(array_key_exists($course, config('course.courses')), 404);

        $data = $request->validate([
            'is_free' => ['sometimes', 'boolean'],
            'price_amount' => ['required', 'integer', 'min:0', 'max:1000000'],
            'subscription_eligible' => ['sometimes', 'boolean'],
        ]);

        CoursePricing::query()->updateOrCreate(
            ['course_slug' => $course],
            [
                'is_free' => $request->boolean('is_free'),
                'price_amount' => $data['price_amount'],
                'subscription_eligible' => $request->boolean('subscription_eligible'),
                'updated_by' => $request->user()->id,
            ],
        );

        return back()->with('status', config("course.courses.{$course}.title").'’s pricing was updated.');
    }
}
