<?php

namespace Tests\Feature;

use App\Models\User;
use App\Updates\UpdateJob;
use App\Updates\Updater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class UpdatesTest extends TestCase
{
    use RefreshDatabase;

    private string $local;

    protected function setUp(): void
    {
        parent::setUp();
        $this->local = sys_get_temp_dir().'/composer.local.'.uniqid().'.json';
        config(['tornado.version' => '0.7.0', 'tornado.updater.assets_timeout' => 0, 'tornado.updater.composer_local' => $this->local]);
        Cache::forget('tornado.core.latest');
    }

    protected function tearDown(): void
    {
        @unlink($this->local);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function githubTags(array $tags): void
    {
        Http::fake(['api.github.com/repos/*/tags*' => Http::response(array_map(fn ($tag) => ['name' => $tag], $tags))]);
    }

    public function test_the_updates_tab_shows_a_newer_official_version(): void
    {
        $this->githubTags(['v0.6.0', 'v0.10.0', 'v0.8.1', 'not-a-version']);

        $this->actingAs($this->admin())->get('/administration/updates')
            ->assertOk()
            ->assertSee('This server runs version 0.7.0.')
            ->assertSee('Version 0.10.0 is available.')
            ->assertSee('Update to 0.10.0');
    }

    public function test_up_to_date_core_has_no_update_button(): void
    {
        $this->githubTags(['v0.7.0']);

        $this->actingAs($this->admin())->get('/administration/updates')->assertSee('That\'s the newest official version.')->assertDontSee('Update to');
        $this->actingAs($this->admin())->from('/administration/updates')->post('/administration/updates/core')->assertSessionHasErrors('updates');
        $this->assertSame(0, UpdateJob::count());
    }

    public function test_an_update_is_queued_for_the_updater_once(): void
    {
        $this->githubTags(['v0.8.0']);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/administration/updates/core')->assertSessionHas('updates_status');
        $this->actingAs($admin)->from('/administration/updates')->post('/administration/updates/core')->assertSessionHasErrors('updates');

        $job = UpdateJob::sole();
        $this->assertSame(['core', '0.8.0', 'queued'], [$job->kind, $job->version, $job->status]);
        $this->actingAs($admin)->get('/administration/updates')->assertSee('Tornado Engine Core 0.8.0')->assertSee('Waiting');
        $this->actingAs(User::factory()->create())->post('/administration/updates/core')->assertForbidden();
    }

    public function test_the_updater_updates_core_with_git_then_composer(): void
    {
        Process::fake([
            "*'status' '--porcelain'*" => Process::result(''),
            "*'diff' '--name-only'*'harness'*" => Process::result("docker-compose.yml\n"),
            '*modules.php*' => Process::result("Modules' containers changed. Run \"docker compose up -d\" again to start them.\n"),
            '*' => Process::result('ok'),
        ]);
        $job = UpdateJob::queue('core', '0.8.0');

        $this->artisan('tornado:updater', ['--once' => true])->assertSuccessful();

        $job->refresh();
        $this->assertSame('done', $job->status, (string) $job->log);
        Process::assertRan(fn ($process) => $process->command === ['git', '-C', '/tornado', 'fetch', '--no-tags', 'https://github.com/DylanBanta-WM/TornadoEngineCore.git', 'refs/tags/v0.8.0:refs/tags/v0.8.0']);
        Process::assertRan(fn ($process) => $process->command === ['git', '-C', '/tornado', 'merge', '--ff-only', 'v0.8.0']);
        Process::assertRan(fn ($process) => $process->command === ['composer', 'install', '--no-interaction', '--no-progress']);
        Process::assertRan(fn ($process) => $process->command === ['php', 'artisan', 'migrate', '--force']);
        $this->assertArrayHasKey('rebuild', $job->notes);
        $this->assertArrayHasKey('containers', $job->notes);
    }

    public function test_core_wont_update_over_local_changes(): void
    {
        Process::fake(["*'status' '--porcelain'*" => Process::result(' M web/app/Models/User.php'), '*' => Process::result('')]);
        $job = UpdateJob::queue('core', '0.8.0');

        app(Updater::class)->run($job);

        $this->assertSame('failed', $job->fresh()->status, (string) $job->fresh()->log);
        $this->assertStringContainsString('can\'t update itself', $job->fresh()->log);
        Process::assertDidntRun(fn ($process) => in_array('merge', $process->command, true));
    }

    public function test_the_updater_installs_a_module_through_composer_local_json(): void
    {
        Process::fake(['*' => Process::result('ok')]);
        $job = UpdateJob::queue('module', 'v0.2.0', 'someone/tornado-thing', 'https://github.com/someone/TornadoThing');

        app(Updater::class)->run($job);

        $this->assertSame('done', $job->fresh()->status);
        Process::assertRan(fn ($process) => $process->command === ['composer', 'require', 'someone/tornado-thing:>=0.2.0', '--update-with-dependencies', '--no-interaction', '--no-progress']
            && $process->environment === ['COMPOSER' => 'composer.local.json']);
        $local = json_decode(file_get_contents($this->local), true);
        $this->assertSame([['type' => 'vcs', 'url' => 'https://github.com/someone/TornadoThing']], $local['repositories']);
        $this->assertTrue($local['extra']['merge-plugin']['merge-extra']);
    }

    public function test_a_module_in_web_modules_is_linked_from_there(): void
    {
        Process::fake(['*' => Process::result('ok')]);
        $job = UpdateJob::queue('module', 'dev', 'district/notes', 'modules/notes');

        app(Updater::class)->run($job);

        $this->assertSame('done', $job->fresh()->status);
        Process::assertRan(fn ($process) => $process->command === ['composer', 'require', 'district/notes:*@dev', '--update-with-dependencies', '--no-interaction', '--no-progress']);
        $this->assertSame([['type' => 'path', 'url' => 'modules/notes', 'options' => ['symlink' => true]]], json_decode(file_get_contents($this->local), true)['repositories']);
    }

    public function test_a_failed_step_stops_the_job(): void
    {
        Process::fake(["'composer'*" => Process::result('Your requirements could not be resolved', exitCode: 2), '*' => Process::result('')]);
        $job = UpdateJob::queue('module', '0.2.0', 'tornado-engine/thing');

        app(Updater::class)->run($job);

        $this->assertSame('failed', $job->fresh()->status);
        $this->assertStringContainsString('Your requirements could not be resolved', $job->fresh()->log);
        Process::assertDidntRun(fn ($process) => $process->command === ['php', 'artisan', 'migrate', '--force']);
    }

    public function test_bad_package_names_are_refused(): void
    {
        Process::fake();
        $job = UpdateJob::queue('module', '1.0.0', 'evil; rm -rf /');

        app(Updater::class)->run($job);

        $this->assertSame('failed', $job->fresh()->status);
        Process::assertNothingRan();
    }
}
