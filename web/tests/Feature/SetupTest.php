<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $setupComplete = false;

    public function test_every_page_leads_to_setup_until_it_is_finished(): void
    {
        $this->get('/')->assertRedirect('/setup');
        $this->get('/dashboard')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee('Admin account');
    }

    public function test_setup_creates_the_admin_and_signs_them_in(): void
    {
        $this->post('/setup/admin', [
            'name' => 'Test Admin',
            'email' => 'admin@example.test',
            'password' => 'correct-horse-9',
            'password_confirmation' => 'correct-horse-9',
        ])->assertRedirect('/setup');

        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertAuthenticatedAs($admin);
        $this->get('/setup')->assertSee('Get connect command');
    }

    public function test_a_second_admin_cannot_be_created_through_setup(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->post('/setup/admin', [
            'name' => 'Someone Else',
            'email' => 'other@example.test',
            'password' => 'correct-horse-9',
            'password_confirmation' => 'correct-horse-9',
        ])->assertForbidden();
    }

    public function test_later_steps_need_the_admin_signed_in(): void
    {
        User::factory()->create(['is_admin' => true]);

        $this->get('/setup')->assertRedirect('/login');
        $this->post('/setup/harness')->assertRedirect('/login');
    }

    public function test_the_claude_login_waits_for_the_harness(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/setup/claude', [
            'type' => 'subscription',
            'credential' => 'sk-ant-oat01-test',
        ])->assertForbidden();
    }

    public function test_the_admin_connects_the_harness_then_saves_the_claude_login(): void
    {
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
