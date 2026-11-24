<?php

namespace App\Updates;

use App\Models\Setting;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * A district's own changes to Core, kept as commits on a local "district" branch of Core's repository
 * (people and Chat change Core's files directly; the updater commits what changed). A Core update replays
 * them onto the new version. If they clash, the update waits, unless someone chose "update anyway": then the
 * new version goes on, the commits that fit come along, and the ones that clash are set aside (a backup
 * branch keeps them all). The same for every district.
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
            throw new RuntimeException(__('Your district\'s changes to Core clash with :tag, so Core wasn\'t updated. Update anyway to set aside the changes that don\'t fit (they\'re kept, and Chat can redo them), or redo them first.', ['tag' => $tag]));
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
     * Saves what the website shows (Settings → Updates): the district's changes and the release they sit on.
     * Only the updater can see Core's repository, so it writes this down for the website.
     */
    public function remember(): void
    {
        Setting::put('district.commits', json_encode(['base' => $this->officialBase(), 'commits' => $this->commits()]));
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
