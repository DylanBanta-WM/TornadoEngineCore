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
use TornadoEngine\Access\Models\Role;
use TornadoEngine\Broker\Broker;

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

    /** The broker's step, done. */
    private function saveBroker(): void
    {
        Setting::put('setup.broker_at', now()->toIso8601String());
    }

    /** @return list<int> the roles ticked on the page */
    private function ticked(string $html): array
    {
        preg_match_all('/name="roles\[\]" value="(\d+)"[^>]*\bchecked\b/', $html, $m);

        return array_map('intval', $m[1]);
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
        $this->get('/setup')->assertSee('Which roles get pseudonyms?');
        $this->saveBroker();
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
        $this->saveBroker();
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
        $this->assertSame('admin-menu', $admin->fresh()->tour_step, 'the tour starts');
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/setup')->assertRedirect('/dashboard');
    }

    public function test_modules_chosen_in_setup_are_queued_for_the_updater(): void
    {
        $this->saveGoogle();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->saveBroker();
        Setting::put('harness.last_seen_at', now()->toIso8601String());
        Setting::putSecret('harness.claude_credential', 'sk-ant-oat01-test');
        Cache::put('module-finder.found.v2', [[
            'package' => 'district/reports', 'name' => 'Reports', 'description' => 'Make reports.', 'url' => 'https://github.com/district/reports',
            'official' => true, 'onPackagist' => true, 'latest' => '0.2.0', 'needsNewerCore' => null, 'requires' => ['district/base'], 'suggested' => true,
        ], [
            'package' => 'district/base', 'name' => 'Base', 'description' => null, 'url' => 'https://github.com/district/base',
            'official' => true, 'onPackagist' => true, 'latest' => '0.7.0', 'needsNewerCore' => null, 'requires' => [],
        ], [
            'package' => 'someone/extra', 'name' => 'Someone Extra', 'description' => null, 'url' => 'https://github.com/someone/extra',
            'official' => false, 'onPackagist' => true, 'latest' => '1.0.0', 'needsNewerCore' => null, 'requires' => [], 'suggested' => true,
        ], [
            'package' => 'tornado-engine/docs', 'name' => 'Docs', 'description' => null, 'url' => 'https://github.com/x/docs',
            'official' => true, 'onPackagist' => true, 'latest' => '0.5.2', 'needsNewerCore' => null, 'requires' => [], 'suggested' => true,
        ]], 3600);

        // Only official modules that ask to be suggested, and aren't installed.
        $this->actingAs($admin)->get('/setup')->assertSee('Reports')->assertSee('Requirements: Base Module (will auto install)')
            ->assertDontSee('Someone Extra')->assertDontSee('value="district/base"', false)
            ->assertDontSee('value="tornado-engine/docs"', false);
        $this->actingAs($admin)->post('/setup/modules', ['packages' => ['district/reports', 'evil/package']])->assertRedirect('/dashboard');

        $this->assertSame(['district/reports'], UpdateJob::pluck('package')->all());
        $this->assertTrue(Setup::complete());
    }

    public function test_going_back_to_an_earlier_step_after_the_admin_exists_can_save_it_again(): void
    {
        $this->saveGoogle();
        SignIn::save(['google', 'password']);
        $admin = User::factory()->create(['is_admin' => true, 'google_id' => 'g-1', 'password' => null]);

        // Saving the sign-in step again works (it used to be refused).
        $this->actingAs($admin)->from('/setup?step=signin')->post('/setup/signin', ['methods' => ['google', 'password']])->assertRedirect('/setup');
        $this->assertSame(['google', 'password'], SignIn::methods());

        // But not without the way the admin signs in.
        $this->actingAs($admin)->from('/setup?step=signin')->post('/setup/signin', ['methods' => ['password']])->assertRedirect('/setup?step=signin')->assertSessionHasErrors('methods');
        $this->assertSame(['google', 'password'], SignIn::methods());

        // Someone else can't change it now that there's an admin.
        $this->actingAs(User::factory()->create())->post('/setup/signin', ['methods' => ['password']])->assertForbidden();

        // A later step can't be saved before it's reached.
        $this->actingAs($admin)->post('/setup/claude', ['type' => 'subscription', 'credential' => 'x'])->assertForbidden();
    }

    public function test_after_the_admin_account_the_admin_chooses_the_roles_given_pseudonyms(): void
    {
        $this->saveGoogle();
        $admin = User::factory()->create(['is_admin' => true]);
        $student = Role::where('name', 'Student')->value('id');
        $staff = Role::where('name', 'Staff')->value('id');
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertSame(['address', 'signin', 'google', 'admin', 'broker', 'harness', 'claude', 'modules'], Setup::steps());
        $this->assertSame('broker', $this->actingAs($admin)->get('/setup')->viewData('current'));

        // Students until roles are chosen.
        $page = $this->actingAs($admin)->get('/setup')->assertOk()->assertSee('Which roles get pseudonyms?')->assertSee(Broker::about());
        $this->assertSame([$student], $this->ticked($page->getContent()));

        // Only the admin, and not the harness step before this one is saved.
        $this->actingAs(User::factory()->create())->post('/setup/broker', ['roles' => [$staff]])->assertForbidden();
        $this->actingAs($admin)->post('/setup/harness')->assertForbidden();

        // Saving stores the Broker's setting, refreshes its directory, and moves on.
        $this->actingAs($admin)->post('/setup/broker', ['roles' => [$student]])->assertRedirect('/setup');
        $this->assertSame([$student], Broker::pseudonymRoleIds());
        $this->assertNotNull(Setting::get('broker.refreshed_at'));
        $this->assertTrue(app(Broker::class)->ready());
        $this->assertSame('harness', $this->actingAs($admin)->get('/setup')->assertSee('docker compose exec harness harness-connect')->viewData('current'));

        // Going back to it shows what was saved, and saves it again.
        Setting::put('harness.last_seen_at', now()->toIso8601String());
        $page = $this->actingAs($admin)->get('/setup?step=broker')->assertSee('Which roles get pseudonyms?')->assertSee('/setup?step=admin', false);
        $this->assertSame([$student], $this->ticked($page->getContent()));
        $this->actingAs($admin)->from('/setup?step=broker')->post('/setup/broker', ['roles' => [$student, $staff]])->assertRedirect('/setup');
        $this->assertEqualsCanonicalizing([$student, $staff], Broker::pseudonymRoleIds());
        $this->assertSame('claude', $this->actingAs($admin)->get('/setup')->viewData('current'));

        // Not once setup is done.
        Setting::put('setup.completed_at', now()->toIso8601String());
        $this->actingAs($admin)->post('/setup/broker', ['roles' => []])->assertForbidden();
    }
}
