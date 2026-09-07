<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\RegisterUser;
use App\Livewire\Auth\Register;
use App\Models\Actor;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use DatabaseMigrations;

    private function input(): array
    {
        return ['username' => 'new_user', 'email' => 'new@example.com',
            'password' => 'secure-password', 'password_confirmation' => 'secure-password'];
    }

    public function test_registration_creates_active_unverified_account_and_logs_in(): void
    {
        Notification::fake();
        Livewire::test(Register::class)->set($this->input())->call('register')
            ->assertHasNoErrors()->assertRedirect(route('verification.notice'));

        $user = User::sole();
        $this->assertDatabaseCount('actors', 1);
        $this->assertTrue($user->actor->is(Actor::sole()));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('active', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secure-password', $user->password));
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    #[DataProvider('identifiers')]
    public function test_registration_rejects_duplicate_identifiers(string $field): void
    {
        User::factory()->create([$field => $this->input()[$field]]);
        Livewire::test(Register::class)->set($this->input())->call('register')
            ->assertHasErrors([$field => 'unique']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public static function identifiers(): array
    {
        return ['username' => ['username'], 'email' => ['email']];
    }

    public function test_registration_rejects_unconfirmed_password(): void
    {
        Livewire::test(Register::class)->set($this->input())->set('password_confirmation', 'different')
            ->call('register')->assertHasErrors(['password' => 'confirmed']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('actors', 0);
    }

    public function test_action_ignores_privileged_input(): void
    {
        Notification::fake();
        $user = app(RegisterUser::class)->handle($this->input() + ['status' => 'closed', 'email_verified_at' => now()]);
        $this->assertSame('active', $user->refresh()->status);
        $this->assertNull($user->email_verified_at);
    }

    public function test_registered_is_dispatched_only_after_outer_commit(): void
    {
        Event::fake([Registered::class]);
        DB::beginTransaction();
        $user = app(RegisterUser::class)->handle($this->input());
        Event::assertNotDispatched(Registered::class);
        $this->assertDatabaseCount('actors', 1);
        DB::commit();
        $this->assertTrue($user->actor->is(Actor::sole()));

        Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
        Event::assertDispatchedTimes(Registered::class, 1);
    }

    public function test_outer_rollback_discards_user_and_registered_event(): void
    {
        Event::fake([Registered::class]);
        DB::beginTransaction();
        app(RegisterUser::class)->handle($this->input());
        DB::rollBack();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('actors', 0);
        Event::assertNotDispatched(Registered::class);
    }

    public function test_creation_failure_rolls_back_the_action_transaction(): void
    {
        Event::fake([Registered::class]);
        Event::listen('eloquent.created: '.User::class, function (): void {
            throw new RuntimeException('Provisioning failure');
        });

        try {
            app(RegisterUser::class)->handle($this->input());
            $this->fail('Expected creation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Provisioning failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('actors', 0);
        $this->assertSame(0, DB::transactionLevel());
        Event::assertNotDispatched(Registered::class);
    }

    public function test_actor_creation_failure_rolls_back_user_and_suppresses_registered(): void
    {
        Event::fake([Registered::class]);
        Event::listen('eloquent.created: '.Actor::class, function (): void {
            throw new RuntimeException('Actor provisioning failed');
        });

        try {
            app(RegisterUser::class)->handle($this->input());
            $this->fail('Expected Actor creation failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Actor provisioning failed', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('actors', 0);
        Event::assertNotDispatched(Registered::class);
    }
}
