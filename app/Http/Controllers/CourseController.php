<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\CoursePricing;
use App\Models\Entitlement;
use App\Services\CourseContent;
use App\Services\Entitlements;
use App\Services\Exam;
use App\Services\Learning;
use App\Services\Quiz;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __construct(
        private CourseContent $content,
        private Learning $learning,
        private Quiz $quizzes,
        private Exam $exams,
        private Entitlements $entitlements,
    ) {}

    public function home(Request $request): View
    {
        $user = $request->user();

        // One query for every active entitlement, so "is this paid course
        // already mine" doesn't cost a query per card.
        $active = $user ? $this->entitlements->activeFor($user) : collect();
        $ownedCourses = $active->where('product_type', Entitlement::COURSE)->pluck('product_key')->all();
        $hasSubscription = $active->contains('product_type', Entitlement::SUBSCRIPTION_ALL_ACCESS);

        return view('home', [
            'courses' => $this->content->courses(),
            'progress' => $user ? $this->learning->progress($user) : [],
            'resume' => $user ? $this->learning->resume($user) : null,
            // One query for every course's pricing row, so the "Paid" badge on
            // each card doesn't cost an extra query per course (no row = free,
            // same rule as CoursePricing::isFree()).
            'pricing' => CoursePricing::query()->get()->keyBy('course_slug'),
            'ownedCourses' => $ownedCourses,
            'hasSubscription' => $hasSubscription,
        ]);
    }

    public function show(Request $request, string $course): View
    {
        $user = $request->user();
        $pricing = CoursePricing::query()->where('course_slug', $course)->first();

        return view('courses.toc', [
            'course' => $this->content->course($course),
            'progress' => $user ? $this->learning->courseProgress($user, $course) : null,
            'done' => $user ? $this->learning->doneSlugs($user, $course) : [],
            'bookmarked' => $user ? $this->learning->bookmarkedSlugs($user, $course) : [],
            'nextUp' => $user ? $this->learning->nextInCourse($user, $course) : null,
            'exam' => $this->exams->summary($user, $course),
            // Null pricing (not yet configured by an admin) means free/open, same as Entitlements::canAccessCourse().
            'pricing' => $pricing,
            'owned' => $this->entitlements->canAccessCourse($user, $course),
        ]);
    }

    public function chapter(Request $request, string $course, string $chapter): View
    {
        $lang = $request->query('lang') === 'bn' ? 'bn' : 'en';

        // Throws a 404 for anything that is not a readable chapter, before we store anything.
        $data = $this->content->chapter($course, $chapter, $lang);

        $user = $request->user();

        // Reading is public. A logged-in reader also gets their own state for this chapter.
        $state = $user ? $this->learning->touchViewed($user, $course, $chapter) : null;

        return view('courses.chapter', [
            ...$data,
            'number' => array_search($chapter, array_keys($data['course']['chapters']), true) + 1,
            'state' => $state,
            'quiz' => $this->quizzes->summary($user, $course, $chapter),
            'note' => $user?->notes()->where(['course' => $course, 'chapter' => $chapter])->first(),
            'done' => $user ? $this->learning->doneSlugs($user, $course) : [],
            'progress' => $user ? $this->learning->courseProgress($user, $course) : null,
            // Approved resources and tips from readers — public, like the chapter itself.
            'contributions' => Contribution::query()->visible()->with('user')
                ->where(['course' => $course, 'chapter' => $chapter])
                ->oldest('reviewed_at')->get(),
            // The reader's own suggestions that are still waiting, so they know they arrived.
            'waiting' => $user
                ? $user->contributions()->where(['course' => $course, 'chapter' => $chapter, 'status' => Contribution::PENDING])->oldest()->get()
                : collect(),
        ]);
    }
}
