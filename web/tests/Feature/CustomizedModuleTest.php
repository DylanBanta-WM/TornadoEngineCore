<?php

namespace Tests\Feature;

use App\Models\User;
use App\Updates\DistrictBranch;
use App\Updates\UpdateJob;
use App\Updates\Updater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as RealProcess;
use Tests\TestCase;

/** A customized module: a real git repository standing in for the module's (releases as tags); Composer is faked. */
class CustomizedModuleTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    /** @var list<string> */
    private array $composer = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/customized-'.uniqid();
        File::ensureDirectoryExists("{$this->dir}/origin/src");
        File::ensureDirectoryExists("{$this->dir}/modules");
        $this->git("{$this->dir}/origin", 'init', '-q', '-b', 'main');
        file_put_contents("{$this->dir}/origin/composer.json", json_encode(['name' => 'tornado-engine/demo']));
        $this->release('1.0.0', $this->lines('Hello'));
        file_put_contents("{$this->dir}/installed.json", json_encode(['packages' => [
            ['name' => 'tornado-engine/demo', 'version' => 'v1.0.0', 'source' => ['type' => 'git', 'url' => 'https://example.test/demo.git']],
        ]]));
        config([
            'tornado.updater.modules_path' => "{$this->dir}/modules",
            'tornado.updater.installed_json' => "{$this->dir}/installed.json",
            'tornado.updater.composer_local' => "{$this->dir}/composer.local.json",
            'tornado.updater.assets_timeout' => 0,
        ]);

        // git runs for real (the module's "GitHub" is the origin folder); Composer and the rest are faked.
        Process::fake(['*' => function ($process) {
            $command = $process->command;
            if (($command[0] ?? null) !== 'git') {
                $this->composer[] = implode(' ', (array) $command);

                return Process::result('ok');
            }
            $command = array_map(fn ($arg) => $arg === 'https://example.test/demo.git' ? "{$this->dir}/origin" : $arg, $command);
            $real = new RealProcess(array_merge(['git', '-c', 'safe.directory=*', '-c', 'protocol.file.allow=always'], array_slice($command, 1)), $process->path);
            $real->run();

            return Process::result($real->getOutput(), $real->getErrorOutput(), $real->getExitCode());
        }]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function git(string $path, string ...$args): void
    {
        (new RealProcess(array_merge(['git', '-c', 'user.name=T', '-c', 'user.email=t@t', '-c', 'safe.directory=*'], $args), $path))->mustRun();
    }

    private function lines(string $top, string $bottom = 'Bye'): string
    {
        return "<?php\n\n// {$top}\n".implode('', array_map(fn ($i) => "// line {$i}\n", range(1, 10)))."// {$bottom}\n";
    }

    private function release(string $version, string $thing): void
    {
        file_put_contents("{$this->dir}/origin/src/Thing.php", $thing);
        $this->git("{$this->dir}/origin", 'add', '-A');
        $this->git("{$this->dir}/origin", 'commit', '-q', '-m', "Demo {$version}");
        $this->git("{$this->dir}/origin", 'tag', "v{$version}");
    }

    private function job(string $kind, string $version = '', bool $anyway = false): UpdateJob
    {
        $job = UpdateJob::queue($kind, $version, 'tornado-engine/demo');
        $job->update(['anyway' => $anyway]);
        app(Updater::class)->run($job);

        return $job->refresh();
    }

    private function thing(): string
    {
        return (string) file_get_contents("{$this->dir}/modules/demo/src/Thing.php");
    }

    public function test_a_customized_module_keeps_the_districts_changes_across_updates(): void
    {
        $this->assertSame('done', $this->job('customize')->status);
        $this->assertSame(['tornado-engine/demo' => 'demo'], DistrictBranch::copies());
        $this->assertStringContainsString('tornado-engine/demo:1.0.0', implode("\n", $this->composer));
        $local = json_decode(file_get_contents("{$this->dir}/composer.local.json"), true);
        $this->assertContains(['type' => 'path', 'url' => 'modules/demo', 'options' => ['symlink' => true, 'versions' => ['tornado-engine/demo' => '1.0.0']]], $local['repositories']);

        // Chat changes it; the updater commits it.
        file_put_contents("{$this->dir}/modules/demo/src/Thing.php", $this->lines('Hello, district'));
        app(DistrictBranch::class, ['repo' => "{$this->dir}/modules/demo"])->record('Changes made by Chat (run 3)');

        // A release that changed other lines: the change comes along.
        $this->release('1.1.0', $this->lines('Hello', 'See you'));
        $this->assertSame('done', $this->job('module', '1.1.0')->status);
        $this->assertSame($this->lines('Hello, district', 'See you'), $this->thing());
        $this->assertSame('v1.1.0', DistrictBranch::rememberedModules()['tornado-engine/demo']['base']);
        $this->assertSame(['Changes made by Chat (run 3)'], array_column(DistrictBranch::rememberedModules()['tornado-engine/demo']['commits'], 'message'));

        // One that clashes waits; anyway sets the change aside.
        $this->release('1.2.0', $this->lines('Hello, world', 'See you'));
        $held = $this->job('module', '1.2.0');
        $this->assertSame('failed', $held->status);
        $this->assertStringContainsString('clash with v1.2.0', $held->log);
        $this->assertSame($this->lines('Hello, district', 'See you'), $this->thing(), 'unchanged');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/settings/updates')->assertSee('Your changes')->assertSee('tornado-engine/demo')->assertSee('Update anyway');

        $this->assertSame('done', $this->job('module', '1.2.0', anyway: true)->status);
        $this->assertSame($this->lines('Hello, world', 'See you'), $this->thing());
        $this->assertSame(['Changes made by Chat (run 3)'], DistrictBranch::setAside()['tornado-engine/demo']['changes']);
        $this->actingAs($admin)->get('/settings/updates')->assertSee('were set aside');
    }

    public function test_a_chats_changes_to_a_customized_module_can_be_undone(): void
    {
        $this->job('customize');
        file_put_contents("{$this->dir}/modules/demo/src/Thing.php", $this->lines('Hello, district'));

        $job = UpdateJob::queue('undo', '7');
        config(['tornado.repository_path' => "{$this->dir}/no-core"]);
        File::ensureDirectoryExists("{$this->dir}/no-core");
        $this->git("{$this->dir}/no-core", 'init', '-q');
        app(Updater::class)->run($job);

        $this->assertSame('done', $job->refresh()->status, $job->log);
        $this->assertStringContainsString('tornado-engine/demo: Changes made by Chat (run 7)', $job->log);
        $this->assertSame($this->lines('Hello'), $this->thing());
    }

    public function test_going_back_to_the_official_version_keeps_the_copy_aside(): void
    {
        $this->job('customize');
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/administration/district/official', ['package' => 'tornado-engine/demo'])->assertSessionHas('district_status');
        $this->actingAs($admin)->post('/administration/district/official', ['package' => 'tornado-engine/other'])->assertNotFound();
        $this->actingAs(User::factory()->create())->post('/administration/district/official', ['package' => 'tornado-engine/demo'])->assertForbidden();

        $this->composer = [];
        app(Updater::class)->run(UpdateJob::latest('id')->first());

        $this->assertSame([], DistrictBranch::copies());
        $this->assertCount(1, glob("{$this->dir}/modules/.demo-customized-*"));
        $this->assertStringContainsString('require tornado-engine/demo:1.0.0', implode("\n", $this->composer));
        $local = json_decode(file_get_contents("{$this->dir}/composer.local.json"), true);
        $this->assertNotContains('modules/demo', array_column($local['repositories'] ?? [], 'url'));
    }

    public function test_a_module_not_installed_from_a_release_cant_be_customized(): void
    {
        file_put_contents("{$this->dir}/installed.json", json_encode(['packages' => []]));
        $job = $this->job('customize');
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('isn\'t installed from a release', $job->log);
    }
}
