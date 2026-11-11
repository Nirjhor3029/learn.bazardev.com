<?php

namespace App\Services;

use App\Models\ChapterState;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A logged-in reader's personal state: which chapters are done, bookmarked,
 * and where they stopped reading. Chapters come from config/course.php, so
 * everything here is keyed by "course-slug/chapter-slug".
 */
class Learning
{
    /** @var array<int, Collection<string, ChapterState>> per-request cache, by user id */
    private array $cache = [];

    /**
     * Only what can actually be read right now.
     *
     * @return array<string, array<string, array{title: string, status: string}>> [course => [chapter => meta]]
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach (config('course.courses') as $slug => $course) {
            if ($course['status'] !== 'available') {
                continue;
            }

            $chapters = array_filter($course['chapters'], fn (array $chapter) => $chapter['status'] === 'available');

            if ($chapters !== []) {
                $catalog[$slug] = $chapters;
            }
        }

        return $catalog;
    }

    public function exists(string $course, string $chapter): bool
    {
        return isset($this->catalog()[$course][$chapter]);
    }

    /** Title, position and link data for one chapter, or null if it is not readable. */
    public function describe(string $course, string $chapter): ?array
    {
        $chapters = $this->catalog()[$course] ?? null;

        if (! isset($chapters[$chapter])) {
            return null;
        }

        return [
            'course' => $course,
            'course_title' => config("course.courses.{$course}.title"),
            'chapter' => $chapter,
            'title' => $chapters[$chapter]['title'],
            'number' => array_search($chapter, array_keys($chapters), true) + 1,
            'total' => count($chapters),
        ];
    }

    /** @return Collection<string, ChapterState> keyed "course/chapter"; rows for chapters that no longer exist are left out */
    public function states(User $user): Collection
    {
        return $this->cache[$user->id] ??= $user->chapterStates()->get()
            ->filter(fn (ChapterState $state) => $this->exists($state->course, $state->chapter))
            ->keyBy(fn (ChapterState $state) => $state->key());
    }

    /** @return list<string> chapter slugs of one course the user has completed */
    public function doneSlugs(User $user, string $course): array
    {
        return $this->states($user)
            ->filter(fn (ChapterState $state) => $state->course === $course && $state->completed_at)
            ->pluck('chapter')->values()->all();
    }

    /** @return list<string> chapter slugs of one course the user has bookmarked */
    public function bookmarkedSlugs(User $user, string $course): array
    {
        return $this->states($user)
            ->filter(fn (ChapterState $state) => $state->course === $course && $state->bookmarked_at)
            ->pluck('chapter')->values()->all();
    }

    /** @return array<string, array{done: int, total: int, percent: int}> for every readable course */
    public function progress(User $user): array
    {
        $progress = [];

        foreach ($this->catalog() as $course => $chapters) {
            $done = count($this->doneSlugs($user, $course));
            $total = count($chapters);

            $progress[$course] = [
                'done' => $done,
                'total' => $total,
                'percent' => (int) round($done / $total * 100),
            ];
        }

        return $progress;
    }

    /** @return array{done: int, total: int, percent: int} */
    public function courseProgress(User $user, string $course): array
    {
        return $this->progress($user)[$course] ?? ['done' => 0, 'total' => 0, 'percent' => 0];
    }

    /**
     * What to open next: the chapter last read if it is not done yet,
     * otherwise the next unfinished chapter. Null when nothing was read yet
     * or everything is finished.
     */
    public function resume(User $user): ?array
    {
        $last = $this->states($user)
            ->filter(fn (ChapterState $state) => $state->last_viewed_at)
            ->sortByDesc('last_viewed_at')
            ->first();

        if (! $last) {
            return null;
        }

        if (! $last->completed_at) {
            return [...$this->describe($last->course, $last->chapter), 'is_next' => false];
        }

        $next = $this->nextUnfinished($user, $last->course, $last->chapter);

        return $next ? [...$next, 'is_next' => true] : null;
    }

    /** First unfinished chapter of one course (used for the "Continue" button on the course page). */
    public function nextInCourse(User $user, string $course): ?array
    {
        $done = $this->doneSlugs($user, $course);

        foreach (array_keys($this->catalog()[$course] ?? []) as $chapter) {
            if (! in_array($chapter, $done, true)) {
                return $this->describe($course, $chapter);
            }
        }

        return null;
    }

    /** The very first chapter of the first course — where a brand-new reader starts. */
    public function firstChapter(): ?array
    {
        foreach ($this->catalog() as $course => $chapters) {
            return $this->describe($course, array_key_first($chapters));
        }

        return null;
    }

    public function touchViewed(User $user, string $course, string $chapter): ChapterState
    {
        return $this->write($user, $course, $chapter, ['last_viewed_at' => now()]);
    }

    public function setDone(User $user, string $course, string $chapter, bool $done): ChapterState
    {
        return $this->write($user, $course, $chapter, ['completed_at' => $done ? now() : null]);
    }

    public function setBookmarked(User $user, string $course, string $chapter, bool $bookmarked): ChapterState
    {
        return $this->write($user, $course, $chapter, ['bookmarked_at' => $bookmarked ? now() : null]);
    }

    private function write(User $user, string $course, string $chapter, array $values): ChapterState
    {
        unset($this->cache[$user->id]);

        return $user->chapterStates()->updateOrCreate(['course' => $course, 'chapter' => $chapter], $values);
    }

    /** Look after the given chapter in its own course first, then anywhere, in course order. */
    private function nextUnfinished(User $user, string $course, string $chapter): ?array
    {
        $chapters = array_keys($this->catalog()[$course] ?? []);
        $position = array_search($chapter, $chapters, true);
        $done = $this->doneSlugs($user, $course);

        foreach (array_slice($chapters, $position + 1) as $candidate) {
            if (! in_array($candidate, $done, true)) {
                return $this->describe($course, $candidate);
            }
        }

        foreach (array_keys($this->catalog()) as $anyCourse) {
            if ($found = $this->nextInCourse($user, $anyCourse)) {
                return $found;
            }
        }

        return null;
    }
}
