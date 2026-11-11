<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Exam;
use App\Services\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamTest extends TestCase
{
    use RefreshDatabase;

    private const EXAM = '/docker/exam';

    private function chapters(): array
    {
        return array_keys(config('course.courses.docker.chapters'));
    }

    /** A reader who has passed the quiz of the given chapters (default: all of them). */
    private function reader(?array $chapters = null, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        foreach ($chapters ?? $this->chapters() as $chapter) {
            $user->quizAttempts()->create(['course' => 'docker', 'chapter' => $chapter, 'score' => 5, 'total' => 5, 'passed' => true]);
        }

        return $user;
    }

    /** Open the exam as $user and return answers for the drawn questions; the first $wrong are wrong. */
    private function sitting(User $user, int $wrong = 0): array
    {
        $this->actingAs($user)->get(self::EXAM)->assertOk();

        // Right after an attempt the first visit shows its one-time result; the next one draws a new sitting.
        if (session('exam_draw.docker') === null) {
            $this->get(self::EXAM)->assertOk();
        }

        $ids = session('exam_draw.docker');
        $pool = app(Exam::class)->pool('docker');
        $answers = [];

        foreach ($ids as $i => $id) {
            $right = $pool[$id]['answer'];
            $answers[$i] = $i < $wrong ? ($right + 1) % count($pool[$id]['options']) : $right;
        }

        return $answers;
    }

    // ---- Content ---------------------------------------------------------------------

    public function test_the_docker_pool_is_valid_bilingual_and_points_at_real_chapters(): void
    {
        $pool = app(Exam::class)->pool('docker');

        $this->assertNotNull($pool);
        $this->assertGreaterThanOrEqual(Exam::QUESTIONS, count($pool));
        $this->assertTrue(app(Quiz::class)->hasBangla($pool, explained: false));

        $refs = [];
        foreach ($pool as $question) {
            $this->assertContains($question['ref'], $this->chapters());
            $this->assertCount(4, $question['options']);
            $refs[$question['ref']] = true;
        }

        $this->assertCount(count($this->chapters()), $refs, 'every chapter should appear in the pool');
        $this->assertSame(count($pool), count(array_unique(array_column(array_column($pool, 'q'), 'en'))), 'duplicate question');
    }

    public function test_every_exam_pool_on_disk_is_valid_complete_and_matches_the_chapter_quizzes(): void
    {
        $exam = app(Exam::class);
        $quiz = app(Quiz::class);

        foreach (glob(resource_path('content/exam/*.json')) as $file) {
            $course = basename($file, '.json');
            $chapters = array_keys(config("course.courses.{$course}.chapters", []));
            $pool = $exam->pool($course);

            $this->assertNotNull($pool, "{$course}: exam pool is invalid, too small, or not a real course");
            $this->assertTrue($quiz->hasBangla($pool, explained: false), "{$course}: a Bangla text is missing");

            $refs = [];
            foreach ($pool as $i => $question) {
                $this->assertContains($question['ref'] ?? null, $chapters, "{$course} #{$i}: ref must be a real chapter");
                $this->assertCount(4, $question['options'], "{$course} #{$i}");
                $refs[$question['ref']] = ($refs[$question['ref']] ?? 0) + 1;
            }

            $this->assertCount(count($chapters), $refs, "{$course}: every chapter needs questions in the pool");
            $this->assertSame(count($pool), count(array_unique(array_column(array_column($pool, 'q'), 'en'))), "{$course}: duplicate question");

            // The exam only means something if every chapter has a quiz to unlock it.
            foreach ($chapters as $chapter) {
                $this->assertTrue($quiz->has($course, $chapter), "{$course}/{$chapter} has no quiz but the course has an exam");
            }
        }
    }

    public function test_every_course_with_an_exam_can_be_passed_end_to_end(): void
    {
        foreach (glob(resource_path('content/exam/*.json')) as $file) {
            $course = basename($file, '.json');
            $user = User::factory()->create(['name' => "Reader {$course}"]);

            foreach (array_keys(config("course.courses.{$course}.chapters")) as $chapter) {
                $user->quizAttempts()->create(['course' => $course, 'chapter' => $chapter, 'score' => 5, 'total' => 5, 'passed' => true]);
            }

            $this->actingAs($user)->get("/{$course}/exam")->assertOk()->assertSee('Submit the exam');

            $pool = app(Exam::class)->pool($course);
            $answers = [];
            foreach (session("exam_draw.{$course}") as $i => $id) {
                $answers[$i] = $pool[$id]['answer'];
            }

            $this->actingAs($user)->post("/{$course}/exam", ['answers' => $answers])->assertRedirect();

            $certificate = $user->certificates()->where('course', $course)->first();
            $this->assertNotNull($certificate, "{$course}: no certificate after a perfect exam");
            $this->assertSame(20, $certificate->score);
            $this->get("/certificate/{$certificate->code}")->assertOk()->assertSee("Reader {$course}")->assertSee(config("course.courses.{$course}.title"));
        }
    }

    public function test_pass_mark_is_sixteen_of_twenty(): void
    {
        $this->assertSame(20, Exam::QUESTIONS);
        $this->assertSame(16, app(Exam::class)->passMark());
    }

    // ---- Access ----------------------------------------------------------------------

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get(self::EXAM)->assertRedirect(route('login'));
        $this->post(self::EXAM, ['answers' => []])->assertRedirect(route('login'));
    }

    public function test_courses_without_an_exam_are_404(): void
    {
        $user = $this->reader();

        $this->actingAs($user)->get('/kubernetes/exam')->assertNotFound();
        $this->actingAs($user)->get('/cloud/exam')->assertNotFound();
        $this->actingAs($user)->post('/kubernetes/exam', ['answers' => [0]])->assertNotFound();
    }

    public function test_the_exam_is_locked_until_every_chapter_quiz_is_passed(): void
    {
        $user = $this->reader(array_slice($this->chapters(), 0, 7));

        $this->actingAs($user)->get(self::EXAM)->assertOk()
            ->assertSee('The exam is not open yet')
            ->assertSee('One quiz is still left')
            ->assertSee('08-real-project-laravel-dockerize/quiz', false)
            ->assertDontSee('Submit the exam');

        $this->assertNull(session('exam_draw.docker'));
    }

    public function test_a_locked_exam_cannot_be_submitted_around_the_lock(): void
    {
        $user = $this->reader(array_slice($this->chapters(), 0, 7));

        $this->actingAs($user)->post(self::EXAM, ['answers' => range(0, 19)])->assertRedirect(url(self::EXAM));

        $this->assertSame(0, QuizAttempt::whereNull('chapter')->count());
        $this->assertSame(0, Certificate::count());
    }

    public function test_a_failed_chapter_quiz_does_not_count_towards_the_unlock(): void
    {
        $user = $this->reader(array_slice($this->chapters(), 0, 7));
        $user->quizAttempts()->create(['course' => 'docker', 'chapter' => '08-real-project-laravel-dockerize', 'score' => 3, 'total' => 5, 'passed' => false]);

        $this->actingAs($user)->get(self::EXAM)->assertSee('The exam is not open yet');
    }

    // ---- The sitting -----------------------------------------------------------------

    public function test_an_open_exam_draws_twenty_different_questions_without_answers(): void
    {
        $user = $this->reader();

        $response = $this->actingAs($user)->get(self::EXAM)->assertOk()
            ->assertSee('Submit the exam')
            ->assertSee('20 questions · 16 right to pass');

        $ids = session('exam_draw.docker');
        $this->assertCount(20, $ids);
        $this->assertCount(20, array_unique($ids));

        $pool = app(Exam::class)->pool('docker');
        $quiz = app(Quiz::class);
        foreach ($ids as $id) {
            $this->assertArrayHasKey($id, $pool);
            $response->assertSee((string) $quiz->format($pool[$id]['q']['en']), false);
        }

        $response->assertDontSee('Why:');
        $this->assertStringNotContainsString('"answer"', $response->getContent());
    }

    public function test_the_same_sitting_is_kept_when_the_page_is_reloaded(): void
    {
        $user = $this->reader();

        $this->actingAs($user)->get(self::EXAM);
        $first = session('exam_draw.docker');
        $this->actingAs($user)->get(self::EXAM);

        $this->assertSame($first, session('exam_draw.docker'));
    }

    public function test_passing_saves_the_attempt_and_issues_one_certificate(): void
    {
        $user = $this->reader(attributes: ['name' => 'Rahim Uddin']);
        $answers = $this->sitting($user);

        $this->actingAs($user)->post(self::EXAM, ['answers' => $answers])->assertRedirect(url(self::EXAM).'#result');

        $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'course' => 'docker', 'chapter' => null, 'score' => 20, 'total' => 20, 'passed' => true]);

        $certificate = $user->certificates()->first();
        $this->assertNotNull($certificate);
        $this->assertSame('Rahim Uddin', $certificate->name);
        $this->assertSame(20, $certificate->score);
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $certificate->code);

        $this->actingAs($user)->get(self::EXAM)->assertOk()
            ->assertSee('You passed the final exam')
            ->assertSee('20 of 20 correct')
            ->assertSee(route('certificate.show', $certificate->code), false);
    }

    public function test_passing_again_keeps_the_first_certificate(): void
    {
        $user = $this->reader();

        $this->actingAs($user)->post(self::EXAM, ['answers' => $this->sitting($user)]);
        $first = $user->certificates()->firstOrFail();

        $this->actingAs($user)->post(self::EXAM, ['answers' => $this->sitting($user)]);

        $this->assertSame(1, $user->certificates()->count());
        $this->assertSame($first->code, $user->certificates()->first()->code);
        $this->assertSame(2, QuizAttempt::whereNull('chapter')->where('user_id', $user->id)->count());
    }

    public function test_sixteen_of_twenty_passes_and_fifteen_does_not(): void
    {
        $passer = $this->reader();
        $this->actingAs($passer)->post(self::EXAM, ['answers' => $this->sitting($passer, wrong: 4)]);
        $this->assertDatabaseHas('quiz_attempts', ['user_id' => $passer->id, 'score' => 16, 'passed' => true]);
        $this->assertSame(1, $passer->certificates()->count());

        $failer = $this->reader();
        $this->actingAs($failer)->post(self::EXAM, ['answers' => $this->sitting($failer, wrong: 5)]);
        $this->assertDatabaseHas('quiz_attempts', ['user_id' => $failer->id, 'score' => 15, 'passed' => false]);
        $this->assertSame(0, $failer->certificates()->count());
    }

    public function test_a_failed_sitting_points_to_chapters_not_to_right_answers(): void
    {
        $user = $this->reader();
        $this->actingAs($user)->post(self::EXAM, ['answers' => $this->sitting($user, wrong: 20)]);

        $page = $this->actingAs($user)->get(self::EXAM)->assertOk()
            ->assertSee('Not quite yet')
            ->assertSee('0 of 20 correct')
            ->assertSee('You need 16 correct to pass')
            ->assertSee('Chapters to read again')
            ->assertSee('Try again with new questions')
            ->assertDontSee('View my certificate')
            ->assertDontSee('Why:')
            ->assertDontSee('— correct answer');

        $this->assertStringContainsString('/docker/', $page->getContent());
    }

    public function test_a_failed_sitting_never_removes_an_earned_certificate(): void
    {
        $user = $this->reader();
        $this->actingAs($user)->post(self::EXAM, ['answers' => $this->sitting($user)]);
        $code = $user->certificates()->firstOrFail()->code;

        $this->actingAs($user)->post(self::EXAM, ['answers' => $this->sitting($user, wrong: 20)]);

        $this->assertSame($code, $user->certificates()->firstOrFail()->code);
    }

    public function test_a_new_sitting_is_drawn_after_each_attempt(): void
    {
        $user = $this->reader();
        $this->actingAs($user)->post(self::EXAM, ['answers' => $this->sitting($user, wrong: 20)]);

        $this->assertNull(session('exam_draw.docker'));

        $this->actingAs($user)->get(self::EXAM)->assertSee('Not quite yet');     // the one-time result
        $this->actingAs($user)->get(self::EXAM)->assertSee('Submit the exam');   // then a fresh form
        $this->assertCount(20, session('exam_draw.docker'));
    }

    // ---- Tampering and mistakes ------------------------------------------------------

    public function test_every_question_must_be_answered(): void
    {
        $user = $this->reader();
        $answers = $this->sitting($user);
        unset($answers[4]);

        $this->actingAs($user)->post(self::EXAM, ['answers' => $answers])
            ->assertRedirect(url(self::EXAM).'#exam')
            ->assertSessionHasErrors('answers.4');

        $this->assertSame(0, QuizAttempt::whereNull('chapter')->count());
        $this->assertNotNull(session('exam_draw.docker'), 'the sitting should stay so the reader can finish it');
    }

    public function test_out_of_range_answers_are_rejected(): void
    {
        $user = $this->reader();
        $answers = $this->sitting($user);
        $answers[0] = 7;

        $this->actingAs($user)->post(self::EXAM, ['answers' => $answers])->assertSessionHasErrors('answers.0');
        $this->assertSame(0, QuizAttempt::whereNull('chapter')->count());
    }

    public function test_the_browser_cannot_choose_the_questions_or_the_score(): void
    {
        $user = $this->reader();
        $answers = $this->sitting($user, wrong: 20);

        $this->actingAs($user)->post(self::EXAM, [
            'answers' => $answers + [20 => 0, 21 => 0],
            'ids' => range(0, 19),
            'score' => 20,
            'passed' => 1,
        ]);

        $this->assertDatabaseHas('quiz_attempts', ['user_id' => $user->id, 'score' => 0, 'total' => 20, 'passed' => false]);
        $this->assertSame(0, $user->certificates()->count());
    }

    public function test_a_lost_session_grades_nothing_and_starts_over(): void
    {
        $user = $this->reader();
        $this->sitting($user);
        session()->forget('exam_draw.docker');

        $this->actingAs($user)->post(self::EXAM, ['answers' => range(0, 19)])->assertRedirect(url(self::EXAM));

        $this->assertSame(0, QuizAttempt::whereNull('chapter')->count());
        $this->actingAs($user)->get(self::EXAM)->assertSee('Submit the exam');
    }

    public function test_the_language_is_kept_in_bangla(): void
    {
        $user = $this->reader();
        $answers = $this->sitting($user);

        $this->actingAs($user)->post(self::EXAM, ['answers' => $answers, 'lang' => 'bn'])
            ->assertRedirect(url(self::EXAM.'?lang=bn').'#result');

        $ids = session('exam_draw.docker');
        $this->assertNull($ids);

        $this->actingAs($user)->get(self::EXAM.'?lang=bn')->assertSee('You passed');
        $this->actingAs($user)->get(self::EXAM.'?lang=bn')->assertSee('বাংলা');
    }

    public function test_the_exam_can_be_read_in_bangla(): void
    {
        $user = $this->reader();
        $this->actingAs($user)->get(self::EXAM.'?lang=bn')->assertOk();

        $first = app(Exam::class)->pool('docker')[session('exam_draw.docker')[0]];

        $this->get(self::EXAM.'?lang=bn')->assertSee((string) app(Quiz::class)->format($first['q']['bn']), false);
    }

    public function test_submitting_is_rate_limited(): void
    {
        $user = $this->reader();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post(self::EXAM, ['answers' => $this->sitting($user, wrong: 20)])->assertRedirect();
        }

        // sitting() above only opens the exam (GET), the throttle counts the POSTs
        $this->actingAs($user)->post(self::EXAM, ['answers' => range(0, 19)])->assertStatus(429);
    }

    // ---- Where the exam shows up -----------------------------------------------------

    public function test_the_course_page_card_follows_the_readers_progress(): void
    {
        $this->get('/docker')->assertOk()
            ->assertSee('Final exam &amp; certificate', false)
            ->assertSee('Log in to start');

        $locked = $this->reader(array_slice($this->chapters(), 0, 5));
        $this->actingAs($locked)->get('/docker')
            ->assertSee('Pass the quiz of the remaining 3 chapters to open the final exam')
            ->assertSee('See what is left');

        $ready = $this->reader();
        $this->actingAs($ready)->get('/docker')
            ->assertSee('All chapter quizzes passed')
            ->assertSee('Take the final exam');

        $this->actingAs($ready)->post(self::EXAM, ['answers' => $this->sitting($ready)]);
        $this->actingAs($ready)->get('/docker')
            ->assertSee('Certificate earned')
            ->assertSee('View certificate');
    }

    public function test_courses_without_an_exam_show_no_exam_card(): void
    {
        $this->get('/cloud')->assertOk()->assertDontSee('Final exam');
    }

    public function test_passing_the_last_chapter_quiz_points_to_the_final_exam(): void
    {
        $last = '/docker/08-real-project-laravel-dockerize/quiz';
        $questions = app(Quiz::class)->questions('docker', '08-real-project-laravel-dockerize');
        $answers = array_column($questions, 'answer');

        $this->post($last, ['answers' => $answers]);

        $this->get($last)->assertSee('You passed')->assertSee('Go to the final exam')->assertDontSee('Next chapter');
    }

    public function test_quiz_results_for_earlier_chapters_still_offer_the_next_chapter(): void
    {
        $first = '/docker/01-what-is-docker/quiz';
        $answers = array_column(app(Quiz::class)->questions('docker', '01-what-is-docker'), 'answer');

        $this->post($first, ['answers' => $answers]);

        $this->get($first)->assertSee('Next chapter')->assertDontSee('Go to the final exam');
    }
}
