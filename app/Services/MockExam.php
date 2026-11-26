<?php

namespace App\Services;

use App\Models\MockExamAttempt;
use App\Models\User;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * A full IELTS mock sitting: Listening, Reading, Writing, Speaking, one after another.
 *
 * Listening has no questions of our own — we never host or synthesize IELTS Listening
 * audio (see resources/content/mock-exams/{set}/README or the project plan for why), so
 * the reader takes the real test on British Council's site and self-reports their raw
 * score; we only convert that to a band. Reading is fully original content, authored by
 * us, auto-graded with App\Services\Quiz::grade(). Writing/Speaking are never scored —
 * they're saved for a side-by-side comparison with Band 6 and Band 8 sample answers.
 *
 * Band conversion tables below are approximate, as IELTS itself describes them (exact
 * cutoffs vary slightly by test version) — anchor points (Listening 16/23/30/35 ->
 * 5/6/7/8, Reading 15/23/30/35 -> 5/6/7/8) are the officially published averages from
 * ielts.org's scoring page; the half-band steps around them follow the same widely
 * published IELTS Australia / IDP tables.
 */
class MockExam
{
    private const LISTENING_TABLE = [
        [39, 9], [37, 8.5], [35, 8], [32, 7.5], [30, 7],
        [26, 6.5], [23, 6], [18, 5.5], [16, 5], [13, 4.5],
        [10, 4], [7, 3.5], [4, 3], [0, 2.5],
    ];

    private const READING_TABLE = [
        [39, 9], [37, 8.5], [35, 8], [33, 7.5], [30, 7],
        [27, 6.5], [23, 6], [19, 5.5], [15, 5], [13, 4.5],
        [10, 4], [8, 3.5], [6, 3], [0, 2.5],
    ];

    /** @var array<string, ?list<array>> per-request cache */
    private array $loaded = [];

    public function __construct(private Quiz $quiz) {}

    public function sets(): array
    {
        return config('mock_exam.sets', []);
    }

    public function exists(string $set): bool
    {
        return isset($this->sets()[$set]) && $this->sets()[$set]['status'] === 'available';
    }

    public function listeningPracticeUrl(): string
    {
        return config('mock_exam.listening_practice_url');
    }

    public function listeningTotal(): int
    {
        return 40;
    }

    public function listeningBand(int $raw): float
    {
        return $this->bandFor(self::LISTENING_TABLE, $raw);
    }

    /** @return list<array>|null the reading questions for this set, or null if missing/invalid */
    public function readingQuestions(string $set): ?array
    {
        return $this->loaded["reading/{$set}"] ??= $this->quiz->load(
            resource_path("content/mock-exams/{$set}/reading.json"),
        );
    }

    /** @return array{passages: list<array{title:string, body:string}>}|null */
    public function readingPassages(string $set): ?array
    {
        $path = resource_path("content/mock-exams/{$set}/reading-passages.json");

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && isset($data['passages']) ? $data : null;
    }

    public function readingBand(int $correct): float
    {
        return $this->bandFor(self::READING_TABLE, $correct);
    }

    /** @return array|null {task1,task2} each with prompt/sample_band6/sample_band8 */
    public function writingTasks(string $set): ?array
    {
        return $this->loadJson("content/mock-exams/{$set}/writing.json");
    }

    /** @return array|null {part1,part2,part3,sample_band6,sample_band8} */
    public function speakingTasks(string $set): ?array
    {
        return $this->loadJson("content/mock-exams/{$set}/speaking.json");
    }

    public function start(User $user, string $set): MockExamAttempt
    {
        $existing = $user->mockExamAttempts()
            ->where('mock_exam', $set)->where('status', MockExamAttempt::IN_PROGRESS)
            ->first();

        if ($existing) {
            return $existing;
        }

        $attempt = new MockExamAttempt();
        $attempt->forceFill([
            'user_id' => $user->id,
            'mock_exam' => $set,
            'status' => MockExamAttempt::IN_PROGRESS,
            'current_section' => MockExamAttempt::LISTENING,
            'listening_started_at' => now(),
        ])->save();

        return $attempt;
    }

    /** Renders a passage body or a task prompt (both plain markdown, e.g. the Task 1 data table) to HTML. */
    public function renderMarkdown(string $markdown): string
    {
        return (new GithubFlavoredMarkdownConverter)->convert($markdown)->getContent();
    }

    private function bandFor(array $table, int $raw): float
    {
        foreach ($table as [$min, $band]) {
            if ($raw >= $min) {
                return $band;
            }
        }

        return 1.0;
    }

    private function loadJson(string $relativePath): ?array
    {
        $path = resource_path($relativePath);

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }
}
