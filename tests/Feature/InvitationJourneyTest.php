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

    public function test_known_unavailable_invitations_explain_their_state_without_offering_onboarding(): void
    {
        $this->withoutVite();
        [, $expired] = $this->invitation(expiresAt: now()->subMinute());
        [, $revoked] = $this->invitation();
        $revoked->update(['revoked_at' => now()]);
        [, $exhausted] = $this->invitation();
        $exhausted->update(['max_uses' => 1, 'uses_count' => 1]);

        foreach ([
            [$expired, 'expired'],
            [$revoked, 'revoked'],
            [$exhausted, 'use limit'],
        ] as [$invitation, $message]) {
            $this->get(route('invitations.show', $invitation->token))
                ->assertOk()
                ->assertSee($message)
                ->assertDontSee('Create an invited account');
        }

        $this->get(route('invitations.show', 'unknown-token'))->assertNotFound();
    }

    public function test_existing_admission_can_be_resumed_after_invitation_is_exhausted(): void
    {
        $this->withoutVite();
        [, $invitation] = $this->invitation();
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($invitation->token, $candidate, $candidate->user->email);
        $invitation->update(['max_uses' => 1]);

        $this->actingAs($candidate->user)
            ->get(route('invitations.show', $invitation->token))
            ->assertOk()
            ->assertSee(route('admissions.show', $admission), false)
            ->assertSee('admission in progress');
    }

    public function test_redemption_rejects_unverified_identity_even_if_invitation_email_is_supplied(): void
    {
        [, $invitation] = $this->invitation(email: 'invited@example.com');
        $candidate = Actor::factory()->create();
        $candidate->user->forceFill(['email_verified_at' => null])->save();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(RedeemGroupInvitation::class)->execute($invitation->token, $candidate, 'invited@example.com');
    }

    public function test_invited_registration_creates_identity_but_does_not_redeem_until_verification_and_continue(): void
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
        $this->assertDatabaseCount('admissions', 0);
        $this->assertSame(0, $invitation->refresh()->uses_count);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $verificationUrl = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($verificationUrl)->assertRedirect(route('invitations.show', $invitation->token));
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        $this->assertDatabaseCount('admissions', 0);

        $this->post(route('invitations.accept', $invitation->token))
            ->assertRedirect(route('admissions.show', Admission::query()->sole()));
        $this->assertSame(1, $invitation->refresh()->uses_count);
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
            ->assertHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
        $this->assertDatabaseCount('admissions', 0);
        $this->assertSame(0, $invitation->refresh()->uses_count);
        Notification::assertNothingSent();
    }

    public function test_existing_account_login_through_an_invitation_returns_to_preview_before_redemption(): void
    {
        $candidate = Actor::factory()->create();
        [, $invitation] = $this->invitation();

        $component = Livewire::test(Login::class, ['token' => $invitation->token])
            ->set('email', $candidate->user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('admissions', 0);
        $component->assertRedirect(route('invitations.show', $invitation->token));
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

    public function test_candidate_cannot_submit_until_every_active_required_version_has_exact_acceptance(): void
    {
        [, $invitation] = $this->invitation();
        $candidate = Actor::factory()->create();
        $admission = app(RedeemGroupInvitation::class)->execute($invitation->token, $candidate, $candidate->user->email);
        $agreement = GroupAgreement::create(['group_id' => $invitation->group_id, 'name' => 'Required rules', 'required_for_admission' => true]);
        $version = GroupAgreementVersion::create(['group_agreement_id' => $agreement->id, 'version' => 1, 'content' => 'Terms to accept', 'status' => 'active', 'effective_from' => now()->subMinute()]);

        Livewire::actingAs($candidate->user)
            ->test(AdmissionShow::class, ['admission' => $admission])
            ->assertSee('0 of 1')
            ->call('submit')
            ->assertHasErrors('agreements');
        $this->assertSame('draft', $admission->refresh()->status);

        Livewire::actingAs($candidate->user)
            ->test(AdmissionShow::class, ['admission' => $admission])
            ->call('acceptVersion', $version->id)
            ->assertSee('1 of 1')
            ->call('submit')
            ->assertHasNoErrors();
        $this->assertSame('submitted', $admission->refresh()->status);
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
