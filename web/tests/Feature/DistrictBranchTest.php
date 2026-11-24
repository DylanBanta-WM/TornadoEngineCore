<?php

namespace Tests\Feature;

use App\Updates\DistrictBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

/** A real git repository standing in for Core: official releases as tags, a district's changes on its branch. */
class DistrictBranchTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = sys_get_temp_dir().'/district-'.uniqid();
        File::ensureDirectoryExists("{$this->repo}/web/app");
        File::ensureDirectoryExists("{$this->repo}/web/config");
        File::ensureDirectoryExists("{$this->repo}/docker");
        $this->git('init', '-q', '-b', 'main');
        $this->write('web/app/Greeting.php', $this->lines('Hello'));
        $this->write('web/app/Other.php', "<?php\n\nreturn 'other';\n");
        $this->write('web/config/tornado.php', "<?php\n\nreturn ['version' => '1.0.0'];\n");
        $this->write('docker/modules.yml', "# none\n");
        $this->commit('v1.0.0');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->repo);
        parent::tearDown();
    }

    private function git(string ...$args): string
    {
        return Process::path($this->repo)->run(array_merge(['git', '-c', 'user.name=T', '-c', 'user.email=t@t', '-c', 'safe.directory=*'], $args))->throw()->output();
    }

    private function write(string $path, string $content): void
    {
        file_put_contents("{$this->repo}/{$path}", $content);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents("{$this->repo}/{$path}");
    }

    /** A file with a greeting at the top and a sign-off at the bottom, far apart. */
    private function lines(string $top, string $bottom = 'Bye'): string
    {
        return "<?php\n\n// {$top}\n".implode('', array_map(fn ($i) => "// line {$i}\n", range(1, 10)))."// {$bottom}\n";
    }

    /** An official release: committed on main and tagged, then back to wherever the district was. */
    private function release(string $tag, callable $changes): void
    {
        $was = trim($this->git('rev-parse', '--abbrev-ref', 'HEAD'));
        $this->git('stash', '-q', '--include-untracked');
        $this->git('checkout', '-q', 'main');
        $changes();
        $this->commit($tag);
        $this->git('checkout', '-q', $was);
    }

    private function commit(string $tag): void
    {
        $this->git('add', '-A');
        $this->git('commit', '-q', '-m', "Core {$tag}");
        $this->git('tag', $tag);
    }

    private function branch(): DistrictBranch
    {
        return new DistrictBranch($this->repo);
    }

    public function test_changes_become_commits_on_the_district_branch_without_the_version(): void
    {
        $this->assertNull($this->branch()->record('Nothing yet'));

        $this->write('web/app/Greeting.php', $this->lines('Hello, district'));
        $this->write('web/app/New.php', "<?php\n\nreturn 'new';\n");
        $this->write('web/config/tornado.php', "<?php\n\nreturn ['version' => '9.9.9'];\n");
        $this->write('docker/modules.yml', "# written by Core\n");

        $this->assertNotNull($this->branch()->record('Chat (run 5)'));
        $this->assertSame('district', trim($this->git('rev-parse', '--abbrev-ref', 'HEAD')));
        $this->assertSame([['message' => 'Chat (run 5)']], array_map(fn ($c) => ['message' => $c['message']], $this->branch()->commits()));
        $this->assertSame("web/app/Greeting.php\nweb/app/New.php", trim($this->git('show', '--name-only', '--format=', 'HEAD')), 'not the version, not docker/modules.yml');
        $this->assertStringContainsString("'1.0.0'", $this->read('web/config/tornado.php'));
        $this->assertSame('v1.0.0', $this->branch()->officialBase());
    }

    public function test_an_update_keeps_the_districts_changes_when_they_fit(): void
    {
        $this->write('web/app/Greeting.php', $this->lines('Hello, district'));
        $this->branch()->record('Our greeting');
        $this->release('v1.1.0', fn () => $this->write('web/app/Greeting.php', $this->lines('Hello', 'See you')));

        $this->assertSame([], $this->branch()->update('v1.1.0', anyway: false));

        $this->assertSame($this->lines('Hello, district', 'See you'), $this->read('web/app/Greeting.php'));
        $this->assertSame('v1.1.0', $this->branch()->officialBase());
        $this->assertSame(['Our greeting'], array_column($this->branch()->commits(), 'message'));
    }

    public function test_without_district_changes_an_update_is_a_fast_forward(): void
    {
        $this->release('v1.1.0', fn () => $this->write('web/app/Other.php', "<?php\n\nreturn 'newer';\n"));
        $this->assertSame([], $this->branch()->update('v1.1.0', anyway: false));
        $this->assertStringContainsString('newer', $this->read('web/app/Other.php'));
    }

    public function test_a_clash_holds_the_update_unless_it_is_updated_anyway(): void
    {
        $this->write('web/app/Greeting.php', $this->lines('Hello, district'));
        $this->branch()->record('Our greeting');
        $this->write('web/app/Other.php', "<?php\n\nreturn 'ours';\n");
        $this->branch()->record('Our other');
        $this->release('v1.1.0', fn () => $this->write('web/app/Greeting.php', $this->lines('Hello, world')));
        $before = trim($this->git('rev-parse', 'HEAD'));

        try {
            $this->branch()->update('v1.1.0', anyway: false);
            $this->fail('It updated over a clash.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('clash with v1.1.0', $e->getMessage());
        }
        $this->assertSame($before, trim($this->git('rev-parse', 'HEAD')), 'nothing changed');
        $this->assertSame($this->lines('Hello, district'), $this->read('web/app/Greeting.php'));

        // Anyway: the new version, the change that fits, and the clashing one set aside (and kept).
        $this->assertSame(['Our greeting'], $this->branch()->update('v1.1.0', anyway: true));
        $this->assertSame($this->lines('Hello, world'), $this->read('web/app/Greeting.php'));
        $this->assertStringContainsString('ours', $this->read('web/app/Other.php'));
        $this->assertSame(['Our other'], array_column($this->branch()->commits(), 'message'));
        $this->assertStringContainsString('Our greeting', $this->git('log', '--format=%s', 'district-before-1.1.0'));
    }
}
