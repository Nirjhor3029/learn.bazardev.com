<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\User;
use App\Services\Learning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Small, private study groups scoped to one course: members join with an
 * invite code and see each other's progress, questions, and comments.
 */
class StudyGroupController extends Controller
{
    /** How many groups one user may belong to at once, to keep this from becoming a spam platform. */
    public const MAX_GROUPS_PER_USER = 10;

    public function __construct(private Learning $learning) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $groups = $user->studyGroupMemberships()
            ->with('studyGroup')
            ->get()
            ->pluck('studyGroup')
            ->map(fn (StudyGroup $group) => [
                'group' => $group,
                'memberCount' => $group->members()->count(),
            ]);

        return view('study-groups.index', [
            'groups' => $groups,
            'courses' => config('course.courses'),
            'atLimit' => $groups->count() >= self::MAX_GROUPS_PER_USER,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->studyGroupMemberships()->count() >= self::MAX_GROUPS_PER_USER) {
            return back()->withErrors(['limit' => 'You are already in '.self::MAX_GROUPS_PER_USER.' groups. Leave one before creating another.'])->withInput();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'course' => ['required', Rule::in(array_keys(config('course.courses')))],
        ]);

        $group = new StudyGroup();
        $group->forceFill([
            'name' => $data['name'],
            'course' => $data['course'],
            'invite_code' => StudyGroup::newInviteCode(),
            'created_by' => $user->id,
        ])->save();

        $group->members()->create([
            'user_id' => $user->id,
            'role' => StudyGroupMember::OWNER,
            'joined_at' => now(),
        ]);

        return redirect()->route('study-groups.show', $group)->with('status', 'Group created! Share the invite code with your study partners.');
    }

    public function join(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->studyGroupMemberships()->count() >= self::MAX_GROUPS_PER_USER) {
            return back()->withErrors(['limit' => 'You are already in '.self::MAX_GROUPS_PER_USER.' groups. Leave one before joining another.'])->withInput();
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);

        $group = StudyGroup::query()->where('invite_code', strtoupper(trim($data['code'])))->first();

        if (! $group) {
            return back()->withErrors(['code' => 'No group found with that code.'])->withInput();
        }

        if ($group->hasMember($user)) {
            return redirect()->route('study-groups.show', $group);
        }

        if ($group->members()->count() >= $group->member_limit) {
            return back()->withErrors(['code' => 'This group is full.'])->withInput();
        }

        $group->members()->create([
            'user_id' => $user->id,
            'role' => StudyGroupMember::MEMBER,
            'joined_at' => now(),
        ]);

        return redirect()->route('study-groups.show', $group)->with('status', 'Welcome to '.$group->name.'!');
    }

    public function show(Request $request, StudyGroup $studyGroup): View
    {
        $user = $request->user();
        abort_unless($studyGroup->hasMember($user), 403);

        $progress = $this->learning->progress($user);

        // Leaderboard: furthest along first, ties broken by who joined earliest.
        $members = $studyGroup->members()->with('user')->oldest('joined_at')->get()
            ->map(function (StudyGroupMember $member) use ($studyGroup) {
                return [
                    'member' => $member,
                    'progress' => $this->learning->courseProgress($member->user, $studyGroup->course),
                ];
            })
            ->sortByDesc(fn ($row) => $row['progress']['percent'])
            ->values();

        $posts = $studyGroup->posts()->whereNull('parent_id')->with(['user', 'replies.user', 'reactions'])->latest()->get();

        $goals = $studyGroup->goals()->with('creator')->oldest('due_date')->get();
        $doneSlugs = $this->learning->doneSlugs($user, $studyGroup->course);

        $myMembership = $studyGroup->members()->where('user_id', $user->id)->first();

        // Reading this group's notifications clears its unread badge.
        $user->unreadNotifications()
            ->get()
            ->filter(fn ($n) => ($n->data['study_group_id'] ?? null) == $studyGroup->id)
            ->each->markAsRead();

        return view('study-groups.show', [
            'group' => $studyGroup,
            'isOwner' => $studyGroup->isOwner($user),
            'myMembership' => $myMembership,
            'members' => $members,
            'posts' => $posts,
            'goals' => $goals,
            'doneSlugs' => $doneSlugs,
            'resources' => $studyGroup->resources()->with('user')->latest()->get(),
            'chapters' => config("course.courses.{$studyGroup->course}.chapters", []),
            'courseProgress' => $progress[$studyGroup->course] ?? ['done' => 0, 'total' => 0, 'percent' => 0],
        ]);
    }

    public function updatePrivacy(Request $request, StudyGroup $studyGroup): RedirectResponse
    {
        $membership = $studyGroup->members()->where('user_id', $request->user()->id)->first();
        abort_unless($membership, 403);

        $membership->update(['hide_counts' => $request->boolean('hide_counts')]);

        return back()->with('status', 'Privacy setting saved.');
    }

    public function rename(Request $request, StudyGroup $studyGroup): RedirectResponse
    {
        abort_unless($studyGroup->isOwner($request->user()), 403);

        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:80']]);

        $studyGroup->update(['name' => $data['name']]);

        return back()->with('status', 'Group renamed.');
    }

    public function regenerateCode(Request $request, StudyGroup $studyGroup): RedirectResponse
    {
        abort_unless($studyGroup->isOwner($request->user()), 403);

        $studyGroup->forceFill(['invite_code' => StudyGroup::newInviteCode()])->save();

        return back()->with('status', 'New invite code generated. The old code no longer works.');
    }

    public function removeMember(Request $request, StudyGroup $studyGroup, User $member): RedirectResponse
    {
        abort_unless($studyGroup->isOwner($request->user()), 403);
        abort_if($member->id === $request->user()->id, 403);

        $studyGroup->members()->where('user_id', $member->id)->delete();

        return back()->with('status', $member->name.' was removed from the group.');
    }

    public function destroy(Request $request, StudyGroup $studyGroup): RedirectResponse
    {
        abort_unless($studyGroup->isOwner($request->user()), 403);

        $studyGroup->delete();

        return redirect()->route('study-groups.index')->with('status', 'Group deleted.');
    }

    public function leave(Request $request, StudyGroup $studyGroup): RedirectResponse
    {
        $user = $request->user();
        $membership = $studyGroup->members()->where('user_id', $user->id)->first();

        abort_unless($membership, 403);

        $remaining = $studyGroup->members()->where('user_id', '!=', $user->id)->oldest('joined_at');

        if ($membership->isOwner() && $remaining->exists()) {
            $remaining->first()->update(['role' => StudyGroupMember::OWNER]);
        }

        $membership->delete();

        // The last member leaving removes the (now empty) group entirely.
        if ($studyGroup->members()->count() === 0) {
            $studyGroup->delete();
        }

        return redirect()->route('study-groups.index')->with('status', 'You left the group.');
    }
}
