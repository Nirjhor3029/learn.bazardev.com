<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IELTS Reading is a complete, 12-chapter course — every chapter is
 * `available`. These tests pin down that the full course renders correctly
 * everywhere, exactly like the already-shipped full-course pattern in
 * ielts-grammar, ielts-vocabulary, and ielts-writing.
 */
class IeltsReadingRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_lists_the_ielts_reading_course(): void
    {
        $this->get('/')->assertOk()->assertSee('IELTS Reading');
    }

    public function test_the_course_page_lists_every_chapter_and_every_chapter_is_clickable(): void
    {
        $response = $this->get('/ielts-reading');

        $response->assertOk()
            ->assertSee('How IELTS Reading is scored')
            ->assertSee('Common mistakes that cap your Reading score');

        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '01-how-ielts-reading-is-scored']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '02-true-false-not-given']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '03-matching-headings']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '04-matching-information-and-features']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '05-multiple-choice-questions']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '06-sentence-and-summary-completion']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '07-table-flow-chart-and-diagram-completion']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '08-short-answer-questions']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '09-skimming-and-scanning']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '10-guessing-unknown-words-from-context']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '11-spotting-paraphrase']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-reading', '12-common-mistakes-that-cap-your-reading-score']).'"', false);
    }

    public function test_chapter_one_is_readable(): void
    {
        $this->get('/ielts-reading/01-how-ielts-reading-is-scored')
            ->assertOk()
            ->assertSee('reading passages')
            ->assertSee('PASSAGE SAYS', false);
    }

    public function test_chapter_two_is_readable(): void
    {
        $this->get('/ielts-reading/02-true-false-not-given')
            ->assertOk()
            ->assertSee('qualifier words', false)
            ->assertSee('NOT GIVEN', false);
    }

    public function test_chapter_three_is_readable(): void
    {
        $this->get('/ielts-reading/03-matching-headings')
            ->assertOk()
            ->assertSee('main idea, not a detail', false)
            ->assertSee('DISTRACTOR', false);
    }

    public function test_chapter_four_is_readable(): void
    {
        $this->get('/ielts-reading/04-matching-information-and-features')
            ->assertOk()
            ->assertSee('MATCHING INFORMATION', false)
            ->assertSee('Underline every name');
    }

    public function test_chapter_five_is_readable(): void
    {
        $this->get('/ielts-reading/05-multiple-choice-questions')
            ->assertOk()
            ->assertSee('BORROWED WORDS', false)
            ->assertSee('predict-before-you-peek', false);
    }

    public function test_chapter_six_is_readable(): void
    {
        $this->get('/ielts-reading/06-sentence-and-summary-completion')
            ->assertOk()
            ->assertSee('word-limit rule', false)
            ->assertSee('word bank');
    }

    public function test_chapter_seven_is_readable(): void
    {
        $this->get('/ielts-reading/07-table-flow-chart-and-diagram-completion')
            ->assertOk()
            ->assertSee('let the column headers do the work', false)
            ->assertSee('woven into cloth');
    }

    public function test_chapter_eight_is_readable(): void
    {
        $this->get('/ielts-reading/08-short-answer-questions')
            ->assertOk()
            ->assertSee('UNCHANGEABLE', false)
            ->assertSee('20 hours');
    }

    public function test_chapter_nine_is_readable(): void
    {
        $this->get('/ielts-reading/09-skimming-and-scanning')
            ->assertOk()
            ->assertSee('paragraph map', false)
            ->assertSee('unchangeable keyword', false);
    }

    public function test_chapter_ten_is_readable(): void
    {
        $this->get('/ielts-reading/10-guessing-unknown-words-from-context')
            ->assertOk()
            ->assertSee('CONTRAST CLUE', false)
            ->assertSee('cumbersome');
    }

    public function test_chapter_eleven_is_readable(): void
    {
        $this->get('/ielts-reading/11-spotting-paraphrase')
            ->assertOk()
            ->assertSee('keyword table', false)
            ->assertSee('QUESTION', false);
    }

    public function test_chapter_twelve_is_readable(): void
    {
        $this->get('/ielts-reading/12-common-mistakes-that-cap-your-reading-score')
            ->assertOk()
            ->assertSee('QUESTION-TYPE STRATEGY', false)
            ->assertSee('Course complete');
    }

    public function test_the_course_page_shows_no_exam_section_yet(): void
    {
        $response = $this->get('/ielts-reading');

        $response->assertOk()->assertDontSee('Final exam');
    }
}
