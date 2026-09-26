<?php

namespace Tests\Feature;

use App\Actions\Access\IssueAccessInvitation;
use App\Actions\Access\RevokeAccessInvitation;
use App\Actions\Auth\RegisterAccessInvitedUser;
use App\Actions\Groups\CreateGroup;
use App\Livewire\Auth\AccessRegister;
use App\Livewire\Groups\Invitations as GroupInvitations;
use App\Livewire\Platform\AccessInvitations;
use App\Models\AccessInvitation;
use App\Models\Actor;
use App\Models\GroupInvitation;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformCapability;
use App\PlatformRole;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Welcome to IET')
            ->assertSee('n•••••••••@example.com')
            ->assertSee(route('access-invitations.register', $token), false);
    }

    public function test_active_verified_superadmin_can_issue_and_revoke_access_invitation(): void
    {
        $administrator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($administrator->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);

        $this->assertSame('active', $administrator->user->status);
        $this->assertTrue($administrator->user->hasVerifiedEmail());
        $this->assertTrue(PlatformRole::Superadmin->grants(PlatformCapability::ManageAccessInvitations));
        $this->assertTrue($administrator->user->hasPlatformCapability(PlatformCapability::ManageAccessInvitations));

        $invitation = app(IssueAccessInvitation::class)->execute(
            $administrator->user,
            'revoke.me@example.com',
        );

        $revoked = app(RevokeAccessInvitation::class)->execute($administrator->user, $invitation);

        $this->assertNotNull($revoked->revoked_at);
        $this->assertNotNull($invitation->refresh()->revoked_at);
    }

    public function test_reserved_access_invitation_must_be_single_use_while_unreserved_link_may_be_multi_use(): void
    {
        $administrator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($administrator->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);

        $this->expectException(HttpException::class);
        app(IssueAccessInvitation::class)->execute(
            $administrator->user,
            'reserved@example.com',
            2,
        );
    }

    public function test_unreserved_access_invitation_may_be_multi_use(): void
    {
        $administrator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($administrator->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);

        $invitation = app(IssueAccessInvitation::class)->execute(
            $administrator->user,
            null,
            3,
        );

        $this->assertNull($invitation->email);
        $this->assertSame(3, $invitation->max_uses);
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

    public function test_consumed_reserved_access_invitation_cannot_register_a_second_account(): void
    {
        Notification::fake();

        $administrator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($administrator->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);

        $invitation = app(IssueAccessInvitation::class)->execute(
            $administrator->user,
            'single.use@example.com',
        );
        $token = $invitation->plainTextToken();
        $this->assertNotNull($token);

        Livewire::test(AccessRegister::class, ['token' => $token])
            ->set('username', 'single_use_owner')
            ->set('email', 'single.use@example.com')
            ->set('password', 'secure-password')
            ->set('password_confirmation', 'secure-password')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertSame(1, $invitation->refresh()->uses_count);

        $this->get(route('access-invitations.show', $token))->assertOk();

        $this->expectException(HttpException::class);
        app(RegisterAccessInvitedUser::class)->preview($token);
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

    public function test_ordinary_user_cannot_issue_or_revoke_access_invitation_or_open_manager(): void
    {
        $administrator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($administrator->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);
        $invitation = app(IssueAccessInvitation::class)->execute(
            $administrator->user,
            'protected@example.com',
        );

        $ordinary = Actor::factory()->create();

        try {
            app(IssueAccessInvitation::class)->execute($ordinary->user, 'blocked@example.com');
            $this->fail('Ordinary user unexpectedly issued an Access Invitation.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            app(RevokeAccessInvitation::class)->execute($ordinary->user, $invitation);
            $this->fail('Ordinary user unexpectedly revoked an Access Invitation.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Livewire::actingAs($ordinary->user)
            ->test(AccessInvitations::class)
            ->assertForbidden();

        $this->assertNull($invitation->refresh()->revoked_at);
    }

    public function test_group_creator_platform_grant_does_not_confer_access_invitation_authority(): void
    {
        $groupCreator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($groupCreator->user)->create([
            'role' => PlatformRole::GroupCreator,
        ]);

        $this->assertFalse(
            $groupCreator->user->hasPlatformCapability(PlatformCapability::ManageAccessInvitations),
        );

        try {
            app(IssueAccessInvitation::class)->execute($groupCreator->user, 'blocked.creator@example.com');
            $this->fail('GroupCreator unexpectedly issued an Access Invitation.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Livewire::actingAs($groupCreator->user)
            ->test(AccessInvitations::class)
            ->assertForbidden();
    }

    public function test_group_roles_and_permissions_do_not_confer_access_invitation_authority(): void
    {
        $groupOwner = Actor::factory()->create();

        app(CreateGroup::class)->execute(
            $groupOwner,
            'Platform capability isolation',
            null,
        );

        $this->assertFalse(
            $groupOwner->user->hasPlatformCapability(PlatformCapability::ManageAccessInvitations),
        );

        try {
            app(IssueAccessInvitation::class)->execute($groupOwner->user, 'blocked.owner@example.com');
            $this->fail('Group role unexpectedly conferred Access Invitation authority.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Livewire::actingAs($groupOwner->user)
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
