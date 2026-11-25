<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\StudyGroupPost;
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

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.self::BODY_MAX],
            'parent_id' => ['nullable', 'integer'],
            'type' => ['required_without:parent_id', 'nullable', Rule::in([StudyGroupPost::QUESTION, StudyGroupPost::COMMENT])],
            'chapter' => ['nullable', 'string', 'max:120'],
        ]);

        $parent = null;

        if (! empty($data['parent_id'])) {
            $parent = $studyGroup->posts()->whereNull('parent_id')->find($data['parent_id']);
            abort_unless($parent, 404);
        }

        $studyGroup->posts()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $parent?->id,
            'type' => $parent ? null : $data['type'],
            'chapter' => $parent ? null : ($data['chapter'] ?? null),
            'body' => $data['body'],
        ]);

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
