<?php

namespace App\Services;

use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\HtmlString;

/**
 * Chapter quizzes. Questions live in resources/content/quiz/{course}/{chapter}.json:
 *
 *   [{"q": {"en": "…", "bn": "…"}, "options": [{"en": "…", "bn": "…"}, …],
 *     "answer": 1, "why": {"en": "…", "bn": "…"}}, …]
 *
 * Grading always happens on the server, so the answers are never sent to the browser
 * before a quiz has been submitted. Bangla is optional per text; English is the fallback.
 */
class Quiz
{
    /** A quiz is passed with at least this share of correct answers. */
    public const PASS_PERCENT = 80;

    /** @var array<string, ?list<array>> per-request cache */
    private array $loaded = [];

    public function __construct(private Learning $learning) {}

    /** @return list<array>|null the raw questions, or null when this chapter has no (valid) quiz */
    public function questions(string $course, string $chapter): ?array
    {
        // Only real, readable chapters are looked up — so the slugs can never point outside the quiz folder.
        if (! $this->learning->exists($course, $chapter)) {
            return null;
        }

        return $this->loaded["{$course}/{$chapter}"] ??= $this->read(resource_path("content/quiz/{$course}/{$chapter}.json"));
    }

    public function has(string $course, string $chapter): bool
    {
        return $this->questions($course, $chapter) !== null;
    }

    /**
     * What the chapter page needs to show its quiz card, or null when the chapter has no quiz.
     *
     * @return array{count: int, need: int, has_bn: bool, best: ?QuizAttempt, passed: bool}|null
     */
    public function summary(?User $user, string $course, string $chapter): ?array
    {
        $questions = $this->questions($course, $chapter);

        if ($questions === null) {
            return null;
        }

        $best = $user ? $this->best($user, $course, $chapter) : null;

        return [
            'count' => count($questions),
            'need' => $this->passMark(count($questions)),
            'has_bn' => $this->hasBangla($questions),
            'best' => $best,
            'passed' => (bool) $best?->passed,
        ];
    }

    /** Correct answers needed to pass a quiz of this size. */
    public function passMark(int $total): int
    {
        return (int) ceil($total * self::PASS_PERCENT / 100);
    }

    /**
     * @param  list<array>  $questions
     * @param  list<int>  $chosen  the picked option for each question, same order
     * @return array{score: int, total: int, need: int, passed: bool, rows: list<array{chosen: int, answer: int, correct: bool}>}
     */
    public function grade(array $questions, array $chosen): array
    {
        $rows = [];
        $score = 0;

        foreach ($questions as $i => $question) {
            $pick = (int) ($chosen[$i] ?? -1);
            $correct = $pick === $question['answer'];
            $score += $correct ? 1 : 0;
            $rows[] = ['chosen' => $pick, 'answer' => $question['answer'], 'correct' => $correct];
        }

        $total = count($questions);

        return [
            'score' => $score,
            'total' => $total,
            'need' => $this->passMark($total),
            'passed' => $score >= $this->passMark($total),
            'rows' => $rows,
        ];
    }

    public function record(User $user, string $course, string $chapter, array $result): QuizAttempt
    {
        return $user->quizAttempts()->create([
            'course' => $course,
            'chapter' => $chapter,
            'score' => $result['score'],
            'total' => $result['total'],
            'passed' => $result['passed'],
        ]);
    }

    /** The reader's best attempt at one chapter quiz: a pass first, then the highest score. */
    public function best(User $user, string $course, string $chapter): ?QuizAttempt
    {
        return $user->quizAttempts()->where(['course' => $course, 'chapter' => $chapter])
            ->orderByDesc('passed')->orderByDesc('score')->orderByDesc('id')->first();
    }

    /**
     * Quizzes passed per course, for the dashboard. Only courses that have quizzes appear.
     *
     * @return array<string, array{passed: int, total: int}>
     */
    public function courseCounts(User $user): array
    {
        $passed = $user->quizAttempts()->where('passed', true)->whereNotNull('chapter')
            ->distinct()->get(['course', 'chapter'])
            ->filter(fn ($row) => $this->has($row->course, $row->chapter))
            ->groupBy('course')->map->count();

        $counts = [];

        foreach ($this->learning->catalog() as $course => $chapters) {
            $total = count(array_filter(array_keys($chapters), fn (string $chapter) => $this->has($course, $chapter)));

            if ($total > 0) {
                $counts[$course] = ['passed' => (int) ($passed[$course] ?? 0), 'total' => $total];
            }
        }

        return $counts;
    }

    /** The text in the wanted language, falling back to English. */
    public function text(array $translations, string $lang): string
    {
        return $translations[$lang] ?? $translations['en'];
    }

    /** Escaped text where `backticks` become inline code, so commands read like commands. */
    public function format(string $text): HtmlString
    {
        $html = preg_replace(
            '/`([^`]+)`/',
            '<code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-sm text-gray-800">$1</code>',
            e($text),
        );

        return new HtmlString($html);
    }

    /** True when every text of the quiz also exists in Bangla. */
    public function hasBangla(array $questions): bool
    {
        foreach ($questions as $question) {
            $texts = [$question['q'], $question['why'], ...$question['options']];

            foreach ($texts as $text) {
                if (empty($text['bn'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return list<array>|null */
    private function read(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $questions = json_decode((string) file_get_contents($path), true);

        if (! is_array($questions) || $questions === [] || ! array_is_list($questions)) {
            return null;
        }

        foreach ($questions as $question) {
            if (! $this->valid($question)) {
                return null;
            }
        }

        return $questions;
    }

    private function valid(mixed $question): bool
    {
        if (! is_array($question) || ! isset($question['q']['en'], $question['why']['en'], $question['options'], $question['answer'])) {
            return false;
        }

        $options = $question['options'];

        return is_array($options)
            && array_is_list($options)
            && count($options) >= 2
            && collect($options)->every(fn ($option) => isset($option['en']))
            && is_int($question['answer'])
            && $question['answer'] >= 0
            && $question['answer'] < count($options);
    }
}
