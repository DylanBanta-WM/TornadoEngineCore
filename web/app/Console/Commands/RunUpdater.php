<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Updates\UpdateJob;
use App\Updates\Updater;
use Illuminate\Console\Command;

/**
 * The updater service: works through the installs and updates admins queue, one at a time.
 */
class RunUpdater extends Command
{
    protected $signature = 'tornado:updater {--once : Run what\'s queued, then stop}';

    protected $description = 'Install and update modules and Core when admins ask';

    public function handle(Updater $updater): int
    {
        // Anything still "running" was cut off when the updater last stopped.
        foreach (UpdateJob::where('status', 'running')->get() as $job) {
            $job->addToLog("\n".__('The updater stopped while this was running. Try again.')."\n");
            $job->update(['status' => 'failed', 'finished_at' => now()]);
        }

        $beat = 0;
        while (true) {
            if (time() - $beat >= 30) {
                Setting::put('updater.heartbeat', now()->toIso8601String());
                $beat = time();
            }

            $job = UpdateJob::where('status', 'queued')->orderBy('id')->first();
            if ($job) {
                $this->line(__('Running :job', ['job' => $job->label()]));
                $updater->run($job);
                $this->line(__('Finished: :status', ['status' => $job->status]));

                continue;
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }
            sleep(3);
        }
    }
}
