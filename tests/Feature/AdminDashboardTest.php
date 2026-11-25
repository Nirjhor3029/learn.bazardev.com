<?php

namespace Tests\Feature;

use App\Models\CoursePricing;
use App\Models\Entitlement;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_a_non_admin_cannot_see_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertNotFound();
    }

    public function test_an_admin_sees_the_dashboard_with_revenue_and_counts(): void
    {
        $admin = $this->admin();
        $buyer = User::factory()->create();

        CoursePricing::create(['course_slug' => 'docker', 'is_free' => false, 'price_amount' => 500]);

        PaymentTransaction::create([
            'user_id' => $buyer->id, 'tran_id' => 'ltx-paid', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PAID,
        ]);
        PaymentTransaction::create([
            'user_id' => $buyer->id, 'tran_id' => 'ltx-pending', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PENDING,
        ]);

        $buyer->entitlements()->create([
            'product_type' => Entitlement::COURSE, 'product_key' => 'docker',
            'source' => Entitlement::PURCHASE, 'starts_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('500') // revenue
            ->assertSee('1 waiting') // the pending transaction
            ->assertSeeText('Dashboard');
    }
}
