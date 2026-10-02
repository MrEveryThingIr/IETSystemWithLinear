<?php

namespace App\Services\Business;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\Models\Actor;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessListing;
use App\Models\BusinessListingVersion;
use App\Models\BusinessPriceVersion;
use App\Models\PublicRealEstateCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessCatalogService
{
    public function __construct(
        private readonly EnsureMonetaryUnit $monetaryUnits,
    ) {}

    public function ensureCategory(
        Business $business,
        string $name,
        string $slug,
        ?BusinessCategory $parent = null,
    ): BusinessCategory {
        if ($parent instanceof BusinessCategory && (int) $parent->business_id !== (int) $business->id) {
            throw ValidationException::withMessages(['category' => 'Category belongs to another Business.']);
        }

        return BusinessCategory::query()->firstOrCreate(
            ['business_id' => $business->id, 'slug' => Str::slug($slug)],
            [
                'parent_id' => $parent?->id,
                'name' => $name,
                'is_active' => true,
            ],
        );
    }

    /**
     * @param  array{title:string, short_description?:?string, description?:?string, structured_data?:array<string,mixed>}  $versionData
     */
    public function createListing(
        Business $business,
        Actor $actor,
        string $listingType,
        array $versionData,
        ?BusinessCategory $category = null,
        string $visibility = 'private',
    ): BusinessListing {
        $listingType = strtolower(Str::squish($listingType));
        abort_unless(in_array($listingType, ['good', 'service', 'property', 'other'], true), 422);

        if ($category instanceof BusinessCategory && (int) $category->business_id !== (int) $business->id) {
            throw ValidationException::withMessages(['category' => 'Category belongs to another Business.']);
        }

        $title = Str::squish((string) $versionData['title']);
        abort_if($title === '' || mb_strlen($title) > 220, 422, 'Listing title is invalid.');

        return DB::transaction(function () use (
            $business,
            $actor,
            $listingType,
            $versionData,
            $category,
            $visibility,
            $title,
        ): BusinessListing {
            $listing = BusinessListing::query()->create([
                'business_id' => $business->id,
                'business_category_id' => $category?->id,
                'listing_type' => $listingType,
                'status' => 'draft',
                'visibility' => $visibility,
            ]);

            $version = BusinessListingVersion::query()->create([
                'business_listing_id' => $listing->id,
                'version_number' => 1,
                'title' => $title,
                'slug' => Str::slug($title),
                'short_description' => $versionData['short_description'] ?? null,
                'description' => $versionData['description'] ?? null,
                'structured_data' => $versionData['structured_data'] ?? null,
                'created_by_actor_id' => $actor->id,
            ]);

            $listing->update(['current_version_id' => $version->id]);

            return $listing->fresh(['business', 'category', 'currentVersion']);
        });
    }

    /**
     * @param  array{title:string, short_description?:?string, description?:?string, structured_data?:array<string,mixed>}  $versionData
     */
    public function reviseListing(
        BusinessListing $listing,
        Actor $actor,
        array $versionData,
    ): BusinessListingVersion {
        return DB::transaction(function () use ($listing, $actor, $versionData): BusinessListingVersion {
            $locked = BusinessListing::query()->lockForUpdate()->findOrFail($listing->id);
            $next = ((int) $locked->versions()->max('version_number')) + 1;
            $title = Str::squish((string) $versionData['title']);
            abort_if($title === '' || mb_strlen($title) > 220, 422);

            $version = BusinessListingVersion::query()->create([
                'business_listing_id' => $locked->id,
                'version_number' => $next,
                'title' => $title,
                'slug' => Str::slug($title),
                'short_description' => $versionData['short_description'] ?? null,
                'description' => $versionData['description'] ?? null,
                'structured_data' => $versionData['structured_data'] ?? null,
                'created_by_actor_id' => $actor->id,
            ]);

            $locked->update([
                'current_version_id' => $version->id,
                'status' => 'draft',
            ]);

            return $version;
        }, attempts: 3);
    }

    public function publish(
        BusinessListing $listing,
        BusinessListingVersion $version,
        Actor $actor,
    ): BusinessListing {
        abort_unless((int) $version->business_listing_id === (int) $listing->id, 404);

        return DB::transaction(function () use ($listing, $version): BusinessListing {
            $lockedListing = BusinessListing::query()->lockForUpdate()->findOrFail($listing->id);
            $lockedVersion = BusinessListingVersion::query()->lockForUpdate()->findOrFail($version->id);

            abort_unless((int) $lockedVersion->business_listing_id === (int) $lockedListing->id, 404);

            if ($lockedVersion->published_at === null) {
                $lockedVersion->forceFill(['published_at' => now()])->save();
            }

            $lockedListing->update([
                'current_version_id' => $lockedVersion->id,
                'published_version_id' => $lockedVersion->id,
                'status' => 'active',
            ]);

            return $lockedListing->fresh(['publishedVersion', 'prices']);
        }, attempts: 3);
    }

    public function addPrice(
        BusinessListing $listing,
        Actor $actor,
        string $unitCode,
        string $priceType,
        int $amountMinor,
        ?BusinessListingVersion $version = null,
        ?string $basis = null,
        string $visibility = 'members',
        ?string $reason = null,
    ): BusinessPriceVersion {
        abort_if($amountMinor < 0, 422, 'Price must not be negative.');
        abort_if($version !== null && (int) $version->business_listing_id !== (int) $listing->id, 422);

        $unit = $this->monetaryUnits->execute($unitCode);

        return BusinessPriceVersion::query()->create([
            'business_listing_id' => $listing->id,
            'business_listing_version_id' => $version?->id,
            'monetary_unit_id' => $unit->id,
            'price_type' => Str::snake(Str::squish($priceType)),
            'amount_minor' => $amountMinor,
            'basis' => $basis,
            'visibility' => $visibility,
            'valid_from' => now(),
            'created_by_actor_id' => $actor->id,
            'reason' => $reason,
        ]);
    }

    public function promoteRealEstateOffer(
        Business $business,
        PublicRealEstateCase $case,
        Actor $actor,
    ): BusinessListing {
        abort_unless($case->intent === 'offer', 422, 'Only property offers become Business Listings.');
        abort_if($case->business_listing_id !== null, 422, 'This case already has a Business Listing.');
        abort_unless(
            $case->portal()->where('business_id', $business->id)->exists(),
            422,
            'This intake case does not belong to this Business.',
        );

        $properties = $this->ensureCategory($business, 'Properties', 'properties');
        $class = trim((string) ($case->property_subtype ?: $case->property_class ?: 'Property'));
        $area = $case->construction_area ?: $case->land_area;
        $title = trim(implode(' ', array_filter([
            $case->transaction_mode === 'rent' ? 'Rent' : 'Sale',
            $class,
            $area ? $area.' m²' : null,
            $case->public_area,
        ])));

        return DB::transaction(function () use ($business, $case, $actor, $properties, $title): BusinessListing {
            $listing = $this->createListing(
                $business,
                $actor,
                'property',
                [
                    'title' => $title !== '' ? $title : 'Property listing',
                    'short_description' => $case->notes,
                    'structured_data' => [
                        'source' => 'public_real_estate_case',
                        'source_reference' => $case->reference_code,
                        'business_contact_id' => $case->business_contact_id,
                    ],
                ],
                $properties,
            );

            $version = $listing->currentVersion()->firstOrFail();

            $version->propertyDetails()->create([
                'transaction_mode' => $case->transaction_mode,
                'property_class' => $case->property_class,
                'property_subtype' => $case->property_subtype,
                'exact_address' => $case->exact_address,
                'public_area' => $case->public_area,
                'land_area' => $case->land_area,
                'construction_area' => $case->construction_area,
                'width' => $case->width,
                'length' => $case->length,
                'frontage_count' => $case->frontage_count,
                'built_year' => $case->built_year,
                'built_year_calendar' => $case->built_year_calendar,
                'building_age_years' => $case->building_age_years,
                'building_condition' => $case->building_condition,
                'bedrooms' => $case->bedrooms,
                'bedrooms_note' => $case->bedrooms_note,
                'cabinet_type' => $case->cabinet_type,
                'has_false_ceiling' => $case->has_false_ceiling,
                'false_ceiling_note' => $case->false_ceiling_note,
                'heating_system' => $case->heating_system,
                'cooling_system' => $case->cooling_system,
                'yard_finish' => $case->yard_finish,
                'flooring_type' => $case->flooring_type,
                'flooring_note' => $case->flooring_note,
                'has_parking' => $case->has_parking,
                'parking_type' => $case->parking_type,
                'parking_spaces' => $case->parking_spaces,
                'car_capacity' => $case->car_capacity,
                'motorbike_capacity' => $case->motorbike_capacity,
                'parking_note' => $case->parking_note,
                'roof_finish' => $case->roof_finish,
                'roof_note' => $case->roof_note,
                'roof_has_parapet' => $case->roof_has_parapet,
                'has_western_toilet' => $case->has_western_toilet,
                'has_iranian_toilet' => $case->has_iranian_toilet,
            ]);

            foreach ([
                'asking_sale' => $case->asking_price,
                'deposit' => $case->deposit_amount,
                'monthly_rent' => $case->monthly_rent_amount,
            ] as $type => $amount) {
                if ($amount === null) {
                    continue;
                }

                [$code, $minor] = $this->normalizeLegacyPropertyAmount((string) $case->price_unit, (int) $amount);

                $this->addPrice(
                    $listing,
                    $actor,
                    $code,
                    $type,
                    $minor,
                    $version,
                    visibility: 'public',
                    reason: 'Imported from '.$case->reference_code,
                );
            }

            $case->update([
                'business_listing_id' => $listing->id,
                'status' => $case->status === 'new' ? 'qualified' : $case->status,
            ]);

            return $listing->fresh([
                'currentVersion.propertyDetails',
                'prices.monetaryUnit',
            ]);
        }, attempts: 3);
    }

    /** @return array{string,int} */
    private function normalizeLegacyPropertyAmount(string $unit, int $amount): array
    {
        abort_if($amount < 0, 422);

        if (strtolower($unit) === 'toman') {
            abort_if($amount > intdiv(PHP_INT_MAX, 10), 422, 'Property price is too large.');

            return ['IRR', $amount * 10];
        }

        return ['IRR', $amount];
    }
}
