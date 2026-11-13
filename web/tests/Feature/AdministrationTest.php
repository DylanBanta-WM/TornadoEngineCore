<?php

namespace Tests\Feature;

use App\Administration\Administration;
use App\Models\Setting;
use App\Models\User;
use App\Support\ConnectionCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_only_admins_can_open_administration(): void
    {
        $this->get('/administration')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/administration')->assertForbidden();
        $this->actingAs($this->admin())->get('/administration')
            ->assertOk()
            ->assertSee('Web address')
            ->assertSee('Google sign-in')
            ->assertSee('Harness')
            ->assertSee('Claude Code login');
    }

    public function test_modules_add_cards(): void
    {
        $view = 'zz-test-card';
        file_put_contents(resource_path("views/{$view}.blade.php"), '<x-settings-card title="Test module">Card body</x-settings-card>');

        try {
            app(Administration::class)->card($view)->card('no.such.view');
            $this->actingAs($this->admin())->get('/administration')->assertSee('Test module')->assertSee('Card body');
        } finally {
            unlink(resource_path("views/{$view}.blade.php"));
        }
    }

    public function test_staff_cannot_change_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/administration/google', ['domain' => 'evil.test', 'client_id' => 'x', 'client_secret' => 'y'])
            ->assertForbidden();
    }

    public function test_the_web_address_can_change(): void
    {
        $this->actingAs($this->admin())
            ->post('/administration/address', ['address' => 'https://new.school.test'])
            ->assertRedirect('https://new.school.test/administration');

        $this->assertSame('https://new.school.test', Setting::get('site.url'));
    }

    public function test_google_sign_in_can_change(): void
    {
        $this->actingAs($this->admin())
            ->post('/administration/google', ['domain' => 'School.Test', 'client_id' => 'new-id', 'client_secret' => 'new-secret'])
            ->assertRedirect('/administration');

        $this->assertSame('school.test', Setting::get('google.domain'));
        $this->assertSame('new-secret', Setting::getSecret('google.client_secret'));
    }

    private function checkFails(string $method, string $message): void
    {
        $this->mock(ConnectionCheck::class)->shouldReceive($method)->andReturn($message);
    }

    public function test_saved_google_keys_are_hidden_behind_clear(): void
    {
        Setting::put('google.domain', 'school.test');
        Setting::put('google.client_id', 'visible-client-id');
        Setting::putSecret('google.client_secret', 'secret');

        $this->actingAs($this->admin())->get('/administration')
            ->assertSee('Client ID and secret:')
            ->assertSee('Clear')
            ->assertDontSee('visible-client-id');
    }

    public function test_google_keys_that_fail_the_check_are_not_saved(): void
    {
        Setting::put('google.client_id', 'working-id');
        Setting::putSecret('google.client_secret', 'working-secret');
        $this->checkFails('google', 'Google didn\'t accept this client ID and secret.');

        $this->actingAs($this->admin())->from('/administration')
            ->post('/administration/google', ['domain' => 'school.test', 'client_id' => 'bad-id', 'client_secret' => 'bad'])
            ->assertRedirect('/administration')
            ->assertSessionHasErrors('google_keys');

        $this->assertSame('working-id', Setting::get('google.client_id'));
        $this->assertSame('working-secret', Setting::getSecret('google.client_secret'));
    }

    public function test_a_claude_login_that_fails_the_check_is_not_saved(): void
    {
        Setting::put('harness.claude_credential_type', 'subscription');
        Setting::putSecret('harness.claude_credential', 'sk-ant-oat01-working');
        $this->checkFails('claude', 'Anthropic didn\'t accept this API key.');

        $this->actingAs($this->admin())->from('/administration')
            ->post('/administration/claude', ['type' => 'api_key', 'credential' => 'sk-ant-api03-bad'])
            ->assertSessionHasErrors('credential');

        $this->assertSame('subscription', Setting::get('harness.claude_credential_type'));
        $this->assertSame('sk-ant-oat01-working', Setting::getSecret('harness.claude_credential'));
        $this->actingAs($this->admin())->get('/administration')->assertSee('A Claude subscription token is saved.')->assertDontSee('sk-ant-oat01-working');
    }

    public function test_the_harness_can_get_a_new_connect_command(): void
    {
        $response = $this->actingAs($this->admin())->post('/administration/harness');
        $token = $response->getSession()->get('harness_token');

        $this->assertNotEmpty($token);
        $this->assertSame(hash('sha256', $token), Setting::get('harness.token_hash'));
        $this->actingAs($this->admin())->get('/administration')->assertSee('harness-connect');
    }

    public function test_the_claude_login_can_change(): void
    {
        $this->actingAs($this->admin())
            ->post('/administration/claude', ['type' => 'api_key', 'credential' => 'sk-ant-api03-new'])
            ->assertRedirect('/administration');

        $this->assertSame('api_key', Setting::get('harness.claude_credential_type'));
        $this->assertSame('sk-ant-api03-new', Setting::getSecret('harness.claude_credential'));
    }

    public function test_the_dns_guide_moves_to_docs_after_setup(): void
    {
        if (! Route::has('docs.show')) {
            $this->get('/dns-setup')->assertOk()->assertSee('Set up DNS and HTTPS');

            return;
        }

        $this->get('/dns-setup')->assertRedirect('/docs/dns-setup');
    }
}
