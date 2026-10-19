<?php

namespace Database\Seeders;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\CreateDirectContract;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\FulfillmentReviewDecision;
use App\Models\ActorProfileIntent;
use App\Models\Business;
use App\Models\BusinessListing;
use App\Models\Contract;
use App\Models\PlatformAccessGrant;
use App\Models\Relationship;
use App\Models\User;
use App\PlatformRole;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileItemVisibility;
use App\Services\Business\BusinessCatalogService;
use App\Services\Business\BusinessMarketService;
use App\Services\Business\BusinessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoherenceBaselineDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(BootstrapSuperadminSeeder::class);

        $provider = User::query()
            ->with('actor')
            ->where('username', (string) config('bootstrap.superadmin.username', 'MrEveryThing'))
            ->firstOrFail();

        if (! $provider->platformAccessGrants()->active()->where('role', PlatformRole::Superadmin->value)->exists()) {
            PlatformAccessGrant::factory()->for($provider)->create([
                'role' => PlatformRole::Superadmin,
                'reason' => 'Local coherence baseline demo superadmin.',
            ]);
        }

        $this->call(RealEstateBusinessDemoSeeder::class);

        $realEstate = Business::query()
            ->where('owner_actor_id', $provider->actor->id)
            ->where('kind', 'real_estate')
            ->first();

        $scenarios = [
            [
                'key' => 'inspection',
                'business' => 'Atlas Inspection Services',
                'kind' => 'services',
                'listing_type' => 'service',
                'title' => 'Dimensional inspection service',
                'concept' => 'Dimensional inspection',
                'subject' => 'service',
                'arrangement' => 'service',
                'commitment_kind' => 'service',
                'unit' => 'job',
                'amount' => 1200000,
                'buyer_email' => 'inspection-buyer@example.com',
                'buyer_username' => 'inspection-buyer',
            ],
            [
                'key' => 'tools',
                'business' => 'Everyday Tools Store',
                'kind' => 'retail',
                'listing_type' => 'good',
                'title' => 'Cordless drill kit',
                'concept' => 'Cordless drill',
                'subject' => 'good',
                'arrangement' => 'ownership_transfer',
                'commitment_kind' => 'deliverable',
                'unit' => 'item',
                'amount' => 800000,
                'buyer_email' => 'tool-buyer@example.com',
                'buyer_username' => 'tool-buyer',
            ],
            [
                'key' => 'home-service',
                'business' => 'Bright Home Services',
                'kind' => 'services',
                'listing_type' => 'service',
                'title' => 'Air conditioner maintenance',
                'concept' => 'Air conditioner maintenance',
                'subject' => 'service',
                'arrangement' => 'service',
                'commitment_kind' => 'work',
                'unit' => 'job',
                'amount' => 1500000,
                'buyer_email' => 'home-buyer@example.com',
                'buyer_username' => 'home-buyer',
            ],
            [
                'key' => 'real-estate-brokerage',
                'business' => $realEstate?->name ?? 'مشاور املاک مهوری',
                'kind' => 'real_estate',
                'listing_type' => 'service',
                'title' => 'Property brokerage service',
                'concept' => 'Property brokerage service',
                'subject' => 'service',
                'arrangement' => 'service',
                'commitment_kind' => 'service',
                'unit' => 'deal',
                'amount' => 2000000,
                'buyer_email' => 'property-buyer@example.com',
                'buyer_username' => 'property-buyer',
            ],
        ];

        foreach ($scenarios as $scenario) {
            $buyer = $this->user($scenario['buyer_email'], $scenario['buyer_username']);
            $business = $scenario['kind'] === 'real_estate' && $realEstate instanceof Business
                ? $realEstate
                : $this->business($provider, $scenario['business'], $scenario['kind']);

            $listing = $this->listing(
                $provider,
                $business,
                $scenario['listing_type'],
                $scenario['title'],
                $scenario['concept'],
                $scenario['amount'],
            );

            $need = $this->need(
                $buyer,
                $scenario['key'],
                $scenario['concept'],
                $scenario['subject'],
                $scenario['arrangement'],
                $scenario['amount'],
            );

            $offer = ActorProfileIntent::query()
                ->where('kind', ProfileIntentKind::Offer->value)
                ->where('status', 'active')
                ->where('metadata->source', 'business_listing_version')
                ->where('metadata->business_listing_uuid', $listing->uuid)
                ->latest('id')
                ->firstOrFail();

            $relationship = $this->deal(
                $buyer,
                $provider,
                $scenario['key'],
                $need,
                $offer,
            );

            $this->acceptedScenario(
                $buyer,
                $provider,
                $relationship,
                $scenario['key'],
                $scenario['title'],
                $scenario['commitment_kind'],
                $scenario['unit'],
                $scenario['amount'],
            );
        }
    }

    private function user(string $email, string $username): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $user = User::factory()->create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]);
        } else {
            $user->forceFill([
                'email_verified_at' => $user->email_verified_at ?? now(),
                'status' => 'active',
            ])->save();
        }

        $user->actor()->firstOrCreate([], ['status' => 'active']);

        return $user->fresh('actor');
    }

    private function business(User $owner, string $name, string $kind): Business
    {
        $existing = Business::query()
            ->where('owner_actor_id', $owner->actor->id)
            ->where('name', $name)
            ->first();

        if ($existing instanceof Business) {
            return $existing;
        }

        return app(BusinessService::class)->create($owner->actor, [
            'name' => $name,
            'kind' => $kind,
            'visibility' => 'public',
            'status' => 'active',
            'timezone' => 'UTC',
            'short_intro' => 'Seeded C5-C7 acceptance Business.',
        ]);
    }

    private function listing(
        User $provider,
        Business $business,
        string $type,
        string $title,
        string $concept,
        int $amount,
    ): BusinessListing {
        $listing = BusinessListing::query()
            ->where('business_id', $business->id)
            ->whereHas('versions', fn ($query) => $query->where('title', $title))
            ->first();

        $catalog = app(BusinessCatalogService::class);

        if (! $listing instanceof BusinessListing) {
            $listing = $catalog->createListing(
                $business,
                $provider->actor,
                $type,
                [
                    'title' => $title,
                    'short_description' => 'Seeded acceptance item priced in toman.',
                ],
                visibility: 'public',
            );

            $version = $listing->currentVersion()->sole();

            $catalog->addPrice(
                $listing,
                $provider->actor,
                'IRT',
                'baseline_rate',
                $amount,
                $version,
                basis: 'per unit',
                visibility: 'public',
                reason: 'C5-C7 seeded acceptance price.',
            );

            $catalog->publish($listing, $version, $provider->actor);
        }

        app(BusinessMarketService::class)->publishListingOffer(
            $business,
            $listing->fresh(),
            $provider,
            $concept,
        );

        return $listing->fresh();
    }

    private function need(
        User $buyer,
        string $key,
        string $concept,
        string $subject,
        string $arrangement,
        int $amount,
    ): ActorProfileIntent {
        $profile = app(EnsureActorProfile::class)->execute($buyer);

        $existing = $profile->intents()
            ->where('kind', ProfileIntentKind::Need->value)
            ->where('metadata->demo_scenario', $key)
            ->first();

        if ($existing instanceof ActorProfileIntent) {
            return $existing;
        }

        $intent = app(CreateActorProfileIntent::class)->execute(
            $buyer,
            $profile,
            ProfileIntentKind::Need,
            $concept,
            [
                'title' => 'Seeded need: '.$concept,
                'description' => 'Seeded C5-C7 acceptance demand.',
                'subject_kind' => $subject,
                'arrangement_kind' => $arrangement,
                'exchange_preference' => ProfileIntentExchangePreference::OpenHybrid->value,
                'cash_min' => (string) $amount,
                'cash_max' => (string) $amount,
                'currency_code' => 'IRT',
                'cash_basis' => 'total',
                'quantity' => '1',
                'unit' => 'unit',
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => 'UTC',
                'visibility' => ProfileItemVisibility::Public->value,
            ],
        );

        $intent->update([
            'metadata' => [
                'source' => 'coherence_baseline_demo',
                'demo_scenario' => $key,
            ],
        ]);

        return $intent->refresh();
    }

    private function deal(
        User $buyer,
        User $provider,
        string $key,
        ActorProfileIntent $need,
        ActorProfileIntent $offer,
    ): Relationship {
        $relationship = Relationship::query()
            ->where('title', 'C5-C7 demo deal '.$key)
            ->first();

        if (! $relationship instanceof Relationship) {
            $relationship = app(CreateRelationship::class)->execute(
                $buyer,
                $need->concept,
                'buyer',
                [[
                    'actor' => $provider->actor,
                    'role' => 'provider',
                ]],
                originatingIntent: $need,
                title: 'C5-C7 demo deal '.$key,
                matchedIntent: $offer,
            );

            app(RespondToRelationship::class)->execute(
                $relationship,
                $provider,
                true,
            );
        }

        return $relationship->fresh();
    }

    private function acceptedScenario(
        User $buyer,
        User $provider,
        Relationship $relationship,
        string $key,
        string $title,
        string $kind,
        string $unit,
        int $amount,
    ): Contract {
        $contract = Contract::query()
            ->where('title', 'C5-C7 demo '.$key)
            ->first();

        if ($contract instanceof Contract && $contract->relationship_id === null) {
            $contract->forceFill(['relationship_id' => $relationship->id])->save();
        }

        if (! $contract instanceof Contract) {
            $contract = app(CreateDirectContract::class)->execute(
                $buyer,
                'C5-C7 demo '.$key,
                [['actor' => $provider->actor, 'role' => 'provider']],
                $title.' for '.number_format($amount).' toman.',
                CarbonImmutable::now(),
                'UTC',
                creatorRole: 'receiver',
                relationship: $relationship,
                serviceTerms: [
                    'employer_username' => $buyer->username,
                    'worker_username' => $provider->username,
                    'service_title' => $title,
                    'service_kind' => $kind,
                    'total_quantity' => '1',
                    'quantity_per_occurrence' => '1',
                    'unit' => $unit,
                    'unit_rate' => (string) $amount,
                    'monetary_unit_code' => 'IRT',
                    'settlement_cycle' => 'per_fulfillment',
                    'payment_due_days' => 0,
                    'auto_create_plan' => false,
                    'auto_recognize_obligation' => true,
                    'plan_frequency' => 'once',
                    'plan_starts_on' => now()->toDateString(),
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
                ],
            );

            app(AcceptContractVersion::class)->execute(
                $contract->versions()->sole(),
                $provider,
            );
        }

        $active = $contract->fresh()->activeVersionRecord();
        abort_unless($active !== null, 500, 'Seeded demo Contract failed to activate.');
        $active->loadMissing('serviceTerm.commitment');

        $commitment = $active->serviceTerm?->commitment;
        abort_unless($commitment !== null, 500, 'Seeded demo Commitment is missing.');

        $fulfillment = $commitment->fulfillments()->first();

        if ($fulfillment === null) {
            $fulfillment = app(SubmitFulfillment::class)->execute(
                $commitment,
                $provider,
                1,
                notes: 'Seeded C5-C7 baseline fulfillment.',
            );

            app(ReviewFulfillment::class)->execute(
                $fulfillment,
                $buyer,
                FulfillmentReviewDecision::Accepted,
            );
        }

        return $contract->fresh();
    }
}
