<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\GoogleSignIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::put('google.domain', 'school.test');
        Setting::put('google.client_id', 'id.apps.googleusercontent.com');
        Setting::putSecret('google.client_secret', 'secret');
    }

    private function googleReturns(string $email, array $raw = []): void
    {
        $account = (new GoogleUser)
            ->setRaw(array_merge(['email_verified' => true, 'hd' => 'school.test'], $raw))
            ->map(['id' => 'g-123', 'email' => $email, 'name' => 'Pat Admin']);

        $this->mock(GoogleSignIn::class)->shouldReceive('user')->andReturn($account);
    }

    public function test_the_login_page_has_only_google(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in with Google')->assertDontSee('Password');
    }

    public function test_sign_in_goes_to_google_for_the_school_domain(): void
    {
        $location = $this->get('/auth/google')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertStringContainsString('hd=school.test', $location);
        $this->assertStringContainsString(urlencode(GoogleSignIn::callbackUrl()), $location);
    }

    public function test_an_existing_user_signs_in(): void
    {
        $user = User::factory()->create(['email' => 'pat@school.test']);
        $this->googleReturns('pat@school.test');

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-123', $user->fresh()->google_id);
    }

    public function test_an_account_from_another_domain_is_refused(): void
    {
        User::factory()->create(['email' => 'pat@other.test']);
        $this->googleReturns('pat@other.test', ['hd' => 'other.test']);

        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_a_personal_gmail_account_is_refused(): void
    {
        $this->googleReturns('pat@gmail.com', ['hd' => null]);

        $this->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_an_unverified_email_is_refused(): void
    {
        User::factory()->create(['email' => 'pat@school.test']);
        $this->googleReturns('pat@school.test', ['email_verified' => false]);

        $this->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_school_accounts_without_a_user_are_refused(): void
    {
        User::factory()->create(['is_admin' => true]);
        $this->googleReturns('student@school.test');

        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'student@school.test']);
    }

    public function test_users_can_sign_out(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
