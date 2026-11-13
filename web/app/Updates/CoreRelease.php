<?php

namespace App\Updates;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Official Tornado Engine Core releases: the version tags on Core's GitHub repository.
 */
class CoreRelease
{
    public static function current(): string
    {
        return config('tornado.version');
    }

    /**
     * The newest official version, or null if GitHub can't be reached. Checked at most hourly.
     */
    public static function latest(bool $fresh = false): ?string
    {
        if ($fresh) {
            Cache::forget('tornado.core.latest');
        }

        return Cache::remember('tornado.core.latest', 3600, function () {
            try {
                $tags = Http::withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Tornado-Engine'])
                    ->timeout(10)
                    ->get('https://api.github.com/repos/'.config('tornado.core_repository').'/tags', ['per_page' => 100])
                    ->throw()
                    ->json('*.name');
            } catch (Throwable) {
                return null;
            }

            $versions = array_values(array_filter(array_map(fn ($tag) => ltrim((string) $tag, 'v'), $tags ?? []),
                fn ($version) => preg_match('/^\d+\.\d+\.\d+$/', $version)));

            return $versions ? Semver::rsort($versions)[0] : null;
        });
    }

    /** The newer official version, if there is one. */
    public static function available(): ?string
    {
        $latest = self::latest();

        return $latest && Comparator::greaterThan($latest, self::current()) ? $latest : null;
    }
}
