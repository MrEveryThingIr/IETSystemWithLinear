<?php

namespace Tests\Feature;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\RedeemGroupInvitation;
use App\Livewire\Admissions\Show as AdmissionShow;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Groups\Index as GroupIndex;
use App\Livewire\Groups\Show as GroupShow;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use App\Models\GroupInvitation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationJourneyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_invitation_secret_is_hashed_at_rest_and_target_email_is_masked_publicly(): void
    {
        $this->withoutVite();
        [, $invitation] = $this->invitation(email: 'private.person@example.com');
        $token = $invitation->token;

        $this->assertNotNull($token);
        $this->assertDatabaseHas('group_invitations', [
            'id' => $invitation->id,
            'token' => GroupInvitation::hashToken($token),
        ]);
        $this->assertDatabaseMissing('group_invitations', ['token' => $token]);

        $this->get(route('invitations.show', $token))
            ->assertOk()
            ->assertDontSee('private.person@example.com')
            ->assertSee('p•••••••••••••@example.com');
    }

    public function test_invitation_page_has_distinct_token_scoped_login_and_registration_paths(): void
    {
        $this->withoutVite();
        [, $invitation] = $this->invitation();

        $this->get(route('invitations.show', $invitation->token))
            ->assertOk()
            ->assertSee(route('invitations.login', $invitation->token), false)
            ->assertSee(route('invitations.register', $invitation->token), false)
            ->assertSee('New accounts can only be created through a valid group invitation.');

        $this->assertNotSame(
            route('invitations.login', $invitation->token),
            route('invitations.register', $invitation->token),
        );
    }

    public function test_registration_is_invitation_only_and_invalid_invites_are_rejected(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get(route('invitations.register', 'missing-token'))->assertNotFound();

        [, $expired] = $this->invitation(expiresAt: now()->subMinute());
        $this->get(route('invitations.register', $expired->token))->assertNotFound();
    }

    public function test_invited_registration_atomically_creates_an_admission_and_verification_returns_to_it(): void
    {
        Notification::fake();
        [, $invitation] = $this->invitation(email: 'invitee@example.com');

        Livewire::test(Register::class, ['token' => $invitation->token])
            ->set('username', 'invitee')
            ->set('email', 'invitee@example.com')
            ->set('password', 'secure-password')
            ->set('password_confirmation', 'secure-password')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'invitee@example.com')->sole();
        $admission = Admission::query()->where('candidate_actor_id', $user->actor->id)->sole();
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($verificationUrl)->assertRedirect(route('admissions.show', $admission));
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_reserved_email_mismatch_rolls_back_the_entire_registration(): void
    {
        Notification::fake();
        [, $invitation] = $this->invitation(email: 'reserved@example.com');

        Livewire::test(Register::class, ['token' => $invitation->token])
            ->set('username', 'wrong_person')
            ->set('email', 'other@example.com')
            ->set('password', 'secure-password')
            ->set('password_confirmation', 'secure-password')
            ->call('register')
            ->assertHasErrors('invitation');

        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
        $this->assertDatabaseCount('admissions', 0);
        $this->assertSame(0, $invitation->refresh()->uses_count);
        Notification::assertNothingSent();
    }

    public function test_existing_account_login_through_an_invitation_redirects_to_the_admission(): void
    {
        $candidate = Actor::factory()->create();
        [, $invitation] = $this->invitation();

        $component = Livewire::test(Login::class, ['token' => $invitation->token])
            ->set('email', $candidate->user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $admission = Admission::query()->where('candidate_actor_id', $candidate->id)->sole();
        $component->assertRedirect(route('admissions.show', $admission));
        $this->assertAuthenticatedAs($candidate->user);
    }

    public function test_targeted_invitation_rejects_a_different_account_before_authentication(): void
    {
        $candidate = Actor::factory()->create();
        [, $invitation] = $this->invitation(email: 'reserved@example.com');

        Livewire::test(Login::class, ['token' => $invitation->token])
            ->set('email', $candidate->user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('admissions', 0);
        $this->assertSame(0, $invitation->refresh()->uses_count);
    }

    public function test_unverified_user_returns_to_the_invitation_after_verification(): void
    {
        $candidate = Actor::factory()->create();
        $candidate->user->forceFill(['email_verified_at' => null])->save();
        [, $invitation] = $this->invitation();

        $this->actingAs($candidate->user)
            ->get(route('invitations.show', $invitation->token))
            ->assertOk()
            ->assertSee('Verify your email address before continuing.')
            ->assertSessionHas('url.intended', route('invitations.show', $invitation->token));
    }

    public function test_account_group_page_tracks_received_and_sent_invitations(): void
    {
        $this->withoutVite();
        [$owner, $invitation] = $this->invitation();
        $candidate = Actor::factory()->create();
        app(RedeemGroupInvitation::class)->execute($invitation->token, $candidate, $candidate->user->email);

        Livewire::actingAs($candidate->user)
            ->test(GroupIndex::class)
            ->assertSee('Invitations you received')
            ->assertSee($owner->user->username)
            ->assertSee($invitation->group->name);

        Livewire::actingAs($owner->user)
            ->test(GroupIndex::class)
            ->assertSee('People you invited')
            ->assertSee($candidate->user->username)
            ->assertSee($invitation->group->name);
    }

    public function test_submitted_message_is_visible_to_both_sides_and_the_owner_has_a_review_queue(): void
    {
        [$owner, $invitation] = $this->invitation();
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($invitation->token, $candidate, $candidate->user->email);

        Livewire::actingAs($candidate->user)
            ->test(AdmissionShow::class, ['admission' => $admission])
            ->set('note', 'I would like to share my daily learning notes with this group.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('I would like to share my daily learning notes with this group.');

        Livewire::actingAs($owner->user)
            ->test(GroupShow::class, ['group' => $invitation->group])
            ->assertSee('Admissions to review')
            ->assertSee($candidate->user->username)
            ->assertSee(route('admissions.show', $admission), false);

        Livewire::actingAs($owner->user)
            ->test(AdmissionShow::class, ['admission' => $admission->refresh()])
            ->assertSee('I would like to share my daily learning notes with this group.')
            ->assertSee('Submitted');
    }

    public function test_admission_shows_only_required_active_agreements_and_acceptance_is_idempotent(): void
    {
        [, $invitation] = $this->invitation();
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($invitation->token, $candidate, $candidate->user->email);
        $requiredAgreement = GroupAgreement::create(['group_id' => $invitation->group_id, 'name' => 'Required rules', 'required_for_admission' => true]);
        $requiredVersion = GroupAgreementVersion::create(['group_agreement_id' => $requiredAgreement->id, 'version' => 1, 'content' => 'Required terms', 'status' => 'active', 'effective_from' => now()->subMinute()]);
        $optionalAgreement = GroupAgreement::create(['group_id' => $invitation->group_id, 'name' => 'Optional pledge', 'required_for_admission' => false]);
        GroupAgreementVersion::create(['group_agreement_id' => $optionalAgreement->id, 'version' => 1, 'content' => 'Optional terms', 'status' => 'active', 'effective_from' => now()->subMinute()]);

        Livewire::actingAs($candidate->user)
            ->test(AdmissionShow::class, ['admission' => $admission])
            ->assertSee('Required terms')
            ->assertDontSee('Optional terms')
            ->call('acceptVersion', $requiredVersion->id)
            ->call('acceptVersion', $requiredVersion->id)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('agreement_acceptances', 1);
        $this->assertSame(1, $admission->events()->where('event', 'agreement.accepted')->count());
        $acceptance = $admission->acceptances()->sole();
        $this->assertSame(GroupAgreementVersion::hashContent('Required terms'), $acceptance->evidence_hash);
        $this->assertSame(1, $acceptance->evidence_schema_version);
    }

    public function test_admission_page_exposes_only_valid_actor_and_state_actions(): void
    {
        $owner = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Review group', null);
        $admission = Admission::create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'draft',
        ]);

        $this->assertFalse(Gate::forUser($owner->user)->allows('update', $admission));
        $this->assertTrue(Gate::forUser($candidate->user)->allows('update', $admission));

        Livewire::actingAs($owner->user)
            ->test(AdmissionShow::class, ['admission' => $admission])
            ->assertDontSee('Submit admission')
            ->assertDontSee('wire:click="review(\'approved\')"', false)
            ->call('review', 'approved')
            ->assertHasErrors('admission');

        Livewire::actingAs($candidate->user)
            ->test(AdmissionShow::class, ['admission' => $admission])
            ->call('submit')
            ->assertSet('admission.status', 'submitted');

        Livewire::actingAs($owner->user)
            ->test(AdmissionShow::class, ['admission' => $admission->refresh()])
            ->assertSee('Start review')
            ->assertDontSee('wire:click="review(\'approved\')"', false)
            ->call('review', 'under_review')
            ->assertSet('admission.status', 'under_review')
            ->assertSee('Approve')
            ->assertDontSee('Start review');
    }

    /** @return array{Actor, GroupInvitation} */
    private function invitation(?string $email = null, ?\DateTimeInterface $expiresAt = null): array
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Inviting group', 'A private place to learn together.');
        $invitation = GroupInvitation::create([
            'group_id' => $group->id,
            'invited_by_actor_id' => $owner->id,
            'email' => $email,
            'token' => 'invite-'.str()->random(12),
            'expires_at' => $expiresAt ?? now()->addDay(),
            'max_uses' => 10,
            'uses_count' => 0,
        ]);

        return [$owner, $invitation];
    }
}
