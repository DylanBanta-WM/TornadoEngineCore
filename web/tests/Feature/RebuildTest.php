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
        $this->actingAs($admin)->get('/settings/updates')
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
        $this->actingAs($admin)->from('/settings/updates')->post('/administration/rebuild')->assertRedirect('/settings/updates');

        $this->assertFileExists("{$this->storage}/app/tornado/rebuild.request");
        $this->actingAs($admin)->getJson('/administration/rebuild/status')->assertJson(['running' => true]);
        $this->actingAs($admin)->get('/settings/updates')->assertSee('Rebuilding.');

        // The helper finishes.
        unlink("{$this->storage}/app/tornado/rebuild.request");
        file_put_contents("{$this->storage}/app/tornado/rebuild.status", json_encode(['state' => 'done', 'started' => now()->toIso8601String(), 'finished' => now()->addSecond()->toIso8601String(), 'exit' => 0]));
        $this->assertFalse(Rebuild::needed());
        $this->actingAs($admin)->get('/settings/updates')->assertDontSee('needs a rebuild to finish installing');
    }

    public function test_a_failed_rebuild_shows_what_happened(): void
    {
        $this->needRebuild();
        $this->helperRunning();
        file_put_contents("{$this->storage}/app/tornado/rebuild.status", json_encode(['state' => 'failed', 'started' => now()->toIso8601String(), 'finished' => now()->addSecond()->toIso8601String(), 'exit' => 1]));
        file_put_contents("{$this->storage}/app/tornado/rebuild.log", 'failed to solve: no space left on device');

        $this->actingAs($this->admin())->get('/settings/updates')->assertSee('The last rebuild failed.')->assertSee('no space left on device');
    }

    public function test_one_thing_at_a_time_and_only_for_admins(): void
    {
        $this->helperRunning();
        UpdateJob::queue('module', '1.0.0', 'district/directory');

        $this->actingAs($this->admin())->from('/settings/updates')->post('/administration/rebuild')->assertSessionHasErrors('rebuild');
        $this->assertFileDoesNotExist("{$this->storage}/app/tornado/rebuild.request");
        $this->actingAs(User::factory()->create())->post('/administration/rebuild')->assertForbidden();
    }

    public function test_each_module_shows_only_its_newest_install_or_update(): void
    {
        UpdateJob::create(['kind' => 'module', 'package' => 'tornado-engine/gam', 'version' => '0.5.0', 'status' => 'done']);
        UpdateJob::create(['kind' => 'module', 'package' => 'tornado-engine/gam', 'version' => '0.6.0', 'status' => 'done']);
        UpdateJob::create(['kind' => 'module', 'package' => 'tornado-engine/chat', 'version' => '0.1.0', 'status' => 'done']);
        UpdateJob::create(['kind' => 'core', 'version' => '0.12.0', 'status' => 'done']);
        UpdateJob::create(['kind' => 'core', 'version' => '0.13.0', 'status' => 'done']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/modules')->assertSee('tornado-engine/gam 0.6.0')->assertDontSee('tornado-engine/gam 0.5.0')->assertSee('tornado-engine/chat 0.1.0');
        $this->actingAs($admin)->get('/settings/updates')->assertSee('Tornado Engine Core 0.13.0')->assertDontSee('Tornado Engine Core 0.12.0');
    }

    public function test_rebuild_notes_go_away_once_the_server_is_rebuilt(): void
    {
        UpdateJob::create(['kind' => 'core', 'version' => '0.12.0', 'status' => 'done', 'notes' => ['rebuild' => 'This update changes Tornado Engine\'s containers. On the server, run docker compose up -d --build to finish it.']]);

        $this->actingAs($this->admin())->get('/settings/updates')->assertDontSee('This update changes Tornado Engine')->assertDontSee('needs a rebuild to finish installing');
    }
}
