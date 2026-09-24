<?php

namespace Tests\Feature;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Contracts\CreateContractFromProposal;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Contracts\ProposeContractAmendment;
use App\Actions\Conversations\PostContextMessage;
use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\RespondToProposal;
use App\ContextKind;
use App\ContractStatus;
use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\ContractVersion;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\ProposalDecisionKind;
use App\ProposalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ContractKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_proposal_requires_fresh_exact_contract_acceptance_before_activation(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 12:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();
            $carol = Actor::factory()->create();

            $proposal = app(CreateProposal::class)->execute(
                $alice->user,
                'Riverside construction collaboration',
                [
                    ['actor' => $bob, 'role' => 'builder'],
                    ['actor' => $carol, 'role' => 'site coordinator'],
                ],
                'Alice coordinates Riverside construction. Bob provides construction work. Carol coordinates site requirements.',
                creatorRole: 'project owner',
            );

            app(RespondToProposal::class)->execute(
                $proposal,
                $bob->user,
                ProposalDecisionKind::Accepted,
            );
            app(RespondToProposal::class)->execute(
                $proposal,
                $carol->user,
                ProposalDecisionKind::Accepted,
            );

            $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
            $proposalVersion = $proposal->currentVersionRecord();
            $this->assertInstanceOf(ProposalVersion::class, $proposalVersion);

            $contract = app(CreateContractFromProposal::class)->execute(
                $proposal,
                $alice->user,
                CarbonImmutable::now(),
                'UTC',
            );

            $version = $contract->versions()->with(['parties.actor', 'parties.acceptance'])->sole();

            $this->assertSame(ContractStatus::Pending, $contract->status);
            $this->assertSame(ContractVersionStatus::Proposed, $version->status);
            $this->assertSame($proposalVersion->terms_content_revision_id, $version->terms_content_revision_id);
            $this->assertSame($proposalVersion->id, $contract->source_proposal_version_id);
            $this->assertSame(1, ContractAcceptance::query()->count());

            $roles = $version->parties->mapWithKeys(
                fn ($party): array => [$party->actor_id => $party->role],
            );

            $this->assertSame('project owner', $roles[$alice->id]);
            $this->assertSame('builder', $roles[$bob->id]);
            $this->assertSame('site coordinator', $roles[$carol->id]);

            app(AcceptContractVersion::class)->execute($version, $bob->user);

            $this->assertSame(ContractStatus::Pending, $contract->fresh()->status);
            $this->assertSame(ContractVersionStatus::Proposed, $version->fresh()->status);

            app(AcceptContractVersion::class)->execute($version, $carol->user);

            $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
            $this->assertSame(ContractVersionStatus::Active, $version->fresh()->status);
            $this->assertSame(3, ContractAcceptance::query()->count());
            $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);
            $this->assertDatabaseCount('plans', 0);
            $this->assertDatabaseCount('journal_entries', 0);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_direct_paid_work_contract_activates_only_after_explicit_contract_acceptance(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 12:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();

            $contract = app(CreateDirectContract::class)->execute(
                $alice->user,
                'Workshop paid work',
                [['actor' => $bob, 'role' => 'worker']],
                'Bob works selected workshop days. Alice reviews accepted work. Compensation terms are stated here.',
                CarbonImmutable::now(),
                'UTC',
                creatorRole: 'employer',
            );

            $version = $contract->versions()->with(['termsRevision.content', 'parties.actor'])->sole();
            $context = $contract->contextBinding()->with('context')->sole()->context;

            $this->assertSame(ContextKind::Contract, $context->kind);
            $this->assertTrue($version->termsRevision->hasVerifiableManifest());
            $this->assertSame($context->id, $version->termsRevision->content->context_id);
            $this->assertSame(ContractVersionStatus::Proposed, $version->status);
            $this->assertSame(1, ContractAcceptance::query()->count());
            $this->assertDatabaseCount('proposals', 0);

            app(PostContextMessage::class)->execute(
                $context,
                $bob->user,
                'I accept these terms.',
            );

            $this->assertSame(1, ContractAcceptance::query()->count());
            $this->assertSame(ContractStatus::Pending, $contract->fresh()->status);

            app(AcceptContractVersion::class)->execute($version, $bob->user);

            $this->assertSame(2, ContractAcceptance::query()->count());
            $this->assertSame(ContractStatus::Active, $contract->fresh()->status);
            $this->assertSame(ContractVersionStatus::Active, $version->fresh()->status);

            $roles = $version->parties->mapWithKeys(
                fn ($party): array => [$party->actor_id => $party->role],
            );

            $this->assertSame('employer', $roles[$alice->id]);
            $this->assertSame('worker', $roles[$bob->id]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_future_amendment_is_accepted_now_but_supersedes_only_at_effective_time(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 12:00:00 UTC');

        try {
            $alice = Actor::factory()->create();
            $bob = Actor::factory()->create();

            $contract = app(CreateDirectContract::class)->execute(
                $alice->user,
                'Workshop paid work',
                [['actor' => $bob, 'role' => 'worker']],
                'Version 1 terms.',
                CarbonImmutable::now(),
                'UTC',
                creatorRole: 'employer',
            );

            $versionOne = $contract->versions()->sole();
            app(AcceptContractVersion::class)->execute($versionOne, $bob->user);

            $effective = CarbonImmutable::parse('2026-09-25 08:00:00 UTC');

            $versionTwo = app(ProposeContractAmendment::class)->execute(
                $contract->fresh(),
                $alice->user,
                'Workshop paid work — amended terms',
                'Version 2 terms for future work only.',
                $effective,
                'UTC',
                versionNote: 'Future-rate amendment',
            );

            $this->assertSame(2, $versionTwo->version);
            $this->assertSame($versionOne->id, $versionTwo->supersedes_version_id);
            $this->assertSame(ContractVersionStatus::Proposed, $versionTwo->status);

            app(AcceptContractVersion::class)->execute($versionTwo, $bob->user);

            $this->assertSame(ContractVersionStatus::Active, $versionOne->fresh()->status);
            $this->assertSame(ContractVersionStatus::Accepted, $versionTwo->fresh()->status);
            $this->assertNull($versionOne->fresh()->effective_until);

            CarbonImmutable::setTestNow('2026-09-25 08:01:00 UTC');

            $this->assertSame(1, app(ActivateDueContractVersions::class)->execute());

            $versionOne = $versionOne->fresh();
            $versionTwo = $versionTwo->fresh();

            $this->assertSame(ContractVersionStatus::Superseded, $versionOne->status);
            $this->assertSame(ContractVersionStatus::Active, $versionTwo->status);
            $this->assertSame(
                $effective->format('Y-m-d H:i:s'),
                $versionOne->effective_until?->utc()->format('Y-m-d H:i:s'),
            );
            $this->assertSame('Version 1 terms.', $versionOne->termsRevision->payload['terms']);
            $this->assertSame('Version 2 terms for future work only.', $versionTwo->termsRevision->payload['terms']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_contract_version_and_acceptance_history_are_immutable(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $contract = app(CreateDirectContract::class)->execute(
            $alice->user,
            'Immutable contract',
            [['actor' => $bob, 'role' => 'worker']],
            'Immutable exact terms.',
            CarbonImmutable::now(),
            'UTC',
        );

        $version = $contract->versions()->sole();
        $acceptance = $version->parties()
            ->where('actor_id', $alice->id)
            ->with('acceptance')
            ->sole()
            ->acceptance;

        $this->assertNotNull($acceptance);

        try {
            $version->update(['note' => 'Mutated']);

            $this->fail('ContractVersion was mutated directly.');
        } catch (LogicException) {
            $this->assertSame('Immutable exact terms.', $version->fresh()->termsRevision->payload['terms']);
        }

        $this->expectException(LogicException::class);
        $acceptance->update(['accepted_at' => now()->addDay()]);
    }

    public function test_outsider_cannot_view_contract_or_contract_context(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $outsider = Actor::factory()->create();

        $contract = app(CreateDirectContract::class)->execute(
            $alice->user,
            'Private contract',
            [['actor' => $bob, 'role' => 'party']],
            'Private terms.',
            CarbonImmutable::now(),
            'UTC',
        );

        $context = $contract->contextBinding()->with('context')->sole()->context;

        $this->assertTrue($alice->user->can('view', $contract));
        $this->assertTrue($bob->user->can('view', $contract));
        $this->assertFalse($outsider->user->can('view', $contract));
        $this->assertFalse($outsider->user->can('view', $context));
        $this->assertFalse($outsider->user->can('interactContent', $context));
    }

    public function test_one_accepted_proposal_version_cannot_create_duplicate_contract_authority(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $proposal = app(CreateProposal::class)->execute(
            $alice->user,
            'One proposal one contract',
            [['actor' => $bob, 'role' => 'worker']],
            'Accepted proposal terms.',
        );

        app(RespondToProposal::class)->execute(
            $proposal,
            $bob->user,
            ProposalDecisionKind::Accepted,
        );

        $this->assertSame(ProposalStatus::Accepted, $proposal->fresh()->status);

        app(CreateContractFromProposal::class)->execute(
            $proposal,
            $alice->user,
            CarbonImmutable::now(),
            'UTC',
        );

        try {
            app(CreateContractFromProposal::class)->execute(
                $proposal,
                $bob->user,
                CarbonImmutable::now(),
                'UTC',
            );

            $this->fail('Duplicate Contract was created from one ProposalVersion.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(1, Contract::query()->count());
    }
}
