<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudyPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/study-plan')->assertRedirect('/login');
    }

    public function test_the_landing_page_shows_before_the_plan_is_started(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/study-plan')
            ->assertOk()
            ->assertSee('Start day 1');
    }

    public function test_starting_the_plan_sets_the_start_timestamp_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/study-plan/start')->assertRedirect('/study-plan');
        $user->refresh();
        $firstStart = $user->study_plan_started_at;
        $this->assertNotNull($firstStart);

        // Starting again must not reset progress by moving the clock forward.
        $this->travel(2)->days();
        $this->actingAs($user)->post('/study-plan/start');
        $user->refresh();

        $this->assertEquals($firstStart, $user->study_plan_started_at);
    }

    public function test_day_one_shows_the_first_grammar_and_vocabulary_chapters(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/study-plan/start');

        $response = $this->actingAs($user)->get('/study-plan');

        $response->assertOk()
            ->assertSee('Day 1 of 90')
            ->assertSee('Parts of speech and sentence basics')
            ->assertSee('How to learn vocabulary that actually sticks');
    }

    public function test_the_day_number_advances_with_the_backdated_start_timestamp(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(4)]);

        // Day 5 pairs Grammar ch.5 with Vocabulary ch.5.
        $response = $this->actingAs($user)->get('/study-plan');

        $response->assertOk()->assertSee('Day 5 of 90');
    }

    public function test_the_day_number_caps_at_90_however_far_in_the_past_the_start_is(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(500)]);

        $this->actingAs($user)->get('/study-plan')
            ->assertOk()
            ->assertSee('Day 90 of 90')
            ->assertSee("You've reached day 90", false);
    }

    public function test_marking_a_chapter_done_from_the_study_plan_page_uses_the_real_learning_done_route(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/study-plan/start');

        $this->actingAs($user)->post('/learn/ielts-grammar/01-parts-of-speech-and-sentence-basics/done', ['value' => 1]);
        $this->actingAs($user)->post('/learn/ielts-vocabulary/01-how-to-learn-vocabulary-that-sticks/done', ['value' => 1]);

        $this->assertTrue(
            app(\App\Services\Learning::class)->doneSlugs($user, 'ielts-grammar') === ['01-parts-of-speech-and-sentence-basics']
        );

        // The same chapters now show as done on the study-plan page too — both marked, so no "Mark done" button left for today.
        $response = $this->actingAs($user)->get('/study-plan');
        $response->assertOk()->assertDontSee('Mark done', false);
    }

    public function test_an_incomplete_earlier_day_appears_in_the_catch_up_section(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(2)]);

        $response = $this->actingAs($user)->get('/study-plan');

        $response->assertOk()
            ->assertSee('Still open from earlier')
            ->assertSee('Day 1');
    }

    public function test_a_fully_done_earlier_day_does_not_appear_in_catch_up(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(1)]);

        $this->actingAs($user)->post('/learn/ielts-grammar/01-parts-of-speech-and-sentence-basics/done', ['value' => 1]);
        $this->actingAs($user)->post('/learn/ielts-vocabulary/01-how-to-learn-vocabulary-that-sticks/done', ['value' => 1]);

        $response = $this->actingAs($user)->get('/study-plan');

        $response->assertOk()->assertDontSee('Still open from earlier');
    }

    public function test_a_mock_test_day_links_to_mock_tests_instead_of_a_chapter_checklist(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(57)]); // day 58

        $response = $this->actingAs($user)->get('/study-plan');

        $response->assertOk()
            ->assertSee('Day 58 of 90')
            ->assertSee('Open Mock Tests')
            ->assertSee('href="'.route('mock-tests.index').'"', false);
    }

    public function test_selecting_a_different_day_via_the_query_string_shows_that_days_detail(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(4)]); // day 5

        $response = $this->actingAs($user)->get('/study-plan?day=2');

        $response->assertOk()
            ->assertSee('Day 2')
            ->assertSee('Articles: a, an, the');
    }

    public function test_an_out_of_range_day_in_the_query_string_is_clamped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/study-plan/start');

        $this->actingAs($user)->get('/study-plan?day=999')->assertOk()->assertSee('Day 90');
        $this->actingAs($user)->get('/study-plan?day=-5')->assertOk()->assertSee('Day 1');
    }

    public function test_the_day_detail_fragment_endpoint_returns_that_days_chapters(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/study-plan/start');

        $response = $this->actingAs($user)->get('/study-plan/day/13');

        $response->assertOk()
            ->assertSee('Day 13')
            ->assertSee('Relative clauses')
            ->assertDontSee('<!DOCTYPE html>', false);
    }

    public function test_a_past_unfinished_content_day_is_marked_missed_in_the_overview(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(4)]); // day 5, day 1-4 untouched

        $overview = app(\App\Services\StudyPlan::class)->overview($user, 5);

        $this->assertSame('missed', $overview[0]['status']);
        $this->assertSame('today', $overview[4]['status']);
        $this->assertSame('upcoming', $overview[5]['status']);
    }

    public function test_the_streak_counts_consecutive_done_days_but_does_not_break_for_an_unfinished_today(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(1)]); // day 2
        $plan = app(\App\Services\StudyPlan::class);

        $this->assertSame(0, $plan->currentStreak($user, 2));

        $this->actingAs($user)->post('/learn/ielts-grammar/01-parts-of-speech-and-sentence-basics/done', ['value' => 1]);
        $this->actingAs($user)->post('/learn/ielts-vocabulary/01-how-to-learn-vocabulary-that-sticks/done', ['value' => 1]);

        // Day 1 (yesterday) is now fully done; today (day 2) isn't yet, but that shouldn't erase it.
        $this->assertSame(1, $plan->currentStreak($user, 2));
    }
}
