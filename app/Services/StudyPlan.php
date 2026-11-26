<?php

namespace App\Services;

use App\Models\User;

/**
 * A hand-paced, 90-day schedule across all 6 IELTS courses (see config/study_plan.php),
 * front-loading Grammar/Vocabulary, then Reading/Listening, then the slower-paced
 * Writing/Speaking, finishing with repeated Mock Test sittings. "Done" is never tracked
 * here — it reuses App\Services\Learning::doneSlugs() (the same chapter_states rows
 * shown on /my-learning), so a day's checklist is just a filtered view of real progress.
 */
class StudyPlan
{
    public const TOTAL_DAYS = 90;

    public function __construct(private Learning $learning) {}

    public function hasStarted(User $user): bool
    {
        return $user->study_plan_started_at !== null;
    }

    public function start(User $user): void
    {
        if ($user->study_plan_started_at === null) {
            $user->forceFill(['study_plan_started_at' => now()])->save();
        }
    }

    /** 1-indexed, capped at TOTAL_DAYS; null if the plan hasn't been started. */
    public function currentDay(User $user): ?int
    {
        if (! $this->hasStarted($user)) {
            return null;
        }

        $elapsed = $user->study_plan_started_at->copy()->startOfDay()->diffInDays(now()->startOfDay());

        return min(self::TOTAL_DAYS, $elapsed + 1);
    }

    /** @return array{chapters: list<array>, note: ?string, mock_test: ?string}|null */
    public function day(int $day): ?array
    {
        return config("study_plan.days.{$day}");
    }

    public function mockSet(): string
    {
        return config('study_plan.mock_set');
    }

    /** A day's chapters with full title/link info and the reader's real done-state attached. */
    public function describeDay(array $day, ?User $user = null): array
    {
        $doneByCourse = [];

        $day['chapters'] = array_map(function (array $c) use ($user, &$doneByCourse) {
            $doneByCourse[$c['course']] ??= $user ? $this->learning->doneSlugs($user, $c['course']) : [];

            return [
                ...$c,
                ...$this->learning->describe($c['course'], $c['chapter']) ?? [],
                'is_done' => in_array($c['chapter'], $doneByCourse[$c['course']], true),
            ];
        }, $day['chapters'] ?? []);

        return $day;
    }

    public function isDayDone(User $user, array $day): bool
    {
        if (($day['chapters'] ?? []) === []) {
            return false;
        }

        $done = [];

        foreach ($day['chapters'] as $chapter) {
            $done[$chapter['course']] ??= $this->learning->doneSlugs($user, $chapter['course']);

            if (! in_array($chapter['chapter'], $done[$chapter['course']], true)) {
                return false;
            }
        }

        return true;
    }

    public function totalMinutes(array $day): int
    {
        return array_sum(array_column($day['chapters'] ?? [], 'minutes'));
    }

    /** @return list<array{day:int, ...}> earlier days (1..currentDay-1) that still have unfinished chapters */
    public function incompleteEarlierDays(User $user, int $currentDay): array
    {
        $incomplete = [];

        for ($d = 1; $d < $currentDay; $d++) {
            $day = $this->day($d);

            if ($day === null || ($day['chapters'] ?? []) === []) {
                continue;
            }

            if (! $this->isDayDone($user, $day)) {
                $incomplete[] = ['day' => $d, ...$this->describeDay($day, $user)];
            }
        }

        return $incomplete;
    }

    /** @return list<array{day:int, label:string, is_mock:bool, done:bool}> a syllabus-level row per day */
    public function overview(User $user): array
    {
        $rows = [];

        foreach (config('study_plan.days') as $number => $day) {
            if (isset($day['mock_test'])) {
                $rows[] = ['day' => $number, 'label' => 'Mock Test', 'is_mock' => true, 'done' => false];

                continue;
            }

            $courses = collect($day['chapters'] ?? [])->pluck('course')->unique()
                ->map(fn ($slug) => config("course.courses.{$slug}.title", $slug))->implode(' + ');

            $rows[] = [
                'day' => $number,
                'label' => $courses !== '' ? $courses : 'Review day',
                'is_mock' => false,
                'done' => $day['chapters'] !== [] && $this->isDayDone($user, $day),
            ];
        }

        return $rows;
    }

    /** @return int how many of the plan's chapters (across all 90 days) are done so far */
    public function chaptersDoneSoFar(User $user): array
    {
        $doneByCourse = [];
        $done = 0;
        $total = 0;

        foreach (config('study_plan.days') as $day) {
            foreach ($day['chapters'] ?? [] as $chapter) {
                $total++;
                $doneByCourse[$chapter['course']] ??= $this->learning->doneSlugs($user, $chapter['course']);

                if (in_array($chapter['chapter'], $doneByCourse[$chapter['course']], true)) {
                    $done++;
                }
            }
        }

        return ['done' => $done, 'total' => $total];
    }
}
