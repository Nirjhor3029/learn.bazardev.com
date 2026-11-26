<?php

namespace Tests\Feature;

use App\Models\MockExamAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockExamTest extends TestCase
{
    use RefreshDatabase;

    private function startAttempt(User $user): MockExamAttempt
    {
        $this->actingAs($user)->post('/mock-tests/set-1/start');

        return MockExamAttempt::query()->where('user_id', $user->id)->first();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/mock-tests')->assertRedirect('/login');
    }

    public function test_starting_a_mock_test_creates_an_in_progress_attempt_on_listening(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/mock-tests/set-1/start');

        $attempt = MockExamAttempt::query()->where('user_id', $user->id)->first();
        $response->assertRedirect("/mock-tests/set-1/{$attempt->id}/listening");
        $this->assertSame('in_progress', $attempt->status);
        $this->assertSame('listening', $attempt->current_section);
    }

    public function test_starting_again_resumes_the_same_in_progress_attempt_instead_of_duplicating(): void
    {
        $user = User::factory()->create();

        $first = $this->startAttempt($user);
        $this->actingAs($user)->post('/mock-tests/set-1/start');

        $this->assertSame(1, MockExamAttempt::query()->where('user_id', $user->id)->count());
        $this->assertSame($first->id, MockExamAttempt::query()->where('user_id', $user->id)->first()->id);
    }

    public function test_submitting_a_listening_score_computes_a_band_and_advances_to_reading(): void
    {
        $user = User::factory()->create();
        $attempt = $this->startAttempt($user);

        $response = $this->actingAs($user)
            ->post("/mock-tests/set-1/{$attempt->id}/listening", ['raw' => 32]);

        $attempt->refresh();
        $response->assertRedirect("/mock-tests/set-1/{$attempt->id}/reading");
        $this->assertSame(32, $attempt->listening_self_reported_raw);
        $this->assertEquals(7.5, $attempt->listening_band);
        $this->assertSame('reading', $attempt->current_section);
    }

    public function test_a_raw_score_above_the_total_is_rejected(): void
    {
        $user = User::factory()->create();
        $attempt = $this->startAttempt($user);

        $this->actingAs($user)
            ->post("/mock-tests/set-1/{$attempt->id}/listening", ['raw' => 41])
            ->assertSessionHasErrors('raw');
    }

    public function test_reading_cannot_be_accessed_before_listening_is_submitted(): void
    {
        $user = User::factory()->create();
        $attempt = $this->startAttempt($user);

        $this->actingAs($user)
            ->get("/mock-tests/set-1/{$attempt->id}/reading")
            ->assertRedirect("/mock-tests/set-1/{$attempt->id}/listening");
    }

    public function test_the_reading_page_lists_all_40_questions_across_3_passages(): void
    {
        $user = User::factory()->create();
        $attempt = $this->startAttempt($user);
        $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/listening", ['raw' => 30]);

        $response = $this->actingAs($user)->get("/mock-tests/set-1/{$attempt->id}/reading");

        $response->assertOk()
            ->assertSee('The History of Street Lighting')
            ->assertSee('Urban Green Roofs')
            ->assertSee('Circadian Rhythms');
    }

    public function test_submitting_reading_answers_grades_them_and_advances_to_writing(): void
    {
        $user = User::factory()->create();
        $attempt = $this->startAttempt($user);
        $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/listening", ['raw' => 30]);

        $questions = app(\App\Services\MockExam::class)->readingQuestions('set-1');
        $answers = [];
        foreach ($questions as $i => $question) {
            $answers[$i] = $question['answer']; // answer every question correctly
        }

        $response = $this->actingAs($user)
            ->post("/mock-tests/set-1/{$attempt->id}/reading", ['answers' => $answers]);

        $attempt->refresh();
        $response->assertRedirect("/mock-tests/set-1/{$attempt->id}/writing");
        $this->assertSame(40, $attempt->reading_correct);
        $this->assertEquals(9.0, $attempt->reading_band);
        $this->assertSame('writing', $attempt->current_section);
    }

    public function test_writing_saves_text_without_any_score_and_advances_to_speaking(): void
    {
        $user = User::factory()->create();
        $attempt = $this->progressToWriting($user);

        $response = $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/writing", [
            'task1' => 'My task 1 answer.',
            'task2' => 'My task 2 essay.',
        ]);

        $attempt->refresh();
        $response->assertRedirect("/mock-tests/set-1/{$attempt->id}/speaking");
        $this->assertSame('My task 1 answer.', $attempt->writing_task1_text);
        $this->assertSame('My task 2 essay.', $attempt->writing_task2_text);
        $this->assertSame('speaking', $attempt->current_section);
    }

    public function test_speaking_completes_the_attempt_and_redirects_to_results(): void
    {
        $user = User::factory()->create();
        $attempt = $this->progressToWriting($user);
        $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/writing", ['task1' => 'x', 'task2' => 'y']);

        $response = $this->actingAs($user)
            ->post("/mock-tests/set-1/{$attempt->id}/speaking", ['notes' => 'Went okay.']);

        $attempt->refresh();
        $response->assertRedirect("/mock-tests/set-1/{$attempt->id}/results");
        $this->assertSame('completed', $attempt->status);
        $this->assertSame('done', $attempt->current_section);
        $this->assertNotNull($attempt->completed_at);
    }

    public function test_the_results_page_shows_bands_and_band6_band8_samples(): void
    {
        $user = User::factory()->create();
        $attempt = $this->progressToWriting($user);
        $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/writing", ['task1' => 'x', 'task2' => 'y']);
        $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/speaking", ['notes' => 'n']);

        $response = $this->actingAs($user)->get("/mock-tests/set-1/{$attempt->id}/results");

        $response->assertOk()
            ->assertSee('Band 6 sample')
            ->assertSee('Band 8 sample')
            ->assertSee('not scored');
    }

    public function test_a_different_user_cannot_view_someone_elses_attempt(): void
    {
        $owner = User::factory()->create();
        $attempt = $this->startAttempt($owner);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/mock-tests/set-1/{$attempt->id}/listening")->assertForbidden();
    }

    public function test_a_completed_attempt_redirects_every_section_page_to_results(): void
    {
        $user = User::factory()->create();
        $attempt = $this->progressToWriting($user);
        $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/writing", ['task1' => 'x', 'task2' => 'y']);
        $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/speaking", ['notes' => 'n']);

        $this->actingAs($user)
            ->get("/mock-tests/set-1/{$attempt->id}/listening")
            ->assertRedirect("/mock-tests/set-1/{$attempt->id}/results");
    }

    private function progressToWriting(User $user): MockExamAttempt
    {
        $attempt = $this->startAttempt($user);
        $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/listening", ['raw' => 30]);
        $this->actingAs($user)->post("/mock-tests/set-1/{$attempt->id}/reading", ['answers' => []]);

        return $attempt->refresh();
    }
}
