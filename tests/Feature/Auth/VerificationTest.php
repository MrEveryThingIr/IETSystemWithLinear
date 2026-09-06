<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\VerifyEmailNotice;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
    }

    public function test_notice_requires_auth_and_allows_active_unverified_accounts(): void
    {
        $this->withoutVite();
        $this->get('/email/verify')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get('/email/verify')
            ->assertSee('Verify your email');
    }

    public function test_notice_rejects_inactive_accounts(): void
    {
        $this->actingAs(User::factory()->unverified()->create(['status' => 'suspended']))
            ->get('/email/verify')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_signed_callback_verifies_account_and_dispatches_event_once(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);
        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        $this->get($url)->assertRedirect(route('dashboard'));
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get($this->verificationUrl($user).'tampered')->assertForbidden();
        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_expired_link_and_another_users_link_are_rejected(): void
    {
        $this->freezeTime();
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $this->actingAs($user)->get($this->verificationUrl($other))->assertForbidden();
        $url = $this->verificationUrl($user);
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_resend_is_throttled_and_available_again_after_delay(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $component = Livewire::actingAs($user)->test(VerifyEmailNotice::class);
        $component->call('resend')->assertHasNoErrors();
        $component->call('resend')->assertHasErrors('resend');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->travel(61)->seconds();
        $component->call('resend')->assertHasNoErrors();
        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    }

    public function test_verified_account_does_not_receive_another_verification_email(): void
    {
        Notification::fake();
        Livewire::actingAs(User::factory()->create())->test(VerifyEmailNotice::class)
            ->call('resend')->assertRedirect(route('dashboard'));
        Notification::assertNothingSent();
    }

    public function test_livewire_update_rechecks_account_status_after_page_load(): void
    {
        $this->withoutVite();
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $page = $this->actingAs($user)->get('/email/verify')->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
        User::whereKey($user->id)->update(['status' => 'suspended']);

        $this->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot, 'updates' => [],
                'calls' => [['method' => 'resend', 'params' => [], 'path' => '']],
            ]],
        ], ['X-Livewire' => 'true'])->assertForbidden();

        $this->assertGuest();
        Notification::assertNothingSent();
    }
}
