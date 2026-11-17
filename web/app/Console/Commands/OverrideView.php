<?php

namespace App\Console\Commands;

use App\Overrides\Overrides;
use Illuminate\Console\Command;
use Throwable;

class OverrideView extends Command
{
    protected $signature = 'tornado:override {view? : For example chat::page, or core::layouts.app} {--list : List the views that can be overridden} {--styles : Rebuild the styles after changing local views or local.css}';

    protected $description = 'Copy a view of Core or a module into web/local/views to change it there (updates never touch web/local)';

    public function handle(Overrides $overrides): int
    {
        if ($this->option('styles')) {
            $overrides->rebuildStyles();
            $this->info('The styles are being rebuilt.');

            return self::SUCCESS;
        }
        if ($this->option('list') || ! $this->argument('view')) {
            $this->line(implode("\n", $overrides->views()));

            return self::SUCCESS;
        }

        try {
            $overrides->create($this->argument('view'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        [$namespace, $view] = explode('::', $this->argument('view'), 2);
        $this->info('Edit web/local/views/'.$namespace.'/'.str_replace('.', '/', $view).'.blade.php');

        return self::SUCCESS;
    }
}
