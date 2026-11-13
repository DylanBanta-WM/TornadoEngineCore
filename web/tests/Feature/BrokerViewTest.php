<?php

namespace Tests\Feature;

use App\Harness\ClaudeUser;
use App\Harness\HarnessMcp;
use App\Harness\HarnessView;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BrokerViewTest extends TestCase
{
    use RefreshDatabase;

    private function asHarness(): static
    {
        return $this->withServerVariables(['TORNADO_VIEW' => 'broker']);
    }

    private function filterReplaces(string $real, string $pseudonym, bool $ready = true): void
    {
        app(HarnessView::class)->filter(fn (string $text) => str_replace($real, $pseudonym, $text), ready: fn () => $ready);
    }

    public function test_without_a_broker_the_harness_gets_no_pages(): void
    {
        $this->asHarness()->get('/dashboard')->assertStatus(503)->assertSee('Broker view isn');
    }

    public function test_a_broker_that_isnt_ready_blocks_pages_too(): void
    {
        $this->filterReplaces('x', 'y', ready: false);

        $this->asHarness()->get('/dashboard')->assertStatus(503);
    }

    public function test_pages_are_filtered_and_shown_as_the_claude_user(): void
    {
        Route::middleware(['web', 'auth'])->get('/test-people', fn () => 'Ann Example is in grade 7. Viewer: '.auth()->user()->email);
        $this->filterReplaces('Ann Example', '[name s-0123456789]');

        $this->asHarness()->get('/test-people')
            ->assertOk()
            ->assertSee('[name s-0123456789] is in grade 7.')
            ->assertDontSee('Ann Example')
            ->assertSee(ClaudeUser::EMAIL);
    }

    public function test_json_is_filtered_and_files_are_refused(): void
    {
        Route::middleware('web')->get('/test-json', fn () => ['student' => 'Ann Example']);
        Route::middleware('web')->get('/test-photo', fn () => response('PNG...', 200, ['Content-Type' => 'image/png']));
        Route::middleware('web')->get('/test-download', fn () => response()->streamDownload(fn () => print ('Ann Example'), 'list.csv'));
        $this->filterReplaces('Ann Example', '[name s-0123456789]');

        $this->asHarness()->get('/test-json')->assertExactJson(['student' => '[name s-0123456789]']);
        $this->asHarness()->get('/test-photo')->assertForbidden();
        $this->asHarness()->get('/test-download')->assertForbidden();
    }

    public function test_the_harness_api_still_works_in_broker_view(): void
    {
        Setting::put('harness.token_hash', hash('sha256', 'harness-token'));
        app(HarnessMcp::class)->server('tornado-broker', 'mcp/broker');

        $this->asHarness()->getJson('/api/harness/mcp', ['Authorization' => 'Bearer harness-token'])
            ->assertExactJson(['servers' => ['tornado-broker' => '/mcp/broker']]);
    }

    public function test_people_using_the_website_see_real_pages(): void
    {
        Route::middleware(['web', 'auth'])->get('/test-people', fn () => 'Ann Example is in grade 7.');
        $this->filterReplaces('Ann Example', '[name s-0123456789]');

        $this->actingAs(User::factory()->create())->get('/test-people')->assertSee('Ann Example is in grade 7.');
    }
}
