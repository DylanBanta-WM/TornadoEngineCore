<?php

namespace App\Harness;

use Illuminate\Database\Eloquent\Model;

class HarnessTaskEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['harness_task_id', 'event'];

    protected function casts(): array
    {
        return ['event' => 'array'];
    }
}
