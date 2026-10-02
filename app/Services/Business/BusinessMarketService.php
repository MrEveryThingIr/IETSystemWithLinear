<?php

namespace App\Services\Business;

use App\Actions\Profile\CreateActorProfileIntent;
use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Profile\SetActorProfileIntentStatus;
use App\Models\ActorProfileIntent;
use App\Models\Business;
use App\Models\BusinessContact;
use App\Models\BusinessListing;
use App\Models\BusinessListingVersion;
use App\Models\BusinessPriceVersion;
use App\Models\User;
use App\ProfileIntentArrangementKind;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentStatus;
use App\ProfileIntentSubjectKind;
use App\ProfileItemVisibility;
use App\Support\BusinessAccess;
use App\Support\MoneyAmount;
use Illuminate\Support\Str;

final class BusinessMarketService
{
    public function __construct(
        private readonly EnsureActorProfile $profiles,
        private readonly CreateActorProfileIntent $createIntent,
        private readonly SetActorProfileIntentStatus $setStatus,
    ) {}

    public function publishListingOffer(
        Business $business,
        BusinessListing $listing,
        User $user,
        ?string $conceptLabel = null,
    ): ActorProfileIntent {
        abort_unless(BusinessAccess::canManage($user, $business), 403);
        abort_unless((int) $listing->business_id === (int) $business->id, 404);

        $version = $listing->publishedVersion()
            ->with(['propertyDetails', 'listing', 'media'])
            ->first();

        abort_unless($version instanceof BusinessListingVersion && $version->published_at !== null, 422, 'Publish the catalog item before offering it to the market.');

        $profile = $this->profiles->execute($user);
        $conceptLabel = $this->conceptLabel($listing, $version, $conceptLabel);

        $existing = $profile->intents()
            ->where('kind', ProfileIntentKind::Offer->value)
            ->where('metadata->source', 'business_listing_version')
            ->where('metadata->business_listing_uuid', $listing->uuid)
            ->get();

        foreach ($existing as $intent) {
            if (($intent->metadata['business_listing_version_uuid'] ?? null) === $version->uuid
                && $intent->status === ProfileIntentStatus::Active) {
                return $intent->load('concept.labels');
            }

            if ($intent->status !== ProfileIntentStatus::Closed) {
                $this->setStatus->execute($user, $intent, ProfileIntentStatus::Closed);
            }
        }

        $price = $this->marketPrice($listing, $version);
        $details = $version->propertyDetails;
        $location = $details?->public_area;
        $quantity = null;
        $unit = null;

        if ($listing->listing_type === 'property' && $details?->construction_area !== null) {
            $quantity = (string) $details->construction_area;
            $unit = 'm2';
        }

        $intent = $this->createIntent->execute(
            $user,
            $profile,
            ProfileIntentKind::Offer,
            $conceptLabel,
            [
                'title' => $version->title,
                'description' => $version->short_description ?: $version->description,
                'subject_kind' => $this->subjectKind($listing)->value,
                'arrangement_kind' => $this->arrangementKind($listing, $version)->value,
                'exchange_preference' => ProfileIntentExchangePreference::OpenHybrid->value,
                'cash_min' => $price ? MoneyAmount::format((int) $price->amount_minor, (int) $price->monetaryUnit->exponent) : null,
                'cash_max' => $price ? MoneyAmount::format((int) $price->amount_minor, (int) $price->monetaryUnit->exponent) : null,
                'currency_code' => $price?->monetaryUnit?->code,
                'cash_basis' => $price?->basis ? $this->cashBasis($price->basis) : null,
                'exchange_notes' => 'Internal settlement may use IET according to the Business agreement.',
                'quantity' => $quantity,
                'unit' => $unit,
                'location_text' => $location,
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => $business->timezone ?: config('app.timezone', 'UTC'),
                'visibility' => ProfileItemVisibility::Public->value,
            ],
        );

        $intent->update([
            'metadata' => [
                'source' => 'business_listing_version',
                'business_uuid' => $business->uuid,
                'business_listing_uuid' => $listing->uuid,
                'business_listing_version_uuid' => $version->uuid,
                'business_contact_uuid' => $listing->businessContact?->uuid,
            ],
        ]);

        return $intent->refresh()->load('concept.labels');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function publishClientNeed(
        Business $business,
        BusinessContact $client,
        User $user,
        string $conceptLabel,
        array $input = [],
    ): ActorProfileIntent {
        abort_unless(BusinessAccess::canManage($user, $business), 403);
        abort_unless(
            $client->owner_type === $business->getMorphClass()
            && (int) $client->owner_id === (int) $business->id,
            404,
        );

        $profile = $this->profiles->execute($user);
        $conceptLabel = Str::squish($conceptLabel);
        abort_if($conceptLabel === '', 422, 'A market concept is required.');

        $intent = $this->createIntent->execute(
            $user,
            $profile,
            ProfileIntentKind::Need,
            $conceptLabel,
            [
                'title' => trim((string) ($input['title'] ?? 'Need for '.$client->display_name)),
                'description' => trim((string) ($input['description'] ?? $client->notes ?? '')),
                'subject_kind' => (string) ($input['subject_kind'] ?? ProfileIntentSubjectKind::Other->value),
                'arrangement_kind' => (string) ($input['arrangement_kind'] ?? ProfileIntentArrangementKind::Other->value),
                'exchange_preference' => ProfileIntentExchangePreference::OpenHybrid->value,
                'cash_min' => $input['cash_min'] ?? null,
                'cash_max' => $input['cash_max'] ?? null,
                'currency_code' => $input['currency_code'] ?? null,
                'cash_basis' => $input['cash_basis'] ?? null,
                'exchange_notes' => 'Business-managed client need; internal settlement may use IET.',
                'quantity' => $input['quantity'] ?? null,
                'unit' => $input['unit'] ?? null,
                'location_text' => $input['location_text'] ?? null,
                'schedule_kind' => ProfileIntentScheduleKind::Ongoing->value,
                'timezone' => $business->timezone ?: config('app.timezone', 'UTC'),
                'visibility' => ProfileItemVisibility::Public->value,
            ],
        );

        $intent->update([
            'metadata' => [
                'source' => 'business_contact_need',
                'business_uuid' => $business->uuid,
                'business_contact_uuid' => $client->uuid,
            ],
        ]);

        return $intent->refresh()->load('concept.labels');
    }

    private function subjectKind(BusinessListing $listing): ProfileIntentSubjectKind
    {
        return match ($listing->listing_type) {
            'property' => ProfileIntentSubjectKind::Property,
            'good' => ProfileIntentSubjectKind::Good,
            'service' => ProfileIntentSubjectKind::Service,
            default => ProfileIntentSubjectKind::Other,
        };
    }

    private function arrangementKind(BusinessListing $listing, BusinessListingVersion $version): ProfileIntentArrangementKind
    {
        if ($listing->listing_type === 'service') {
            return ProfileIntentArrangementKind::Service;
        }

        if ($listing->listing_type === 'property') {
            return match ($version->propertyDetails?->transaction_mode) {
                'rent' => ProfileIntentArrangementKind::TemporaryUse,
                default => ProfileIntentArrangementKind::OwnershipTransfer,
            };
        }

        if ($listing->listing_type === 'good') {
            return ProfileIntentArrangementKind::OwnershipTransfer;
        }

        return ProfileIntentArrangementKind::Other;
    }

    private function conceptLabel(BusinessListing $listing, BusinessListingVersion $version, ?string $requested): string
    {
        $requested = Str::squish((string) $requested);
        if ($requested !== '') {
            return $requested;
        }

        if ($listing->listing_type === 'property' && $version->propertyDetails?->property_class) {
            return Str::headline((string) $version->propertyDetails->property_class).' property';
        }

        return $listing->category?->name ?: $version->title;
    }

    private function marketPrice(BusinessListing $listing, BusinessListingVersion $version): ?BusinessPriceVersion
    {
        return $listing->prices()
            ->where('business_listing_version_id', $version->id)
            ->where('visibility', 'public')
            ->with('monetaryUnit')
            ->latest('id')
            ->first();
    }

    private function cashBasis(string $basis): ?string
    {
        $basis = Str::lower($basis);

        foreach (['hour', 'day', 'week', 'month', 'year', 'total'] as $candidate) {
            if (str_contains($basis, $candidate)) {
                return $candidate;
            }
        }

        return 'total';
    }
}
