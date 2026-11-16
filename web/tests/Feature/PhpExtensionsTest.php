<?php

namespace Tests\Feature;

use App\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PhpExtensionsTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/php-extensions-'.uniqid();
        File::ensureDirectoryExists("{$this->dir}/vendor/composer");
        File::ensureDirectoryExists("{$this->dir}/docker");
        file_put_contents("{$this->dir}/vendor/composer/installed.json", json_encode(['packages' => [
            ['name' => 'district/directory', 'pretty_version' => 'v1.0.0', 'extra' => ['tornado' => ['name' => 'Directory', 'core' => '>=0.1', 'php-extensions' => ['ldap', 'zz_not_real']]]],
            ['name' => 'district/reports', 'pretty_version' => 'v1.0.0', 'extra' => ['tornado' => ['name' => 'Reports', 'core' => '>=0.1', 'php-extensions' => ['gd', 'ldap', 'evil; rm -rf /']]]],
            ['name' => 'district/plain', 'pretty_version' => 'v1.0.0', 'extra' => ['tornado' => ['name' => 'Plain', 'core' => '>=0.1', 'php-extensions' => ['json']]]],
        ]]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_modules_php_collects_the_extensions_modules_ask_for(): void
    {
        $result = Process::path($this->dir)->run(['php', base_path('../docker/modules.php'), "{$this->dir}/docker/modules.yml"]);

        $this->assertTrue($result->successful(), $result->errorOutput());
        $this->assertSame("gd\nldap\n", file_get_contents("{$this->dir}/docker/php-extensions.txt"));
        $this->assertStringContainsString('PHP extensions changed', $result->output());
        $this->assertStringContainsString('"evil; rm -rf /", which modules can\'t add', $result->errorOutput());

        // Unchanged the second time.
        $again = Process::path($this->dir)->run(['php', base_path('../docker/modules.php'), "{$this->dir}/docker/modules.yml"]);
        $this->assertStringNotContainsString('PHP extensions changed', $again->output());
    }

    public function test_a_module_stays_off_until_the_website_has_its_extensions(): void
    {
        $modules = (new ModuleRegistry("{$this->dir}/vendor/composer/installed.json", '0.9.0'))->all();

        $this->assertFalse($modules['district/directory']->compatible);
        $this->assertContains('zz_not_real', $modules['district/directory']->missingExtensions);
        $this->assertTrue($modules['district/plain']->compatible);
        $this->assertSame([], $modules['district/plain']->missingExtensions);
    }
}
