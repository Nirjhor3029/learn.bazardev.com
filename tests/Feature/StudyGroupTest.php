<?php

namespace Tests\Feature;

use App\Http\Controllers\StudyGroupController;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudyGroupTest extends TestCase
{
    use RefreshDatabase;

    private function createGroup(User $owner, string $course = 'docker', string $name = 'Docker crew'): StudyGroup
    {
        $this->actingAs($owner)->post('/study-groups', ['name' => $name, 'course' => $course]);

        return StudyGroup::query()->where('name', $name)->first();
    }

    public function test_creating_a_group_makes_the_creator_its_owner_with_a_unique_invite_code(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post('/study-groups', ['name' => 'IELTS buddies', 'course' => 'ielts-listening']);

        $group = StudyGroup::query()->where('name', 'IELTS buddies')->first();
        $response->assertRedirect('/study-groups/'.$group->id);

        $this->assertSame('ielts-listening', $group->course);
        $this->assertNotEmpty($group->invite_code);
        $this->assertTrue($group->isOwner($owner));
        $this->assertSame(1, $group->members()->count());
    }

    public function test_joining_with_a_valid_code_adds_the_user_as_a_member(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $joiner = User::factory()->create();

        $response = $this->actingAs($joiner)->post('/study-groups/join', ['code' => $group->invite_code]);

        $response->assertRedirect('/study-groups/'.$group->id);
        $this->assertTrue($group->hasMember($joiner));
        $this->assertFalse($group->isOwner($joiner));
    }

    public function test_joining_with_an_invalid_code_shows_an_error_and_adds_nobody(): void
    {
        $joiner = User::factory()->create();

        $response = $this->actingAs($joiner)->post('/study-groups/join', ['code' => 'NOPE1234']);

        $response->assertSessionHasErrors('code');
        $this->assertSame(0, \App\Models\StudyGroupMember::count());
    }

    public function test_joining_is_lowercase_tolerant_and_trims_whitespace(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $joiner = User::factory()->create();

        $this->actingAs($joiner)->post('/study-groups/join', ['code' => ' '.strtolower($group->invite_code).' '])
            ->assertRedirect('/study-groups/'.$group->id);

        $this->assertTrue($group->hasMember($joiner));
    }

    public function test_a_full_group_rejects_a_new_joiner(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $group->update(['member_limit' => 1]);

        $joiner = User::factory()->create();

        $this->actingAs($joiner)->post('/study-groups/join', ['code' => $group->invite_code])
            ->assertSessionHasErrors('code');

        $this->assertFalse($group->hasMember($joiner));
    }

    public function test_a_user_cannot_belong_to_more_than_the_group_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < StudyGroupController::MAX_GROUPS_PER_USER; $i++) {
            $this->createGroup($user, name: "Group {$i}");
        }

        $response = $this->actingAs($user)->post('/study-groups', ['name' => 'One too many', 'course' => 'docker']);

        $response->assertSessionHasErrors('limit');
        $this->assertSame(StudyGroupController::MAX_GROUPS_PER_USER, $user->studyGroupMemberships()->count());
    }

    public function test_the_group_page_shows_each_members_progress_on_the_groups_course(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner, course: 'docker');

        $this->actingAs($owner)->postJson('/learn/docker/01-what-is-docker/done', ['value' => true]);

        $response = $this->actingAs($owner)->get('/study-groups/'.$group->id);

        $response->assertOk()->assertSee('1/8 chapters done', false);
    }

    public function test_a_non_member_cannot_view_the_group_page(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get('/study-groups/'.$group->id)->assertForbidden();
    }

    public function test_a_member_can_post_a_question_and_a_comment(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        $this->actingAs($owner)->post("/study-groups/{$group->id}/posts", ['type' => 'question', 'body' => 'What does EXPOSE do?'])
            ->assertRedirect();
        $this->actingAs($owner)->post("/study-groups/{$group->id}/posts", ['type' => 'comment', 'body' => 'Great chapter!'])
            ->assertRedirect();

        $response = $this->actingAs($owner)->get('/study-groups/'.$group->id);
        $response->assertOk()->assertSee('What does EXPOSE do?')->assertSee('Great chapter!');
    }

    public function test_a_member_can_reply_to_a_question(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);

        $this->actingAs($owner)->post("/study-groups/{$group->id}/posts", ['type' => 'question', 'body' => 'Stuck on chapter 4']);
        $question = $group->posts()->first();

        $this->actingAs($member)->post("/study-groups/{$group->id}/posts", ['parent_id' => $question->id, 'body' => 'Try restarting the container'])
            ->assertRedirect();

        $response = $this->actingAs($owner)->get('/study-groups/'.$group->id);
        $response->assertOk()->assertSee('Try restarting the container');
        $this->assertSame(1, $question->replies()->count());
    }

    public function test_a_non_member_cannot_post_to_a_group(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post("/study-groups/{$group->id}/posts", ['type' => 'comment', 'body' => 'Sneaky'])
            ->assertForbidden();
    }

    public function test_only_the_owner_can_rename_regenerate_the_code_remove_a_member_or_delete_the_group(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);
        $oldCode = $group->invite_code;

        $this->actingAs($member)->patch("/study-groups/{$group->id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($member)->post("/study-groups/{$group->id}/regenerate-code")->assertForbidden();
        $this->actingAs($member)->delete("/study-groups/{$group->id}/members/{$owner->id}")->assertForbidden();
        $this->actingAs($member)->delete("/study-groups/{$group->id}")->assertForbidden();

        $this->assertSame($oldCode, $group->refresh()->invite_code);
        $this->assertNotNull(StudyGroup::find($group->id));

        $this->actingAs($owner)->patch("/study-groups/{$group->id}", ['name' => 'Renamed'])->assertRedirect();
        $this->assertSame('Renamed', $group->refresh()->name);

        $this->actingAs($owner)->post("/study-groups/{$group->id}/regenerate-code")->assertRedirect();
        $this->assertNotSame($oldCode, $group->refresh()->invite_code);

        $this->actingAs($owner)->delete("/study-groups/{$group->id}/members/{$member->id}")->assertRedirect();
        $this->assertFalse($group->hasMember($member));

        $this->actingAs($owner)->delete("/study-groups/{$group->id}")->assertRedirect();
        $this->assertNull(StudyGroup::find($group->id));
    }

    public function test_leaving_hands_ownership_to_the_next_oldest_member(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);

        $this->actingAs($owner)->post("/study-groups/{$group->id}/leave")->assertRedirect('/study-groups');

        $this->assertFalse($group->hasMember($owner));
        $this->assertTrue($group->isOwner($member));
        $this->assertNotNull(StudyGroup::find($group->id));
    }

    public function test_the_last_member_leaving_deletes_the_group(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        $this->actingAs($owner)->post("/study-groups/{$group->id}/leave")->assertRedirect('/study-groups');

        $this->assertNull(StudyGroup::find($group->id));
    }

    public function test_the_owner_or_the_author_can_remove_a_post_but_nobody_else_can(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);

        $this->actingAs($member)->post("/study-groups/{$group->id}/posts", ['type' => 'comment', 'body' => 'My comment']);
        $post = $group->posts()->first();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->delete("/study-groups/{$group->id}/posts/{$post->id}")->assertForbidden();

        $this->actingAs($owner)->delete("/study-groups/{$group->id}/posts/{$post->id}")->assertRedirect();
        $this->assertNull(\App\Models\StudyGroupPost::find($post->id));
    }

    public function test_only_the_owner_can_add_or_remove_a_goal(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);

        $this->actingAs($member)->post("/study-groups/{$group->id}/goals", ['title' => 'Sneaky goal'])->assertForbidden();

        $this->actingAs($owner)->post("/study-groups/{$group->id}/goals", [
            'title' => 'Finish Chapter 1',
            'target_chapter' => '01-what-is-docker',
            'due_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect();

        $goal = $group->goals()->first();
        $this->assertSame('Finish Chapter 1', $goal->title);

        $this->actingAs($member)->delete("/study-groups/{$group->id}/goals/{$goal->id}")->assertForbidden();
        $this->actingAs($owner)->delete("/study-groups/{$group->id}/goals/{$goal->id}")->assertRedirect();
        $this->assertNull(\App\Models\StudyGroupGoal::find($goal->id));
    }

    public function test_a_goal_shows_as_met_once_the_target_chapter_is_done(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        $this->actingAs($owner)->post("/study-groups/{$group->id}/goals", ['title' => 'Finish Chapter 1', 'target_chapter' => '01-what-is-docker']);
        $goal = $group->goals()->first();

        $this->assertFalse($goal->isMetBy([]));

        $this->actingAs($owner)->postJson('/learn/docker/01-what-is-docker/done', ['value' => true]);

        $response = $this->actingAs($owner)->get('/study-groups/'.$group->id);
        $response->assertOk()->assertSee('line-through', false);
    }

    public function test_any_member_can_pin_a_resource_but_only_the_owner_or_author_can_remove_it(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);

        $this->actingAs($member)->post("/study-groups/{$group->id}/resources", [
            'title' => 'Docker docs',
            'url' => 'https://docs.docker.com',
        ])->assertRedirect();

        $resource = $group->resources()->first();

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->delete("/study-groups/{$group->id}/resources/{$resource->id}")->assertForbidden();

        $this->actingAs($owner)->delete("/study-groups/{$group->id}/resources/{$resource->id}")->assertRedirect();
        $this->assertNull(\App\Models\StudyGroupResource::find($resource->id));
    }

    public function test_reacting_to_a_post_toggles_it_on_and_off(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $this->actingAs($owner)->post("/study-groups/{$group->id}/posts", ['type' => 'comment', 'body' => 'Hello']);
        $post = $group->posts()->first();

        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);

        $this->actingAs($member)->post("/study-groups/{$group->id}/posts/{$post->id}/react")->assertRedirect();
        $this->assertSame(1, $post->reactions()->count());

        $this->actingAs($member)->post("/study-groups/{$group->id}/posts/{$post->id}/react")->assertRedirect();
        $this->assertSame(0, $post->reactions()->count());
    }

    public function test_a_member_can_hide_their_exact_progress_count_from_others(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);

        $this->actingAs($member)->put("/study-groups/{$group->id}/privacy", ['hide_counts' => '1'])->assertRedirect();

        $response = $this->actingAs($owner)->get('/study-groups/'.$group->id);
        $response->assertOk()->assertDontSee('0/8 done');
    }

    public function test_posting_a_question_notifies_other_members_but_not_the_author(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);

        $this->actingAs($owner)->post("/study-groups/{$group->id}/posts", ['type' => 'question', 'body' => 'Help?']);

        $this->assertSame(1, $member->unreadNotifications()->count());
        $this->assertSame(0, $owner->unreadNotifications()->count());
    }

    public function test_viewing_the_group_marks_its_notifications_read(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);
        $member = User::factory()->create();
        $this->actingAs($member)->post('/study-groups/join', ['code' => $group->invite_code]);

        $this->actingAs($owner)->post("/study-groups/{$group->id}/posts", ['type' => 'question', 'body' => 'Help?']);
        $this->assertSame(1, $member->unreadNotifications()->count());

        $this->actingAs($member)->get('/study-groups/'.$group->id);

        $this->assertSame(0, $member->unreadNotifications()->count());
    }

    public function test_a_question_can_be_anchored_to_a_specific_chapter(): void
    {
        $owner = User::factory()->create();
        $group = $this->createGroup($owner);

        $this->actingAs($owner)->post("/study-groups/{$group->id}/posts", [
            'type' => 'question',
            'body' => 'Stuck here',
            'chapter' => '01-what-is-docker',
        ])->assertRedirect();

        $response = $this->actingAs($owner)->get('/study-groups/'.$group->id);
        $response->assertOk()->assertSee('What is Docker, and why does it matter?');
    }
}
