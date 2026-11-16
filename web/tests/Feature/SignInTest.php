<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\ConnectionCheck;
use App\Support\PasswordSignIn;
use App\Support\Setup;
use App\Support\SignIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;

    protected bool $setupComplete = false;

    protected function setUp(): void
    {
        parent::setUp();
        // Reach the site over its saved https address, as setup needs.
        URL::forceRootUrl('https://te.school.test');
        URL::forceScheme('https');
        Setting::put('site.url', 'https://te.school.test');
    }

    /** A site whose setup is done, signing in with the given methods. */
    private function site(array $methods): void
    {
        SignIn::save($methods);
        Setting::put('setup.completed_at', now()->toIso8601String());
    }

    public function test_setup_can_use_website_passwords_only(): void
    {
        $this->post('/setup/signin', ['methods' => []])->assertSessionHasErrors('methods');
        $this->post('/setup/signin', ['methods' => ['password']])->assertRedirect('/setup');
        $this->get('/setup');
        $this->assertSame('admin', Setup::step(), 'no Google or Microsoft steps');
        $this->assertSame(['address', 'signin', 'admin', 'harness', 'claude'], Setup::steps());

        $this->get('/setup')->assertSee('Create account')->assertDontSee('Sign in with Google');
        $this->post('/setup/admin', ['name' => 'Sam Admin', 'email' => 'Sam@school.test', 'password' => 'a-good-password', 'password_confirmation' => 'a-good-password'])
            ->assertRedirect('/setup');

        $admin = User::sole();
        $this->assertTrue($admin->is_admin);
        $this->assertSame('sam@school.test', $admin->email);
        $this->assertAuthenticatedAs($admin);
        $this->get('/setup')->assertOk();
        $this->assertSame('harness', Setup::step());
    }

    public function test_setup_offers_microsoft_and_checks_its_keys(): void
    {
        $this->post('/setup/signin', ['methods' => ['microsoft', 'password']]);
        $this->get('/setup');
        $this->assertSame('microsoft', Setup::step());
        $this->get('/setup')->assertSee('Microsoft Entra admin center')->assertSee('/auth/microsoft/callback');

        $this->mock(ConnectionCheck::class)->shouldReceive('microsoft')->andReturn('Microsoft didn\'t accept this client secret (use the secret\'s Value, not its ID).', null);
        $keys = ['tenant_id' => 'contoso.onmicrosoft.com', 'client_id' => 'app-id', 'client_secret' => 'secret'];
        $this->from('/setup')->post('/setup/microsoft', $keys)->assertSessionHasErrors('microsoft_keys');
        $this->assertNull(Setting::get('microsoft.client_id'));

        $this->post('/setup/microsoft', $keys)->assertRedirect('/setup');
        $this->assertSame('secret', Setting::getSecret('microsoft.client_secret'));
        $this->get('/setup')->assertSee('Sign in with Microsoft')->assertSee('Create account');
    }

    public function test_microsoft_keys_are_checked_against_microsoft(): void
    {
        Http::fake(['login.microsoftonline.com/good/*' => Http::response(['error' => 'invalid_grant', 'error_codes' => [9002313]], 400),
            'login.microsoftonline.com/bad-secret/*' => Http::response(['error' => 'invalid_client', 'error_codes' => [7000215]], 401),
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_request', 'error_codes' => [90002]], 400)]);
        $check = app(ConnectionCheck::class);

        $this->assertNull($check->microsoft('good', 'id', 'secret', 'https://te.test/auth/microsoft/callback'));
        $this->assertStringContainsString('client secret', $check->microsoft('bad-secret', 'id', 'secret', 'x'));
        $this->assertStringContainsString('tenant ID', $check->microsoft('nobody', 'id', 'secret', 'x'));
    }

    public function test_people_sign_in_with_a_website_password(): void
    {
        $this->site(['password']);
        $user = User::factory()->create(['email' => 'kim@school.test', 'password' => Hash::make('right-password')]);

        $this->get('/login')->assertSee('Password')->assertDontSee('Sign in with Google');
        $this->post('/login', ['email' => 'kim@school.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'KIM@school.test', 'password' => 'right-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_sign_in_is_off_unless_chosen(): void
    {
        $this->site(['google']);
        User::factory()->create(['email' => 'kim@school.test', 'password' => Hash::make('right-password')]);

        $this->post('/login', ['email' => 'kim@school.test', 'password' => 'right-password'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_too_many_wrong_passwords_are_slowed_down(): void
    {
        $this->site(['password']);
        User::factory()->create(['email' => 'kim@school.test', 'password' => Hash::make('right-password')]);

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'kim@school.test', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'kim@school.test', 'password' => 'right-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_people_must_change_a_password_set_for_them(): void
    {
        $this->site(['password']);
        $user = User::factory()->create(['password' => Hash::make(SignIn::DEFAULT_PASSWORD), 'must_change_password' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/password');
        $this->actingAs($user)->get('/password')->assertOk()->assertDontSee('Current password');
        $this->actingAs($user)->put('/password', ['password' => SignIn::DEFAULT_PASSWORD, 'password_confirmation' => SignIn::DEFAULT_PASSWORD])->assertSessionHasErrors('password');
        $this->actingAs($user)->put('/password', ['password' => 'my-own-secret', 'password_confirmation' => 'my-own-secret'])->assertRedirect('/dashboard');

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('my-own-secret', $user->fresh()->password));
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Change password');

        // Later changes need the current password.
        $this->actingAs($user)->put('/password', ['password' => 'another-secret', 'password_confirmation' => 'another-secret'])->assertSessionHasErrors('current_password');
    }

    public function test_admins_choose_sign_in_methods_without_locking_themselves_out(): void
    {
        $this->site(['google', 'password']);
        $admin = User::factory()->create(['is_admin' => true, 'password' => Hash::make('secret-pass')]);

        $this->actingAs($admin)->post('/administration/signin', ['methods' => ['google']])->assertSessionHasErrors('methods');
        $this->actingAs($admin)->post('/administration/signin', ['methods' => ['microsoft', 'password']])->assertRedirect('/administration');
        $this->assertSame(['microsoft', 'password'], SignIn::methods());
        $this->actingAs($admin)->get('/administration')->assertSee('Microsoft sign-in')->assertSee('/auth/microsoft/callback');
    }

    public function test_sites_set_up_before_sign_in_methods_keep_google(): void
    {
        Setting::put('google.client_id', 'id');
        $this->assertSame(['google'], SignIn::methods());
    }

    public function test_modules_can_check_passwords_too(): void
    {
        $this->site(['google']);
        $kim = User::factory()->create(['email' => 'kim@school.test']);
        $on = true;
        app(PasswordSignIn::class)->add('test', fn ($login, $password) => $login === 'kim' && $password === 'directory-pass' ? $kim : null, fn () => $on);

        $this->get('/login')->assertSee('Email or username');
        $this->post('/login', ['email' => 'kim', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'kim', 'password' => 'directory-pass'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($kim);
    }
}
