<?php

namespace App\Http\Controllers;

use App\Services\StudyPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudyPlanController extends Controller
{
    public function __construct(private StudyPlan $studyPlan) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        if (! $this->studyPlan->hasStarted($user)) {
            return view('study-plan.start');
        }

        $currentDay = $this->studyPlan->currentDay($user);
        $selectedDay = $this->clampDay((int) $request->query('day', $currentDay));

        return view('study-plan.show', [
            'currentDay' => $currentDay,
            'totalDays' => StudyPlan::TOTAL_DAYS,
            'selectedDay' => $selectedDay,
            'selected' => $this->studyPlan->detailFor($user, $selectedDay, $currentDay),
            'catchUp' => $this->studyPlan->incompleteEarlierDays($user, $currentDay),
            'progress' => $this->studyPlan->chaptersDoneSoFar($user),
            'streak' => $this->studyPlan->currentStreak($user, $currentDay),
            'overview' => $this->studyPlan->overview($user, $currentDay),
            'finished' => $currentDay === StudyPlan::TOTAL_DAYS,
        ]);
    }

    /** A detail-panel fragment for one day, fetched by the grid's click handler (no full reload). */
    public function dayDetail(Request $request, int $day): View
    {
        $user = $request->user();
        abort_unless($this->studyPlan->hasStarted($user), 404);

        $day = $this->clampDay($day);
        $currentDay = $this->studyPlan->currentDay($user);

        return view('study-plan.partials.day-detail', [
            'selectedDay' => $day,
            'selected' => $this->studyPlan->detailFor($user, $day, $currentDay),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $this->studyPlan->start($request->user());

        return redirect()->route('study-plan.show');
    }

    private function clampDay(int $day): int
    {
        return max(1, min(StudyPlan::TOTAL_DAYS, $day));
    }
}
