<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public static function adminPages(): array
    {
        return [
            ['/admin'], ['/admin/conversations'], ['/admin/bot-settings'], ['/admin/meta-accounts'],
            ['/admin/meta-accounts/create'], ['/admin/meta-accounts/connect'], ['/admin/webhook-events'], ['/admin/api/conversations'],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_guest_is_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    #[DataProvider('adminPages')]
    public function test_non_admin_gets_403(string $uri): void
    {
        $this->actingAs(User::factory()->create())->get($uri)->assertForbidden();
    }

    public function test_guest_api_json_request_gets_401(): void
    {
        $this->getJson('/admin/api/conversations')->assertUnauthorized();
    }

    public function test_root_redirects_to_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_admin_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $user->forceFill(['is_admin' => true])->save();

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->from('/login')->post('/login', ['email' => 'admin@example.com', 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        RateLimiter::clear('login:admin@example.com|127.0.0.1');
        User::factory()->create(['email' => 'admin@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();

        // A different email from the same IP is not locked out.
        User::factory()->create(['email' => 'other@example.com']);
        $this->post('/login', ['email' => 'other@example.com', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_logged_in_user_visiting_login_is_redirected(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect();
    }
}
