<?php

namespace App\Updates;

use App\Models\Setting;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * A district's own changes to Core, or to a module it customized, kept as commits on a local "district" branch
 * of that repository (people and Chat change the files directly; the updater commits what changed). An update
 * replays them onto the new version. If they clash, the update waits, unless someone chose "update anyway": then
 * the new version goes on, the commits that fit come along, and the ones that clash are set aside (a backup
 * branch keeps them all). A customized module is a working copy of its repository in web/modules. The same for
 * every district.
 */
class DistrictBranch
{
    public const BRANCH = 'district';

    /** Files Core itself writes on each server; never a district change. */
    private const SERVER_FILES = ['docker/modules.yml', 'docker/php-extensions.txt'];

    public function __construct(private ?string $repo = null)
    {
        $this->repo ??= (string) config('tornado.repository_path');
    }

    /** Whether Core's files have changes not committed yet. */
    public function dirty(): bool
    {
        return trim($this->git(['status', '--porcelain', '--', '.', ...$this->excluded()])) !== '';
    }

    /**
     * Commits what changed in Core's files to the district branch. Core's version number is never a district
     * change (it comes from releases), so it's put back. Returns the commit, or null if nothing changed.
     */
    public function record(string $message): ?string
    {
        if (! $this->dirty()) {
            return null;
        }
        if ($this->branch() !== self::BRANCH) {
            $this->git(['checkout', '-q', '-B', self::BRANCH]);
        }
        $this->git(['add', '-A', '--', '.', ...$this->excluded()]);
        $this->keepOfficialVersion();
        if (trim($this->git(['diff', '--cached', '--name-only'])) === '') {
            return null;
        }
        $this->git(['commit', '-q', '-m', $message]);

        return trim($this->git(['rev-parse', 'HEAD']));
    }

    /**
     * The district's commits on top of the official version it's on.
     *
     * @return list<array{hash: string, message: string, date: string}>
     */
    public function commits(?string $base = null): array
    {
        $base ??= $this->officialBase();
        if ($base === null) {
            return [];
        }
        $out = trim($this->git(['log', '--reverse', '--format=%H%x09%cI%x09%s', "{$base}..HEAD"]));

        return $out === '' ? [] : array_map(function ($line) {
            [$hash, $date, $message] = explode("\t", $line, 3) + ['', '', ''];

            return ['hash' => $hash, 'message' => $message, 'date' => $date];
        }, explode("\n", $out));
    }

    /**
     * Moves to the official version $tag (fetched already), with the district's commits on top.
     *
     * @return list<string> the messages of commits set aside (only when $anyway)
     */
    public function update(string $tag, bool $anyway): array
    {
        $this->record(__('Changes made on this server before updating to :tag', ['tag' => $tag]));

        $base = trim($this->git(['merge-base', 'HEAD', $tag]));
        $commits = $this->commits($base);
        if ($commits === []) {
            $this->git(['merge', '--ff-only', $tag]);

            return [];
        }

        // Kept, whatever happens next.
        $this->git(['branch', '-f', self::BRANCH.'-before-'.ltrim($tag, 'v'), 'HEAD']);

        if ($this->run(['rebase', '--onto', $tag, $base])->successful()) {
            return [];
        }
        $this->run(['rebase', '--abort']);

        if (! $anyway) {
            throw new RuntimeException(__('Your district\'s changes clash with :tag, so it wasn\'t updated. Update anyway to set aside the changes that don\'t fit (they\'re kept, and Chat can redo them), or redo them first.', ['tag' => $tag]));
        }

        // Update anyway: the new version, then each change that still fits.
        $this->git(['reset', '-q', '--hard', $tag]);
        $setAside = [];
        foreach ($commits as $commit) {
            if (! $this->run(['cherry-pick', $commit['hash']])->successful()) {
                $this->run(['cherry-pick', '--abort']);
                $setAside[] = $commit['message'];
            }
        }

        return $setAside;
    }

    /**
     * Saves what the website shows (Settings → Updates): the district's changes and the release they sit on, for
     * Core or (with $package) a customized module. Only the updater can see the repositories, so it writes this down.
     */
    public function remember(?string $package = null): void
    {
        $summary = ['base' => $this->officialBase(), 'commits' => $this->commits()];
        if ($package === null) {
            Setting::put('district.commits', json_encode($summary));

            return;
        }
        $modules = self::rememberedModules();
        $modules[$package] = $summary;
        Setting::put('district.modules', json_encode($modules));
    }

    public static function forget(string $package): void
    {
        $modules = self::rememberedModules();
        unset($modules[$package]);
        Setting::put('district.modules', $modules ? json_encode($modules) : null);
        self::saveSetAside($package, null);
    }

