<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as ResetNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('statuses')]
    public function test_password_broker_flow_preserves_status_and_does_not_login(string $status): void
    {
        Notification::fake();
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create(['status' => $status]);
        $oldRememberToken = $user->remember_token;
        Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('sendResetLink')->assertHasNoErrors();
        $notification = Notification::sent($user, ResetNotification::class)->sole();
        $component = Livewire::test(ResetPassword::class, ['token' => $notification->token])
            ->set('email', $user->email)->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password');
        $component->call('resetPassword')->assertHasNoErrors()->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));
        $this->assertSame($status, $user->status);
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertGuest();
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $component->set('password', 'another-password')->set('password_confirmation', 'another-password')
            ->call('resetPassword')->assertHasErrors('email');
    }

    public static function statuses(): array
    {
        return ['active' => ['active'], 'suspended' => ['suspended'], 'closed' => ['closed']];
    }

    public function test_invalid_reset_token_does_not_change_password(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;
        Livewire::test(ResetPassword::class, ['token' => 'invalid'])
            ->set('email', $user->email)->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')->call('resetPassword')->assertHasErrors('email');
        $this->assertSame($hash, $user->refresh()->password);
    }

    public function test_forgot_password_does_not_disclose_missing_accounts(): void
    {
        Notification::fake();
        Livewire::test(ForgotPassword::class)->set('email', 'missing@example.com')->call('sendResetLink')
            ->assertHasNoErrors()->assertSee('If an account matches that email, a password reset link will be sent.');
        Notification::assertNothingSent();
    }
}
