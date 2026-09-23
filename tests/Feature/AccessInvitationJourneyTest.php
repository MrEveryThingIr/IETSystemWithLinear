<?php

namespace Tests\Feature;

use App\Actions\Access\IssueAccessInvitation;
use App\Actions\Groups\CreateGroup;
use App\Livewire\Auth\AccessRegister;
use App\Livewire\Groups\Invitations as GroupInvitations;
use App\Livewire\Platform\AccessInvitations;
use App\Models\AccessInvitation;
use App\Models\Actor;
use App\Models\GroupInvitation;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class AccessInvitationJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_issue_a_hashed_standalone_access_invitation(): void
    {
        $administrator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($administrator->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);

        $invitation = app(IssueAccessInvitation::class)->execute(
            $administrator->user,
            'new.person@example.com',
            1,
            14,
        );

        $token = $invitation->plainTextToken();

        $this->assertNotNull($token);
        $this->assertDatabaseHas('access_invitations', [
            'id' => $invitation->id,
            'token' => AccessInvitation::hashToken($token),
            'email' => 'new.person@example.com',
        ]);
        $this->assertDatabaseMissing('access_invitations', ['token' => $token]);

        $this->withoutVite()
            ->get(route('access-invitations.show', $token))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('Welcome to IET')
            ->assertSee('n•••••••••@example.com')
            ->assertSee(route('access-invitations.register', $token), false);
    }

    public function test_access_invited_registration_creates_identity_and_returns_to_getting_started_after_verification(): void
    {
        Notification::fake();

        $administrator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($administrator->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);

        $invitation = app(IssueAccessInvitation::class)->execute(
            $administrator->user,
            'invitee@example.com',
        );

        $token = $invitation->plainTextToken();
        $this->assertNotNull($token);

        Livewire::test(AccessRegister::class, ['token' => $token])
            ->set('username', 'intent_user')
            ->set('email', 'invitee@example.com')
            ->set('password', 'secure-password')
            ->set('password_confirmation', 'secure-password')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'invitee@example.com')->sole();

        $this->assertNotNull($user->actor);
        $this->assertDatabaseHas('access_invitation_acceptances', [
            'access_invitation_id' => $invitation->id,
            'user_id' => $user->id,
            'actor_id' => $user->actor->id,
        ]);
        $this->assertSame(1, $invitation->refresh()->uses_count);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($verificationUrl)->assertRedirect(route('getting-started'));
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_reserved_access_invitation_rejects_wrong_email_without_partial_registration(): void
    {
        Notification::fake();

        $administrator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($administrator->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);
        $invitation = app(IssueAccessInvitation::class)->execute(
            $administrator->user,
            'reserved@example.com',
        );

        Livewire::test(AccessRegister::class, ['token' => $invitation->plainTextToken()])
            ->set('username', 'wrong_person')
            ->set('email', 'other@example.com')
            ->set('password', 'secure-password')
            ->set('password_confirmation', 'secure-password')
            ->call('register')
            ->assertHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
        $this->assertDatabaseCount('access_invitation_acceptances', 0);
        $this->assertSame(0, $invitation->refresh()->uses_count);
        Notification::assertNothingSent();
    }

    public function test_access_invitation_manager_requires_platform_user_management_authority(): void
    {
        $ordinary = Actor::factory()->create();

        Livewire::actingAs($ordinary->user)
            ->test(AccessInvitations::class)
            ->assertForbidden();
    }

    public function test_group_invitation_creation_requires_an_existing_verified_account(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute(
            $owner,
            'Office collaborators',
            null,
        );

        Livewire::actingAs($owner->user)
            ->test(GroupInvitations::class, ['group' => $group])
            ->set('email', 'not-registered@example.com')
            ->call('create')
            ->assertHasErrors('email');

        $existing = Actor::factory()->create();

        Livewire::actingAs($owner->user)
            ->test(GroupInvitations::class, ['group' => $group])
            ->set('email', $existing->user->email)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('group_invitations', [
            'group_id' => $group->id,
            'email' => $existing->user->email,
            'max_uses' => 1,
        ]);

        $invitation = GroupInvitation::query()->where('group_id', $group->id)->sole();
        $this->assertSame(1, $invitation->max_uses);
    }
}
