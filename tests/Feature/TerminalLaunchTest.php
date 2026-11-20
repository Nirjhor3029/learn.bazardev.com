<?php

namespace Tests\Feature;

use App\Models\Entitlement;
use App\Models\TerminalLaunchTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminalLaunchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reader_with_no_terminal_entitlement_is_sent_back_with_a_message_and_no_ticket_is_minted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/terminal/launch')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('terminal_launch_tickets', 0);
    }

    public function test_a_reader_with_a_terminal_entitlement_gets_a_ticket_and_is_sent_to_the_terminal_app(): void
    {
        $user = User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::TERMINAL,
            'product_key' => 'linux-shell',
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);

        $this->actingAs($user)->get('/terminal/launch')->assertRedirect();

        $this->assertDatabaseCount('terminal_launch_tickets', 1);
        $ticket = TerminalLaunchTicket::first();
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame('linux-shell', $ticket->course_slug);
        $this->assertTrue($ticket->isValid());
    }

    public function test_owning_the_linux_shell_course_alone_does_not_unlock_the_terminal(): void
    {
        $user = User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'linux-shell',
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);

        $this->actingAs($user)->get('/terminal/launch')->assertSessionHas('status');
        $this->assertDatabaseCount('terminal_launch_tickets', 0);
    }

    public function test_an_all_access_subscription_does_not_unlock_the_terminal_either(): void
    {
        // The terminal is a deliberately separate add-on - not even the
        // all-access subscription implies it.
        $user = User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::SUBSCRIPTION_ALL_ACCESS,
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);

        $this->actingAs($user)->get('/terminal/launch')->assertSessionHas('status');
        $this->assertDatabaseCount('terminal_launch_tickets', 0);
    }

    public function test_verify_echoes_back_the_tickets_course_slug(): void
    {
        config(['services.terminal.shared_secret' => 'test-secret']);

        $user = User::factory()->create();
        $ticket = TerminalLaunchTicket::create([
            'user_id' => $user->id,
            'course_slug' => 'linux-shell',
            'token' => 'test-token-1234',
            'expires_at' => now()->addSeconds(60),
        ]);

        $response = $this->withHeader('X-Terminal-Secret', 'test-secret')
            ->post('/internal/terminal/verify-ticket', ['ticket' => $ticket->token]);

        $response->assertOk()->assertJson(['course' => 'linux-shell']);
    }
}
