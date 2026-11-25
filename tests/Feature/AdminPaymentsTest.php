<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    public function test_a_non_admin_cannot_see_the_payments_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/payments')->assertNotFound();
    }

    public function test_an_admin_sees_every_transaction(): void
    {
        $admin = $this->admin();
        $buyer = User::factory()->create(['name' => 'Rina Akter']);

        PaymentTransaction::create([
            'user_id' => $buyer->id, 'tran_id' => 'ltx-1', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PAID,
        ]);

        $this->actingAs($admin)->get('/admin/payments')
            ->assertOk()
            ->assertSeeText('Rina Akter')
            ->assertSeeText('ltx-1');
    }

    public function test_filtering_by_status_hides_other_transactions(): void
    {
        $admin = $this->admin();
        $buyer = User::factory()->create();

        PaymentTransaction::create([
            'user_id' => $buyer->id, 'tran_id' => 'ltx-paid', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PAID,
        ]);
        PaymentTransaction::create([
            'user_id' => $buyer->id, 'tran_id' => 'ltx-expired', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::EXPIRED,
        ]);

        $response = $this->actingAs($admin)->get('/admin/payments?status=paid');

        $response->assertSeeText('ltx-paid');
        $response->assertDontSeeText('ltx-expired');
    }

    public function test_searching_by_user_name_filters_the_list(): void
    {
        $admin = $this->admin();
        $rina = User::factory()->create(['name' => 'Rina Akter']);
        $karim = User::factory()->create(['name' => 'Karim Hossain']);

        PaymentTransaction::create([
            'user_id' => $rina->id, 'tran_id' => 'ltx-rina', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PAID,
        ]);
        PaymentTransaction::create([
            'user_id' => $karim->id, 'tran_id' => 'ltx-karim', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PAID,
        ]);

        $response = $this->actingAs($admin)->get('/admin/payments?q=Rina');

        $response->assertSeeText('ltx-rina');
        $response->assertDontSeeText('ltx-karim');
    }
}
