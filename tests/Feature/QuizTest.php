<?php

namespace Tests\Feature;

use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    private const COURSE = 'docker';

    private const CHAPTER = '01-what-is-docker';

    private const URL = '/docker/01-what-is-docker/quiz';

    /** @return list<array> */
    private function questions(): array
    {
        return app(Quiz::class)->questions(self::COURSE, self::CHAPTER);
    }

    /** Answers for every question; the first $wrong of them are deliberately wrong. */
    private function answers(int $wrong = 0): array
    {
        $answers = [];

        foreach ($this->questions() as $i => $question) {
            $answers[$i] = $i < $wrong ? ($question['answer'] + 1) % count($question['options']) : $question['answer'];
        }

        return $answers;
    }

    // ---- Content ---------------------------------------------------------------------

    public function test_every_docker_chapter_has_a_valid_quiz_with_english_and_bangla(): void
    {
        $quiz = app(Quiz::class);

        foreach (array_keys(config('course.courses.docker.chapters')) as $chapter) {
            $questions = $quiz->questions('docker', $chapter);

            $this->assertNotNull($questions, "{$chapter} has no valid quiz");
            $this->assertCount(5, $questions, $chapter);
            $this->assertTrue($quiz->hasBangla($questions), "{$chapter} is missing a Bangla text");

            foreach ($questions as $question) {
                $this->assertCount(4, $question['options'], $chapter);
                $this->assertSame(count($question['options']), count(array_unique(array_column($question['options'], 'en'))), "{$chapter}: duplicate options");
            }
        }
    }

    public function test_every_quiz_file_on_disk_belongs_to_a_real_chapter_and_is_valid(): void
    {
        $quiz = app(Quiz::class);

        foreach (glob(resource_path('content/quiz/*/*.json')) as $file) {
            $chapter = basename($file, '.json');
            $course = basename(dirname($file));

            $this->assertNotNull($quiz->questions($course, $chapter), "{$course}/{$chapter} is invalid or not a real chapter");
        }
    }

    public function test_pass_mark_is_eighty_percent_rounded_up(): void
    {
        $quiz = app(Quiz::class);

        $this->assertSame(4, $quiz->passMark(5));
        $this->assertSame(8, $quiz->passMark(10));
        $this->assertSame(16, $quiz->passMark(20));
        $this->assertSame(1, $quiz->passMark(1));
    }

    // ---- Chapter page ----------------------------------------------------------------

    public function test_chapter_page_shows_the_quiz_card_to_everyone(): void
    {
        $this->get('/docker/01-what-is-docker')->assertOk()
            ->assertSee('Check what you learned')
            ->assertSee('Take the quiz')
            ->assertSee(self::URL, false);
    }

    public function test_chapter_page_shows_best_score_and_passed_badge_to_the_reader(): void
    {
        $user = User::factory()->create();
        $user->quizAttempts()->create(['course' => 'docker', 'chapter' => self::CHAPTER, 'score' => 3, 'total' => 5, 'passed' => false]);
        $user->quizAttempts()->create(['course' => 'docker', 'chapter' => self::CHAPTER, 'score' => 5, 'total' => 5, 'passed' => true]);
        $user->quizAttempts()->create(['course' => 'docker', 'chapter' => self::CHAPTER, 'score' => 2, 'total' => 5, 'passed' => false]);

        $this->actingAs($user)->get('/docker/01-what-is-docker')->assertOk()
            ->assertSee('Passed')
            ->assertSee('Your best: 5/5')
            ->assertSee('Take it again');
    }

    // ---- Taking a quiz ---------------------------------------------------------------

    public function test_the_quiz_form_never_contains_the_answers(): void
    {
        $response = $this->get(self::URL)->assertOk()
            ->assertSee('Check my answers')
            ->assertDontSee('Why:');

        $quiz = app(Quiz::class);

        foreach ($this->questions() as $question) {
            $response->assertSee((string) $quiz->format($question['q']['en']), false);
            $response->assertDontSee((string) $quiz->format($question['why']['en']), false);
        }
    }

    public function test_bangla_version_is_available_when_every_text_is_translated(): void
    {
        $first = $this->questions()[0];

        $this->get(self::URL)->assertOk()->assertSee('বাংলা')->assertDontSee((string) app(Quiz::class)->format($first['q']['bn']), false);
        $this->get(self::URL.'?lang=bn')->assertOk()->assertSee((string) app(Quiz::class)->format($first['q']['bn']), false);
    }

    public function test_guests_can_take_a_quiz_but_nothing_is_saved(): void
    {
        $this->post(self::URL, ['answers' => $this->answers()])
            ->assertRedirect(url(self::URL).'#result');

        $this->get(self::URL)->assertOk()
            ->assertSee('You passed')
            ->assertSee('5 of 5 correct')
            ->assertSee('This result is not saved')
            ->assertSee((string) app(Quiz::class)->format($this->questions()[0]['why']['en']), false);

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_a_logged_in_reader_gets_the_attempt_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(self::URL, ['answers' => $this->answers()]);

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $user->id, 'course' => 'docker', 'chapter' => self::CHAPTER,
            'score' => 5, 'total' => 5, 'passed' => true,
        ]);

        $this->actingAs($user)->get(self::URL)->assertSee('Your result is saved in');
    }

    public function test_four_of_five_passes_and_three_of_five_does_not(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(self::URL, ['answers' => $this->answers(wrong: 1)]);
        $this->actingAs($user)->post(self::URL, ['answers' => $this->answers(wrong: 2)]);

        $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'score' => 4, 'passed' => true]);
        $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'score' => 3, 'passed' => false]);
    }

    public function test_a_failed_attempt_says_how_many_are_needed_and_shows_the_explanations(): void
    {
        $this->post(self::URL, ['answers' => $this->answers(wrong: 3)]);

        $this->get(self::URL)->assertOk()
            ->assertSee('Not quite yet')
            ->assertSee('2 of 5 correct')
            ->assertSee('You need 4 correct to pass')
            ->assertSee('Try again');
    }

    public function test_the_result_is_shown_once_and_a_reload_gives_a_fresh_form(): void
    {
        $this->post(self::URL, ['answers' => $this->answers()]);

        $this->get(self::URL)->assertSee('You passed');
        $this->get(self::URL)->assertDontSee('You passed')->assertSee('Check my answers');
    }

    public function test_every_question_must_be_answered_and_nothing_is_saved_otherwise(): void
    {
        $user = User::factory()->create();
        $answers = $this->answers();
        unset($answers[2]);

        $this->actingAs($user)->from(self::URL)->post(self::URL, ['answers' => $answers])
            ->assertRedirect(url(self::URL).'#quiz')
            ->assertSessionHasErrors('answers.2');

        $this->actingAs($user)->post(self::URL, [])->assertSessionHasErrors('answers');

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_answers_outside_the_options_are_rejected(): void
    {
        foreach ([9, -1, 'abc', ''] as $bad) {
            $answers = $this->answers();
            $answers[1] = $bad;

            $this->post(self::URL, ['answers' => $answers])->assertSessionHasErrors('answers.1');
        }

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_extra_answers_cannot_change_the_score_or_the_saved_total(): void
    {
        $user = User::factory()->create();
        $answers = $this->answers(wrong: 5);
        $answers[7] = 0;
        $answers['score'] = 5;

        $this->actingAs($user)->post(self::URL, ['answers' => $answers, 'score' => 5, 'passed' => 1]);

        $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'score' => 0, 'total' => 5, 'passed' => false]);
    }

    public function test_unknown_chapters_and_chapters_without_a_quiz_are_404(): void
    {
        $this->get('/docker/no-such-chapter/quiz')->assertNotFound();
        $this->post('/docker/no-such-chapter/quiz', ['answers' => [0]])->assertNotFound();
        $this->get('/docker/..%2F..%2Fenv/quiz')->assertNotFound();

        // These chapters exist but have no quiz yet.
        $this->get('/linux-shell/01-finding-your-way-around/quiz')->assertNotFound();
        $this->get('/linux-shell/01-finding-your-way-around')->assertOk()->assertDontSee('Check what you learned');
    }

    public function test_lang_is_kept_when_the_form_is_sent_in_bangla(): void
    {
        $this->post(self::URL, ['answers' => $this->answers(), 'lang' => 'bn'])
            ->assertRedirect(url(self::URL.'?lang=bn').'#result');
    }

    public function test_submitting_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post(self::URL, ['answers' => $this->answers()])->assertRedirect();
        }

        $this->post(self::URL, ['answers' => $this->answers()])->assertStatus(429);
    }

    // ---- Dashboard, account ----------------------------------------------------------

    public function test_dashboard_counts_passed_quizzes_per_chapter_once(): void
    {
        $user = User::factory()->create();

        foreach ([['01-what-is-docker', 5, true], ['01-what-is-docker', 4, true], ['02-install-and-first-container', 2, false]] as [$chapter, $score, $passed]) {
            $user->quizAttempts()->create(['course' => 'docker', 'chapter' => $chapter, 'score' => $score, 'total' => 5, 'passed' => $passed]);
        }

        $this->actingAs($user)->get('/my-learning')->assertOk()->assertSee('Quizzes passed: 1 / 8');
    }

    public function test_quiz_results_are_deleted_with_the_account(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);
        $user->quizAttempts()->create(['course' => 'docker', 'chapter' => self::CHAPTER, 'score' => 5, 'total' => 5, 'passed' => true]);

        $this->actingAs($user)->delete('/account', ['password' => 'secret-pass-1'])->assertRedirect();

        $this->assertSame(0, QuizAttempt::count());
    }

    public function test_html_in_question_text_is_escaped_and_backticks_become_code(): void
    {
        $html = (string) app(Quiz::class)->format('Run `docker ps` <script>alert(1)</script>');

        $this->assertStringContainsString('<code', $html);
        $this->assertStringContainsString('docker ps</code>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
