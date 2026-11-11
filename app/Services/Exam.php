<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A course's final exam. The questions live in resources/content/exam/{course}.json as a
 * pool larger than one sitting; every sitting draws QUESTIONS of them at random. It opens
 * once the reader has passed every chapter quiz of the course, and passing it is the only
 * way to get a certificate. Wrong answers are reported by topic, never by their right answer.
 */
class Exam
{
    /** How many questions one sitting has. */
    public const QUESTIONS = 20;

    /** @var array<string, ?list<array>> per-request cache */
    private array $loaded = [];

    public function __construct(private Quiz $quiz, private Learning $learning) {}

    /** @return list<array>|null the whole pool, or null when this course has no usable exam */
    public function pool(string $course): ?array
    {
        if (! isset($this->learning->catalog()[$course])) {
            return null;
        }

        $pool = $this->loaded[$course] ??= $this->quiz->load(resource_path("content/exam/{$course}.json"), explained: false);

        return $pool !== null && count($pool) >= self::QUESTIONS ? $pool : null;
    }

    public function exists(string $course): bool
    {
        return $this->pool($course) !== null;
    }

    public function passMark(): int
    {
        return $this->quiz->passMark(self::QUESTIONS);
    }

    /** @return list<array> the chapters whose quiz the reader has not passed yet (empty = the exam is open) */
    public function remaining(User $user, string $course): array
    {
        $passed = $this->quiz->passedChapters($user, $course);
        $left = [];

        foreach (array_keys($this->learning->catalog()[$course] ?? []) as $chapter) {
            if ($this->quiz->has($course, $chapter) && ! in_array($chapter, $passed, true)) {
                $left[] = $this->learning->describe($course, $chapter);
            }
        }

        return $left;
    }

    /** @return list<int> positions in the pool of a fresh random selection, in random order */
    public function draw(string $course): array
    {
        return collect(array_keys($this->pool($course)))->shuffle()->take(self::QUESTIONS)->values()->all();
    }

    /**
     * @param  list<int>  $ids  positions in the pool
     * @return list<array>|null null when the ids do not describe a valid sitting
     */
    public function questionsFor(string $course, array $ids): ?array
    {
        $pool = $this->pool($course);

        if ($pool === null || count($ids) !== self::QUESTIONS || count(array_unique($ids)) !== count($ids)) {
            return null;
        }

        $questions = [];
        foreach ($ids as $id) {
            if (! is_int($id) || ! isset($pool[$id])) {
                return null;
            }
            $questions[] = $pool[$id];
        }

        return $questions;
    }

    /**
     * Grade one sitting. `review` names the chapters behind the questions that were missed,
     * in course order — a pointer to what to read again, not a list of right answers.
     *
     * @param  list<array>  $questions
     * @param  list<int>  $picked
     * @return array{score: int, total: int, need: int, passed: bool, review: list<array>}
     */
    public function grade(string $course, array $questions, array $picked): array
    {
        $result = $this->quiz->grade($questions, $picked);

        $missed = [];
        foreach ($result['rows'] as $i => $row) {
            if (! $row['correct'] && isset($questions[$i]['ref'])) {
                $missed[$questions[$i]['ref']] = true;
            }
        }

        $review = [];
        foreach (array_keys($this->learning->catalog()[$course] ?? []) as $chapter) {
            if (isset($missed[$chapter])) {
                $review[] = $this->learning->describe($course, $chapter);
            }
        }

        return [
            'score' => $result['score'],
            'total' => $result['total'],
            'need' => $result['need'],
            'passed' => $result['passed'],
            'review' => $review,
        ];
    }

    public function record(User $user, string $course, array $result): QuizAttempt
    {
        return $user->quizAttempts()->create([
            'course' => $course,
            'chapter' => null,
            'score' => $result['score'],
            'total' => $result['total'],
            'passed' => $result['passed'],
        ]);
    }

    /** The reader's certificate for the course; an existing one is kept, never replaced. */
    public function issue(User $user, string $course, array $result): Certificate
    {
        try {
            return $user->certificates()->firstOrCreate(['course' => $course], [
                'code' => Certificate::newCode(),
                'name' => mb_substr(trim($user->name), 0, 120),
                'score' => $result['score'],
                'total' => $result['total'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // A double submit raced us; the certificate from the other request is the one.
            return $user->certificates()->where('course', $course)->firstOrFail();
        }
    }

    public function best(User $user, string $course): ?QuizAttempt
    {
        return $user->quizAttempts()->where('course', $course)->whereNull('chapter')
            ->orderByDesc('passed')->orderByDesc('score')->orderByDesc('id')->first();
    }

    public function certificate(User $user, string $course): ?Certificate
    {
        return $user->certificates()->where('course', $course)->first();
    }

    /**
     * What the course page shows about the exam, or null when the course has none.
     *
     * @return array{questions: int, need: int, remaining: int, ready: bool, best: ?QuizAttempt, certificate: ?Certificate}|null
     */
    public function summary(?User $user, string $course): ?array
    {
        if (! $this->exists($course)) {
            return null;
        }

        $remaining = $user
            ? count($this->remaining($user, $course))
            : count(array_filter(array_keys($this->learning->catalog()[$course]), fn (string $chapter) => $this->quiz->has($course, $chapter)));

        return [
            'questions' => self::QUESTIONS,
            'need' => $this->passMark(),
            'remaining' => $remaining,
            'ready' => $user !== null && $remaining === 0,
            'best' => $user ? $this->best($user, $course) : null,
            'certificate' => $user ? $this->certificate($user, $course) : null,
        ];
    }
}
