<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\StudyGroupGoal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A shared target the group owner sets, e.g. "Finish Chapter 5 by Friday". */
class StudyGroupGoalController extends Controller
{
    public function store(Request $request, StudyGroup $studyGroup): RedirectResponse
    {
        abort_unless($studyGroup->isOwner($request->user()), 403);

        $chapters = array_keys(config("course.courses.{$studyGroup->course}.chapters", []));

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'target_chapter' => ['nullable', 'string', Rule::in($chapters)],
            'due_date' => ['nullable', 'date'],
        ]);

        $goal = new StudyGroupGoal();
        $goal->studyGroup()->associate($studyGroup);
        $goal->forceFill([
            'title' => $data['title'],
            'target_chapter' => $data['target_chapter'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'created_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'Goal added.');
    }

    public function destroy(Request $request, StudyGroup $studyGroup, StudyGroupGoal $goal): RedirectResponse
    {
        abort_unless($goal->study_group_id === $studyGroup->id, 404);
        abort_unless($studyGroup->isOwner($request->user()), 403);

        $goal->delete();

        return back()->with('status', 'Goal removed.');
    }
}
