<?php

namespace App\Updates;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Runs an install, update or uninstall inside the updater service: Composer (or git for Core), then the
 * modules' containers list, migrations, the styles, and cleared caches. Each step's output goes
 * into the job's log; the first step that fails stops the job.
 */
class Updater
{
    /** Steps run so far, and how many there will be about (the progress bar's share of each). */
    private int $done = 0;

    private int $total = 1;

    public function run(UpdateJob $job): void
    {
        $this->done = 0;
        // What finish() runs is 4 more steps (containers, migrations, styles, caches).
        $this->total = 4 + match ($job->kind) {
            'core' => 7,
            'customize' => 3,
            'official', 'undo' => 1,
            'remove' => $job->delete_data ? 2 : 1,
            default => 1,
        };
        $job->update(['status' => 'running', 'progress' => 0, 'log' => '']);
        $notes = [];

        try {
            match ($job->kind) {
                'core' => $this->core($job, $notes),
                'undo' => $this->undo($job),
                'customize' => $this->customize($job),
                'official' => $this->official($job),
                'remove' => $this->remove($job),
                default => $this->module($job),
            };
            $this->finish($job, $notes);
            $job->update(['status' => 'done', 'progress' => 100, 'notes' => $notes, 'finished_at' => now()]);
            if ($notes) {
                Rebuild::markNeeded();
            }
        } catch (Throwable $e) {
            $job->addToLog("\n".$e->getMessage()."\n");
            $job->update(['status' => 'failed', 'notes' => $notes, 'finished_at' => now()]);
        }
    }

