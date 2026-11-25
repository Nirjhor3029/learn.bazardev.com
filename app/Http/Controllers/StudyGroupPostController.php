<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\StudyGroupPost;
use App\Notifications\StudyGroupActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A question, a comment, or a reply to either, inside one study group. */
class StudyGroupPostController extends Controller
{
    public const BODY_MAX = 2000;

    public function store(Request $request, StudyGroup $studyGroup): RedirectResponse
    {
        abort_unless($studyGroup->hasMember($request->user()), 403);

        $chapters = array_keys(config("course.courses.{$studyGroup->course}.chapters", []));

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.self::BODY_MAX],
            'parent_id' => ['nullable', 'integer'],
            'type' => ['required_without:parent_id', 'nullable', Rule::in([StudyGroupPost::QUESTION, StudyGroupPost::COMMENT])],
            'chapter' => ['nullable', 'string', Rule::in($chapters)],
        ]);

        $parent = null;

        if (! empty($data['parent_id'])) {
            $parent = $studyGroup->posts()->whereNull('parent_id')->find($data['parent_id']);
            abort_unless($parent, 404);
        }

        $post = $studyGroup->posts()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'type' => $parent ? null : $data['type'],
            'chapter' => $parent ? null : ($data['chapter'] ?? null),
            'body' => $data['body'],
        ]);

        $studyGroup->members()->with('user')->where('user_id', '!=', $request->user()->id)->get()
            ->each(fn ($member) => $member->user->notify(new StudyGroupActivity($post)));

        return back()->with('status', $parent ? 'Reply posted.' : 'Posted.');
    }

    public function destroy(Request $request, StudyGroup $studyGroup, StudyGroupPost $post): RedirectResponse
    {
        abort_unless($post->study_group_id === $studyGroup->id, 404);
        abort_unless($post->user_id === $request->user()->id || $studyGroup->isOwner($request->user()), 403);

        $post->delete();

        return back()->with('status', 'Removed.');
    }
}
