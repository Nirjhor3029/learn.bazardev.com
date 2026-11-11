<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rahim Uddin',
            'email' => 'rahim@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ], $overrides);
    }

    // ---- Public reading is untouched -------------------------------------------------

    public function test_course_pages_stay_public_and_auth_urls_are_not_swallowed_by_course_routes(): void
    {
        $this->get('/')->assertOk()->assertSee('Sign up free');
        $this->get('/docker')->assertOk();
        $this->get('/docker/01-what-is-docker')->assertOk();
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->get('/register')->assertOk()->assertSee('Create your account');
        $this->get('/forgot-password')->assertOk();
        $this->get('/no-such-course')->assertNotFound();
    }

    // ---- Register --------------------------------------------------------------------

    public function test_someone_can_register_and_is_logged_in_but_not_yet_verified(): void
    {
        Notification::fake();

        $this->post('/register', $this->validRegistration(['email' => '  Rahim@Example.com ']))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status');

        $user = User::where('email', 'rahim@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
        // Mail is not live in tests ("array" mailer), so no verification email is promised or sent.
        Notification::assertNothingSent();
    }

    public function test_verification_email_is_sent_once_a_real_mailer_is_configured(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);

        $this->post('/register', $this->validRegistration());

        Notification::assertSentTo(User::firstOrFail(), VerifyEmail::class);
    }

    public function test_registration_rejects_bad_input_and_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'rahim@example.com']);

        $this->post('/register', $this->validRegistration())->assertSessionHasErrors('email');
        $this->post('/register', $this->validRegistration(['email' => 'new@example.com', 'password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');
        $this->post('/register', $this->validRegistration(['email' => 'new@example.com', 'password_confirmation' => 'different-1']))
            ->assertSessionHasErrors('password');

        $this->assertSame(1, User::count());
        $this->assertGuest();
    }

    public function test_honeypot_silently_drops_bots_and_admin_flag_cannot_be_injected(): void
    {
        $this->post('/register', $this->validRegistration(['nickname_confirm' => 'http://spam.example']))
            ->assertRedirect(route('home'));
        $this->assertSame(0, User::count());

        $this->post('/register', $this->validRegistration(['is_admin' => 1, 'email_verified_at' => now()]));
        $user = User::firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    // ---- Login / logout --------------------------------------------------------------

    public function test_login_logout_and_wrong_password(): void
    {
        $user = User::factory()->create(['email' => 'rahim@example.com', 'password' => 'secret-pass-1']);

        $this->post('/login', ['email' => 'rahim@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'RAHIM@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        // Logged-in people see their menu instead of the sign-up button.
        $this->get('/')->assertOk()->assertSee('Account settings')->assertDontSee('Sign up free');
        $this->get('/login')->assertRedirect(route('home'));

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_login_is_locked_after_five_wrong_attempts(): void
    {
        User::factory()->create(['email' => 'rahim@example.com', 'password' => 'secret-pass-1']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'rahim@example.com', 'password' => 'wrong-password']);
        }

        // Even the correct password is refused while locked.
        $this->post('/login', ['email' => 'rahim@example.com', 'password' => 'secret-pass-1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guests_are_sent_to_login_then_back_to_where_they_were_going(): void
    {
        User::factory()->create(['email' => 'rahim@example.com', 'password' => 'secret-pass-1']);

        $this->get('/account')->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'rahim@example.com', 'password' => 'secret-pass-1'])
            ->assertRedirect(route('account.edit'));
    }

    // ---- Account settings ------------------------------------------------------------

    public function test_profile_update_and_email_visibility_opt_in(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->fresh()->show_email);

        $this->actingAs($user)->get('/account')->assertOk()->assertSee($user->email);

        $this->actingAs($user)->patch('/account', ['name' => 'New Name', 'show_email' => '1', 'is_admin' => 1])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertTrue($user->show_email);
        $this->assertFalse($user->is_admin);

        // Unticking the box turns it back off.
        $this->actingAs($user)->patch('/account', ['name' => 'New Name']);
        $this->assertFalse($user->fresh()->show_email);
    }

    public function test_password_change_needs_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'not-my-password',
            'password' => 'brand-new-pass-2',
            'password_confirmation' => 'brand-new-pass-2',
        ])->assertSessionHasErrorsIn('password', 'current_password');
        $this->assertTrue(Hash::check('secret-pass-1', $user->fresh()->password));

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'secret-pass-1',
            'password' => 'brand-new-pass-2',
            'password_confirmation' => 'brand-new-pass-2',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-new-pass-2', $user->fresh()->password));
    }

    public function test_account_deletion_needs_password_and_admins_cannot_delete_themselves(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);

        $this->actingAs($user)->delete('/account', ['password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('delete', 'password');
        $this->assertNotNull($user->fresh());

        $this->actingAs($user)->delete('/account', ['password' => 'secret-pass-1'])
            ->assertRedirect(route('home'));
        $this->assertNull($user->fresh());
        $this->assertGuest();

        $admin = User::factory()->create(['password' => 'secret-pass-1']);
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->delete('/account', ['password' => 'secret-pass-1'])
            ->assertSessionHasErrorsIn('delete', 'password');
        $this->assertNotNull($admin->fresh());
    }

    // ---- Email verification ----------------------------------------------------------

    public function test_signed_link_verifies_the_email_and_a_tampered_link_does_not(): void
    {
        $user = User::factory()->unverified()->create();

        $params = ['id' => $user->id, 'hash' => sha1($user->email)];
        $goodUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), $params);

        $this->actingAs($user)->get(route('verification.verify', $params))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $this->actingAs($user)->get($goodUrl)->assertRedirect(route('account.edit'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    // ---- Password reset --------------------------------------------------------------

    public function test_password_can_be_reset_with_the_emailed_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'rahim@example.com', 'password' => 'secret-pass-1']);

        $this->post('/forgot-password', ['email' => 'rahim@example.com'])->assertSessionHas('status');
        // Unknown emails get the exact same answer (no account enumeration).
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=rahim@example.com")->assertOk();

        $this->post('/reset-password', [
            'token' => 'not-the-token',
            'email' => 'rahim@example.com',
            'password' => 'brand-new-pass-2',
            'password_confirmation' => 'brand-new-pass-2',
        ])->assertSessionHasErrors('email');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'rahim@example.com',
            'password' => 'brand-new-pass-2',
            'password_confirmation' => 'brand-new-pass-2',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('brand-new-pass-2', $user->fresh()->password));
    }

    // ---- Admin -----------------------------------------------------------------------

    public function test_admin_area_is_hidden_from_everyone_but_admins(): void
    {
        $pending = User::factory()->unverified()->create(['name' => 'Pending Person']);

        $this->get('/admin/users')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertNotFound();
        $this->actingAs(User::factory()->create())->post("/admin/users/{$pending->id}/verify")->assertNotFound();
        $this->assertFalse($pending->fresh()->hasVerifiedEmail());
    }

    public function test_admin_can_list_filter_and_approve_users(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $pending = User::factory()->unverified()->create(['name' => 'Pending Person']);
        User::factory()->create(['name' => 'Verified Person']);

        $this->actingAs($admin)->get('/admin/users')->assertOk()
            ->assertSee('Pending Person')->assertSee('Verified Person');
        $this->actingAs($admin)->get('/admin/users?filter=pending')->assertOk()
            ->assertSee('Pending Person')->assertDontSee('Verified Person');
        $this->actingAs($admin)->get('/admin/users?q=Verified')->assertOk()
            ->assertDontSee('Pending Person')->assertSee('Verified Person');

        $this->actingAs($admin)->post("/admin/users/{$pending->id}/verify")->assertSessionHas('status');
        $this->assertTrue($pending->fresh()->hasVerifiedEmail());
    }

    public function test_make_admin_command_creates_a_verified_admin_and_keeps_existing_passwords(): void
    {
        $this->artisan('app:make-admin', ['email' => 'Boss@Example.com'])->assertSuccessful();

        $admin = User::where('email', 'boss@example.com')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue($admin->hasVerifiedEmail());

        $existing = User::factory()->unverified()->create(['password' => 'secret-pass-1']);
        $this->artisan('app:make-admin', ['email' => $existing->email])->assertSuccessful();

        $existing->refresh();
        $this->assertTrue($existing->is_admin);
        $this->assertTrue(Hash::check('secret-pass-1', $existing->password));
    }
}
