<?php

namespace Tests\Feature;

use App\Models\User;
use App\Overrides\Overrides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class OverridesTest extends TestCase
{
    use RefreshDatabase;

    private string $module;

    private string $local;

    protected function setUp(): void
    {
        parent::setUp();
        // A module's views, and a local folder of its own for the test.
        $this->module = base_path('modules/zz-overrides-test/resources/views');
        $this->local = storage_path('framework/testing/zz-local');
        File::ensureDirectoryExists($this->module);
        File::deleteDirectory($this->local);
        file_put_contents("{$this->module}/card.blade.php", "<p>Original card</p>\n");
        View::addNamespace('zz-overrides', $this->module);
        $this->app->instance(Overrides::class, new class($this->local) extends Overrides
        {
            public function __construct(private string $root) {}

            public function dir(string $path = ''): string
            {
                return $this->root.($path !== '' ? '/'.$path : '');
            }
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('modules/zz-overrides-test'));
        File::deleteDirectory($this->local);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function render(): string
    {
        View::getFinder()->flush();
        app(Overrides::class)->register();

        return view('zz-overrides::card')->render();
    }

    public function test_a_view_can_be_overridden_changed_and_removed(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/settings/overrides')->assertOk()->assertSee('zz-overrides::card')->assertSee('core::layouts.app');

        $this->actingAs($admin)->post('/administration/overrides', ['view' => 'zz-overrides::card']);
        $this->assertFileExists("{$this->local}/views/zz-overrides/card.blade.php");

        $this->actingAs($admin)->post('/administration/overrides/view', ['view' => 'zz-overrides::card', 'contents' => '<p>Our card</p>', 'action' => 'save']);
        $this->assertStringContainsString('Our card', $this->render());

        $this->actingAs($admin)->from('/settings/overrides')->post('/administration/overrides/view', ['view' => 'zz-overrides::card', 'contents' => '@if (true) <?php echo ; ?>', 'action' => 'save'])
            ->assertSessionHasErrors('overrides');
        $this->assertStringContainsString('Our card', $this->render());

        $this->actingAs($admin)->post('/administration/overrides/view', ['view' => 'zz-overrides::card', 'action' => 'remove']);
        $this->assertStringContainsString('Original card', $this->render());
    }

    public function test_an_update_that_changes_the_original_asks_for_review(): void
    {
        $admin = $this->admin();
        app(Overrides::class)->create('zz-overrides::card');
        $this->assertSame(0, app(Overrides::class)->needingReview());

        file_put_contents("{$this->module}/card.blade.php", "<p>Original card, now with a link</p>\n");

        $this->assertSame('changed', app(Overrides::class)->all()[0]['status']);
        $this->actingAs($admin)->get('/settings/updates')->assertSee('An update changed a view you\'ve overridden.');
        $this->actingAs($admin)->get('/settings/overrides')->assertSee('The module changed it')->assertSee('+&lt;p&gt;Original card, now with a link&lt;/p&gt;', false);

        $this->actingAs($admin)->post('/administration/overrides/view', ['view' => 'zz-overrides::card', 'action' => 'review']);
        $this->assertSame(0, app(Overrides::class)->needingReview());
    }

    public function test_names_cant_reach_outside_the_folders(): void
    {
        $this->actingAs($this->admin())->from('/settings/overrides')->post('/administration/overrides', ['view' => 'zz-overrides::../../../.env'])->assertSessionHasErrors('overrides');
        $this->actingAs(User::factory()->create())->post('/administration/overrides', ['view' => 'zz-overrides::card'])->assertForbidden();
    }
}
