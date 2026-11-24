<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Updates\DistrictBranch;
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

        $this->actingAs($this->admin())->get('/settings/updates')
            ->assertOk()
            ->assertSee('This server runs version 0.7.0.')
            ->assertSee('Version 0.10.0 is available.')
            ->assertSee('Update to 0.10.0');
    }

    public function test_up_to_date_core_has_no_update_button(): void
    {
        $this->githubTags(['v0.7.0']);

        $this->actingAs($this->admin())->get('/settings/updates')->assertSee('That\'s the newest official version.')->assertDontSee('Update to');
        $this->actingAs($this->admin())->from('/settings/updates')->post('/administration/updates/core')->assertSessionHasErrors('updates');
        $this->assertSame(0, UpdateJob::count());
    }

    public function test_an_update_is_queued_for_the_updater_once(): void
    {
        $this->githubTags(['v0.8.0']);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/administration/updates/core')->assertSessionHas('updates_status');
        $this->actingAs($admin)->from('/settings/updates')->post('/administration/updates/core')->assertSessionHasErrors('updates');

        $job = UpdateJob::sole();
        $this->assertSame(['core', '0.8.0', 'queued'], [$job->kind, $job->version, $job->status]);
        $this->actingAs($admin)->get('/settings/updates')->assertSee('Tornado Engine Core 0.8.0')->assertSee('Waiting');
        $this->actingAs(User::factory()->create())->post('/administration/updates/core')->assertForbidden();
    }

    public function test_the_updater_updates_core_with_git_then_composer(): void
    {
        Process::fake([
            "*'status' '--porcelain'*" => Process::result(''),
            "*'log' '--reverse'*" => Process::result(''),
            "*'diff' '--name-only'*'harness'*" => Process::result("docker-compose.yml\n"),
            '*modules.php*' => Process::result("Modules' containers changed. Run \"docker compose up -d\" again to start them.\n"),
            '*' => Process::result('ok'),
        ]);
        $job = UpdateJob::queue('core', '0.8.0');

        $this->artisan('tornado:updater', ['--once' => true])->assertSuccessful();

        $job->refresh();
        $this->assertSame('done', $job->status, (string) $job->log);
        Process::assertRan(fn ($process) => $process->command === ['git', '-C', '/tornado', 'fetch', '--no-tags', 'https://github.com/DylanBanta-WM/TornadoEngineCore.git', 'refs/tags/v0.8.0:refs/tags/v0.8.0']);
        // No district changes: a fast-forward to the new version.
        Process::assertRan(fn ($process) => array_slice($process->command, 0, 3) === ['git', '-C', '/tornado'] && array_slice($process->command, -3) === ['merge', '--ff-only', 'v0.8.0']);
        Process::assertRan(fn ($process) => $process->command === ['composer', 'install', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*']);
        Process::assertRan(fn ($process) => $process->command === ['php', 'artisan', 'migrate', '--force']);
        $this->assertArrayHasKey('rebuild', $job->notes);
        $this->assertArrayHasKey('containers', $job->notes);
    }

    public function test_a_clash_with_local_changes_holds_core_unless_updated_anyway(): void
    {
        // Real git behaviour is in DistrictBranchTest; here, the updater passes "anyway" along and reports a clash.
        Process::fake([
            "*'status' '--porcelain'*" => Process::result(''),
            "*'log' '--reverse'*" => Process::result("abc123\t2026-11-24T10:00:00+00:00\tOur change\n"),
            "*'rebase' '--onto'*" => Process::result('', 'CONFLICT', 1),
            "*'cherry-pick' 'abc123'*" => Process::result('', 'CONFLICT', 1),
            '*' => Process::result(''),
        ]);
        $job = UpdateJob::queue('core', '0.8.0');
        app(Updater::class)->run($job);
        $this->assertSame('failed', $job->fresh()->status);
        $this->assertStringContainsString('clash with v0.8.0', $job->fresh()->log);
        Process::assertDidntRun(fn ($process) => in_array('reset', $process->command, true));

        $anyway = UpdateJob::queue('core', '0.8.0');
        $anyway->update(['anyway' => true]);
        app(Updater::class)->run($anyway);
        $this->assertSame('done', $anyway->fresh()->status, (string) $anyway->fresh()->log);
        $this->assertSame(['Our change'], DistrictBranch::setAside()['core']['changes']);
    }

    public function test_the_updater_installs_a_module_through_composer_local_json(): void
    {
        Process::fake(['*' => Process::result('ok')]);
        $job = UpdateJob::queue('module', 'v0.2.0', 'someone/tornado-thing', 'https://github.com/someone/TornadoThing');

        app(Updater::class)->run($job);

        $this->assertSame('done', $job->fresh()->status);
        Process::assertRan(fn ($process) => $process->command === ['composer', 'require', 'someone/tornado-thing:>=0.2.0', '--update-with-dependencies', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*']
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
        Process::assertRan(fn ($process) => $process->command === ['composer', 'require', 'district/notes:*@dev', '--update-with-dependencies', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*']);
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

    public function test_the_updater_stops_after_a_job_so_docker_restarts_it_with_new_code(): void
    {
        Process::fake(['*' => Process::result('ok')]);
        UpdateJob::queue('module', '1.0.0', 'district/one');
        UpdateJob::queue('module', '1.0.0', 'district/two');

        $this->artisan('tornado:updater')->assertSuccessful();

        $this->assertSame(['done', 'queued'], UpdateJob::orderBy('id')->pluck('status')->all());
    }

    public function test_a_new_module_list_starts_from_cores_lock(): void
    {
        Process::fake(['*' => Process::result('ok')]);
        $lock = preg_replace('/\.json$/', '.lock', $this->local);

        app(Updater::class)->run(UpdateJob::queue('module', '1.0.0', 'tornado-engine/thing'));

        $this->assertFileEquals(base_path('composer.lock'), $lock);
        @unlink($lock);
    }

    public function test_composer_is_tried_again_when_network_storage_is_busy(): void
    {
        config(['tornado.updater.retry_wait' => 0]);
        Process::fake(["'composer'*" => Process::sequence()
            ->push(Process::result('Could not delete vendor/guzzlehttp/guzzle/.nfs0000000049b037', exitCode: 1))
            ->push(Process::result('ok')), '*' => Process::result('ok')]);
        $job = UpdateJob::queue('module', '1.0.0', 'tornado-engine/thing');

        app(Updater::class)->run($job);

        $this->assertSame('done', $job->fresh()->status, (string) $job->fresh()->log);
        $this->assertStringContainsString('Network storage was busy. Trying again.', $job->fresh()->log);
        @unlink(preg_replace('/\.json$/', '.lock', $this->local));
    }

    private function localWith(array $require, array $repositories = []): void
    {
        file_put_contents($this->local, json_encode(['require' => $require, 'repositories' => $repositories]));
    }

    public function test_uninstalling_removes_the_module_through_composer_local_json(): void
    {
        Process::fake(['*' => Process::result('ok')]);
        $this->localWith(['someone/tornado-thing' => '>=0.2.0'], [['type' => 'path', 'url' => 'modules/*'], ['type' => 'vcs', 'url' => 'https://github.com/someone/TornadoThing']]);
        $job = UpdateJob::remove('someone/tornado-thing', repository: 'https://github.com/someone/TornadoThing');

        app(Updater::class)->run($job);

        $this->assertSame('done', $job->fresh()->status, (string) $job->fresh()->log);
        $this->assertSame('Uninstall someone/tornado-thing', $job->fresh()->label());
        Process::assertRan(fn ($process) => $process->command === ['composer', 'remove', 'someone/tornado-thing', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*']
            && $process->environment === ['COMPOSER' => 'composer.local.json']);
        Process::assertDidntRun(fn ($process) => in_array('migrate:reset', $process->command, true));
        Process::assertRan(fn ($process) => $process->command === ['php', 'artisan', 'optimize:clear']);
        $this->assertSame([['type' => 'path', 'url' => 'modules/*']], json_decode(file_get_contents($this->local), true)['repositories']);
    }

    public function test_uninstalling_with_its_data_rolls_back_its_migrations_first(): void
    {
        $commands = [];
        Process::fake(['*' => function ($process) use (&$commands) {
            $commands[] = $process->command;

            return Process::result('ok');
        }]);
        $this->localWith(['tornado-engine/gam-runner' => '*']);
        $job = UpdateJob::remove('tornado-engine/gam-runner', deleteData: true);

        app(Updater::class)->run($job);

        $this->assertSame('done', $job->fresh()->status, (string) $job->fresh()->log);
        $this->assertSame('Uninstall tornado-engine/gam-runner and delete its data', $job->fresh()->label());
        $reset = array_search(['php', 'artisan', 'migrate:reset', '--path=vendor/tornado-engine/gam-runner/database/migrations', '--force'], $commands, true);
        $remove = array_search(['composer', 'remove', 'tornado-engine/gam-runner', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*'], $commands, true);
        $this->assertNotFalse($reset);
        $this->assertGreaterThan($reset, $remove);
    }

    public function test_only_modules_in_composer_local_json_can_be_uninstalled(): void
    {
        Process::fake();
        $this->localWith(['tornado-engine/gam' => '*']);
        $job = UpdateJob::remove('tornado-engine/nav', deleteData: true);

        app(Updater::class)->run($job);

        $this->assertSame('failed', $job->fresh()->status);
        $this->assertStringContainsString('isn\'t listed in composer.local.json', $job->fresh()->log);
        Process::assertNothingRan();
    }

    public function test_uninstalling_is_tried_again_when_network_storage_is_busy(): void
    {
        config(['tornado.updater.retry_wait' => 0]);
        Process::fake(["*'remove'*" => Process::sequence()
            ->push(Process::result('Could not delete vendor/someone/thing/.nfs0000000049b037', exitCode: 1))
            ->push(Process::result('ok')), '*' => Process::result('ok')]);
        $this->localWith(['someone/thing' => '*']);
        $job = UpdateJob::remove('someone/thing');

        app(Updater::class)->run($job);

        $this->assertSame('done', $job->fresh()->status, (string) $job->fresh()->log);
        $this->assertStringContainsString('Network storage was busy. Trying again.', $job->fresh()->log);
    }

    public function test_a_finished_job_is_at_100_percent_and_a_running_one_shows_a_bar(): void
    {
        Process::fake();
        $job = UpdateJob::queue('module', '1.0.0', 'tornado-engine/thing');
        app(Updater::class)->run($job);
        $this->assertSame(100, $job->fresh()->progress);

        $running = UpdateJob::queue('module', '1.1.0', 'tornado-engine/other');
        $running->update(['status' => 'running', 'progress' => 55]);
        $this->actingAs($this->admin())->get('/settings/updates')->assertOk();
        $this->assertSame(55, UpdateJob::activeProgress());
        $this->assertStringContainsString('role="progressbar"', view('components.update-jobs', ['jobs' => collect([$running->fresh()])])->render());
    }

    public function test_the_updates_page_shows_district_changes_and_offers_update_anyway_after_a_clash(): void
    {
        Setting::put('district.commits', json_encode(['base' => 'v0.7.0', 'commits' => [['hash' => 'a1', 'message' => 'Changes made by Chat (run 12)', 'date' => now()->toIso8601String()]]]));
        $held = UpdateJob::queue('core', '0.8.0');
        $held->update(['status' => 'failed', 'log' => 'Your district\'s changes to Core clash with v0.8.0, so Core wasn\'t updated.']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/settings/updates')->assertSee('Your changes')->assertSee('Changes made by Chat (run 12)')
            ->assertSee('Core 0.8.0 wasn&#039;t installed', false)->assertSee('Update anyway');

        $this->actingAs($admin)->post('/administration/updates/anyway', ['version' => '0.8.0'])->assertSessionHas('district_status');
        $this->assertTrue(UpdateJob::latest('id')->first()->anyway);

        Setting::put('district.set_aside', json_encode(['tag' => 'v0.8.0', 'changes' => ['Our greeting'], 'backup' => 'district-before-0.8.0']));
        $this->actingAs($admin)->get('/settings/updates')->assertSee('Our greeting')->assertSee('district-before-0.8.0');
        $this->actingAs($admin)->post('/administration/district/dismiss');
        $this->assertNull(Setting::get('district.set_aside'));

        $this->actingAs(User::factory()->create())->post('/administration/updates/anyway', ['version' => '0.8.0'])->assertForbidden();
    }
}
