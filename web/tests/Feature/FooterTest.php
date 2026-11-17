<?php

namespace Tests\Feature;

use App\Layout\Footer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class FooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_can_add_to_the_footer_in_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertDontSee('<footer>', false);

        View::addNamespace('zz', storage_path('framework/testing/zz-footer'));
        @mkdir(storage_path('framework/testing/zz-footer'), 0777, true);
        file_put_contents(storage_path('framework/testing/zz-footer/one.blade.php'), '<p>Second</p>');
        file_put_contents(storage_path('framework/testing/zz-footer/two.blade.php'), '<p>First</p>');
        app(Footer::class)->add('zz::one', order: 20)->add('zz::two', order: 10);

        $this->actingAs($user)->get('/profile')->assertSeeInOrder(['<footer>', 'First', 'Second'], false);
    }
}
