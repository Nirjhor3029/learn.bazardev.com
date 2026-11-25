<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\StudyGroupPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A toggleable 👍 on a post or reply. */
class StudyGroupPostReactionController extends Controller
{
    public function toggle(Request $request, StudyGroup $studyGroup, StudyGroupPost $post): RedirectResponse
    {
        abort_unless($studyGroup->hasMember($request->user()), 403);
        abort_unless($post->study_group_id === $studyGroup->id, 404);

        $existing = $post->reactions()->where('user_id', $request->user()->id)->first();

        if ($existing) {
            $existing->delete();
        } else {
            $post->reactions()->create(['user_id' => $request->user()->id]);
        }

        return back();
    }
}
