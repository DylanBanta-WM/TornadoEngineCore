<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ConnectionCheck;
use App\Support\GoogleSignIn;
use App\Support\Setup;
use App\Support\SignIn;
use App\Updates\UpdateJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $setupComplete = false;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('https://te.school.test');
        URL::forceScheme('https');
        Setting::put('site.url', 'https://te.school.test');
    }

    private function saveGoogle(): void
    {
        Setting::put('google.domain', 'school.test');
        Setting::put('google.client_id', 'id.apps.googleusercontent.com');
        Setting::putSecret('google.client_secret', 'secret');
    }

    public function test_setup_starts_with_the_web_address(): void
    {
        Setting::put('site.url', null);
        URL::forceRootUrl('http://10.0.0.5:8090');
        URL::forceScheme('http');

        $this->get('/')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('Find out how to set up DNS and HTTPS')->assertSee('/dns-setup');
        $this->get('/dns-setup')->assertOk()->assertSee('Back to setup')->assertSee('dns_cloudflare_api_token')->assertSee('save-cloudflare-token.sh')->assertSee('add-tornado-engine-site.sh');

        $this->post('/setup/address', ['address' => 'https://10.0.0.5'])->assertSessionHasErrors('address');
        $this->post('/setup/address', ['address' => 'http://te.school.test'])->assertSessionHasErrors('address');
        $this->post('/setup/address', ['address' => 'https://TE.school.test/setup'])->assertRedirect('https://te.school.test/setup');

        $this->assertSame('https://te.school.test', Setting::get('site.url'));
        $this->get('/setup')->assertSee('Open Tornado Engine at its address');
    }

    public function test_setup_warns_when_the_site_listens_on_every_address(): void
    {
        config(['app.web_bind' => '0.0.0.0']);
        $this->get('/setup')->assertSee('Any computer on the network can reach this site directly');

        config(['app.web_bind' => '127.0.0.1']);
        $this->get('/setup')->assertDontSee('Any computer on the network can reach this site directly');
    }

    public function test_finished_steps_can_be_looked_at_again(): void
    {
        $this->saveGoogle();

        $this->get('/setup')->assertSee('Sign in with Google');
        $this->get('/setup?step=google')->assertSee('Enter the app')->assertSee('/setup?step=address', false);
        $this->get('/setup?step=address')->assertSee('te.school.test')->assertSee('Find out how to set up DNS');
        // A step that isn't reached yet shows the current one instead.
        $this->get('/setup?step=claude')->assertSee('Sign in with Google');
    }

    public function test_after_sign_in_the_earlier_steps_are_read_only(): void
    {
        $this->saveGoogle();
        $admin = User::factory()->create(['is_admin' => true, 'email' => 'it@school.test']);

        $this->actingAs($admin)->get('/setup?step=address')->assertDontSee('address-form')->assertSee('https://te.school.test');
        $this->actingAs($admin)->get('/setup?step=google')->assertSee('Sign-in is set up for school.test');
        $this->actingAs($admin)->get('/setup?step=admin')->assertSee('it@school.test')->assertSee('/setup?step=google', false);
    }

    public function test_the_address_cannot_change_once_there_is_an_admin(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->post('/setup/address', ['address' => 'https://evil.test'])->assertForbidden();
    }

    public function test_every_page_leads_to_setup_until_it_is_finished(): void
    {
        $this->get('/')->assertRedirect('/setup');
        $this->get('/dashboard')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('How will people sign in?');

        $this->post('/setup/signin', ['methods' => ['google']])->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('Google Cloud Console')->assertSee(GoogleSignIn::callbackUrl());
    }

    public function test_setup_saves_the_google_sign_in(): void
    {
        SignIn::save(['google']);
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

    public function test_setup_wont_save_google_keys_that_fail_the_check(): void
    {
        SignIn::save(['google']);
        $this->mock(ConnectionCheck::class)->shouldReceive('google')->andReturn('Google didn\'t accept this client ID and secret.');

        $this->from('/setup')->post('/setup/google', ['domain' => 'school.test', 'client_id' => 'bad', 'client_secret' => 'bad'])
            ->assertSessionHasErrors('google_keys');
        $this->assertNull(Setting::get('google.client_id'));
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
        // The harness step shows its connect command straight away, with a Copy button.
        $page = $this->get('/setup')->assertSee('docker compose exec harness harness-connect')->assertSee('Copy');
        $this->assertSame(session('harness_token'), $this->get('/setup')->viewData('harnessToken'), 'the same command until it connects');
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
        ])->assertRedirect('/setup');

        $this->assertNotSame('sk-ant-oat01-test', Setting::get('harness.claude_credential'));
        $this->assertSame('sk-ant-oat01-test', Setting::getSecret('harness.claude_credential'));

        // Last: the modules to install. Choosing none finishes setup.
        Http::fake(['*' => Http::response([], 500)]);
        $this->actingAs($admin)->get('/setup')->assertSee('Choose the modules to install');
        $this->actingAs($admin)->post('/setup/modules')->assertRedirect('/dashboard');
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/setup')->assertRedirect('/dashboard');
    }

    public function test_modules_chosen_in_setup_are_queued_for_the_updater(): void
    {
        $this->saveGoogle();
        $admin = User::factory()->create(['is_admin' => true]);
        Setting::put('harness.last_seen_at', now()->toIso8601String());
        Setting::putSecret('harness.claude_credential', 'sk-ant-oat01-test');
        Cache::put('module-finder.found', [[
            'package' => 'district/reports', 'name' => 'Reports', 'description' => 'Make reports.', 'url' => 'https://github.com/district/reports',
            'official' => true, 'onPackagist' => true, 'latest' => '0.2.0', 'needsNewerCore' => null, 'requires' => ['district/base'],
        ], [
            'package' => 'district/base', 'name' => 'Base', 'description' => null, 'url' => 'https://github.com/district/base',
            'official' => true, 'onPackagist' => true, 'latest' => '0.7.0', 'needsNewerCore' => null, 'requires' => [],
        ]], 3600);

        $this->actingAs($admin)->get('/setup')->assertSee('Reports')->assertSee('Requirements: Base Module (will auto install)');
        $this->actingAs($admin)->post('/setup/modules', ['packages' => ['district/reports', 'evil/package']])->assertRedirect('/modules');

        $this->assertSame(['district/reports'], UpdateJob::pluck('package')->all());
        $this->assertTrue(Setup::complete());
    }
}
