<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarnessApiTest extends TestCase
{
    use RefreshDatabase;

    private function connect(string $token = 'test-harness-token'): array
    {
        Setting::put('harness.token_hash', hash('sha256', $token));

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_the_harness_needs_its_token(): void
    {
        $this->connect();

        $this->postJson('/api/harness/hello')->assertUnauthorized();
        $this->postJson('/api/harness/hello', [], ['Authorization' => 'Bearer wrong'])->assertUnauthorized();
    }

    public function test_nothing_is_accepted_before_a_token_exists(): void
    {
        $this->postJson('/api/harness/hello', [], ['Authorization' => 'Bearer anything'])->assertUnauthorized();
    }

    public function test_hello_records_when_the_harness_was_last_seen(): void
    {
        $this->postJson('/api/harness/hello', [], $this->connect())->assertOk();

        $this->assertNotNull(Setting::get('harness.last_seen_at'));
    }

    public function test_the_harness_gets_its_claude_login(): void
    {
        $headers = $this->connect();
        $this->getJson('/api/harness/claude', $headers)->assertNotFound();

        Setting::put('harness.claude_credential_type', 'api_key');
        Setting::putSecret('harness.claude_credential', 'sk-ant-api03-test');

        $this->getJson('/api/harness/claude', $headers)
            ->assertOk()
            ->assertExactJson(['type' => 'api_key', 'credential' => 'sk-ant-api03-test']);
    }
}
