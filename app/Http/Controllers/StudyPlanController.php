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
        $today = $this->studyPlan->describeDay($this->studyPlan->day($currentDay), $user);

        return view('study-plan.show', [
            'currentDay' => $currentDay,
            'totalDays' => StudyPlan::TOTAL_DAYS,
            'today' => $today,
            'todayDone' => $this->studyPlan->isDayDone($user, $today),
            'todayMinutes' => $this->studyPlan->totalMinutes($today),
            'catchUp' => $this->studyPlan->incompleteEarlierDays($user, $currentDay),
            'progress' => $this->studyPlan->chaptersDoneSoFar($user),
            'overview' => $this->studyPlan->overview($user),
            'finished' => $currentDay === StudyPlan::TOTAL_DAYS,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $this->studyPlan->start($request->user());

        return redirect()->route('study-plan.show');
    }
}