    private function module(UpdateJob $job): void
    {
        $this->checkPackage($job);

        // A module the district customized moves to the new version with the district's changes on top.
        if ($folder = DistrictBranch::copies()[$job->package] ?? null) {
            $this->moveCopy($job, $folder, (string) $job->version);

            return;
        }

        $this->localComposer($job->repository);

        // A module in web/modules (for example one Chat built) is linked from there.
        $constraint = str_starts_with((string) $job->repository, 'modules/') ? '*@dev' : ">={$job->version}";

        $this->step($job, ['composer', 'require', "{$job->package}:{$constraint}", '--update-with-dependencies', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*'],
            ['COMPOSER' => 'composer.local.json']);
    }

    /**
     * Uninstalls a module: rolls back its migrations first when its data goes too, then takes it
     * out of composer.local.json. A module linked from web/modules keeps its folder there.
     */
    private function remove(UpdateJob $job): void
    {
        $this->checkPackage($job);

        $path = config('tornado.updater.composer_local') ?? base_path('composer.local.json');
        $local = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        if (! isset($local['require'][$job->package])) {
            throw new RuntimeException(__('It isn\'t listed in composer.local.json, so it can\'t be uninstalled from here.'));
        }

        $migrations = "vendor/{$job->package}/database/migrations";
        if ($job->delete_data && is_dir(base_path($migrations))) {
            $this->step($job, ['php', 'artisan', 'migrate:reset', "--path={$migrations}", '--force']);
        }

        $this->step($job, ['composer', 'remove', $job->package, '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*'],
            ['COMPOSER' => 'composer.local.json']);

        // Its GitHub repository, if it was listed for it, isn't needed any more.
        if ($job->repository && ! str_starts_with($job->repository, 'modules/')) {
            // Objects, not arrays, so an empty "require" stays {}.
            $local = json_decode(file_get_contents($path));
            $repositories = array_values(array_filter($local->repositories ?? [], fn ($repo) => ($repo->url ?? null) !== $job->repository));
            if (count($repositories) !== count($local->repositories ?? [])) {
                $local->repositories = $repositories;
                file_put_contents($path, json_encode($local, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            }
        }
    }

    private function checkPackage(UpdateJob $job): void
    {
        if (! preg_match('#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$#', (string) $job->package)) {
            throw new RuntimeException(__('That isn\'t a Composer package name.'));
        }
    }

    /**
     * Customizes an installed module: a working copy of its repository at the installed release, in
     * web/modules/<folder> on a district branch, linked in its place. Changes to it are then kept across updates.
     */
    private function customize(UpdateJob $job): void
    {
        $this->checkPackage($job);
        if (isset(DistrictBranch::copies()[$job->package])) {
            return;
        }
        $installed = collect(json_decode((string) @file_get_contents(config('tornado.updater.installed_json') ?? base_path('vendor/composer/installed.json')), true)['packages'] ?? [])
            ->firstWhere('name', $job->package);
        $url = $installed['source']['url'] ?? null;
        if (! $installed || is_link(base_path("vendor/{$job->package}"))) {
            throw new RuntimeException(__(':package isn\'t installed from a release, so there\'s nothing to customize: change it in web/modules.', ['package' => $job->package]));
        }
        if (! is_string($url) || ! preg_match('#^https://#', $url)) {
            throw new RuntimeException(__('Couldn\'t find where :package\'s code comes from.', ['package' => $job->package]));
        }
        $version = ltrim((string) $installed['version'], 'v');
        $folder = substr($job->package, strpos($job->package, '/') + 1);
        $dir = DistrictBranch::modules($folder);
        if (file_exists($dir)) {
            throw new RuntimeException(__('web/modules/:folder is already used by something else.', ['folder' => $folder]));
        }

        $this->step($job, ['git', '-c', 'advice.detachedHead=false', 'clone', '-q', '--branch', "v{$version}", $url, $dir]);
        $this->step($job, ['git', '-C', $dir, 'checkout', '-q', '-b', DistrictBranch::BRANCH]);
        app(DistrictBranch::class, ['repo' => $dir])->remember($job->package);
        $this->link($job, $folder, $version);
    }

    /** Stops customizing a module: back to its official release. The working copy is kept, set aside in web/modules. */
    private function official(UpdateJob $job): void
    {
        $this->checkPackage($job);
        $folder = DistrictBranch::copies()[$job->package] ?? null;
        if (! $folder) {
            throw new RuntimeException(__(':package isn\'t customized.', ['package' => $job->package]));
        }
        $base = ltrim((string) app(DistrictBranch::class, ['repo' => DistrictBranch::modules($folder)])->officialBase(), 'v');

        $path = config('tornado.updater.composer_local') ?? base_path('composer.local.json');
        $local = json_decode((string) @file_get_contents($path), true) ?: [];
        $local['repositories'] = array_values(array_filter($local['repositories'] ?? [], fn ($entry) => ($entry['url'] ?? null) !== "modules/{$folder}"));
        file_put_contents($path, json_encode($local, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        // Kept, out of the way: a folder starting with a dot isn't a module.
        $kept = DistrictBranch::modules('.'.$folder.'-customized-'.now()->format('Ymd-His'));
        rename(DistrictBranch::modules($folder), $kept);
        $job->addToLog(__('The customized copy is kept in web/modules/:kept.', ['kept' => basename($kept)])."\n");
        DistrictBranch::forget($job->package);

        $this->step($job, ['composer', 'require', "{$job->package}:".($job->version ?: ($base !== '' ? $base : '*')), '--update-with-dependencies', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*'],
            ['COMPOSER' => 'composer.local.json']);
    }

    /** Moves a customized module to an official version (fetched from its repository), the district's changes on top. */
    private function moveCopy(UpdateJob $job, string $folder, string $version): void
    {
        $dir = DistrictBranch::modules($folder);
        $district = app(DistrictBranch::class, ['repo' => $dir]);
        try {
            $this->step($job, ['git', '-C', $dir, 'fetch', '-q', '--tags', 'origin']);
            $job->addToLog(__('Putting your changes to :package on top of :version.', ['package' => $job->package, 'version' => $version])."\n");
            $setAside = $district->update("v{$version}", (bool) $job->anyway);
            DistrictBranch::saveSetAside($job->package, $setAside ? ['tag' => "v{$version}", 'changes' => $setAside, 'backup' => DistrictBranch::BRANCH.'-before-'.$version] : null);
        } finally {
            $district->remember($job->package);
        }
        $this->link($job, $folder, $version);
    }

    /** Links a customized module from web/modules, reporting its official version (so what needs it still fits). */
    private function link(UpdateJob $job, string $folder, string $version): void
    {
        $this->localComposer("modules/{$folder}", [$job->package => $version]);
        $this->step($job, ['composer', 'require', "{$job->package}:{$version}", '--update-with-dependencies', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*'],
            ['COMPOSER' => 'composer.local.json']);
    }

    /** Undoes the changes to Core and customized modules that some Chat runs made (the job's version lists the runs: "12,13"). */
    private function undo(UpdateJob $job): void
    {
        $runs = array_values(array_filter(array_map('intval', explode(',', (string) $job->version))));
        $pending = __('Changes made by Chat (run :id)', ['id' => end($runs) ?: 0]);
        $district = app(DistrictBranch::class, ['repo' => config('tornado.repository_path')]);
        try {
            $undone = $district->undo($runs, $pending);
        } finally {
            $district->remember();
        }
        foreach (DistrictBranch::copies() as $package => $folder) {
            $copy = app(DistrictBranch::class, ['repo' => DistrictBranch::modules($folder)]);
            try {
                $undone = array_merge($undone, array_map(fn ($change) => "{$package}: {$change}", $copy->undo($runs, $pending)));
            } finally {
                $copy->remember($package);
            }
        }
        $job->addToLog($undone ? __('Undone: :changes', ['changes' => implode('; ', $undone)])."\n" : __('Those runs made no changes to Core.')."\n");
    }

    /**
     * @param  array<string, string>  $notes
     */
    private function core(UpdateJob $job, array &$notes): void
    {
        $repo = config('tornado.repository_path');
        $tag = 'v'.$job->version;
        $git = fn (array $args) => array_merge(['git', '-C', $repo], $args);

        $this->step($job, $git(['fetch', '--no-tags', 'https://github.com/'.config('tornado.core_repository').'.git', "refs/tags/{$tag}:refs/tags/{$tag}"]));

        if (trim($this->step($job, $git(['diff', '--name-only', 'HEAD', $tag, '--', 'docker', 'docker-compose.yml', 'harness']))) !== '') {
            $notes['rebuild'] = __('Needs a rebuild to finish.');
        }

        // The district's own changes (commits on its branch) go on top of the new version; if they clash,
        // Core waits, unless someone chose to update anyway.
        $job->addToLog("\n".__('Putting this district\'s changes on top of :tag.', ['tag' => $tag])."\n");
        $district = app(DistrictBranch::class, ['repo' => $repo]);
        try {
            $setAside = $district->update($tag, (bool) $job->anyway);
        } finally {
            $district->remember();
        }
        DistrictBranch::saveSetAside('core', $setAside ? ['tag' => $tag, 'changes' => $setAside, 'backup' => DistrictBranch::BRANCH.'-before-'.$job->version] : null);
        if ($setAside) {
            $job->addToLog(__('Set aside (they didn\'t fit :tag): :changes', ['tag' => $tag, 'changes' => implode('; ', $setAside)])."\n");
        }

        if (is_file(config('tornado.updater.composer_local') ?? base_path('composer.local.json'))) {
            $this->step($job, ['composer', 'update', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*'], ['COMPOSER' => 'composer.local.json']);
        } else {
            $this->step($job, ['composer', 'install', '--no-interaction', '--no-progress', '--ignore-platform-req=ext-*']);
        }
    }

    /**
     * @param  array<string, string>  $notes
     */
    private function finish(UpdateJob $job, array &$notes): void
    {
        $repo = config('tornado.repository_path');
        $containers = $this->step($job, ['php', "{$repo}/docker/modules.php", "{$repo}/docker/modules.yml"]);
        if (str_contains($containers, 'containers changed')) {
            $notes['containers'] = __('Needs a rebuild to start its containers.');
        }
        if (str_contains($containers, 'PHP extensions changed')) {
            $notes['rebuild'] = __('Needs a rebuild for its PHP extensions. It stays off until then.');
        }

        $this->step($job, ['php', 'artisan', 'migrate', '--force']);
        $this->styles($job);
        $this->step($job, ['php', 'artisan', 'optimize:clear']);
    }

    /**
     * Asks the assets-watcher service to rebuild the styles, and waits for it.
     */
    private function styles(UpdateJob $job): void
    {
        $timeout = (int) config('tornado.updater.assets_timeout');
        if ($timeout <= 0) {
            return;
        }

        $dir = storage_path('app/tornado');
        @mkdir($dir, 0775, true);
        @unlink("{$dir}/assets.done");
        file_put_contents("{$dir}/assets.request", now()->toIso8601String());
        $job->addToLog("\n$ npm run build (assets-watcher)\n");

        $deadline = time() + $timeout;
        while (! is_file("{$dir}/assets.done")) {
            if (time() > $deadline) {
                throw new RuntimeException(__('The styles weren\'t rebuilt: the assets-watcher service didn\'t answer. Run docker compose up -d on the server.'));
            }
            sleep(2);
        }

        if (trim((string) file_get_contents("{$dir}/assets.done")) !== 'ok') {
            $job->addToLog((string) @file_get_contents("{$dir}/assets.log"));
            throw new RuntimeException(__('Rebuilding the styles failed.'));
        }

        $this->done++;
        $this->report($job);
    }

    /**
     * Runs one command, adds it and its output to the log, and returns the output.
     *
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    private function step(UpdateJob $job, array $command, array $env = []): string
    {
        $job->addToLog("\n$ ".implode(' ', $command)."\n");

        // Starting a step counts the ones before it as done; the bar stays under 100 until the job is.
        $this->report($job);

        // On network storage (NFS), a file a running process still has open leaves a .nfs file
        // behind that can't be deleted for a moment; Composer then fails. Wait and try again.
        for ($try = 1; ; $try++) {
            $result = Process::path(base_path())->env($env)->timeout(1800)->run($command);
            $output = $result->output().$result->errorOutput();
            $job->addToLog($output);

            if ($result->successful() || $try >= 3 || ! str_contains($output, '.nfs')) {
                break;
            }
            $job->addToLog("\n".__('Network storage was busy. Trying again.')."\n");
            sleep((int) config('tornado.updater.retry_wait', 10));
        }

        if (! $result->successful()) {
            throw new RuntimeException(__('This step failed, so the rest didn\'t run.'));
        }

        $this->done++;
        $this->report($job);

        return $output;
    }

    private function report(UpdateJob $job): void
    {
        $job->update(['progress' => min(95, (int) floor($this->done / max($this->total, 1) * 100))]);
    }

    /**
     * Makes sure web/composer.local.json exists (a district's modules go there, not in Core's
     * composer.json), and lists a module's GitHub repository if it isn't on Packagist.
     */
    /** @param  array<string, string>  $versions  for a linked folder: the version Composer reports for its package */
    private function localComposer(?string $repository, array $versions = []): void
    {
        $path = config('tornado.updater.composer_local') ?? base_path('composer.local.json');

        // A new list starts from Core's own lock, so installing one module doesn't update everything.
        $lock = preg_replace('/\.json$/', '.lock', $path);
        if (! is_file($path) && ! is_file($lock) && is_file(base_path('composer.lock'))) {
            copy(base_path('composer.lock'), $lock);
        }

        $local = is_file($path) ? json_decode(file_get_contents($path), true) : [
            'require' => new \stdClass,
            'extra' => ['merge-plugin' => ['include' => ['composer.json'], 'merge-dev' => true, 'merge-extra' => true, 'merge-scripts' => true]],
            'config' => ['allow-plugins' => ['wikimedia/composer-merge-plugin' => true, 'pestphp/pest-plugin' => true, 'php-http/discovery' => true]],
        ];

        if ($repository && ! in_array($repository, array_column($local['repositories'] ?? [], 'url'), true)) {
            $local['repositories'][] = str_starts_with($repository, 'modules/')
                ? ['type' => 'path', 'url' => $repository, 'options' => ['symlink' => true]]
                : ['type' => 'vcs', 'url' => $repository];
        }
        foreach ($versions ? $local['repositories'] : [] as $i => $entry) {
            if (($entry['url'] ?? null) === $repository) {
                $local['repositories'][$i]['options']['versions'] = $versions;
            }
        }

        file_put_contents($path, json_encode($local, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
}
