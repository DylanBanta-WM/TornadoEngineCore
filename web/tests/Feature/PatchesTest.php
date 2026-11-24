<?php

namespace Tests\Feature;

use App\Models\User;
use App\Patches\Patches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

/** Patches, in a scratch web folder: a module (tornado-engine/demo) and a Core file. */
class PatchesTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private Patches $patches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/patches-test-'.uniqid();
        File::ensureDirectoryExists("{$this->base}/vendor/tornado-engine/demo/src");
        File::ensureDirectoryExists("{$this->base}/vendor/laravel/framework");
        File::ensureDirectoryExists("{$this->base}/vendor/composer");
        File::ensureDirectoryExists("{$this->base}/app");
        File::ensureDirectoryExists("{$this->base}/config");
        file_put_contents("{$this->base}/vendor/tornado-engine/demo/composer.json", json_encode(['name' => 'tornado-engine/demo', 'extra' => ['tornado' => ['name' => 'Demo']]]));
        file_put_contents("{$this->base}/vendor/laravel/framework/composer.json", '{"name": "laravel/framework"}');
        file_put_contents("{$this->base}/vendor/tornado-engine/demo/src/Thing.php", "<?php\n\nreturn 'hello';\n");
        file_put_contents("{$this->base}/app/Core.php", "<?php\n\nreturn 'core';\n");
        file_put_contents("{$this->base}/config/tornado.php", "<?php\n\nreturn ['version' => '0.31.0'];\n");
        $this->installed('1.0.0');
        $this->patches = new Patches($this->base);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function installed(string $version): void
    {
        file_put_contents("{$this->base}/vendor/composer/installed.json", json_encode(['packages' => [['name' => 'tornado-engine/demo', 'version' => $version]]]));
    }

    private function script(string ...$args): string
    {
        $result = Process::path($this->base)->run(array_merge(['php', base_path('../docker/patches.php')], $args));

        return $result->output().$result->errorOutput();
    }

    private function state(string $target): ?string
    {
        return $this->patches->all()[$target]['state'] ?? null;
    }

    public function test_a_change_to_a_module_is_made_now_and_kept_as_its_patch(): void
    {
        $this->assertSame('tornado-engine/demo', $this->patches->change('/workspace/web/vendor/tornado-engine/demo/src/Thing.php', "<?php\n\nreturn 'hello, district';\n"));

        $this->assertStringContainsString('hello, district', file_get_contents("{$this->base}/vendor/tornado-engine/demo/src/Thing.php"));
        $patch = file_get_contents("{$this->base}/local/patches/tornado-engine/demo.patch");
        $this->assertStringContainsString("--- a/src/Thing.php\n+++ b/src/Thing.php", $patch);
        $this->assertSame(['state' => 'on', 'files' => ['src/Thing.php']], array_intersect_key($this->patches->all()['tornado-engine/demo'], ['state' => 1, 'files' => 1]));

        // Changed again: still one part for the file, against the released one.
        $this->patches->change('vendor/tornado-engine/demo/src/Thing.php', "<?php\n\nreturn 'hi';\n");
        $patch = file_get_contents("{$this->base}/local/patches/tornado-engine/demo.patch");
        $this->assertSame(1, substr_count($patch, 'diff --git'));
        $this->assertStringContainsString("-return 'hello';\n+return 'hi';", $patch);

        // Back to the released file: no patch left.
        $this->patches->change('vendor/tornado-engine/demo/src/Thing.php', "<?php\n\nreturn 'hello';\n");
        $this->assertFileDoesNotExist("{$this->base}/local/patches/tornado-engine/demo.patch");
        $this->assertSame([], $this->patches->all());
    }

    public function test_core_and_new_files_can_be_patched(): void
    {
        $this->assertSame('core', $this->patches->change('app/Core.php', "<?php\n\nreturn 'district core';\n"));
        $this->patches->change('app/New.php', "<?php\n\nreturn 'new';\n");

        $patch = file_get_contents("{$this->base}/local/patches/core.patch");
        $this->assertStringContainsString('+++ b/app/Core.php', $patch);
        $this->assertStringContainsString('new file mode', $patch);
        $this->assertStringContainsString('+++ b/app/New.php', $patch);

        // Before a Core update it comes off, and goes back on after.
        $this->script('remove', 'core');
        $this->assertStringContainsString("'core'", file_get_contents("{$this->base}/app/Core.php"));
        $this->assertFileDoesNotExist("{$this->base}/app/New.php");
        $this->script('apply');
        $this->assertStringContainsString('district core', file_get_contents("{$this->base}/app/Core.php"));
        $this->assertFileExists("{$this->base}/app/New.php");
    }

    public function test_only_core_and_tornado_engine_modules_are_patched(): void
    {
        foreach (['modules/notes/src/A.php', 'local/views/x.blade.php', 'storage/logs/a.log', 'vendor/laravel/framework/src/X.php', '.env', 'composer.json', '../etc/passwd'] as $path) {
            try {
                $this->patches->change($path, 'x');
                $this->fail("{$path} was patched");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDirectoryDoesNotExist("{$this->base}/local/patches");
    }

    public function test_after_an_update_a_patch_goes_back_on_or_is_turned_off_or_found_in_the_release(): void
    {
        $this->patches->change('vendor/tornado-engine/demo/src/Thing.php', "<?php\n\nreturn 'hello, district';\n");
        $released = "<?php\n\nreturn 'hello';\n";

        // Reinstalled (same version): it goes back on.
        file_put_contents("{$this->base}/vendor/tornado-engine/demo/src/Thing.php", $released);
        $this->script('apply');
        $this->assertStringContainsString('hello, district', file_get_contents("{$this->base}/vendor/tornado-engine/demo/src/Thing.php"));
        $this->assertSame('on', $this->state('tornado-engine/demo'));

        // An update that changed the same lines: the update stays, the patch turns off.
        $this->installed('1.1.0');
        file_put_contents("{$this->base}/vendor/tornado-engine/demo/src/Thing.php", "<?php\n\nreturn 'hello there';\n");
        $this->assertStringContainsString('doesn\'t fit version 1.1.0', $this->script('apply'));
        $this->assertSame('off', $this->state('tornado-engine/demo'));
        $this->assertStringContainsString('hello there', file_get_contents("{$this->base}/vendor/tornado-engine/demo/src/Thing.php"));
        $this->assertSame(['tornado-engine/demo'], $this->patches->off());

        // A release with the change in it: included.
        $this->installed('1.2.0');
        file_put_contents("{$this->base}/vendor/tornado-engine/demo/src/Thing.php", "<?php\n\nreturn 'hello, district';\n");
        $this->script('apply');
        $this->assertSame('included', $this->state('tornado-engine/demo'));
    }

    public function test_a_patch_survives_an_update_that_changed_other_lines(): void
    {
        $file = "{$this->base}/vendor/tornado-engine/demo/src/Thing.php";
        $lines = fn (string $first, string $last) => "<?php\n\n// {$first}\n".implode('', array_map(fn ($i) => "// line {$i}\n", range(1, 12)))."// {$last}\n";
        file_put_contents($file, $lines('top', 'bottom'));
        $this->patches->change('vendor/tornado-engine/demo/src/Thing.php', $lines('district top', 'bottom'));

        $this->installed('2.0.0');
        file_put_contents($file, $lines('top', 'new bottom'));
        $this->script('apply');

        $this->assertSame('on', $this->state('tornado-engine/demo'));
        $this->assertSame($lines('district top', 'new bottom'), file_get_contents($file));
    }

    public function test_a_linked_working_copy_is_never_patched(): void
    {
        File::ensureDirectoryExists("{$this->base}/modules/linked");
        symlink("{$this->base}/modules/linked", "{$this->base}/vendor/tornado-engine/linked");

        $this->expectException(RuntimeException::class);
        $this->patches->change('vendor/tornado-engine/linked/src/A.php', 'x');
    }

    public function test_admins_see_patches_on_the_updates_page_and_can_remove_one(): void
    {
        $this->app->instance(Patches::class, $this->patches);
        $this->patches->change('vendor/tornado-engine/demo/src/Thing.php', "<?php\n\nreturn 'hello, district';\n");
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/settings/updates')->assertSee('Patches')->assertSee('tornado-engine/demo')->assertSee('src/Thing.php');
        $this->actingAs($admin)->post('/administration/patches/remove', ['target' => 'tornado-engine/demo'])->assertSessionHas('patches_status');
        $this->assertSame([], $this->patches->all());
        $this->assertStringContainsString("'hello'", file_get_contents("{$this->base}/vendor/tornado-engine/demo/src/Thing.php"), 'its change undone');

        $this->actingAs(User::factory()->create())->post('/administration/patches/remove', ['target' => 'core'])->assertForbidden();
    }
}
