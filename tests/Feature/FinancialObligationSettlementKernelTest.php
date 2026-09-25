<?php

namespace Tests\Feature;

use App\Actions\Commitments\CreateCommitment;
use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Financial\PostFinancialObligationAccounting;
use App\Actions\Financial\PostSettlementAccounting;
use App\Actions\Financial\ProposeSettlement;
use App\Actions\Financial\RecognizeFulfillmentFinancialObligation;
use App\Actions\Financial\RespondToSettlement;
use App\Actions\Fulfillments\OpenFulfillmentDispute;
use App\Actions\Fulfillments\ResolveFulfillmentDispute;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\CommitmentKind;
use App\FulfillmentReviewDecision;
use App\FulfillmentStatus;
use App\JournalEntryKind;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\FinancialObligation;
use App\Models\Fulfillment;
use App\Models\JournalEntry;
use App\SettlementStatus;
use App\Support\ContractFinancialSummary;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinancialObligationSettlementKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_accepted_workdays_derive_earned_paid_outstanding_and_disputed_values(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 09:00:00 UTC');

        try {
            [$alice, $bob, $contract, $commitment] = $this->activePaidWorkCommitment(3);

            $fulfillments = collect(range(1, 3))->map(function () use ($alice, $bob, $commitment): Fulfillment {
                $fulfillment = app(SubmitFulfillment::class)->execute(
                    $commitment,
                    $bob->user,
                    1,
                    notes: 'Completed one Riverside workday.',
                );

                app(ReviewFulfillment::class)->execute(
                    $fulfillment,
                    $alice->user,
                    FulfillmentReviewDecision::Accepted,
                );

                return $fulfillment->fresh();
            });

            $obligations = $fulfillments->map(
                fn (Fulfillment $fulfillment): FinancialObligation => app(
                    RecognizeFulfillmentFinancialObligation::class,
                )->execute(
                    $fulfillment,
                    $alice->user,
                    'IRR',
                    1_500_000,
                    description: 'Accepted Riverside workday compensation.',
                ),
            );

            $unit = $obligations->first()->monetaryUnit;
            $summary = app(ContractFinancialSummary::class);

            $this->assertSame([
                'scheduled_count' => 0,
                'worked_count' => 3,
                'accepted_count' => 3,
                'disputed_count' => 0,
                'earned_minor' => 4_500_000,
                'paid_minor' => 0,
                'outstanding_minor' => 4_500_000,
                'disputed_minor' => 0,
                'obligation_count' => 3,
                'confirmed_settlement_count' => 0,
            ], $summary->forContractUnit($contract, $unit));

            foreach ($obligations->take(2) as $index => $obligation) {
                $aliceRecognition = app(PostFinancialObligationAccounting::class)->execute(
                    $obligation,
                    $alice->user,
                );
                $bobRecognition = app(PostFinancialObligationAccounting::class)->execute(
                    $obligation,
                    $bob->user,
                );

                $this->assertSame(JournalEntryKind::ObligationRecognition, $aliceRecognition->kind);
                $this->assertSame(JournalEntryKind::ObligationRecognition, $bobRecognition->kind);

                $settlement = app(ProposeSettlement::class)->execute(
                    $obligation,
                    $alice->user,
                    1_500_000,
                    CarbonImmutable::now()->subMinutes(10 - $index),
                    'bank transfer',
                    'RIVERSIDE-'.($index + 1),
                );

                app(RespondToSettlement::class)->confirm($settlement, $bob->user);

                app(PostSettlementAccounting::class)->execute($settlement->fresh(), $alice->user);
                app(PostSettlementAccounting::class)->execute($settlement->fresh(), $bob->user);
            }

            $this->assertSame([
                'scheduled_count' => 0,
                'worked_count' => 3,
                'accepted_count' => 3,
                'disputed_count' => 0,
                'earned_minor' => 4_500_000,
                'paid_minor' => 3_000_000,
                'outstanding_minor' => 1_500_000,
                'disputed_minor' => 0,
                'obligation_count' => 3,
                'confirmed_settlement_count' => 2,
            ], $summary->forContractUnit($contract, $unit));

            $third = $obligations->last();

            $pendingSettlement = app(ProposeSettlement::class)->execute(
                $third,
                $alice->user,
                1_500_000,
                CarbonImmutable::now()->subMinute(),
                'cash',
                'RIVERSIDE-3',
            );

            app(OpenFulfillmentDispute::class)->execute(
                $third->fulfillment,
                $bob->user,
                'The accepted quantity is now disputed.',
            );

            $this->assertSame([
                'scheduled_count' => 0,
                'worked_count' => 3,
                'accepted_count' => 2,
                'disputed_count' => 1,
                'earned_minor' => 3_000_000,
                'paid_minor' => 3_000_000,
                'outstanding_minor' => 0,
                'disputed_minor' => 1_500_000,
                'obligation_count' => 3,
                'confirmed_settlement_count' => 2,
            ], $summary->forContractUnit($contract, $unit));

            try {
                app(RespondToSettlement::class)->confirm($pendingSettlement, $bob->user);

                $this->fail('Settlement was confirmed while source Fulfillment was disputed.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }

            $dispute = $third->fulfillment->fresh()->dispute;
            $this->assertNotNull($dispute);

            app(ResolveFulfillmentDispute::class)->execute(
                $dispute,
                $alice->user,
                FulfillmentStatus::Accepted,
                'Accepted after review of dispute.',
            );

            $this->assertSame(1_500_000, $summary->forContractUnit($contract, $unit)['outstanding_minor']);
            $this->assertSame(0, $summary->forContractUnit($contract, $unit)['disputed_minor']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_each_actor_posts_only_their_own_balanced_accounting_and_retries_are_idempotent(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 09:00:00 UTC');

        try {
            [$alice, $bob, $contract, $commitment] = $this->activePaidWorkCommitment();

            $fulfillment = app(SubmitFulfillment::class)->execute($commitment, $bob->user, 1);

            app(ReviewFulfillment::class)->execute(
                $fulfillment,
                $alice->user,
                FulfillmentReviewDecision::Accepted,
            );

            $obligation = app(RecognizeFulfillmentFinancialObligation::class)->execute(
                $fulfillment->fresh(),
                $alice->user,
                'IRR',
                1_500_000,
            );

            $aliceEntry = app(PostFinancialObligationAccounting::class)->execute(
                $obligation,
                $alice->user,
            );
            $aliceRetry = app(PostFinancialObligationAccounting::class)->execute(
                $obligation,
                $alice->user,
            );
            $bobEntry = app(PostFinancialObligationAccounting::class)->execute(
                $obligation,
                $bob->user,
            );

            $this->assertSame($aliceEntry->id, $aliceRetry->id);
            $this->assertNotSame($aliceEntry->ledger_id, $bobEntry->ledger_id);
            $this->assertSame('financial_obligation', $aliceEntry->source_type);
            $this->assertSame($obligation->uuid, $aliceEntry->source_uuid);
            $this->assertSame($alice->user->id, $aliceEntry->acting_user_id);
            $this->assertSame($bob->user->id, $bobEntry->acting_user_id);
            $this->assertDatabaseCount('journal_entries', 2);
            $this->assertDatabaseCount('financial_obligation_events', 3);

            foreach (JournalEntry::query()->with('lines')->get() as $entry) {
                $this->assertSame(
                    $entry->lines->sum('debit_minor'),
                    $entry->lines->sum('credit_minor'),
                );
            }

            $settlement = app(ProposeSettlement::class)->execute(
                $obligation,
                $alice->user,
                1_500_000,
                CarbonImmutable::now()->subMinute(),
            );

            app(RespondToSettlement::class)->confirm($settlement, $bob->user);

            $aliceSettlement = app(PostSettlementAccounting::class)->execute(
                $settlement->fresh(),
                $alice->user,
            );
            $bobSettlement = app(PostSettlementAccounting::class)->execute(
                $settlement->fresh(),
                $bob->user,
            );

            $this->assertSame(JournalEntryKind::Settlement, $aliceSettlement->kind);
            $this->assertSame(JournalEntryKind::Settlement, $bobSettlement->kind);
            $this->assertDatabaseCount('journal_entries', 4);
            $this->assertSame(0, $obligation->fresh()->outstandingMinor());
            $this->assertSame(
                1_500_000,
                app(ContractFinancialSummary::class)
                    ->forContractUnit($contract, $obligation->monetaryUnit)['paid_minor'],
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_obligation_cannot_be_recognized_before_explicit_fulfillment_acceptance(): void
    {
        [$alice, $bob, , $commitment] = $this->activePaidWorkCommitment();

        $fulfillment = app(SubmitFulfillment::class)->execute(
            $commitment,
            $bob->user,
            1,
        );

        try {
            app(RecognizeFulfillmentFinancialObligation::class)->execute(
                $fulfillment,
                $alice->user,
                'IRR',
                1_500_000,
            );

            $this->fail('Unaccepted Fulfillment created a Financial Obligation.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('financial_obligations', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_outsider_cannot_view_or_post_an_obligation(): void
    {
        [$alice, $bob, , $commitment] = $this->activePaidWorkCommitment();
        $outsider = Actor::factory()->create();

        $fulfillment = app(SubmitFulfillment::class)->execute($commitment, $bob->user, 1);

        app(ReviewFulfillment::class)->execute(
            $fulfillment,
            $alice->user,
            FulfillmentReviewDecision::Accepted,
        );

        $obligation = app(RecognizeFulfillmentFinancialObligation::class)->execute(
            $fulfillment->fresh(),
            $alice->user,
            'IRR',
            1_500_000,
        );

        $this->assertTrue($alice->user->can('view', $obligation));
        $this->assertTrue($bob->user->can('view', $obligation));
        $this->assertFalse($outsider->user->can('view', $obligation));

        $this->expectException(AuthorizationException::class);

        app(PostFinancialObligationAccounting::class)->execute(
            $obligation,
            $outsider->user,
        );
    }

    public function test_settlement_claim_is_immutable_and_requires_counterparty_confirmation(): void
    {
        [$alice, $bob, , $commitment] = $this->activePaidWorkCommitment();
        $fulfillment = app(SubmitFulfillment::class)->execute($commitment, $bob->user, 1);

        app(ReviewFulfillment::class)->execute(
            $fulfillment,
            $alice->user,
            FulfillmentReviewDecision::Accepted,
        );

        $obligation = app(RecognizeFulfillmentFinancialObligation::class)->execute(
            $fulfillment->fresh(),
            $alice->user,
            'IRR',
            1_500_000,
        );

        $settlement = app(ProposeSettlement::class)->execute(
            $obligation,
            $alice->user,
            500_000,
            now()->subMinute(),
            'cash',
        );

        $this->assertSame(SettlementStatus::PendingConfirmation, $settlement->status);

        try {
            app(RespondToSettlement::class)->confirm($settlement, $alice->user);

            $this->fail('Settlement proposer confirmed their own payment claim.');
        } catch (AuthorizationException) {
            $this->assertSame(SettlementStatus::PendingConfirmation, $settlement->fresh()->status);
        }

        app(RespondToSettlement::class)->confirm($settlement, $bob->user);
        $this->assertSame(SettlementStatus::Confirmed, $settlement->fresh()->status);

        $this->expectException(LogicException::class);
        $settlement->update(['amount_minor' => 1]);
    }

    /**
     * @return array{0: Actor, 1: Actor, 2: Contract, 3: Commitment}
     */
    private function activePaidWorkCommitment(int $quantity = 1): array
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();

        $contract = app(CreateDirectContract::class)->execute(
            $alice->user,
            'Riverside paid work',
            [['actor' => $bob, 'role' => 'worker']],
            'Bob performs accepted construction work. Alice owes 1,500,000 IRR per accepted workday.',
            CarbonImmutable::now(),
            'UTC',
            creatorRole: 'client',
        );

        $version = $contract->versions()->sole();

        app(AcceptContractVersion::class)->execute($version, $bob->user);

        $commitment = app(CreateCommitment::class)->execute(
            $contract->fresh(),
            $alice->user,
            $bob,
            $alice,
            CommitmentKind::Work,
            'Riverside construction workdays',
            $quantity,
            'day',
        );

        return [$alice, $bob, $contract->fresh(), $commitment];
    }
}
