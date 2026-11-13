<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
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
