<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContributionTest extends TestCase
{
    use RefreshDatabase;

    private const CH1 = '/docker/01-what-is-docker';

    private const SEND = '/learn/docker/01-what-is-docker/contributions';

    private function admin(): User
    {
        $admin = User::factory()->create(['name' => 'Site Admin']);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function resource(array $overrides = []): array
    {
        return [
            'type' => 'resource',
            'title' => 'Docker networking in 15 minutes',
            'url' => 'https://example.com/docker-networking',
            'details' => 'Short and clear.',
            ...$overrides,
        ];
    }

    /** Put a contribution straight into the database, in the given state. */
    private function make(User $user, array $attributes = [], string $status = Contribution::PENDING): Contribution
    {
        $contribution = $user->contributions()->create([
            'course' => 'docker',
            'chapter' => '01-what-is-docker',
            'type' => 'tip',
            'body' => 'Run docker ps -a to see stopped containers too.',
            ...$attributes,
        ]);
        $contribution->forceFill(['status' => $status])->save();

        return $contribution;
    }

    // ---- Sending ---------------------------------------------------------------------

    public function test_guests_see_the_section_but_must_log_in_to_send(): void
    {
        $this->get(self::CH1)->assertOk()
            ->assertSee('From the community')
            ->assertSee('Log in to share')
            ->assertDontSee('Send for review');

        $this->post(self::SEND, $this->resource())->assertRedirect(route('login'));
        $this->assertSame(0, Contribution::count());
    }

    public function test_a_suggestion_waits_for_review_and_is_not_public(): void
    {
        $user = User::factory()->create(['name' => 'Rahim Uddin']);

        $this->actingAs($user)->get(self::CH1)->assertOk()->assertSee('Send for review');

        $this->actingAs($user)->post(self::SEND, $this->resource(['lang' => 'bn']))
            ->assertRedirect(url(self::CH1.'?lang=bn#contribute'))
            ->assertSessionHas('status');

        $saved = Contribution::sole();
        $this->assertSame(Contribution::PENDING, $saved->status);
        $this->assertSame($user->id, $saved->user_id);
        $this->assertSame('https://example.com/docker-networking', $saved->url);

        // The sender is told it arrived…
        $this->actingAs($user)->get(self::CH1)->assertOk()
            ->assertSee('Waiting for review')
            ->assertSee('Docker networking in 15 minutes')
            ->assertDontSee('https://example.com/docker-networking');
        $this->actingAs($user)->get('/my-learning')->assertOk()->assertSee('Docker networking in 15 minutes');

        // …but nobody else sees anything.
        $this->actingAs(User::factory()->create())->get(self::CH1)->assertOk()->assertDontSee('Docker networking in 15 minutes');
        auth()->logout();
        $this->get(self::CH1)->assertOk()->assertDontSee('Docker networking in 15 minutes');
    }

    public function test_a_sender_cannot_approve_their_own_suggestion_through_the_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(self::SEND, $this->resource(['status' => 'approved', 'user_id' => 999, 'admin_note' => 'x']));

        $saved = Contribution::sole();
        $this->assertSame(Contribution::PENDING, $saved->status);
        $this->assertSame($user->id, $saved->user_id);
        $this->assertNull($saved->admin_note);
    }

    public function test_what_is_sent_is_checked(): void
    {
        $user = User::factory()->create();
        $send = fn (array $data) => $this->actingAs($user)->post(self::SEND, $data);

        $send(['type' => 'nonsense', 'details' => 'x'])->assertSessionHasErrorsIn('contribution', ['type']);
        $send($this->resource(['title' => '']))->assertSessionHasErrorsIn('contribution', ['title']);
        $send($this->resource(['url' => '']))->assertSessionHasErrorsIn('contribution', ['url']);
        $send($this->resource(['url' => 'not a link']))->assertSessionHasErrorsIn('contribution', ['url']);
        $send($this->resource(['url' => 'javascript:alert(1)']))->assertSessionHasErrorsIn('contribution', ['url']);
        $send($this->resource(['url' => 'ftp://example.com/file']))->assertSessionHasErrorsIn('contribution', ['url']);
        $send(['type' => 'tip', 'details' => '   '])->assertSessionHasErrorsIn('contribution', ['details']);
        $send(['type' => 'correction'])->assertSessionHasErrorsIn('contribution', ['details']);
        $send(['type' => 'tip', 'details' => str_repeat('a', Contribution::BODY_MAX + 1)])->assertSessionHasErrorsIn('contribution', ['details']);

        $this->assertSame(0, Contribution::count());

        // The form comes back open, with what was typed and the reason it failed.
        $this->actingAs($user)->followingRedirects()->post(self::SEND, $this->resource(['url' => 'not a link']))
            ->assertOk()
            ->assertSee('That does not look like a link')
            ->assertSee('Docker networking in 15 minutes');

        // Unknown chapters do not exist for this either.
        $send($this->resource())->assertRedirect();
        $this->actingAs($user)->post('/learn/docker/no-such-chapter/contributions', $this->resource())->assertNotFound();
        $this->actingAs($user)->post('/learn/kubernetes/anything/contributions', $this->resource())->assertNotFound();
        $this->assertSame(1, Contribution::count());
    }

    public function test_a_tip_keeps_only_its_text_and_a_resource_may_skip_the_description(): void
    {
        $user = User::factory()->create();

        // Title and link typed before switching to "tip" are ignored.
        $this->actingAs($user)->post(self::SEND, ['type' => 'tip', 'title' => 'left over', 'url' => 'https://example.com/x', 'details' => "line one\r\nline two"]);
        $tip = Contribution::sole();
        $this->assertNull($tip->title);
        $this->assertNull($tip->url);
        $this->assertSame("line one\nline two", $tip->body);

        $this->actingAs($user)->post(self::SEND, $this->resource(['details' => '']))->assertSessionHasNoErrors();
        $this->assertNull(Contribution::where('type', 'resource')->sole()->body);
    }

    public function test_the_same_link_is_not_suggested_twice_for_one_chapter(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->post(self::SEND, $this->resource())->assertSessionHasNoErrors();
        $this->actingAs($second)->post(self::SEND, $this->resource(['title' => 'Same link again']))
            ->assertSessionHasErrorsIn('contribution', ['url']);

        // Another chapter is fine, and so is trying again after a rejection.
        $this->actingAs($second)->post('/learn/docker/02-install-and-first-container/contributions', $this->resource())->assertSessionHasNoErrors();
        Contribution::where('user_id', $first->id)->sole()->forceFill(['status' => Contribution::REJECTED])->save();
        $this->actingAs($second)->post(self::SEND, $this->resource())->assertSessionHasNoErrors();

        $this->assertSame(3, Contribution::count());
    }

    public function test_there_is_a_daily_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= Contribution::DAILY_LIMIT; $i++) {
            $this->actingAs($user)->post(self::SEND, ['type' => 'tip', 'details' => "tip number {$i}"])->assertSessionHasNoErrors();
        }

        $this->actingAs($user)->post(self::SEND, ['type' => 'tip', 'details' => 'one too many'])
            ->assertSessionHasErrorsIn('contribution', ['limit']);
        $this->assertSame(Contribution::DAILY_LIMIT, Contribution::count());

        // A day later the reader can send again.
        $this->travel(25)->hours();
        $this->actingAs($user)->post(self::SEND, ['type' => 'tip', 'details' => 'a new day'])->assertSessionHasNoErrors();
        $this->assertSame(Contribution::DAILY_LIMIT + 1, Contribution::count());
    }

    // ---- Review ----------------------------------------------------------------------

    public function test_only_admins_can_open_the_queue_or_decide(): void
    {
        $user = User::factory()->create();
        $item = $this->make($user);

        $this->get('/admin/contributions')->assertRedirect(route('login'));
        $this->actingAs($user)->get('/admin/contributions')->assertNotFound();
        $this->actingAs($user)->post("/admin/contributions/{$item->id}/approve")->assertNotFound();
        $this->actingAs($user)->post("/admin/contributions/{$item->id}/reject")->assertNotFound();

        $this->assertSame(Contribution::PENDING, $item->fresh()->status);
    }

    public function test_an_approved_resource_shows_under_the_chapter_with_the_senders_name(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Rahim Uddin', 'email' => 'rahim@example.com']);
        $item = $this->make($user, ['type' => 'resource', 'title' => 'Great <b>video</b>', 'url' => 'https://www.example.com/v?a=1&b=2', 'body' => '<script>alert(1)</script> worth it']);

        $this->actingAs($admin)->get('/admin/contributions')->assertOk()
            ->assertSee('Rahim Uddin')
            ->assertSee('rahim@example.com')
            ->assertSee('Approve &amp; publish', false);

        $this->actingAs($admin)->post("/admin/contributions/{$item->id}/approve")->assertRedirect()->assertSessionHas('status');

        $item->refresh();
        $this->assertSame(Contribution::APPROVED, $item->status);
        $this->assertSame($admin->id, $item->reviewed_by);
        $this->assertNotNull($item->reviewed_at);

        auth()->logout();
        $this->get(self::CH1)->assertOk()
            ->assertSee('Rahim Uddin')
            ->assertSee('example.com')
            ->assertSee('rel="nofollow ugc noopener noreferrer"', false)
            ->assertSee('href="https://www.example.com/v?a=1&amp;b=2"', false)
            // Whatever was typed is shown as text, never run as HTML.
            ->assertSee('Great &lt;b&gt;video&lt;/b&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            // Email is private unless the sender chose to show it.
            ->assertDontSee('rahim@example.com');

        // Other chapters are not affected.
        $this->get('/docker/02-install-and-first-container')->assertOk()->assertDontSee('Rahim Uddin');

        $user->update(['show_email' => true]);
        $this->get(self::CH1)->assertOk()->assertSee('rahim@example.com');
    }

    public function test_rejecting_keeps_it_private_and_tells_the_sender_why(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $item = $this->make($user, ['body' => 'Buy my course at spam dot com']);

        $this->actingAs($admin)->post("/admin/contributions/{$item->id}/reject", ['admin_note' => 'This is an advert.'])->assertRedirect();

        $this->assertSame(Contribution::REJECTED, $item->fresh()->status);
        $this->actingAs($admin)->get('/admin/contributions?status=rejected')->assertOk()->assertSee('This is an advert.');

        $this->actingAs($user)->get('/my-learning')->assertOk()
            ->assertSee('Not accepted')
            ->assertSee('This is an advert.');
        $this->actingAs($user)->get(self::CH1)->assertOk()->assertDontSee('Buy my course');

        // The admin can change their mind in both directions.
        $this->actingAs($admin)->post("/admin/contributions/{$item->id}/approve");
        $this->assertNull($item->fresh()->admin_note);
        auth()->logout();
        $this->get(self::CH1)->assertOk()->assertSee('Buy my course');

        $this->actingAs($admin)->post("/admin/contributions/{$item->id}/reject");
        auth()->logout();
        $this->get(self::CH1)->assertOk()->assertDontSee('Buy my course');
    }

    public function test_mistake_reports_are_never_public(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($user)->post(self::SEND, ['type' => 'correction', 'details' => 'The port in step 3 should be 8080.'])
            ->assertSessionHas('status');
        $item = Contribution::sole();

        $this->actingAs($admin)->get('/admin/contributions')->assertOk()->assertSee('Mark as resolved');
        $this->actingAs($admin)->post("/admin/contributions/{$item->id}/approve");

        $this->actingAs($user)->get('/my-learning')->assertOk()->assertSee('Resolved — thank you');
        auth()->logout();
        $this->get(self::CH1)->assertOk()->assertDontSee('The port in step 3');
    }

    // ---- Taking it back --------------------------------------------------------------

    public function test_a_sender_can_remove_their_own_contribution_but_not_someone_elses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->make($user, [], Contribution::APPROVED);
        $theirs = $this->make($other, ['body' => 'Someone else wrote this.'], Contribution::APPROVED);

        $this->actingAs($user)->delete("/contributions/{$theirs->id}")->assertNotFound();
        $this->actingAs($user)->from('/my-learning')->delete("/contributions/{$mine->id}")->assertRedirect('/my-learning');

        $this->assertSame([$theirs->id], Contribution::pluck('id')->all());
        $this->get(self::CH1)->assertOk()->assertDontSee('docker ps -a')->assertSee('Someone else wrote this.');
    }

    public function test_deleting_an_account_removes_its_contributions(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['password' => 'secret-pass-1']);
        $item = $this->make($user);
        $this->actingAs($admin)->post("/admin/contributions/{$item->id}/approve");

        $this->actingAs($user)->delete('/account', ['password' => 'secret-pass-1'])->assertRedirect(route('home'));

        $this->assertSame(0, Contribution::count());
        $this->get(self::CH1)->assertOk()->assertDontSee('docker ps -a');
    }

    public function test_the_account_menu_shows_the_admin_how_many_are_waiting(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->make($user);
        $this->make($user, ['body' => 'second tip']);

        $this->actingAs($admin)->get('/')->assertOk()->assertSee('2 to review');
        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('to review')->assertDontSee('Admin · Contributions');
    }
}
