<?php

namespace Tests\Feature;

use App\Models\CoursePricing;
use App\Models\Entitlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementTest extends TestCase
{
    use RefreshDatabase;

    private const CH1 = '/docker/01-what-is-docker';

    private const CH2 = '/docker/02-install-and-first-container';

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function markPaid(string $course = 'docker', int $price = 500): void
    {
        CoursePricing::create([
            'course_slug' => $course,
            'is_free' => false,
            'price_amount' => $price,
            'subscription_eligible' => true,
        ]);
    }

    public function test_a_course_with_no_pricing_row_stays_open_to_a_guest(): void
    {
        $this->get(self::CH1)->assertOk();
        $this->get(self::CH2)->assertOk();
    }

    public function test_marking_a_course_paid_still_lets_a_guest_read_chapter_one(): void
    {
        $this->markPaid();

        $this->get(self::CH1)->assertOk();
    }

    public function test_marking_a_course_paid_locks_a_guest_out_of_chapter_two(): void
    {
        $this->markPaid();

        $this->get(self::CH2)->assertRedirect(route('course.show', 'docker'));
    }

    public function test_marking_a_course_paid_locks_a_logged_in_reader_with_no_entitlement(): void
    {
        $this->markPaid();
        $user = User::factory()->create();

        $this->actingAs($user)->get(self::CH2)->assertRedirect(route('course.show', 'docker'));
    }

    public function test_a_course_entitlement_unlocks_chapter_two(): void
    {
        $this->markPaid();
        $user = User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);

        $this->actingAs($user)->get(self::CH2)->assertOk();
    }

    public function test_a_subscription_unlocks_every_paid_course(): void
    {
        $this->markPaid();
        $user = User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::SUBSCRIPTION_ALL_ACCESS,
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);

        $this->actingAs($user)->get(self::CH2)->assertOk();
    }

    public function test_an_expired_entitlement_does_not_unlock_the_course(): void
    {
        $this->markPaid();
        $user = User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->get(self::CH2)->assertRedirect(route('course.show', 'docker'));
    }

    public function test_a_revoked_entitlement_does_not_unlock_the_course(): void
    {
        $this->markPaid();
        $user = User::factory()->create();
        $entitlement = $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);
        $entitlement->forceFill(['revoked_at' => now()])->save();

        $this->actingAs($user)->get(self::CH2)->assertRedirect(route('course.show', 'docker'));
    }

    public function test_admin_can_set_a_course_free(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put('/admin/pricing/docker', ['is_free' => '1', 'price_amount' => 0, 'subscription_eligible' => '1'])
            ->assertRedirect();

        $this->assertTrue(CoursePricing::isFree('docker'));
    }

    public function test_admin_can_mark_a_course_paid_with_a_price(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put('/admin/pricing/docker', ['price_amount' => 750])
            ->assertRedirect();

        $pricing = CoursePricing::query()->where('course_slug', 'docker')->first();
        $this->assertFalse($pricing->is_free);
        $this->assertSame(750, $pricing->price_amount);
        $this->assertSame($admin->id, $pricing->updated_by);
    }

    public function test_saving_a_price_over_ajax_returns_json_instead_of_a_redirect(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->putJson('/admin/pricing/docker', ['price_amount' => 750]);

        $response->assertOk()->assertJsonStructure(['message', 'updated_at', 'updated_by']);
        $response->assertJsonPath('updated_by', $admin->name);

        $pricing = CoursePricing::query()->where('course_slug', 'docker')->first();
        $this->assertFalse($pricing->is_free);
        $this->assertSame(750, $pricing->price_amount);
    }

    public function test_an_invalid_price_over_ajax_returns_a_422_with_errors_not_a_redirect(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/admin/pricing/docker', ['price_amount' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price_amount');
    }

    public function test_admin_can_pick_a_different_chapter_as_the_free_preview(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/admin/pricing/docker', [
                'price_amount' => 500,
                'free_chapter_slugs' => ['02-install-and-first-container'],
            ])
            ->assertOk();

        $this->get(self::CH1)->assertRedirect(route('course.show', 'docker'));
        $this->get(self::CH2)->assertOk();
    }

    public function test_an_admin_can_lock_every_chapter_by_submitting_no_free_chapters(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/admin/pricing/docker', [
                'price_amount' => 500,
                'free_chapter_slugs' => [],
            ])
            ->assertOk();

        $this->get(self::CH1)->assertRedirect(route('course.show', 'docker'));
    }

    public function test_updating_the_price_without_touching_chapters_keeps_the_default_free_preview(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->putJson('/admin/pricing/docker', ['price_amount' => 600])
            ->assertOk();

        $this->get(self::CH1)->assertOk();
        $this->get(self::CH2)->assertRedirect(route('course.show', 'docker'));
    }

    public function test_admin_pricing_screen_renders(): void
    {
        $admin = $this->admin();
        $this->markPaid();

        $this->actingAs($admin)->get('/admin/pricing')
            ->assertOk()
            ->assertSee('Docker')
            ->assertSee('value="500"', false)
            ->assertSee('Not configured — currently open/free');
    }

    public function test_the_home_page_shows_a_lock_and_price_on_a_paid_course_a_guest_does_not_own(): void
    {
        $this->markPaid();

        $this->get('/')->assertOk()->assertSee('500 BDT');
    }

    public function test_the_home_page_hides_the_lock_once_the_user_owns_the_course(): void
    {
        $this->markPaid();
        $user = User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);

        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('500 BDT');
    }

    public function test_the_course_page_locks_every_chapter_but_the_first_for_a_non_owner(): void
    {
        $this->markPaid();

        $response = $this->get('/docker');

        $response->assertOk();
        $response->assertSeeInOrder(['01-what-is-docker', 'Requires purchase'], false);
    }

    public function test_the_course_page_shows_no_lock_once_the_course_is_owned(): void
    {
        $this->markPaid();
        $user = User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);

        $this->actingAs($user)->get('/docker')->assertOk()->assertDontSee('Requires purchase');
    }

    public function test_admin_entitlements_screen_renders(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Karim Hasan']);
        $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'granted_by' => $admin->id,
            'grant_note' => 'manual test grant',
            'starts_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/entitlements')
            ->assertOk()
            ->assertSee('Karim Hasan')
            ->assertSee('manual test grant');

        $this->actingAs($admin)->get('/admin/entitlements?q=Karim')
            ->assertOk()
            ->assertSee('Karim Hasan');
    }

    public function test_a_non_admin_cannot_reach_the_pricing_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/pricing')->assertNotFound();
    }

    public function test_admin_can_grant_a_user_free_access_to_a_course_with_a_note(): void
    {
        $admin = $this->admin();
        $this->markPaid();
        $user = User::factory()->create();

        $this->actingAs($admin)->post('/admin/entitlements', [
            'user_id' => $user->id,
            'product_type' => 'course',
            'product_key' => 'docker',
            'grant_note' => 'Early supporter, comped by the owner.',
        ])->assertRedirect();

        $this->assertDatabaseHas('entitlements', [
            'user_id' => $user->id,
            'product_type' => 'course',
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'granted_by' => $admin->id,
        ]);

        $this->actingAs($user)->get(self::CH2)->assertOk();
    }

    public function test_granting_access_without_a_note_fails_validation(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->post('/admin/entitlements', [
            'user_id' => $user->id,
            'product_type' => 'course',
            'product_key' => 'docker',
            'grant_note' => '',
        ])->assertSessionHasErrors('grant_note');
    }

    public function test_admin_can_revoke_a_grant_and_access_is_removed(): void
    {
        $admin = $this->admin();
        $this->markPaid();
        $user = User::factory()->create();
        $entitlement = $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'granted_by' => $admin->id,
            'grant_note' => 'test',
            'starts_at' => now(),
        ]);

        $this->actingAs($user)->get(self::CH2)->assertOk();

        $this->actingAs($admin)->post("/admin/entitlements/{$entitlement->id}/revoke")->assertRedirect();

        $this->actingAs($user)->get(self::CH2)->assertRedirect(route('course.show', 'docker'));
    }

    public function test_terminal_access_is_separate_from_owning_the_course(): void
    {
        $this->markPaid();
        $user = User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);

        $entitlements = app(\App\Services\Entitlements::class);

        $this->assertTrue($entitlements->canAccessCourse($user, 'docker'));
        $this->assertFalse($entitlements->canAccessTerminal($user, 'docker'));
    }
}
