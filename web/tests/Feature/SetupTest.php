<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\GoogleSignIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $setupComplete = false;

    private function saveGoogle(): void
    {
        Setting::put('google.domain', 'school.test');
        Setting::put('google.client_id', 'id.apps.googleusercontent.com');
        Setting::putSecret('google.client_secret', 'secret');
    }

    public function test_every_page_leads_to_setup_until_it_is_finished(): void
    {
        $this->get('/')->assertRedirect('/setup');
        $this->get('/dashboard')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('Google Cloud Console')->assertSee(GoogleSignIn::callbackUrl());
    }

    public function test_setup_saves_the_google_sign_in(): void
    {
        $this->post('/setup/google', [
            'domain' => 'School.Test',
            'client_id' => 'id.apps.googleusercontent.com',
            'client_secret' => 'secret',
        ])->assertRedirect('/setup');

        $this->assertSame('school.test', Setting::get('google.domain'));
        $this->assertNotSame('secret', Setting::get('google.client_secret'));
        $this->assertSame('secret', Setting::getSecret('google.client_secret'));
        $this->get('/setup')->assertSee('Sign in with Google');
    }

    public function test_the_first_google_sign_in_becomes_the_admin(): void
    {
        $this->saveGoogle();
        $account = (new GoogleUser)
            ->setRaw(['email_verified' => true, 'hd' => 'school.test'])
            ->map(['id' => 'g-1', 'email' => 'it@school.test', 'name' => 'IT Admin']);
        $this->mock(GoogleSignIn::class)->shouldReceive('user')->andReturn($account);

        $this->get('/auth/google/callback')->assertRedirect('/dashboard');

        $admin = User::where('email', 'it@school.test')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertAuthenticatedAs($admin);
        $this->get('/setup')->assertSee('Get connect command');
    }

    public function test_google_settings_cannot_change_once_there_is_an_admin(): void
    {
        $this->saveGoogle();
        User::factory()->create(['is_admin' => true]);

        $this->post('/setup/google', [
            'domain' => 'evil.test',
            'client_id' => 'x',
            'client_secret' => 'y',
        ])->assertForbidden();
        $this->assertSame('school.test', Setting::get('google.domain'));
    }

    public function test_later_steps_need_the_admin_signed_in(): void
    {
        $this->saveGoogle();
        User::factory()->create(['is_admin' => true]);

        $this->get('/setup')->assertRedirect('/login');
        $this->post('/setup/harness')->assertRedirect('/login');
    }

    public function test_the_claude_login_waits_for_the_harness(): void
    {
        $this->saveGoogle();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/setup/claude', [
            'type' => 'subscription',
            'credential' => 'sk-ant-oat01-test',
        ])->assertForbidden();
    }

    public function test_the_admin_connects_the_harness_then_saves_the_claude_login(): void
    {
        $this->saveGoogle();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/setup/harness');
        $token = $response->getSession()->get('harness_token');
        $this->assertNotEmpty($token);

        $this->postJson('/api/harness/hello', [], ['Authorization' => "Bearer {$token}"])->assertOk();
        $this->actingAs($admin)->get('/setup')->assertSee('Token or key');

        $this->actingAs($admin)->post('/setup/claude', [
            'type' => 'subscription',
            'credential' => 'sk-ant-oat01-test',
        ])->assertRedirect('/dashboard');

        $this->assertNotSame('sk-ant-oat01-test', Setting::get('harness.claude_credential'));
        $this->assertSame('sk-ant-oat01-test', Setting::getSecret('harness.claude_credential'));
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/setup')->assertRedirect('/dashboard');
    }
}
