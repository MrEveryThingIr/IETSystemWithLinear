<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_authenticates_active_account_and_regenerates_session(): void
    {
        $user = User::factory()->create();
        session()->start();
        $sessionId = session()->getId();
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')
            ->set('remember', true)->call('login')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotEmpty($user->refresh()->getRememberToken());
    }

    #[DataProvider('inactiveStatuses')]
    public function test_inactive_account_cannot_login(string $status): void
    {
        $user = User::factory()->create(['status' => $status]);
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')
            ->call('login')->assertHasErrors('email');
        $this->assertGuest();
    }

    public static function inactiveStatuses(): array
    {
        return ['suspended' => ['suspended'], 'closed' => ['closed']];
    }

    public function test_login_is_rate_limited_even_with_correct_password_after_failures(): void
    {
        $user = User::factory()->create();
        $component = Livewire::test(Login::class)->set('email', $user->email)->set('password', 'incorrect');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->call('login')->assertHasErrors('email');
        }
        $component->set('password', 'password')->call('login')
            ->assertSee('Too many login attempts. Please try again in a minute.');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $component->call('login')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('inactiveStatuses')]
    public function test_existing_session_loses_access_after_status_change(string $status): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private' => 'value']);
        $user->status = $status;
        $user->save();
        $this->get('/dashboard')->assertRedirect(route('login'))->assertSessionMissing('private');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_is_available_to_inactive_unverified_users(): void
    {
        $user = User::factory()->unverified()->create(['status' => 'closed']);
        $this->actingAs($user)->withSession(['private' => 'value', '_token' => 'old-token']);
        $this->post('/logout')->assertRedirect(route('login'))->assertSessionMissing('private');
        $this->assertGuest();
        $this->assertNotSame('old-token', session()->token());
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_dashboard_requires_verified_email(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_active_verified_account_can_view_dashboard(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('Your email is verified.');
    }

    public function test_guest_pages_render_and_authenticated_users_are_redirected(): void
    {
        $this->withoutVite();
        foreach (['/login', '/forgot-password', '/reset-password/example-token'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/register')->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_login_through_livewire_http_endpoint_creates_a_protected_session(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $page = $this->get('/login')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => html_entity_decode($matches[1], ENT_QUOTES),
                'updates' => ['email' => $user->email, 'password' => 'password'],
                'calls' => [['method' => 'login', 'params' => [], 'path' => '']],
            ]],
        ], ['X-Livewire' => 'true'])->assertOk()
            ->assertJsonPath('components.0.effects.redirect', route('dashboard'));
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
