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

    /** Next or Back from the tour card on the page at $from. */
    private function step(User $admin, string $way, string $from): array
    {
        return $this->actingAs($admin)->postJson("/tour/{$way}", ['from' => $from])->assertOk()->json('tour') ?? [];
    }

    public function test_the_tour_walks_across_pages(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/dashboard')
            ->assertSee('Admin pages are in the Administration menu.')
            ->assertSee('nav-group-administration', false);

        // The Administration menu, then Users and roles in it, on the same page.
        $tour = $this->step($admin, 'next', '/dashboard');
        $this->assertSame(['users-and-roles', true], [$tour['key'], $tour['here']]);
        $this->assertContains('[data-tour="nav-item-access.users.index"]', $tour['targets']);
        $this->assertContains('[data-tour="nav-group-administration"]', $tour['open']);

        $tour = $this->step($admin, 'next', '/dashboard');
        $this->assertSame(['access', false, url('/access/users')], [$tour['key'], $tour['here'], $tour['url']]);
        $this->assertContains('Email patterns: roles by email address.', $tour['lines']);

        // The Users and roles step stays on each of its tabs.
        foreach (['/access/users', '/access/roles', '/access/patterns', '/access/exclusions'] as $page) {
            $this->assertTrue(Tour::state($admin->fresh(), $page)['here'], $page);
        }
        $this->assertFalse(Tour::state($admin->fresh(), '/modules')['here']);

        $tour = $this->step($admin, 'next', '/access/roles');
        $this->assertSame(['modules-menu', false], [$tour['key'], $tour['here']]);

        // One step for the Modules page, sharing included.
        $tour = $this->step($admin, 'next', '/dashboard');
        $this->assertSame('modules', $tour['key']);
        $this->assertContains('Made a module other districts could use? Share it.', $tour['lines']);
        $this->assertSame(['url' => url('/docs/sharing-modules'), 'label' => 'Sharing a module'], $tour['link']);

        $this->assertSame('modules-menu', $this->step($admin, 'back', '/modules')['key']);
        $this->step($admin, 'next', '/dashboard');

        $this->assertSame('docs-menu', $this->step($admin, 'next', '/modules')['key']);
        $this->assertSame('docs', $this->step($admin, 'next', '/dashboard')['key']);
        $this->assertSame('chat-menu', $this->step($admin, 'next', '/docs')['key']);
        $this->assertSame('chat', $this->step($admin, 'next', '/dashboard')['key']);

        // The bell last.
        $tour = $this->step($admin, 'next', '/chat');
        $this->assertSame(['notifications', false], [$tour['key'], $tour['here']]);
        $this->assertSame($tour['total'], $tour['number']);

        $this->assertSame([], $this->step($admin, 'next', '/dashboard'));
        $this->assertNull($admin->fresh()->tour_step);
        $this->actingAs($admin)->get('/dashboard')->assertDontSee('Admin pages are in');

        // Without JavaScript, Next still goes to the step's page.
        Tour::start($admin);
        $this->actingAs($admin)->post('/tour/next')->assertRedirect('/dashboard');
    }

    public function test_it_can_be_taken_again_from_the_profile(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/profile')->assertSee('Take the tour again');
        $this->actingAs($admin)->post('/tour/restart')->assertRedirect('/dashboard');
        $this->assertSame('admin-menu', $admin->fresh()->tour_step);

        $this->actingAs(User::factory()->create())->get('/profile')->assertDontSee('Take the tour again');
    }

    public function test_the_users_and_roles_tabs_dont_count_as_next(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['tour_step' => 'access'])->save();

        $this->assertFalse(Tour::state($admin, '/access/roles')['click']);
        $this->assertTrue(Tour::state($admin->forceFill(['tour_step' => 'admin-menu']), '/dashboard')['click']);
    }

    public function test_skip_ends_it(): void
    {
        $admin = $this->admin();

        $this->assertFalse(Tour::state($admin, '/modules')['here']);
        $this->actingAs($admin)->get('/tour')->assertRedirect('/dashboard');

        $this->actingAs($admin)->from('/modules')->post('/tour/skip')->assertRedirect('/modules');
        $this->assertNull($admin->fresh()->tour_step);
    }

    public function test_only_the_person_taking_it_sees_it(): void
    {
        $this->admin();

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/dashboard')->assertDontSee('Admin pages are in');
    }
}
