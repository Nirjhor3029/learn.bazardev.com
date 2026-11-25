<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IELTS Speaking is a complete, 12-chapter course — every chapter is
 * `available`. These tests pin down that the full course renders correctly
 * everywhere, exactly like the already-shipped full-course pattern in
 * ielts-grammar, ielts-vocabulary, ielts-writing, ielts-reading, and
 * ielts-listening.
 */
class IeltsSpeakingRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_lists_the_ielts_speaking_course(): void
    {
        $this->get('/ielts')->assertOk()->assertSee('IELTS Speaking');
    }

    public function test_the_course_page_lists_every_chapter_and_every_chapter_is_clickable(): void
    {
        $response = $this->get('/ielts-speaking');

        $response->assertOk()
            ->assertSee('How IELTS Speaking is scored')
            ->assertSee('Common mistakes that cap your Speaking score');

        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '01-how-ielts-speaking-is-scored']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '02-part-1-introduction-and-interview']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '03-part-2-the-cue-card']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '04-extending-your-cue-card-answer']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '05-part-3-discussion-questions']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '06-fluency-and-coherence']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '07-lexical-resource']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '08-grammatical-range-and-accuracy']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '09-pronunciation']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '10-preparing-topics-without-memorising']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '11-common-mistakes-that-cap-your-speaking-score']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-speaking', '12-mock-test-and-final-checklist']).'"', false);
    }

    public function test_chapter_one_is_readable(): void
    {
        $this->get('/ielts-speaking/01-how-ielts-speaking-is-scored')
            ->assertOk()
            ->assertSee('no multiple choice', false)
            ->assertSee('11 to 14 minutes', false);
    }

    public function test_chapter_two_is_readable(): void
    {
        $this->get('/ielts-speaking/02-part-1-introduction-and-interview')
            ->assertOk()
            ->assertSee('put you at ease', false)
            ->assertSee('Student');
    }

    public function test_chapter_three_is_readable(): void
    {
        $this->get('/ielts-speaking/03-part-2-the-cue-card')
            ->assertOk()
            ->assertSee('pencil and paper', false)
            ->assertSee('Bengali curry');
    }

    public function test_chapter_four_is_readable(): void
    {
        $this->get('/ielts-speaking/04-extending-your-cue-card-answer')
            ->assertOk()
            ->assertSee('translation app', false)
            ->assertSee('camera');
    }

    public function test_chapter_five_is_readable(): void
    {
        $this->get('/ielts-speaking/05-part-3-discussion-questions')
            ->assertOk()
            ->assertSee('difficult question, actually', false)
            ->assertSee('musical instrument');
    }

    public function test_chapter_six_is_readable(): void
    {
        $this->get('/ielts-speaking/06-fluency-and-coherence')
            ->assertOk()
            ->assertSee('self-correction', false)
            ->assertSee('thinking time');
    }

    public function test_chapter_seven_is_readable(): void
    {
        $this->get('/ielts-speaking/07-lexical-resource')
            ->assertOk()
            ->assertSee('make a decision', false)
            ->assertSee('keep dry when it rains');
    }

    public function test_chapter_eight_is_readable(): void
    {
        $this->get('/ielts-speaking/08-grammatical-range-and-accuracy')
            ->assertOk()
            ->assertSee('night shifts', false)
            ->assertSee('subject-verb disagreement');
    }

    public function test_chapter_nine_is_readable(): void
    {
        $this->get('/ielts-speaking/09-pronunciation')
            ->assertOk()
            ->assertSee('Robotic', false)
            ->assertSee('Natural chunking');
    }

    public function test_chapter_ten_is_readable(): void
    {
        $this->get('/ielts-speaking/10-preparing-topics-without-memorising')
            ->assertOk()
            ->assertSee('friendly neighbours', false)
            ->assertSee('known since school');
    }

    public function test_chapter_eleven_is_readable(): void
    {
        $this->get('/ielts-speaking/11-common-mistakes-that-cap-your-speaking-score')
            ->assertOk()
            ->assertSee('Parroting the question back', false)
            ->assertSee('stalling tactic');
    }

    public function test_chapter_twelve_is_readable(): void
    {
        $this->get('/ielts-speaking/12-mock-test-and-final-checklist')
            ->assertOk()
            ->assertSee('self-run mock test', false)
            ->assertSee('Course complete');
    }

    public function test_the_course_page_shows_no_exam_section_yet(): void
    {
        $response = $this->get('/ielts-speaking');

        $response->assertOk()->assertDontSee('Final exam');
    }
}
