<?php

namespace App\Updates;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An install or update an admin asked for. The updater service runs them one at a time.
 *
 *     UpdateJob::queue('module', '0.2.0', 'tornado-engine/gam-runner');   // install or update a module
 *     UpdateJob::queue('core', '0.7.0');                                  // update Core
 *     UpdateJob::queue('module', 'dev', 'district/notes', 'modules/notes');  // install a module from web/modules
 */
class UpdateJob extends Model
{
    protected $fillable = ['kind', 'package', 'version', 'repository', 'status', 'log', 'notes', 'user_id', 'finished_at'];

    public static function queue(string $kind, string $version, ?string $package = null, ?string $repository = null): self
    {
        return self::create([
            'kind' => $kind,
            'package' => $package,
            'version' => ltrim($version, 'v'),
            'repository' => $repository,
            'user_id' => auth()->id(),
        ]);
    }

    /** Whether an install or update is queued or running. */
    public static function busy(): bool
    {
        return self::whereIn('status', ['queued', 'running'])->exists();
    }

    /** The queued or running job for this package (or Core, with null), if any. */
    public static function pendingFor(?string $package): ?self
    {
        return self::whereIn('status', ['queued', 'running'])
            ->where('kind', $package ? 'module' : 'core')
            ->when($package, fn ($query) => $query->where('package', $package))
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return $this->kind === 'core'
            ? __('Tornado Engine Core :version', ['version' => $this->version])
            : __(':package :version', ['package' => $this->package, 'version' => $this->version]);
    }

    public function addToLog(string $text): void
    {
        $this->log = mb_substr(($this->log ?? '').$text, -200_000);
        $this->save();
    }

    protected function casts(): array
    {
        return ['notes' => 'array', 'finished_at' => 'datetime'];
    }
}
