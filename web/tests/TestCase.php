<?php

namespace Tests;

use App\Models\Setting;
use App\Support\ConnectionCheck;
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

        // Tests never call Google or Anthropic: settings pass the connection check unless a test says otherwise.
        $this->app->instance(ConnectionCheck::class, new class extends ConnectionCheck
        {
            public function google(string $clientId, string $clientSecret, string $redirectUri): ?string
            {
                return null;
            }

            public function claude(string $type, string $credential): ?string
            {
                return null;
            }
        });

        if ($this->setupComplete && isset($this->app['db'])) {
            Setting::put('setup.completed_at', now()->toIso8601String());
        }
    }
}
