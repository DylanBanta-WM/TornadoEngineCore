<?php

namespace Tests\Feature;

use App\Administration\Administration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_administration(): void
    {
        $this->get('/administration')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/administration')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/administration')->assertOk();
    }

    public function test_modules_add_sections(): void
    {
        Route::middleware(['web', 'auth', 'admin'])->get('/administration/reports', fn () => 'reports')->name('administration.reports');
        Route::getRoutes()->refreshNameLookups();

        app(Administration::class)->add('Reports', 'administration.reports', description: 'Monthly reports');

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/administration')
            ->assertSee('Reports')
            ->assertSee('Monthly reports');
    }

    public function test_sections_for_missing_routes_are_left_out(): void
    {
        app(Administration::class)->add('Gone', 'no.such.route');

        $this->assertNotContains('Gone', array_map(fn ($section) => $section->label, app(Administration::class)->sections()));
    }

    public function test_the_dns_guide_moves_into_administration_after_setup(): void
    {
        // Before Docs is installed, the guide stays at /dns-setup.
        if (! Route::has('administration.docs.show')) {
            $this->get('/dns-setup')->assertOk()->assertSee('Set up DNS and HTTPS');

            return;
        }

        $this->get('/dns-setup')->assertRedirect('/administration/docs/dns-setup');
    }
}
