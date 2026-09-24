<?php

namespace Tests\Feature;

use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\RespondToProposal;
use App\ContractStatus;
use App\ContractVersionStatus;
use App\Livewire\Contracts\Create as ContractCreate;
use App\Livewire\Contracts\Index as ContractIndex;
use App\Livewire\Contracts\Show as ContractShow;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\Proposal;
use App\ProposalDecisionKind;
use App\ProposalStatus;
use App\Support\ContextTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContractExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_alice_and_bob_create_and_activate_direct_paid_work_contract_in_ui(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 12:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();

            Livewire::actingAs($alice->user)
                ->test(ContractCreate::class)
                ->set('title', 'Workshop paid work')
                ->set('creatorRole', 'employer')
                ->set('partyLines', $bob->user->username.' | worker')
                ->set('terms', 'Bob works selected workshop days under the exact terms stated here.')
                ->set('effectiveAt', '2026-09-24T12:00')
                ->set('timezone', 'UTC')
                ->call('save')
                ->assertHasNoErrors();

            $contract = Contract::query()
                ->with(['versions.parties.actor', 'versions.parties.acceptance'])
                ->sole();

            $version = $contract->versions->sole();

            $this->assertSame(ContractStatus::Pending, $contract->status);
            $this->assertSame(ContractVersionStatus::Proposed, $version->status);
            $this->assertSame(1, $version->parties->whereNotNull('acceptance')->count());

            Livewire::actingAs($bob->user)
                ->test(ContractShow::class, ['contract' => $contract])
                ->assertSee('Workshop paid work')
                ->assertSee('Bob works selected workshop days')
                ->call('accept')
                ->assertHasNoErrors()
                ->assertSee(__('contracts.status.active'));

            $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
            $this->assertSame(ContractVersionStatus::Active, $version->fresh()->status);

            Livewire::actingAs($alice->user)
                ->test(ContractIndex::class)
                ->assertSee('Workshop paid work');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_accepted_proposal_handoff_still_requires_new_contract_acceptance(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 12:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();

            $proposal = app(CreateProposal::class)->execute(
                $alice->user,
                'Riverside paid-work proposal',
                [['actor' => $bob, 'role' => 'worker']],
                'Bob may perform Riverside work under these proposed terms.',
                creatorRole: 'project owner',
            );

            app(RespondToProposal::class)->execute(
                $proposal,
                $bob->user,
                ProposalDecisionKind::Accepted,
            );

            $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);

            $this->actingAs($alice->user)
                ->get(route('proposals.show', $proposal))
                ->assertOk()
                ->assertSee(route('contracts.create', ['proposal' => $proposal->uuid]), false);

            Livewire::actingAs($alice->user)
                ->withQueryParams(['proposal' => $proposal->uuid])
                ->test(ContractCreate::class)
                ->assertSet('title', 'Riverside paid-work proposal')
                ->set('effectiveAt', '2026-09-24T12:00')
                ->set('timezone', 'UTC')
                ->call('save')
                ->assertHasNoErrors();

            $contract = Contract::query()
                ->with(['sourceProposalVersion', 'versions.parties.acceptance'])
                ->sole();

            $version = $contract->versions->sole();

            $this->assertSame($proposal->currentVersionRecord()?->id, $contract->source_proposal_version_id);
            $this->assertSame(ContractStatus::Pending, $contract->status);
            $this->assertSame(ContractVersionStatus::Proposed, $version->status);
            $this->assertSame(1, $version->parties->whereNotNull('acceptance')->count());

            Livewire::actingAs($bob->user)
                ->test(ContractShow::class, ['contract' => $contract])
                ->call('accept')
                ->assertHasNoErrors();

            $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_future_amendment_ui_preserves_active_terms_until_due_activation(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 12:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();

            Livewire::actingAs($alice->user)
                ->test(ContractCreate::class)
                ->set('title', 'Workshop paid work')
                ->set('creatorRole', 'employer')
                ->set('partyLines', $bob->user->username.' | worker')
                ->set('terms', 'Version 1 terms.')
                ->set('effectiveAt', '2026-09-24T12:00')
                ->set('timezone', 'UTC')
                ->call('save');

            $contract = Contract::query()->sole();
            $versionOne = $contract->versions()->sole();

            Livewire::actingAs($bob->user)
                ->test(ContractShow::class, ['contract' => $contract])
                ->call('accept');

            $this->assertSame(ContractVersionStatus::Active, $versionOne->fresh()->status);

            Livewire::actingAs($alice->user)
                ->test(ContractShow::class, ['contract' => $contract])
                ->set('amendmentTitle', 'Workshop paid work — version 2')
                ->set('amendmentTerms', 'Version 2 terms for future work.')
                ->set('versionNote', 'Future amendment')
                ->set('effectiveAt', '2026-09-25T08:00')
                ->set('timezone', 'UTC')
                ->call('proposeAmendment')
                ->assertHasNoErrors();

            $versionTwo = $contract->versions()->where('version', 2)->firstOrFail();

            Livewire::actingAs($bob->user)
                ->test(ContractShow::class, ['contract' => $contract])
                ->call('accept')
                ->assertHasNoErrors();

            $this->assertSame(ContractVersionStatus::Active, $versionOne->fresh()->status);
            $this->assertSame(ContractVersionStatus::Accepted, $versionTwo->fresh()->status);

            CarbonImmutable::setTestNow('2026-09-25 08:01:00 UTC');
            $this->assertSame(1, app(ActivateDueContractVersions::class)->execute());

            $this->assertSame(ContractVersionStatus::Superseded, $versionOne->fresh()->status);
            $this->assertSame(ContractVersionStatus::Active, $versionTwo->fresh()->status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_contract_events_compose_into_context_timeline_and_outsider_is_denied(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 12:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();
            $outsider = Actor::factory()->create();

            Livewire::actingAs($alice->user)
                ->test(ContractCreate::class)
                ->set('title', 'Private contract')
                ->set('partyLines', $bob->user->username.' | party')
                ->set('terms', 'Private exact terms.')
                ->set('effectiveAt', '2026-09-24T12:00')
                ->set('timezone', 'UTC')
                ->call('save');

            $contract = Contract::query()->with('contextBinding.context')->sole();
            $context = $contract->contextBinding->context;

            $entries = app(ContextTimeline::class)->entries($context, $alice->user);
            $this->assertTrue($entries->contains(fn ($entry): bool => $entry->kind === 'contract'));
            $this->assertTrue($entries->contains(
                fn ($entry): bool => $entry->url === route('contracts.show', $contract),
            ));

            $this->actingAs($outsider->user)
                ->get(route('contracts.show', $contract))
                ->assertForbidden();

            $this->actingAs($outsider->user)
                ->get(route('contexts.conversation', $context))
                ->assertForbidden();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
