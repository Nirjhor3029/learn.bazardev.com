<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IELTS Vocabulary is a complete, 12-chapter course — every chapter is
 * `available`. These tests pin down that the full course renders correctly
 * everywhere, exactly like the already-shipped full-course pattern in
 * ielts-grammar.
 */
class IeltsVocabularyRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_lists_the_ielts_vocabulary_course(): void
    {
        $this->get('/')->assertOk()->assertSee('IELTS Vocabulary');
    }

    public function test_the_course_page_lists_every_chapter_and_every_chapter_is_clickable(): void
    {
        $response = $this->get('/ielts-vocabulary');

        $response->assertOk()
            ->assertSee('How to learn vocabulary that actually sticks')
            ->assertSee('Government and politics vocabulary');

        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '01-how-to-learn-vocabulary-that-sticks']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '02-education-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '03-environment-and-climate-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '04-technology-and-the-internet-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '05-health-and-medicine-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '06-crime-and-the-law-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '07-work-business-and-the-economy-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '08-travel-and-tourism-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '09-media-and-communication-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '10-society-culture-and-relationships-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '11-science-and-research-vocabulary']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-vocabulary', '12-government-and-politics-vocabulary']).'"', false);
    }

    public function test_chapter_one_is_readable(): void
    {
        $this->get('/ielts-vocabulary/01-how-to-learn-vocabulary-that-sticks')
            ->assertOk()
            ->assertSee('forgetting curve')
            ->assertSee('spaced repetition');
    }

    public function test_chapter_two_is_readable(): void
    {
        $this->get('/ielts-vocabulary/02-education-vocabulary')
            ->assertOk()
            ->assertSee('education pipeline', false)
            ->assertSee('vocational training');
    }

    public function test_chapter_three_is_readable(): void
    {
        $this->get('/ielts-vocabulary/03-environment-and-climate-vocabulary')
            ->assertOk()
            ->assertSee('cause-and-effect chain', false)
            ->assertSee('renewable energy');
    }

    public function test_chapter_four_is_readable(): void
    {
        $this->get('/ielts-vocabulary/04-technology-and-the-internet-vocabulary')
            ->assertOk()
            ->assertSee('double-edged', false)
            ->assertSee('bridge the digital divide');
    }

    public function test_chapter_five_is_readable(): void
    {
        $this->get('/ielts-vocabulary/05-health-and-medicine-vocabulary')
            ->assertOk()
            ->assertSee('Prevention vs. cure', false)
            ->assertSee('sedentary lifestyle');
    }

    public function test_chapter_six_is_readable(): void
    {
        $this->get('/ielts-vocabulary/06-crime-and-the-law-vocabulary')
            ->assertOk()
            ->assertSee('central IELTS debate', false)
            ->assertSee('reduce recidivism');
    }

    public function test_chapter_seven_is_readable(): void
    {
        $this->get('/ielts-vocabulary/07-work-business-and-the-economy-vocabulary')
            ->assertOk()
            ->assertSee('business cycle', false)
            ->assertSee('made redundant');
    }

    public function test_chapter_eight_is_readable(): void
    {
        $this->get('/ielts-vocabulary/08-travel-and-tourism-vocabulary')
            ->assertOk()
            ->assertSee('classic IELTS debate', false)
            ->assertSee('off the beaten track');
    }

    public function test_chapter_nine_is_readable(): void
    {
        $this->get('/ielts-vocabulary/09-media-and-communication-vocabulary')
            ->assertOk()
            ->assertSee('classic IELTS debate', false)
            ->assertSee('echo chamber');
    }

    public function test_chapter_ten_is_readable(): void
    {
        $this->get('/ielts-vocabulary/10-society-culture-and-relationships-vocabulary')
            ->assertOk()
            ->assertSee('recurring essay contrast', false)
            ->assertSee('nuclear family');
    }

    public function test_chapter_eleven_is_readable(): void
    {
        $this->get('/ielts-vocabulary/11-science-and-research-vocabulary')
            ->assertOk()
            ->assertSee('scientific method, as a cycle', false)
            ->assertSee('empirical evidence');
    }

    public function test_chapter_twelve_is_readable(): void
    {
        $this->get('/ielts-vocabulary/12-government-and-politics-vocabulary')
            ->assertOk()
            ->assertSee('AUTHORITARIAN REGIME', false)
            ->assertSee('Course complete');
    }

    public function test_the_course_page_shows_no_exam_section_yet(): void
    {
        $response = $this->get('/ielts-vocabulary');

        $response->assertOk()->assertDontSee('Final exam');
    }
}
