<?php

namespace App\Updates;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Runs an install or update inside the updater service: Composer (or git for Core), then the
 * modules' containers list, migrations, the styles, and cleared caches. Each step's output goes
 * into the job's log; the first step that fails stops the job.
 */
class Updater
{
    public function run(UpdateJob $job): void
    {
        $job->update(['status' => 'running', 'log' => '']);
        $notes = [];

        try {
            $job->kind === 'core' ? $this->core($job, $notes) : $this->module($job);
            $this->finish($job, $notes);
            $job->update(['status' => 'done', 'notes' => $notes, 'finished_at' => now()]);
        } catch (Throwable $e) {
            $job->addToLog("\n".$e->getMessage()."\n");
            $job->update(['status' => 'failed', 'notes' => $notes, 'finished_at' => now()]);
        }
    }

    private function module(UpdateJob $job): void
    {
        if (! preg_match('#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$#', (string) $job->package)) {
            throw new RuntimeException(__('That isn\'t a Composer package name.'));
        }

        $this->localComposer($job->repository);

        $this->step($job, ['composer', 'require', "{$job->package}:>={$job->version}", '--update-with-dependencies', '--no-interaction', '--no-progress'],
            ['COMPOSER' => 'composer.local.json']);
    }

    /**
     * @param  array<string, string>  $notes
     */
    private function core(UpdateJob $job, array &$notes): void
    {
        $repo = config('tornado.repository_path');
        $tag = 'v'.$job->version;
        $git = fn (array $args) => array_merge(['git', '-C', $repo], $args);

        $changed = trim($this->step($job, $git(['status', '--porcelain', '--untracked-files=no'])));
        if ($changed !== '') {
            throw new RuntimeException(__('Core\'s own files have been changed on this server, so it can\'t update itself. Update it by hand with git.'));
        }

        $this->step($job, $git(['fetch', '--no-tags', 'https://github.com/'.config('tornado.core_repository').'.git', "refs/tags/{$tag}:refs/tags/{$tag}"]));

        if (trim($this->step($job, $git(['diff', '--name-only', 'HEAD', $tag, '--', 'docker', 'docker-compose.yml']))) !== '') {
            $notes['rebuild'] = __('This update changes Tornado Engine\'s containers. On the server, run docker compose up -d --build to finish it.');
        }

        $this->step($job, $git(['merge', '--ff-only', $tag]));

        if (is_file(config('tornado.updater.composer_local') ?? base_path('composer.local.json'))) {
            $this->step($job, ['composer', 'update', '--no-interaction', '--no-progress'], ['COMPOSER' => 'composer.local.json']);
        } else {
            $this->step($job, ['composer', 'install', '--no-interaction', '--no-progress']);
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
            $notes['containers'] = __('This brings containers of its own. On the server, run docker compose up -d to start them.');
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

        $result = Process::path(base_path())->env($env)->timeout(1800)->run($command);
        $output = $result->output().$result->errorOutput();
        $job->addToLog($output);

        if (! $result->successful()) {
            throw new RuntimeException(__('This step failed, so the rest didn\'t run.'));
        }

        return $output;
    }

    /**
     * Makes sure web/composer.local.json exists (a district's modules go there, not in Core's
     * composer.json), and lists a module's GitHub repository if it isn't on Packagist.
     */
    private function localComposer(?string $repository): void
    {
        $path = config('tornado.updater.composer_local') ?? base_path('composer.local.json');
        $local = is_file($path) ? json_decode(file_get_contents($path), true) : [
            'require' => new \stdClass,
            'extra' => ['merge-plugin' => ['include' => ['composer.json'], 'merge-dev' => true, 'merge-extra' => true, 'merge-scripts' => true]],
            'config' => ['allow-plugins' => ['wikimedia/composer-merge-plugin' => true, 'pestphp/pest-plugin' => true, 'php-http/discovery' => true]],
        ];

        if ($repository && ! in_array($repository, array_column($local['repositories'] ?? [], 'url'), true)) {
            $local['repositories'][] = ['type' => 'vcs', 'url' => $repository];
        }

        file_put_contents($path, json_encode($local, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
}