    /** @return array<string, array{base: ?string, commits: list<array{hash: string, message: string, date: string}>}> */
    public static function rememberedModules(): array
    {
        return json_decode((string) Setting::get('district.modules'), true) ?: [];
    }

    /**
     * The modules the district customized: working copies in web/modules on a district branch.
     *
     * @return array<string, string> package => folder
     */
    public static function copies(): array
    {
        $copies = [];
        foreach (glob(self::modules('*/.git')) ?: [] as $git) {
            $dir = dirname($git);
            $composer = json_decode((string) @file_get_contents("{$dir}/composer.json"), true);
            if (trim((string) @file_get_contents("{$git}/HEAD")) === 'ref: refs/heads/'.self::BRANCH && isset($composer['name'])) {
                $copies[strtolower($composer['name'])] = basename($dir);
            }
        }

        return $copies;
    }

    /** A path in web/modules (tests use a scratch folder). */
    public static function modules(string $path = ''): string
    {
        return rtrim((string) (config('tornado.updater.modules_path') ?? base_path('modules')), '/').($path !== '' ? '/'.$path : '');
    }

    /**
     * What updates set aside, by what they were in: 'core' or a package.
     *
     * @return array<string, array{tag: string, changes: list<string>, backup: string}>
     */
    public static function setAside(): array
    {
        $saved = json_decode((string) Setting::get('district.set_aside'), true) ?: [];

        return isset($saved['tag']) ? ['core' => $saved] : $saved; // Core 0.35 to 0.37 saved Core's alone.
    }

    public static function saveSetAside(string $target, ?array $entry): void
    {
        $all = self::setAside();
        $all[$target] = $entry;
        $all = array_filter($all);
        Setting::put('district.set_aside', $all ? json_encode($all) : null);
    }

    /** @return array{base: ?string, commits: list<array{hash: string, message: string, date: string}>} */
    public static function remembered(): array
    {
        return (json_decode((string) Setting::get('district.commits'), true) ?: []) + ['base' => null, 'commits' => []];
    }

    /**
     * Undoes the changes Chat runs made (their commits say "(run N)"), newest first, as new commits.
     * Anything not committed yet is committed first, so a run's latest changes are included.
     *
     * @param  list<int>  $runs
     * @return list<string> the changes undone
     */
    public function undo(array $runs, string $pending): array
    {
        $this->record($pending);
        $undone = [];
        foreach (array_reverse($this->commits()) as $commit) {
            if (! preg_match('/\(run (\d+)\)/', $commit['message'], $m) || ! in_array((int) $m[1], $runs, true)) {
                continue;
            }
            if (! $this->run(['revert', '--no-edit', $commit['hash']])->successful()) {
                $this->run(['revert', '--abort']);
                throw new RuntimeException(__('Couldn\'t undo ":change": later changes depend on it. Nothing else was undone after it.', ['change' => $commit['message']]));
            }
            $undone[] = $commit['message'];
        }

        return $undone;
    }

    /** The official release the district branch sits on: the newest version tag in its history. */
    public function officialBase(): ?string
    {
        $tag = trim($this->run(['describe', '--tags', '--abbrev=0', '--match', 'v[0-9]*', 'HEAD'])->output());

        return $tag !== '' ? $tag : null;
    }

    private function branch(): string
    {
        return trim($this->run(['rev-parse', '--abbrev-ref', 'HEAD'])->output());
    }

    /** A version change in config/tornado.php comes from releases only. */
    private function keepOfficialVersion(): void
    {
        $file = 'web/config/tornado.php';
        $diff = $this->run(['diff', '--cached', '-U0', '--', $file])->output();
        if ($diff === '') {
            return;
        }
        $changed = array_filter(explode("\n", $diff), fn ($line) => preg_match('/^[+-](?![+-])/', $line));
        if (array_filter($changed, fn ($line) => ! str_contains($line, "'version'")) === []) {
            $this->git(['reset', '-q', 'HEAD', '--', $file]);
            $this->git(['checkout', '--', $file]);
        }
    }

    /** @return list<string> */
    private function excluded(): array
    {
        return array_map(fn ($file) => ":!{$file}", self::SERVER_FILES);
    }

    private function git(array $args): string
    {
        $result = $this->run($args);
        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput() ?: $result->output()));
        }

        return $result->output();
    }

    private function run(array $args): ProcessResult
    {
        // Commits it makes (and replays) are the website's, whoever's set up on the server.
        return Process::timeout(600)->run(array_merge(['git', '-C', $this->repo, '-c', 'user.name=Tornado Engine', '-c', 'user.email=tornado-engine@localhost'], $args));
    }
}
