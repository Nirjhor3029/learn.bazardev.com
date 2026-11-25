<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IELTS Listening is a complete, 12-chapter course — every chapter is
 * `available`. These tests pin down that the full course renders correctly
 * everywhere, exactly like the already-shipped full-course pattern in
 * ielts-grammar, ielts-vocabulary, ielts-writing, and ielts-reading.
 */
class IeltsListeningRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_lists_the_ielts_listening_course(): void
    {
        $this->get('/')->assertOk()->assertSee('IELTS Listening');
    }

    public function test_the_course_page_lists_every_chapter_and_every_chapter_is_clickable(): void
    {
        $response = $this->get('/ielts-listening');

        $response->assertOk()
            ->assertSee('How IELTS Listening is scored')
            ->assertSee('Common mistakes that cap your Listening score');

        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '01-how-ielts-listening-is-scored']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '02-form-and-note-completion']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '03-table-and-flow-chart-completion']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '04-multiple-choice-questions']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '05-matching']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '06-plan-map-and-diagram-labelling']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '07-sentence-completion']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '08-short-answer-questions']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '09-numbers-spelling-and-letters']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '10-distractors-and-the-changed-answer-trap']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '11-accents-and-signposting-language']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-listening', '12-common-mistakes-that-cap-your-listening-score']).'"', false);
    }

    public function test_chapter_one_is_readable(): void
    {
        $this->get('/ielts-listening/01-how-ielts-listening-is-scored')
            ->assertOk()
            ->assertSee('predict before you hear', false)
            ->assertSee('self-correction', false);
    }

    public function test_chapter_two_is_readable(): void
    {
        $this->get('/ielts-listening/02-form-and-note-completion')
            ->assertOk()
            ->assertSee('Turn each gap into a question first', false)
            ->assertSee('Surname');
    }

    public function test_chapter_three_is_readable(): void
    {
        $this->get('/ielts-listening/03-table-and-flow-chart-completion')
            ->assertOk()
            ->assertSee('top-to-bottom, left-to-right', false)
            ->assertSee('after that');
    }

    public function test_chapter_four_is_readable(): void
    {
        $this->get('/ielts-listening/04-multiple-choice-questions')
            ->assertOk()
            ->assertSee('mentioned last, after the others are raised and rejected', false)
            ->assertSee('polyester');
    }

    public function test_chapter_five_is_readable(): void
    {
        $this->get('/ielts-listening/05-matching')
            ->assertOk()
            ->assertSee('SPEAKERS', false)
            ->assertSee('disagreed with the plan');
    }

    public function test_chapter_six_is_readable(): void
    {
        $this->get('/ielts-listening/06-plan-map-and-diagram-labelling')
            ->assertOk()
            ->assertSee('ENTRANCE', false)
            ->assertSee('mini-compass');
    }

    public function test_chapter_seven_is_readable(): void
    {
        $this->get('/ielts-listening/07-sentence-completion')
            ->assertOk()
            ->assertSee('PRINTED SENTENCE', false)
            ->assertSee('warehouse');
    }

    public function test_chapter_eight_is_readable(): void
    {
        $this->get('/ielts-listening/08-short-answer-questions')
            ->assertOk()
            ->assertSee('Predict before you hear, just like every other type', false)
            ->assertSee('half past nine');
    }

    public function test_chapter_nine_is_readable(): void
    {
        $this->get('/ielts-listening/09-numbers-spelling-and-letters')
            ->assertOk()
            ->assertSee('Write numerals, not words', false)
            ->assertSee('double five');
    }

    public function test_chapter_ten_is_readable(): void
    {
        $this->get('/ielts-listening/10-distractors-and-the-changed-answer-trap')
            ->assertOk()
            ->assertSee('hidden negative', false)
            ->assertSee('packed lunch');
    }

    public function test_chapter_eleven_is_readable(): void
    {
        $this->get('/ielts-listening/11-accents-and-signposting-language')
            ->assertOk()
            ->assertSee('NORTH AMERICAN', false)
            ->assertSee('signposting');
    }

    public function test_chapter_twelve_is_readable(): void
    {
        $this->get('/ielts-listening/12-common-mistakes-that-cap-your-listening-score')
            ->assertOk()
            ->assertSee('LISTENING TRAPS', false)
            ->assertSee('Course complete');
    }

    public function test_the_course_page_shows_no_exam_section_yet(): void
    {
        $response = $this->get('/ielts-listening');

        $response->assertOk()->assertDontSee('Final exam');
    }
}
