<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IELTS Grammar is a complete, 18-chapter roadmap — every chapter is
 * `available`. These tests pin down that the full course renders correctly
 * everywhere (home page, course page, every chapter, exam/progress sections),
 * exactly like the already-shipped full-course pattern elsewhere in this app.
 */
class IeltsGrammarRoadmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_lists_the_ielts_grammar_course(): void
    {
        $this->get('/ielts')->assertOk()->assertSee('IELTS Grammar');
    }

    public function test_the_course_page_lists_every_chapter_and_every_chapter_is_clickable(): void
    {
        $response = $this->get('/ielts-grammar');

        $response->assertOk()
            ->assertSee('Parts of speech and sentence basics')
            ->assertSee('Common mistakes Bangla speakers make in English', false);

        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '01-parts-of-speech-and-sentence-basics']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '02-articles-a-an-the']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '03-nouns-countable-uncountable-and-quantifiers']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '04-present-tenses']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '05-past-tenses']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '06-future-forms']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '07-subject-verb-agreement']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '08-questions-and-word-order']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '09-modal-verbs']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '10-comparatives-and-superlatives']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '11-passive-voice']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '12-conditionals']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '13-relative-clauses']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '14-reported-speech']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '15-gerunds-vs-infinitives']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '16-linking-words-and-cohesion']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '17-complex-sentences-and-punctuation']).'"', false);
        $response->assertSee('href="'.route('course.chapter', ['ielts-grammar', '18-common-mistakes-for-bangla-speakers']).'"', false);
    }

    public function test_chapter_one_is_readable(): void
    {
        $this->get('/ielts-grammar/01-parts-of-speech-and-sentence-basics')
            ->assertOk()
            ->assertSee('The 8 parts of speech')
            ->assertSee('Subject + Verb + Object', false);
    }

    public function test_chapter_two_is_readable(): void
    {
        $this->get('/ielts-grammar/02-articles-a-an-the')
            ->assertOk()
            ->assertSee('not spelling')
            ->assertSee('Zero article', false);
    }

    public function test_chapter_three_is_readable(): void
    {
        $this->get('/ielts-grammar/03-nouns-countable-uncountable-and-quantifiers')
            ->assertOk()
            ->assertSee('The basic split')
            ->assertSee('The trap');
    }

    public function test_chapter_four_is_readable(): void
    {
        $this->get('/ielts-grammar/04-present-tenses')
            ->assertOk()
            ->assertSee('All four, on one timeline')
            ->assertSee('trips up Bangla speakers most');
    }

    public function test_chapter_five_is_readable(): void
    {
        $this->get('/ielts-grammar/05-past-tenses')
            ->assertOk()
            ->assertSee('All four, on one timeline')
            ->assertSee('which one happened first');
    }

    public function test_chapter_six_is_readable(): void
    {
        $this->get('/ielts-grammar/06-future-forms')
            ->assertOk()
            ->assertSee('decided right now')
            ->assertSee('Future perfect');
    }

    public function test_chapter_seven_is_readable(): void
    {
        $this->get('/ielts-grammar/07-subject-verb-agreement')
            ->assertOk()
            ->assertSee('The basic rule')
            ->assertSee('words in between');
    }

    public function test_chapter_eight_is_readable(): void
    {
        $this->get('/ielts-grammar/08-questions-and-word-order')
            ->assertOk()
            ->assertSee('swap subject and auxiliary')
            ->assertSee('this trips people up');
    }

    public function test_chapter_nine_is_readable(): void
    {
        $this->get('/ielts-grammar/09-modal-verbs')
            ->assertOk()
            ->assertSee('almost certainly true')
            ->assertSee('past participle', false);
    }

    public function test_chapter_ten_is_readable(): void
    {
        $this->get('/ielts-grammar/10-comparatives-and-superlatives')
            ->assertOk()
            ->assertSee('Short adjectives')
            ->assertSee('output was higher');
    }

    public function test_chapter_eleven_is_readable(): void
    {
        $this->get('/ielts-grammar/11-passive-voice')
            ->assertOk()
            ->assertSee('is made')
            ->assertSee('woven into cloth');
    }

    public function test_chapter_twelve_is_readable(): void
    {
        $this->get('/ielts-grammar/12-conditionals')
            ->assertOk()
            ->assertSee('real or imaginary')
            ->assertSee('Mixed conditional');
    }

    public function test_chapter_thirteen_is_readable(): void
    {
        $this->get('/ielts-grammar/13-relative-clauses')
            ->assertOk()
            ->assertSee('is a doctor')
            ->assertSee('drop the pronoun from a non-defining clause', false);
    }

    public function test_chapter_fourteen_is_readable(): void
    {
        $this->get('/ielts-grammar/14-reported-speech')
            ->assertOk()
            ->assertSee('tired')
            ->assertSee('one step into the past', false);
    }

    public function test_chapter_fifteen_is_readable(): void
    {
        $this->get('/ielts-grammar/15-gerunds-vs-infinitives')
            ->assertOk()
            ->assertSee('mostly arbitrary')
            ->assertSee('bare infinitive');
    }

    public function test_chapter_sixteen_is_readable(): void
    {
        $this->get('/ielts-grammar/16-linking-words-and-cohesion')
            ->assertOk()
            ->assertSee('we went out')
            ->assertSee('sentence-starter, not a clause-joiner', false);
    }

    public function test_chapter_seventeen_is_readable(): void
    {
        $this->get('/ielts-grammar/17-complex-sentences-and-punctuation')
            ->assertOk()
            ->assertSee('Independent vs. dependent clauses', false)
            ->assertSee('comma splice');
    }

    public function test_chapter_eighteen_is_readable(): void
    {
        $this->get('/ielts-grammar/18-common-mistakes-for-bangla-speakers')
            ->assertOk()
            ->assertSee('One trap per chapter')
            ->assertSee('Course complete');
    }

    public function test_the_course_page_shows_no_exam_section_yet(): void
    {
        $response = $this->get('/ielts-grammar');

        $response->assertOk()->assertDontSee('Final exam');
    }
}
