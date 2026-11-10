<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reads course chapters straight from the sibling devops-for-developers repo,
 * so the markdown there (GitHub-readable) is the single source of truth and
 * the website never drifts out of sync with it.
 */
class CourseContent
{
    public function courses(): array
    {
        return config('course.courses');
    }

    public function course(string $slug): array
    {
        $course = config("course.courses.{$slug}");

        if (! $course) {
            throw new NotFoundHttpException;
        }

        return ['slug' => $slug, ...$course];
    }

    public function chapter(string $courseSlug, string $chapterSlug): array
    {
        $course = $this->course($courseSlug);
        $meta = $course['chapters'][$chapterSlug] ?? null;

        if (! $meta || $meta['status'] !== 'available') {
            throw new NotFoundHttpException;
        }

        $path = rtrim(config('course.content_path'), '/')."/{$course['folder']}/{$chapterSlug}.md";

        if (! File::exists($path)) {
            throw new NotFoundHttpException;
        }

        $markdown = File::get($path);
        $html = (new GithubFlavoredMarkdownConverter)->convert($markdown)->getContent();

        $slugs = array_keys($course['chapters']);
        $index = array_search($chapterSlug, $slugs);

        return [
            'slug' => $chapterSlug,
            'title' => $meta['title'],
            'html' => $html,
            'course' => $course,
            'github_url' => config('course.github_repo')."/blob/main/{$course['folder']}/{$chapterSlug}.md",
            'prev' => $this->sibling($course, $slugs, $index - 1),
            'next' => $this->sibling($course, $slugs, $index + 1),
        ];
    }

    private function sibling(array $course, array $slugs, int $index): ?array
    {
        if (! isset($slugs[$index])) {
            return null;
        }

        $slug = $slugs[$index];

        return ['slug' => $slug, ...$course['chapters'][$slug]];
    }
}
