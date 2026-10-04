<?php

namespace Tests\Feature;

use App\Livewire\Profile\AccountEmail;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileAccountEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_change_account_email_with_current_password(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        Livewire::actingAs($user)
            ->test(AccountEmail::class)
            ->call('openEditor')
            ->set('email', 'NEW@example.com')
            ->set('currentPassword', 'password')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.notice'));

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_change_rejects_wrong_password_and_duplicate_email(): void
    {
        Notification::fake();

        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'old@example.com']);

        Livewire::actingAs($user)
            ->test(AccountEmail::class)
            ->call('openEditor')
            ->set('email', 'new@example.com')
            ->set('currentPassword', 'wrong-password')
            ->call('save')
            ->assertHasErrors('currentPassword');

        $this->assertSame('old@example.com', $user->fresh()->email);

        Livewire::actingAs($user)
            ->test(AccountEmail::class)
            ->call('openEditor')
            ->set('email', $existing->email)
            ->set('currentPassword', 'password')
            ->call('save')
            ->assertHasErrors('email');

        $this->assertSame('old@example.com', $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_same_email_does_not_revoke_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'same@example.com']);
        $verifiedAt = $user->email_verified_at;

        Livewire::actingAs($user)
            ->test(AccountEmail::class)
            ->call('openEditor')
            ->set('email', 'same@example.com')
            ->set('currentPassword', 'password')
            ->call('save')
            ->assertHasErrors('email');

        $user->refresh();

        $this->assertSame('same@example.com', $user->email);
        $this->assertEquals($verifiedAt, $user->email_verified_at);
        Notification::assertNothingSent();
    }
}
