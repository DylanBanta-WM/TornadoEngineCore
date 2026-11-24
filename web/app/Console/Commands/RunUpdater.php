<?php

namespace App\Console\Commands;

use App\Harness\HarnessTask;
use App\Models\Setting;
use App\Updates\DistrictBranch;
use App\Updates\UpdateJob;
use App\Updates\Updater;
use Illuminate\Console\Command;

/**
 * The updater service: works through the installs and updates admins queue, one at a time.
 * It stops after each job and Docker starts it again, so the next job runs the newest code.
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
        $recorded = 0;
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

                if ($this->option('once')) {
                    continue;
                }

                // Stop, so Docker starts the updater again with any code the job just installed.
                return self::SUCCESS;
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            // Changes made to Core's files (by Chat, or by hand) become commits on the district branch, about once
            // a minute, and never while Chat is working (so a half-done change isn't one).
            if (time() - $recorded >= 60 && ! HarnessTask::where('status', 'running')->exists()) {
                $recorded = time();
                try {
                    $note = Setting::get('district.note');
                    $district = app(DistrictBranch::class);
                    if ($district->record($note ?: __('Changes made on this server'))) {
                        Setting::put('district.note', null);
                    }
                    $district->remember();
                } catch (\Throwable $e) {
                    report($e);
                }
            }
            sleep(3);
        }
    }
}
