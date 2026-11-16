<?php

namespace Tests\Feature;

use App\Models\User;
use App\Updates\Rebuild;
use App\Updates\UpdateJob;
use App\Updates\Updater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RebuildTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().'/rebuild-'.uniqid();
        File::ensureDirectoryExists("{$this->storage}/app/tornado");
        $this->app->useStoragePath($this->storage);
        config(['tornado.updater.assets_timeout' => 0, 'tornado.updater.composer_local' => "{$this->storage}/composer.local.json"]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function helperRunning(): void
    {
        file_put_contents("{$this->storage}/app/tornado/rebuild.heartbeat", now()->toIso8601String());
    }

    private function needRebuild(): void
    {
        Process::fake(['*modules.php*' => Process::result("PHP extensions changed. Run \"docker compose up -d --build\" to add them.\n"), '*' => Process::result('ok')]);
        app(Updater::class)->run(UpdateJob::queue('module', '1.0.0', 'district/directory'));
    }

    public function test_without_the_helper_the_page_says_what_to_run(): void
    {
        $this->needRebuild();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/administration/updates')
            ->assertSee('The server needs a rebuild to finish installing.')
            ->assertSee('docker compose up -d --build')
            ->assertSee('sudo ./host/install.sh');
        $this->actingAs($admin)->get('/modules')->assertSee('Needs a rebuild for its PHP extensions.');
    }

    public function test_with_the_helper_the_button_asks_it_to_rebuild(): void
    {
        $this->needRebuild();
        $this->helperRunning();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/modules')->assertSee('Rebuild')->assertDontSee('sudo ./host/install.sh');
        $this->actingAs($admin)->from('/administration/updates')->post('/administration/rebuild')->assertRedirect('/administration/updates');

        $this->assertFileExists("{$this->storage}/app/tornado/rebuild.request");
        $this->actingAs($admin)->getJson('/administration/rebuild/status')->assertJson(['running' => true]);
        $this->actingAs($admin)->get('/administration/updates')->assertSee('Rebuilding.');

        // The helper finishes.
        unlink("{$this->storage}/app/tornado/rebuild.request");
        file_put_contents("{$this->storage}/app/tornado/rebuild.status", json_encode(['state' => 'done', 'started' => now()->toIso8601String(), 'finished' => now()->addSecond()->toIso8601String(), 'exit' => 0]));
        $this->assertFalse(Rebuild::needed());
        $this->actingAs($admin)->get('/administration/updates')->assertDontSee('needs a rebuild to finish installing');
    }

    public function test_a_failed_rebuild_shows_what_happened(): void
    {
        $this->needRebuild();
        $this->helperRunning();
        file_put_contents("{$this->storage}/app/tornado/rebuild.status", json_encode(['state' => 'failed', 'started' => now()->toIso8601String(), 'finished' => now()->addSecond()->toIso8601String(), 'exit' => 1]));
        file_put_contents("{$this->storage}/app/tornado/rebuild.log", 'failed to solve: no space left on device');

        $this->actingAs($this->admin())->get('/administration/updates')->assertSee('The last rebuild failed.')->assertSee('no space left on device');
    }

    public function test_one_thing_at_a_time_and_only_for_admins(): void
    {
        $this->helperRunning();
        UpdateJob::queue('module', '1.0.0', 'district/directory');

        $this->actingAs($this->admin())->from('/administration/updates')->post('/administration/rebuild')->assertSessionHasErrors('rebuild');
        $this->assertFileDoesNotExist("{$this->storage}/app/tornado/rebuild.request");
        $this->actingAs(User::factory()->create())->post('/administration/rebuild')->assertForbidden();
    }
}
