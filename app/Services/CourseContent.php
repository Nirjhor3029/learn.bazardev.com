<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reads course chapters straight from the sibling devops-for-developers repo,
 * so the markdown there (GitHub-readable) is the single source of truth and
 * the website never drifts out of sync with it. Courses flagged
 * `local_content` (no GitHub repo of their own) are the exception — their
 * markdown lives entirely under resources/content/{folder}/ in this app.
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

    public function chapter(string $courseSlug, string $chapterSlug, string $lang = 'en'): array
    {
        $course = $this->course($courseSlug);
        $meta = $course['chapters'][$chapterSlug] ?? null;

        if (! $meta || $meta['status'] !== 'available') {
            throw new NotFoundHttpException;
        }

        // Most courses' English content is the GitHub-readable master copy in the
        // sibling devops-for-developers repo, with Bangla as a website-only translation
        // kept inside this app. A course marked `local_content` (e.g. ielts-grammar)
        // has no GitHub repo at all — both languages live under resources/content/.
        $isLocal = $course['local_content'] ?? false;
        $path = match (true) {
            $isLocal => resource_path("content/{$course['folder']}/{$chapterSlug}".($lang === 'bn' ? '.bn' : '').'.md'),
            $lang === 'bn' => resource_path("content/bn/{$course['folder']}/{$chapterSlug}.md"),
            default => rtrim(config('course.content_path'), '/')."/{$course['folder']}/{$chapterSlug}.md",
        };

        $hasBn = $isLocal
            ? File::exists(resource_path("content/{$course['folder']}/{$chapterSlug}.bn.md"))
            : File::exists(resource_path("content/bn/{$course['folder']}/{$chapterSlug}.md"));

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
            'lang' => $lang,
            'has_bn' => $hasBn,
            'github_url' => $isLocal ? null : config('course.github_repo')."/blob/main/{$course['folder']}/{$chapterSlug}.md",
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
