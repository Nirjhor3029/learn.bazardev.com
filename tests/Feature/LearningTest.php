<?php

namespace Tests\Feature;

use App\Models\ChapterState;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    private const CH1 = '/docker/01-what-is-docker';

    private const CH2 = '/docker/02-install-and-first-container';

    // ---- Guests ----------------------------------------------------------------------

    public function test_guests_can_read_but_personal_actions_need_a_login(): void
    {
        $this->get(self::CH1)->assertOk()
            ->assertSee('Sign up free')
            ->assertSee('next='.urlencode(self::CH1), false)
            ->assertDontSee('Mark as done')
            ->assertDontSee('My notes');

        $this->post('/learn'.self::CH1.'/done', ['value' => 1])->assertRedirect(route('login'));
        $this->post('/learn'.self::CH1.'/bookmark', ['value' => 1])->assertRedirect(route('login'));
        $this->put('/learn'.self::CH1.'/note', ['body' => 'hello'])->assertRedirect(route('login'));
        $this->get('/my-learning')->assertRedirect(route('login'));

        $this->assertSame(0, ChapterState::count());
        $this->assertSame(0, Note::count());
    }

    public function test_login_link_from_a_chapter_brings_the_reader_back_to_it(): void
    {
        User::factory()->create(['email' => 'rahim@example.com', 'password' => 'secret-pass-1']);

        $this->get('/login?next='.urlencode(self::CH1.'?lang=bn'))->assertOk();
        $this->post('/login', ['email' => 'rahim@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect(url(self::CH1.'?lang=bn'));
    }

    public function test_next_parameter_cannot_redirect_anywhere_else(): void
    {
        User::factory()->create(['email' => 'rahim@example.com', 'password' => 'secret-pass-1']);

        foreach (['https://evil.example/x/y', '//evil.example/x', '/admin/users', '/docker/no-such-chapter', '/account'] as $bad) {
            $this->get('/login?next='.urlencode($bad))->assertOk();
        }

        $this->post('/login', ['email' => 'rahim@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect(route('home'));
    }

    // ---- Done / progress -------------------------------------------------------------

    public function test_marking_a_chapter_done_and_undoing_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(self::CH1)->assertOk()
            ->assertSee('Mark as done')
            ->assertSee('0 of 8 chapters done');

        // Plain form post (JavaScript off).
        $this->actingAs($user)->from(self::CH1)->post('/learn'.self::CH1.'/done', ['value' => '1'])
            ->assertRedirect(self::CH1)->assertSessionHas('status');
        $this->assertNotNull($user->chapterStates()->first()->completed_at);

        // Asking for "done" twice keeps it done (a double click must not undo it).
        $this->actingAs($user)->postJson('/learn'.self::CH1.'/done', ['value' => true])
            ->assertOk()->assertJson(['on' => true, 'progress' => ['done' => 1, 'total' => 8, 'percent' => 13]]);

        $this->actingAs($user)->postJson('/learn'.self::CH2.'/done', ['value' => true])
            ->assertJsonPath('progress.done', 2);

        $this->actingAs($user)->get('/docker')->assertOk()->assertSee('2 of 8 chapters done');
        $this->actingAs($user)->get('/')->assertOk()->assertSee('2/8 done');

        $this->actingAs($user)->postJson('/learn'.self::CH1.'/done', ['value' => false])
            ->assertJson(['on' => false, 'progress' => ['done' => 1]]);
        $this->assertNull($user->chapterStates()->where('chapter', '01-what-is-docker')->first()->completed_at);
    }

    public function test_actions_reject_unknown_chapters_and_missing_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/learn/docker/no-such-chapter/done', ['value' => true])->assertNotFound();
        $this->actingAs($user)->postJson('/learn/no-such-course/01-what-is-docker/bookmark', ['value' => true])->assertNotFound();
        $this->actingAs($user)->postJson('/learn/kubernetes/anything/done', ['value' => true])->assertNotFound();
        $this->actingAs($user)->putJson('/learn/docker/no-such-chapter/note', ['body' => 'x'])->assertNotFound();
        $this->actingAs($user)->postJson('/learn'.self::CH1.'/done', [])->assertUnprocessable();

        $this->assertSame(0, ChapterState::count());
        $this->assertSame(0, Note::count());
    }

    // ---- Bookmarks -------------------------------------------------------------------

    public function test_bookmarking_a_chapter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/learn'.self::CH2.'/bookmark', ['value' => true])
            ->assertOk()->assertJson(['on' => true]);

        $this->actingAs($user)->get(self::CH2)->assertOk()->assertSee('aria-pressed="true"', false);
        $this->actingAs($user)->get('/my-learning')->assertOk()->assertSee('Install Docker + your first container');

        $this->actingAs($user)->postJson('/learn'.self::CH2.'/bookmark', ['value' => false])->assertJson(['on' => false]);
        $this->actingAs($user)->get('/my-learning')->assertOk()->assertSee('No bookmarks yet');
    }

    // ---- Continue where you left off -------------------------------------------------

    public function test_reading_a_chapter_is_remembered_for_continue_where_you_left_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('Continue where you left off');
        $this->actingAs($user)->get('/my-learning')->assertOk()->assertSee('Start here');

        $this->actingAs($user)->get(self::CH1)->assertOk();
        $this->assertNotNull($user->chapterStates()->first()->last_viewed_at);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('Continue where you left off')
            ->assertSee('What is Docker, and why does it matter?');

        // Once that chapter is done, the next unfinished one is offered instead.
        $this->actingAs($user)->postJson('/learn'.self::CH1.'/done', ['value' => true]);
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('Up next')
            ->assertSee('Install Docker + your first container');
    }

    // ---- Notes -----------------------------------------------------------------------

    public function test_notes_are_saved_updated_removed_and_private(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->putJson('/learn'.self::CH1.'/note', ['body' => "docker ps\r\nshows running containers"])
            ->assertOk()->assertJson(['saved' => true, 'empty' => false]);
        $this->assertSame("docker ps\nshows running containers", $user->notes()->first()->body);

        // Saving again updates the same note instead of adding a second one.
        $this->actingAs($user)->from(self::CH1)->put('/learn'.self::CH1.'/note', ['body' => 'my secret note <b>x</b>'])
            ->assertRedirect(self::CH1);
        $this->assertSame(1, Note::count());

        // Shown back to its owner (escaped), on the chapter and on the dashboard…
        $this->actingAs($user)->get(self::CH1)->assertOk()->assertSee('my secret note')->assertDontSee('<b>x</b>', false);
        $this->actingAs($user)->get('/my-learning')->assertOk()->assertSee('my secret note');

        // …and to nobody else.
        $this->actingAs($other)->get(self::CH1)->assertOk()->assertDontSee('my secret note');
        $this->actingAs($other)->get('/my-learning')->assertOk()->assertDontSee('my secret note');
        auth()->logout();
        $this->get(self::CH1)->assertOk()->assertDontSee('my secret note');

        // Too long is refused; an empty note deletes it.
        $this->actingAs($user)->putJson('/learn'.self::CH1.'/note', ['body' => str_repeat('a', Note::MAX_LENGTH + 1)])
            ->assertUnprocessable();
        $this->actingAs($user)->putJson('/learn'.self::CH1.'/note', ['body' => '   '])
            ->assertOk()->assertJson(['empty' => true]);
        $this->assertSame(0, Note::count());
    }

    // ---- Dashboard -------------------------------------------------------------------

    public function test_my_learning_shows_numbers_for_this_user_only(): void
    {
        $user = User::factory()->create(['name' => 'Rahim Uddin']);
        $other = User::factory()->create();

        $this->actingAs($other)->postJson('/learn'.self::CH1.'/done', ['value' => true]);
        $this->actingAs($user)->postJson('/learn'.self::CH2.'/done', ['value' => true]);

        // A leftover row for a chapter that no longer exists must not break or inflate anything.
        $user->chapterStates()->create(['course' => 'docker', 'chapter' => '99-removed', 'completed_at' => now(), 'bookmarked_at' => now()]);

        $this->actingAs($user)->get('/my-learning')->assertOk()
            ->assertSee('Rahim')
            ->assertSeeInOrder(['Chapters done', '1', '/ 32'])
            ->assertSee('1 / 8')
            ->assertSee('No notes yet');
    }

    public function test_deleting_an_account_removes_its_progress_and_notes(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);

        $this->actingAs($user)->postJson('/learn'.self::CH1.'/done', ['value' => true]);
        $this->actingAs($user)->putJson('/learn'.self::CH1.'/note', ['body' => 'remember this']);

        $this->actingAs($user)->delete('/account', ['password' => 'secret-pass-1'])->assertRedirect(route('home'));

        $this->assertSame(0, ChapterState::count());
        $this->assertSame(0, Note::count());
    }
}
