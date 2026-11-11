<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Most tests run against a site that has finished first-time setup.
     */
    protected bool $setupComplete = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->setupComplete && isset($this->app['db'])) {
            Setting::put('setup.completed_at', now()->toIso8601String());
        }
    }
}
