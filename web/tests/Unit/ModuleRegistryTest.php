<?php

namespace Tests\Unit;

use App\Modules\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class ModuleRegistryTest extends TestCase
{
    private function registry(array $modules, string $core): ModuleRegistry
    {
        $dir = sys_get_temp_dir().'/tornado-modules-'.uniqid();
        mkdir($dir);
        $packages = [['name' => 'other/package', 'version' => '1.0.0']];
        foreach ($modules as $name => $range) {
            $packages[] = ['name' => $name, 'pretty_version' => 'v1.0.0', 'extra' => ['tornado' => ['name' => $name, 'core' => $range]]];
        }
        file_put_contents("$dir/installed.json", json_encode(['packages' => $packages]));

        return new ModuleRegistry("$dir/installed.json", $core);
    }

    public function test_only_packages_with_a_tornado_section_are_modules(): void
    {
        $this->assertSame(['tornado-engine/a'], array_keys($this->registry(['tornado-engine/a' => '>=0.2'], '0.2.0')->all()));
    }

    public function test_modules_outside_their_core_range_stay_off(): void
    {
        $registry = $this->registry([
            'tornado-engine/open' => '>=0.2',
            'tornado-engine/old' => '^0.1',
            'tornado-engine/none' => '',
        ], '0.3.0');

        $this->assertSame(['tornado-engine/open'], array_keys($registry->compatible()));
        $this->assertSame(['tornado-engine/old', 'tornado-engine/none'], array_keys($registry->incompatible()));
    }
}
