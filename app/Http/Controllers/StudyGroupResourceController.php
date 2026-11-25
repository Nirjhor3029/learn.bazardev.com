<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\StudyGroupResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** A link a member pins for the whole group — no admin approval needed, unlike Contribution. */
class StudyGroupResourceController extends Controller
{
    public function store(Request $request, StudyGroup $studyGroup): RedirectResponse
    {
        abort_unless($studyGroup->hasMember($request->user()), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'url' => ['required', 'string', 'max:500', 'url:http,https'],
        ]);

        $studyGroup->resources()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'url' => $data['url'],
        ]);

        return back()->with('status', 'Resource pinned.');
    }

    public function destroy(Request $request, StudyGroup $studyGroup, StudyGroupResource $resource): RedirectResponse
    {
        abort_unless($resource->study_group_id === $studyGroup->id, 404);
        abort_unless($resource->user_id === $request->user()->id || $studyGroup->isOwner($request->user()), 403);

        $resource->delete();

        return back()->with('status', 'Resource removed.');
    }
}
