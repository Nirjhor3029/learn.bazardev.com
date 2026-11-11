<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    private function certificate(?User $user = null, array $attributes = []): Certificate
    {
        $user ??= User::factory()->create(['name' => 'Rahim Uddin', 'email' => 'rahim-secret@example.com']);

        return $user->certificates()->create([
            'course' => 'docker',
            'code' => 'ABCD-2345-WXYZ',
            'name' => 'Rahim Uddin',
            'score' => 18,
            'total' => 20,
            ...$attributes,
        ]);
    }

    public function test_anyone_can_open_a_certificate_by_its_code(): void
    {
        $this->certificate();

        $this->get('/certificate/ABCD-2345-WXYZ')->assertOk()
            ->assertSee('Certificate of Completion')
            ->assertSee('Rahim Uddin')
            ->assertSee('Docker')
            ->assertSee('18 out of 20 (90%)')
            ->assertSee('ABCD-2345-WXYZ')
            ->assertSee('not an accredited qualification')
            ->assertDontSee('rahim-secret@example.com')
            ->assertDontSee('Use my account name')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_code_is_not_case_sensitive(): void
    {
        $this->certificate();

        $this->get('/certificate/abcd-2345-wxyz')->assertOk()->assertSee('Rahim Uddin');
    }

    public function test_unknown_or_malformed_codes_are_404(): void
    {
        $this->certificate();

        $this->get('/certificate/ZZZZ-2345-WXYZ')->assertNotFound();
        $this->get('/certificate/short')->assertNotFound();
        $this->get('/certificate/ABCD-2345-WXYZ-EXTRA')->assertNotFound();
        $this->get('/certificate/'.str_repeat('A', 14))->assertNotFound();
    }

    public function test_a_name_is_printed_escaped(): void
    {
        $this->certificate(attributes: ['name' => '<script>alert(1)</script>']);

        $this->get('/certificate/ABCD-2345-WXYZ')->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_the_owner_sees_the_name_box_and_other_readers_do_not(): void
    {
        $certificate = $this->certificate();

        $this->actingAs($certificate->user)->get('/certificate/ABCD-2345-WXYZ')->assertOk()
            ->assertSee('Name on this certificate')
            ->assertSee('My Learning');

        $this->actingAs(User::factory()->create())->get('/certificate/ABCD-2345-WXYZ')->assertOk()
            ->assertDontSee('Name on this certificate');
    }

    public function test_the_owner_can_follow_their_account_name_again(): void
    {
        $certificate = $this->certificate();
        $certificate->user->update(['name' => 'Rahim Uddin Ahmed']);

        $this->actingAs($certificate->user)->get('/certificate/ABCD-2345-WXYZ')
            ->assertSee('your account name is now')
            ->assertSee('Use my account name');

        $this->actingAs($certificate->user)->post('/certificate/ABCD-2345-WXYZ/name')->assertRedirect();

        $this->assertSame('Rahim Uddin Ahmed', $certificate->fresh()->name);
        $this->assertSame('ABCD-2345-WXYZ', $certificate->fresh()->code);
    }

    public function test_nobody_else_can_change_the_name(): void
    {
        $certificate = $this->certificate();

        $this->post('/certificate/ABCD-2345-WXYZ/name')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['name' => 'Someone Else']))
            ->post('/certificate/ABCD-2345-WXYZ/name')->assertNotFound();

        $this->assertSame('Rahim Uddin', $certificate->fresh()->name);
    }

    public function test_certificates_cannot_be_created_through_mass_assignment(): void
    {
        $user = User::factory()->create();

        $certificate = new Certificate(['user_id' => $user->id, 'course' => 'docker', 'code' => 'AAAA-BBBB-CCCC', 'name' => 'X', 'score' => 20, 'total' => 20]);

        $this->assertNull($certificate->user_id);
    }

    public function test_one_certificate_per_reader_per_course(): void
    {
        $certificate = $this->certificate();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->certificate($certificate->user, ['code' => 'EEEE-FFFF-GGGG']);
    }

    public function test_new_codes_are_unique_and_readable(): void
    {
        $codes = [];
        for ($i = 0; $i < 50; $i++) {
            $codes[] = Certificate::newCode();
        }

        $this->assertCount(50, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $code);
        }
    }

    public function test_the_dashboard_lists_certificates_only_when_there_are_some(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/my-learning')->assertOk()->assertDontSee('id="certificates"', false);

        $this->certificate($user);

        $this->actingAs($user)->get('/my-learning')->assertOk()
            ->assertSee('id="certificates"', false)
            ->assertSee('Score 18/20')
            ->assertSee('/certificate/ABCD-2345-WXYZ', false);
    }

    public function test_certificates_are_deleted_with_the_account(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);
        $this->certificate($user);

        $this->actingAs($user)->delete('/account', ['password' => 'secret-pass-1'])->assertRedirect();

        $this->assertSame(0, Certificate::count());
        $this->get('/certificate/ABCD-2345-WXYZ')->assertNotFound();
    }
}
