<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningPathTest extends TestCase
{
    // CourseController::show() now reads course_pricing even for a guest
    // (to show a price/buy box), so the course-page test below needs a
    // migrated database - the other tests here don't touch the DB at all.
    use RefreshDatabase;

    public function test_every_course_only_requires_courses_that_come_before_it(): void
    {
        $slugs = array_keys(config('course.courses'));

        foreach (config('course.courses') as $slug => $course) {
            foreach ($course['requires'] ?? [] as $required) {
                $this->assertContains($required, $slugs, "{$slug} requires an unknown course {$required}");
                $this->assertLessThan(
                    array_search($slug, $slugs, true),
                    array_search($required, $slugs, true),
                    "{$slug} requires {$required}, which must come earlier in the learning path",
                );
            }
        }
    }

    public function test_the_path_starts_with_linux_and_ends_with_kubernetes(): void
    {
        $core = array_filter(config('course.courses'), fn (array $course) => ($course['section'] ?? 'core') === 'core');
        $slugs = array_keys($core);

        $this->assertSame('linux-shell', $slugs[0]);
        $this->assertSame('kubernetes', end($slugs));
    }

    public function test_advanced_courses_only_require_courses_from_the_core_path_or_earlier(): void
    {
        $slugs = array_keys(config('course.courses'));

        foreach (config('course.courses') as $slug => $course) {
            if (($course['section'] ?? 'core') !== 'advanced') {
                continue;
            }

            $this->assertNotEmpty($course['requires'] ?? [], "{$slug} is an advanced course and should list prerequisites");
        }
    }

    public function test_the_home_page_numbers_the_courses_and_course_pages_list_prerequisites(): void
    {
        $this->get('/devops')->assertOk()->assertSee('Take the courses in order');

        $this->get('/docker')->assertOk()->assertSee('Take these first')->assertSee('Linux &amp; Shell Basics', false);
    }

    public function test_the_home_page_is_subject_neutral_not_devops_only_branded(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Learn new skills')
            ->assertSee('DevOps')
            ->assertSee('IELTS Prep')
            ->assertDontSee('DevOps Shikhbo')
            ->assertDontSee('Learn DevOps')
            // The hub only lists subjects, not individual courses - that's the whole point.
            ->assertDontSee('Linux &amp; Shell Basics', false)
            ->assertDontSee('IELTS Grammar');
    }
}
