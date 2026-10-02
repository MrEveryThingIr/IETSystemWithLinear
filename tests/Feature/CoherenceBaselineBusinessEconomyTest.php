<?php

namespace Tests\Feature;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateContractFromProposal;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Exchange\CreateIetExchangeRequest;
use App\Actions\Exchange\PublishIetValuationQuote;
use App\Actions\Exchange\ReviewIetExchangeRequest;
use App\Actions\Financial\ProposeSettlement;
use App\Actions\Financial\RespondToSettlement;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\FulfillmentReviewDecision;
use App\IetExchangeDirection;
use App\Models\Actor;
use App\Models\Business;
use App\Models\BusinessListing;
use App\Models\FinancialObligation;
use App\Models\PlatformAccessGrant;
use App\ProfileIntentArrangementKind;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentSubjectKind;
use App\ProfileItemVisibility;
use App\ProposalDecisionKind;
use App\PlatformRole;
use App\Services\Business\BusinessCatalogService;
use App\Services\Business\BusinessMarketService;
use App\Services\Business\BusinessService;
use App\Support\IetPosition;
use App\Support\IntentMatchFinder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CoherenceBaselineBusinessEconomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_catalog_offer_flows_through_match_deal_contract_work_and_iet_settlement(): void
    {
        CarbonImmutable::setTestNow('2026-10-02 12:00:00 UTC');

        try {
            $buyer = Actor::factory()->create();
            $provider = Actor::factory()->create();

            $buyer->user->update(['timezone' => 'UTC']);
            $provider->user->update(['timezone' => 'UTC']);

            $business = app(BusinessService::class)->create($provider, [
                'name' => 'Atlas Inspection Services',
                'kind' => 'services',
                'visibility' => 'public',
                'status' => 'active',
                'timezone' => 'UTC',
            ]);

            $listing = app(BusinessCatalogService::class)->createListing(
                $business,
                $provider,
                'service',
                [
                    'title' => 'Dimensional inspection',
                    'short_description' => 'One dimensional inspection service unit.',
                ],
                visibility: 'public',
            );

            $version = $listing->currentVersion()->sole();

            app(BusinessCatalogService::class)->addPrice(
                $listing,
                $provider,
                'IET',
                'service_rate',
                100,
                $version,
                basis: 'per unit',
                visibility: 'public',
            );
            app(BusinessCatalogService::class)->publish($listing, $version, $provider);

            $offer = app(BusinessMarketService::class)->publishListingOffer(
                $business,
                $listing->fresh(),
                $provider->user,
                'Dimensional inspection',
            );

            $buyerProfile = app(EnsureActorProfile::class)->execute($buyer->user);
            $need = app(CreateActorProfileIntent::class)->execute(
                $buyer->user,
                $buyerProfile,
                ProfileIntentKind::Need,
                'Dimensional inspection',
                [
                    'title' => 'Need one dimensional inspection',
                    'description' => 'Need one accepted inspection service unit.',
                    'subject_kind' => ProfileIntentSubjectKind::Service->value,
                    'arrangement_kind' => ProfileIntentArrangementKind::Service->value,
                    'exchange_preference' => ProfileIntentExchangePreference::OpenHybrid->value,
                    'cash_min' => '100',
                    'cash_max' => '100',
                    'currency_code' => 'IET',
                    'cash_basis' => 'total',
                    'quantity' => '1',
                    'unit' => 'unit',
                    'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                    'timezone' => 'UTC',
                    'visibility' => ProfileItemVisibility::Public->value,
                ],
            );

            $this->assertNotNull(app(IntentMatchFinder::class)->match($buyer->user, $need, $offer));
            $this->assertSame($version->uuid, $offer->metadata['business_listing_version_uuid']);
            $this->assertSame($listing->uuid, $offer->metadata['business_listing_uuid']);

            $relationship = app(CreateRelationship::class)->execute(
                $buyer->user,
                $need->concept,
                'client',
                [[
                    'actor' => $provider,
                    'role' => 'service provider',
                ]],
                originatingIntent: $need,
                title: 'Dimensional inspection deal',
                matchedIntent: $offer,
            );

            app(RespondToRelationship::class)->execute($relationship, $provider->user, true);
            $relationship->refresh();

            $proposal = app(CreateProposal::class)->execute(
                $buyer->user,
                'Inspection terms',
                [[
                    'actor' => $provider,
                    'role' => 'provider',
                    'required' => true,
                ]],
                'Provider performs one dimensional inspection for 100 IET.',
                creatorRole: 'client',
                relationship: $relationship,
            );

            app(RespondToProposal::class)->execute(
                $proposal,
                $buyer->user,
                ProposalDecisionKind::Accepted,
            );
            app(RespondToProposal::class)->execute(
                $proposal->fresh(),
                $provider->user,
                ProposalDecisionKind::Accepted,
            );

            $proposal = $proposal->fresh();
            $contract = app(CreateContractFromProposal::class)->execute(
                $proposal,
                $buyer->user,
                CarbonImmutable::now()->subMinute(),
                'UTC',
                serviceTerms: $this->serviceTerms(
                    $buyer,
                    $provider,
                    'Dimensional inspection',
                    '100',
                ),
            );

            $contractVersion = $contract->versions()->with('serviceTerm.commitment')->sole();
            app(AcceptContractVersion::class)->execute($contractVersion, $provider->user);

            $contract = $contract->fresh([
                'relationship.originatingIntent',
                'relationship.matchedIntent',
                'versions.serviceTerm.commitment',
            ]);
            $contractVersion = $contract->activeVersionRecord();
            $this->assertNotNull($contractVersion);

            $this->assertSame($need->id, $contract->relationship?->originating_intent_id);
            $this->assertSame($offer->id, $contract->relationship?->matched_intent_id);
            $this->assertSame(
                $version->uuid,
                $contract->relationship?->matchedIntent?->metadata['business_listing_version_uuid'],
            );
            $this->assertSame(
                $proposal->currentVersionRecord()?->id,
                $contract->source_proposal_version_id,
            );

            $contractVersion->loadMissing('serviceTerm.commitment');
            $commitment = $contractVersion->serviceTerm?->commitment;
            $this->assertNotNull($commitment);

            $fulfillment = app(SubmitFulfillment::class)->execute(
                $commitment,
                $provider->user,
                1,
                notes: 'Inspection completed.',
            );

            app(ReviewFulfillment::class)->execute(
                $fulfillment,
                $buyer->user,
                FulfillmentReviewDecision::Accepted,
            );

            $obligation = FinancialObligation::query()->with('monetaryUnit')->sole();
            $this->assertSame('IET', $obligation->monetaryUnit->code);
            $this->assertSame(100, $obligation->amount_minor);

            $buyerPosition = app(IetPosition::class)->forUser($buyer->user);
            $providerPosition = app(IetPosition::class)->forUser($provider->user);

            $this->assertSame(0, $buyerPosition['wallet_minor']);
            $this->assertSame(100, $buyerPosition['payable_minor']);
            $this->assertSame(-100, $buyerPosition['net_position_minor']);
            $this->assertSame(100, $buyerPosition['debt_position_minor']);

            $this->assertSame(0, $providerPosition['wallet_minor']);
            $this->assertSame(100, $providerPosition['receivable_minor']);
            $this->assertSame(100, $providerPosition['net_position_minor']);
            $this->assertSame(0, $providerPosition['cashout_eligible_minor']);

            $reviewer = Actor::factory()->create();
            PlatformAccessGrant::factory()->for($reviewer->user)->create([
                'role' => PlatformRole::Superadmin,
                'reason' => 'C5-C7 acceptance exchange reviewer.',
            ]);

            app(PublishIetValuationQuote::class)->execute(
                $reviewer->user,
                '0.010000000000000000',
                'Deterministic acceptance quote: one IET equals one US cent.',
            );

            $deposit = app(CreateIetExchangeRequest::class)->execute(
                $buyer->user,
                IetExchangeDirection::Deposit,
                100,
                'DEMO-DEPOSIT-1',
                'Placeholder external deposit to cover the IET debt.',
            );
            $this->assertSame(100, $deposit->iet_amount);

            app(ReviewIetExchangeRequest::class)->confirm($deposit, $reviewer->user);

            $fundedBuyer = app(IetPosition::class)->forUser($buyer->user);
            $this->assertSame(100, $fundedBuyer['wallet_minor']);
            $this->assertSame(0, $fundedBuyer['net_position_minor']);

            $settlement = app(ProposeSettlement::class)->execute(
                $obligation,
                $buyer->user,
                100,
                CarbonImmutable::now()->subSecond(),
                'IET',
                'IET-SETTLEMENT-1',
            );

            app(RespondToSettlement::class)->confirm($settlement, $provider->user);

            $settledBuyer = app(IetPosition::class)->forUser($buyer->user);
            $settledProvider = app(IetPosition::class)->forUser($provider->user);

            $this->assertSame(0, $settledBuyer['wallet_minor']);
            $this->assertSame(0, $settledBuyer['payable_minor']);
            $this->assertSame(0, $settledBuyer['net_position_minor']);

            $this->assertSame(100, $settledProvider['wallet_minor']);
            $this->assertSame(0, $settledProvider['receivable_minor']);
            $this->assertSame(100, $settledProvider['net_position_minor']);
            $this->assertSame(100, $settledProvider['cashout_eligible_minor']);

            $cashout = app(CreateIetExchangeRequest::class)->execute(
                $provider->user,
                IetExchangeDirection::Cashout,
                100,
                'DEMO-CASHOUT-1',
                'Placeholder cash-out request for earned IET.',
            );

            $this->assertSame(100, $cashout->iet_amount);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @return array<string,array{string,string,int}>
     */
    public static function economyScenarioProvider(): array
    {
        return [
            'professional service' => ['service', 'hour', 120],
            'finished good / deliverable' => ['deliverable', 'item', 80],
            'daily resource use' => ['resource', 'day', 200],
            'general work unit' => ['work', 'job', 150],
        ];
    }

    #[DataProvider('economyScenarioProvider')]
    public function test_four_economic_scenarios_create_negative_receiver_and_positive_provider_positions(
        string $kind,
        string $unit,
        int $amount,
    ): void {
        $receiver = Actor::factory()->create();
        $provider = Actor::factory()->create();

        $obligation = $this->acceptedIetWork(
            $receiver,
            $provider,
            strtoupper($kind).' baseline',
            $kind,
            $unit,
            $amount,
        );

        $this->assertSame($amount, $obligation->amount_minor);
        $this->assertSame(-$amount, app(IetPosition::class)->forUser($receiver->user)['net_position_minor']);
        $this->assertSame($amount, app(IetPosition::class)->forUser($provider->user)['net_position_minor']);
        $this->assertSame(0, app(IetPosition::class)->forUser($provider->user)['cashout_eligible_minor']);
    }

    public function test_debtor_can_earn_more_than_their_debt_and_end_with_a_positive_internal_position(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $carol = Actor::factory()->create();

        $this->acceptedIetWork($alice, $bob, 'Service Alice receives', 'service', 'job', 100);

        $this->assertSame(-100, app(IetPosition::class)->forUser($alice->user)['net_position_minor']);

        $this->acceptedIetWork($carol, $alice, 'Service Alice provides', 'service', 'job', 150);

        $alicePosition = app(IetPosition::class)->forUser($alice->user);

        $this->assertSame(0, $alicePosition['wallet_minor']);
        $this->assertSame(150, $alicePosition['receivable_minor']);
        $this->assertSame(100, $alicePosition['payable_minor']);
        $this->assertSame(50, $alicePosition['net_position_minor']);
        $this->assertSame(50, $alicePosition['positive_position_minor']);
        $this->assertSame(0, $alicePosition['cashout_eligible_minor']);
    }

    private function acceptedIetWork(
        Actor $receiver,
        Actor $provider,
        string $title,
        string $kind,
        string $unit,
        int $amount,
    ): FinancialObligation {
        $receiver->user->update(['timezone' => 'UTC']);
        $provider->user->update(['timezone' => 'UTC']);

        $contract = app(CreateDirectContract::class)->execute(
            $receiver->user,
            $title,
            [['actor' => $provider, 'role' => 'provider']],
            $title.' for '.$amount.' IET.',
            now()->subMinute(),
            'UTC',
            creatorRole: 'receiver',
            serviceTerms: $this->serviceTerms(
                $receiver,
                $provider,
                $title,
                (string) $amount,
                $kind,
                $unit,
            ),
        );

        $version = $contract->versions()->sole();
        app(AcceptContractVersion::class)->execute($version, $provider->user);

        $active = $contract->fresh()->activeVersionRecord();
        $this->assertNotNull($active);
        $active->loadMissing('serviceTerm.commitment');

        $commitment = $active->serviceTerm?->commitment;
        $this->assertNotNull($commitment);

        $fulfillment = app(SubmitFulfillment::class)->execute(
            $commitment,
            $provider->user,
            1,
            notes: 'Baseline scenario fulfilled.',
        );

        app(ReviewFulfillment::class)->execute(
            $fulfillment,
            $receiver->user,
            FulfillmentReviewDecision::Accepted,
        );

        return $fulfillment->fresh()->financialObligation()->with('monetaryUnit')->sole();
    }

    /**
     * @return array<string,mixed>
     */
    private function serviceTerms(
        Actor $receiver,
        Actor $provider,
        string $title,
        string $unitRate,
        string $kind = 'service',
        string $unit = 'unit',
    ): array {
        return [
            'employer_username' => $receiver->user->username,
            'worker_username' => $provider->user->username,
            'service_title' => $title,
            'service_kind' => $kind,
            'total_quantity' => '1',
            'quantity_per_occurrence' => '1',
            'unit' => $unit,
            'unit_rate' => $unitRate,
            'monetary_unit_code' => 'IET',
            'settlement_cycle' => 'per_fulfillment',
            'payment_due_days' => 0,
            'auto_create_plan' => false,
            'auto_recognize_obligation' => true,
            'plan_frequency' => 'once',
            'plan_starts_on' => '2026-10-02',
            'plan_start_time' => '12:00',
            'plan_duration_minutes' => 60,
            'plan_interval' => 1,
            'plan_weekdays' => [],
            'plan_selected_dates' => [],
            'plan_ends_on' => null,
            'plan_occurrence_limit' => 1,
            'window_before_minutes' => 0,
            'window_after_minutes' => 0,
            'reminder_offsets' => [],
            'timezone' => 'UTC',
        ];
    }
}
