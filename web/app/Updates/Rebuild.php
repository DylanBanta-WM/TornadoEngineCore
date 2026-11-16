<?php

namespace App\Updates;

use App\Models\Setting;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Carbon;

/**
 * Rebuilding the containers (docker compose up -d --build) from the website. The host helper
 * (host/rebuilder.sh, installed with host/install.sh) does it; the website only asks.
 */
class Rebuild
{
    private static function file(string $name): string
    {
        return storage_path("app/tornado/rebuild.{$name}");
    }

    /** Whether the host helper is installed and running. */
    public static function helper(): bool
    {
        $beat = @filemtime(self::file('heartbeat'));

        return $beat !== false && $beat > time() - 60;
    }

    /** @return array{state: string, started: string, finished: string, exit: ?int}|null */
    public static function status(): ?array
    {
        return json_decode((string) @file_get_contents(self::file('status')), true) ?: null;
    }

    public static function running(): bool
    {
        return is_file(self::file('request')) || (self::status()['state'] ?? null) === 'running';
    }

    public static function log(): string
    {
        return mb_substr((string) @file_get_contents(self::file('log')), -20000);
    }

    /** An install or update changed something only a rebuild applies. */
    public static function markNeeded(): void
    {
        Setting::put('rebuild.needed_at', now()->toIso8601String());
    }

    public static function needed(): bool
    {
        if (array_filter(app(ModuleRegistry::class)->all(), fn ($module) => ($module->missingExtensions ?? []) !== [])) {
            return true;
        }

        $neededAt = Setting::get('rebuild.needed_at');
        $status = self::status();

        return $neededAt !== null && ! (($status['state'] ?? null) === 'done'
            && Carbon::parse($status['finished'])->gte(Carbon::parse($neededAt)));
    }

    public static function request(): void
    {
        @mkdir(dirname(self::file('request')), 0775, true);
        file_put_contents(self::file('request'), now()->toIso8601String());
    }
}
