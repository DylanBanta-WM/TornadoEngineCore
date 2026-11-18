<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Tour::start($admin);

        return $admin;
    }

    public function test_the_tour_walks_across_pages(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/dashboard')
            ->assertSee('Tour · 1 of')
            ->assertSee('Admin pages are in the Administration menu.')
            ->assertSee('data-tour="nav-group-administration"', false);

        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/dashboard');
        $this->actingAs($admin)->get('/dashboard')->assertSee('Notifications: modules that still need setting up')->assertSee('data-tour="notifications"', false);

        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/dashboard');
        $this->actingAs($admin)->get('/dashboard')->assertSee('Users and roles: who can sign in')->assertSee('data-tour="nav-item-access.users.index"', false)
            ->assertSee('nav-group-administration', false);

        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/access/users');
        $this->actingAs($admin)->get('/access/users')
            ->assertSee('Email patterns: roles by email address.')
            ->assertSee('Give people roles here by hand')
            ->assertSee('data-tour="access-tab-users"', false);

        // Modules, Docs and Chat: first where they are in the menu, then the page.
        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/dashboard');
        $this->actingAs($admin)->get('/dashboard')->assertSee('Modules: add features to Tornado Engine.');
        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/modules');
        $this->actingAs($admin)->get('/modules')->assertSee('Core modules are always installed.')->assertSee('Other districts&#039; modules aren&#039;t reviewed.', false);

        $this->actingAs($admin)->post('/tour/next');
        $this->actingAs($admin)->get('/modules')->assertSee('Made a module other districts could use? Share it.')->assertSee('Sharing a module')->assertSee('/docs/sharing-modules');

        $this->actingAs($admin)->post('/tour/back')->assertRedirect('/modules');
        $this->assertSame('modules', $admin->fresh()->tour_step);

        $this->actingAs($admin)->post('/tour/next');
        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/dashboard');
        $this->actingAs($admin)->get('/dashboard')->assertSee('Docs: guides for setting up');
        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/docs');
        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/dashboard');
        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/chat');
        $this->actingAs($admin)->get('/chat')->assertSee('Ask Claude for a new tool')->assertSee('Finish');

        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/dashboard');
        $this->assertNull($admin->fresh()->tour_step);
        $this->actingAs($admin)->get('/dashboard')->assertDontSee('Tour ·');
    }

    public function test_on_another_page_it_offers_to_continue_and_skip_ends_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/modules')->assertSee('Continue tour')->assertDontSee('Admin pages are in');
        $this->actingAs($admin)->get('/tour')->assertRedirect('/dashboard');

        $this->actingAs($admin)->from('/modules')->post('/tour/skip')->assertRedirect('/modules');
        $this->assertNull($admin->fresh()->tour_step);
    }

    public function test_only_the_person_taking_it_sees_it(): void
    {
        $this->admin();

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/dashboard')->assertDontSee('Tour ·');
    }
}
