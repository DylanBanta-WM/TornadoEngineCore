<?php

namespace App\Updates;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An install, update or uninstall an admin asked for. The updater service runs them one at a time.
 *
 *     UpdateJob::queue('module', '0.2.0', 'tornado-engine/gam-runner');   // install or update a module
 *     UpdateJob::queue('core', '0.7.0');                                  // update Core
 *     UpdateJob::queue('module', 'dev', 'district/notes', 'modules/notes');  // install a module from web/modules
 *     UpdateJob::remove('district/notes', deleteData: true);                 // uninstall a module (and roll back its migrations)
 */
class UpdateJob extends Model
{
    protected $fillable = ['kind', 'package', 'version', 'repository', 'install', 'delete_data', 'anyway', 'status', 'progress', 'log', 'notes', 'user_id', 'finished_at', 'opened_at'];

    public static function queue(string $kind, string $version, ?string $package = null, ?string $repository = null, bool $install = false): self
    {
        // The status is set here, not left to the column's default, so the returned job has it.
        return self::create([
            'kind' => $kind,
            'status' => 'queued',
            'package' => $package,
            'version' => ltrim($version, 'v'),
            'repository' => $repository,
            'install' => $install,
            'user_id' => auth()->id(),
        ]);
    }

    /** Uninstalls a module. A repository (vcs URL) is taken out of composer.local.json too. */
    public static function remove(string $package, bool $deleteData = false, ?string $repository = null): self
    {
        return self::create([
            'kind' => 'remove',
            'status' => 'queued',
            'package' => $package,
            'version' => '',
            'repository' => $repository,
            'delete_data' => $deleteData,
            'user_id' => auth()->id(),
        ]);
    }

    /** Whether an install or update is queued or running. */
    public static function busy(): bool
    {
        return self::whereIn('status', ['queued', 'running'])->exists();
    }

    /** The percentage of the queued or running jobs together, or null when none is. */
    public static function activeProgress(): ?int
    {
        $jobs = self::whereIn('status', ['queued', 'running'])->get(['status', 'progress']);

        return $jobs->isEmpty() ? null : (int) round($jobs->avg(fn ($job) => $job->status === 'queued' ? 0 : $job->progress));
    }

    /** The queued or running job for this package (or Core, with null), if any. */
    public static function pendingFor(?string $package): ?self
    {
        return self::whereIn('status', ['queued', 'running'])
            ->whereIn('kind', $package ? ['module', 'remove'] : ['core'])
            ->when($package, fn ($query) => $query->where('package', $package))
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->kind) {
            'core' => __('Tornado Engine Core :version', ['version' => $this->version]),
            'undo' => __('Undo Chat\'s changes'),
            'customize' => __('Customize :package', ['package' => $this->package]),
            'official' => __(':package back to its official version', ['package' => $this->package]),
            'remove' => $this->delete_data
                ? __('Uninstall :package and delete its data', ['package' => $this->package])
                : __('Uninstall :package', ['package' => $this->package]),
            default => __(':package :version', ['package' => $this->package, 'version' => $this->version]),
        };
    }

    /** Whether it's queued or running (so a progress bar belongs to it). */
    public function active(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }

    public function addToLog(string $text): void
    {
        $this->log = mb_substr(($this->log ?? '').$text, -200_000);
        $this->save();
    }

    protected function casts(): array
    {
        return ['notes' => 'array', 'install' => 'boolean', 'delete_data' => 'boolean', 'anyway' => 'boolean', 'finished_at' => 'datetime', 'opened_at' => 'datetime'];
    }
}
