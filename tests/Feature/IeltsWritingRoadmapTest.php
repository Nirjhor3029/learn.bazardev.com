<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IELTS Writing is a complete, 12-chapter course — every chapter is
 * `available`. These tests pin down that the full course renders correctly
 * everywhere, exactly like the already-shipped full-course pattern in
 * ielts-grammar and ielts-vocabulary.
 */
class IeltsWritingRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_lists_the_ielts_writing_course(): void
    {
        $this->get('/ielts')->assertOk()->assertSee('IELTS Writing');
    }

    public function test_the_course_page_lists_every_chapter_and_every_chapter_is_clickable(): void
    {
        $response = $this->get('/ielts-writing');

        $response->assertOk()
            ->assertSee('How IELTS Writing is scored')
            ->assertSee('Common mistakes that cap your Writing score');

        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '01-how-ielts-writing-is-scored']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '02-task1-describing-trends']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '03-task1-describing-static-data']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '04-task1-processes-and-maps']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '05-task1-writing-a-strong-overview']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '06-task2-the-five-question-types']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '07-task2-essay-structure']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '08-task2-thesis-and-topic-sentences']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '09-task2-developing-ideas-with-examples']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '10-task2-opinion-and-discussion-essays']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '11-task2-problem-solution-and-advantage-disadvantage-essays']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-writing', '12-common-mistakes-that-cap-your-score']).'"', false);
    }

    public function test_chapter_one_is_readable(): void
    {
        $this->get('/ielts-writing/01-how-ielts-writing-is-scored')
            ->assertOk()
            ->assertSee('Task Achievement')
            ->assertSee('66% of the Writing score', false);
    }

    public function test_chapter_two_is_readable(): void
    {
        $this->get('/ielts-writing/02-task1-describing-trends')
            ->assertOk()
            ->assertSee('four-paragraph structure', false)
            ->assertSee('rocketed');
    }

    public function test_chapter_three_is_readable(): void
    {
        $this->get('/ielts-writing/03-task1-describing-static-data')
            ->assertOk()
            ->assertSee('Proportion language, visualised', false)
            ->assertSee('roughly a third');
    }

    public function test_chapter_four_is_readable(): void
    {
        $this->get('/ielts-writing/04-task1-processes-and-maps')
            ->assertOk()
            ->assertSee('sequencing + passive voice', false)
            ->assertSee('was converted into');
    }

    public function test_chapter_five_is_readable(): void
    {
        $this->get('/ielts-writing/05-task1-writing-a-strong-overview')
            ->assertOk()
            ->assertSee('Why it matters this much', false)
            ->assertSee('most noticeable feature');
    }

    public function test_chapter_six_is_readable(): void
    {
        $this->get('/ielts-writing/06-task2-the-five-question-types')
            ->assertOk()
            ->assertSee('TWO-PART (DIRECT QUESTION)', false)
            ->assertSee('mandatory safety training');
    }

    public function test_chapter_seven_is_readable(): void
    {
        $this->get('/ielts-writing/07-task2-essay-structure')
            ->assertOk()
            ->assertSee('Anatomy of a body paragraph', false)
            ->assertSee('outline statement');
    }

    public function test_chapter_eight_is_readable(): void
    {
        $this->get('/ielts-writing/08-task2-thesis-and-topic-sentences')
            ->assertOk()
            ->assertSee('promise-and-delivery link', false)
            ->assertSee('social isolation');
    }

    public function test_chapter_nine_is_readable(): void
    {
        $this->get('/ielts-writing/09-task2-developing-ideas-with-examples')
            ->assertOk()
            ->assertSee('chain-of-reasoning technique', false)
            ->assertSee('reduced private car use');
    }

    public function test_chapter_ten_is_readable(): void
    {
        $this->get('/ielts-writing/10-task2-opinion-and-discussion-essays')
            ->assertOk()
            ->assertSee('induced demand', false)
            ->assertSee('MY OPINION', false);
    }

    public function test_chapter_eleven_is_readable(): void
    {
        $this->get('/ielts-writing/11-task2-problem-solution-and-advantage-disadvantage-essays')
            ->assertOk()
            ->assertSee('private car ownership')
            ->assertSee('DISADVANTAGES', false);
    }

    public function test_chapter_twelve_is_readable(): void
    {
        $this->get('/ielts-writing/12-common-mistakes-that-cap-your-score')
            ->assertOk()
            ->assertSee('GRAMMATICAL RANGE AND ACCURACY', false)
            ->assertSee('Course complete');
    }

    public function test_the_course_page_shows_no_exam_section_yet(): void
    {
        $response = $this->get('/ielts-writing');

        $response->assertOk()->assertDontSee('Final exam');
    }
}
