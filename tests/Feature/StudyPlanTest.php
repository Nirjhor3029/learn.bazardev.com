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
            ->assertSee('Start my 3-month plan');
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
            ->assertSee("reached the end of the 90-day plan");
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
            ->assertSee('Catch up')
            ->assertSee('Day 1');
    }

    public function test_a_fully_done_earlier_day_does_not_appear_in_catch_up(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(1)]);

        $this->actingAs($user)->post('/learn/ielts-grammar/01-parts-of-speech-and-sentence-basics/done', ['value' => 1]);
        $this->actingAs($user)->post('/learn/ielts-vocabulary/01-how-to-learn-vocabulary-that-sticks/done', ['value' => 1]);

        $response = $this->actingAs($user)->get('/study-plan');

        $response->assertOk()->assertDontSee('Catch up');
    }

    public function test_a_mock_test_day_links_to_mock_tests_instead_of_a_chapter_checklist(): void
    {
        $user = User::factory()->create(['study_plan_started_at' => now()->subDays(57)]); // day 58

        $response = $this->actingAs($user)->get('/study-plan');

        $response->assertOk()
            ->assertSee('Day 58 of 90')
            ->assertSee('Go to Mock Tests')
            ->assertSee('href="'.route('mock-tests.index').'"', false);
    }
}
