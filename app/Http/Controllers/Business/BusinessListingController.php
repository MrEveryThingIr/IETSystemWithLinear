<?php

namespace App\Http\Controllers\Business;

use App\Actions\Business\SyncBusinessListingPresentation;
use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Business;
use App\Models\BusinessListing;
use App\Models\BusinessListingMedia;
use App\Services\Business\BusinessCatalogService;
use App\Services\Business\BusinessListingMediaService;
use App\Support\BusinessAccess;
use App\Support\LocalizedNumber;
use App\Support\MonetaryUnitCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BusinessListingController extends Controller
{
    public function edit(Request $request, Business $business, BusinessListing $listing): View
    {
        $this->authorizeManage($request, $business, $listing);

        $business->load(['businessContacts.contactPoints', 'categories', 'defaultMonetaryUnit', 'contextBinding.context']);
        $listing->load([
            'businessContact.contactPoints',
            'category',
            'currentVersion.propertyDetails',
            'currentVersion.media.asset',
            'currentVersion.presentationContent',
            'publishedVersion',
            'prices.monetaryUnit',
        ]);

        return view('businesses.catalog.edit', [
            'business' => $business,
            'listing' => $listing,
            'version' => $listing->currentVersion,
            'unitCatalog' => MonetaryUnitCatalog::all(),
            'availabilityStatuses' => $this->availabilityStatuses(),
        ]);
    }

    public function update(
        Request $request,
        Business $business,
        BusinessListing $listing,
        BusinessCatalogService $catalog,
    ): RedirectResponse {
        $this->authorizeManage($request, $business, $listing);
        $this->normalizeNumbers($request);

        $data = $request->validate([
            'business_contact_id' => ['nullable', 'integer'],
            'business_category_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:220'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'visibility' => ['required', Rule::in(['private', 'members', 'public'])],
            'availability_status' => ['required', Rule::in(array_keys($this->availabilityStatuses()))],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],
            'simple_office_mode' => ['nullable', 'boolean'],

            'transaction_mode' => ['nullable', Rule::in(['sale', 'rent', 'sale_or_rent'])],
            'property_class' => ['nullable', Rule::in([
                'residential', 'commercial', 'office', 'land', 'industrial',
                'agricultural', 'mixed', 'other',
            ])],
            'property_subtype' => ['nullable', 'string', 'max:80'],
            'exact_address' => ['nullable', 'string', 'max:2000'],
            'public_area' => ['nullable', 'string', 'max:180'],
            'land_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'construction_area' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'frontage_count' => ['nullable', 'integer', 'min:0', 'max:9'],
            'built_year' => ['nullable', 'integer', 'min:1200', 'max:2500'],
            'built_year_calendar' => ['nullable', Rule::in(['jalali', 'gregorian'])],
            'building_age_years' => ['nullable', 'integer', 'min:0', 'max:300'],
            'building_condition' => ['nullable', Rule::in([
                'new', 'excellent', 'good', 'renovated', 'needs_renovation', 'old', 'teardown',
            ])],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'bedrooms_note' => ['nullable', 'string', 'max:255'],
            'cabinet_type' => ['nullable', 'string', 'max:60'],
            'has_false_ceiling' => ['nullable', 'boolean'],
            'false_ceiling_note' => ['nullable', 'string', 'max:255'],
            'heating_system' => ['nullable', 'string', 'max:80'],
            'cooling_system' => ['nullable', 'string', 'max:80'],
            'yard_finish' => ['nullable', 'string', 'max:80'],
            'flooring_type' => ['nullable', 'string', 'max:80'],
            'flooring_note' => ['nullable', 'string', 'max:255'],
            'has_parking' => ['nullable', 'boolean'],
            'parking_type' => ['nullable', 'string', 'max:60'],
            'parking_spaces' => ['nullable', 'integer', 'min:0', 'max:999'],
            'car_capacity' => ['nullable', 'integer', 'min:0', 'max:999'],
            'motorbike_capacity' => ['nullable', 'integer', 'min:0', 'max:999'],
            'parking_note' => ['nullable', 'string', 'max:255'],
            'roof_finish' => ['nullable', 'string', 'max:80'],
            'roof_note' => ['nullable', 'string', 'max:255'],
            'roof_has_parapet' => ['nullable', 'boolean'],
            'has_western_toilet' => ['nullable', 'boolean'],
            'has_iranian_toilet' => ['nullable', 'boolean'],
            'floor_number' => ['nullable', 'integer', 'min:-20', 'max:300'],
            'total_floors' => ['nullable', 'integer', 'min:0', 'max:300'],
            'unit_number' => ['nullable', 'string', 'max:40'],
            'units_per_floor' => ['nullable', 'integer', 'min:0', 'max:100'],
            'has_elevator' => ['nullable', 'boolean'],
            'has_storage' => ['nullable', 'boolean'],
            'storage_area' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'has_balcony' => ['nullable', 'boolean'],
            'balcony_area' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'orientation' => ['nullable', 'string', 'max:40'],
            'deed_type' => ['nullable', 'string', 'max:80'],
            'usage_type' => ['nullable', 'string', 'max:80'],
            'occupancy_status' => ['nullable', 'string', 'max:48'],
            'utilities_text' => ['nullable', 'string', 'max:1000'],
            'facilities_text' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'public_notes' => ['nullable', 'string', 'max:5000'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $actor = $request->user()?->actor;
        abort_unless($actor instanceof Actor, 403);

        $propertyData = null;

        if ($listing->listing_type === 'property') {
            $propertyKeys = [
                'transaction_mode', 'property_class', 'property_subtype', 'exact_address',
                'public_area', 'land_area', 'construction_area', 'width', 'length',
                'frontage_count', 'built_year', 'built_year_calendar', 'building_age_years',
                'building_condition', 'bedrooms', 'bedrooms_note', 'cabinet_type',
                'has_false_ceiling', 'false_ceiling_note', 'heating_system', 'cooling_system',
                'yard_finish', 'flooring_type', 'flooring_note', 'has_parking', 'parking_type',
                'parking_spaces', 'car_capacity', 'motorbike_capacity', 'parking_note',
                'roof_finish', 'roof_note', 'roof_has_parapet', 'has_western_toilet',
                'has_iranian_toilet', 'floor_number', 'total_floors', 'unit_number',
                'units_per_floor', 'has_elevator', 'has_storage', 'storage_area',
                'has_balcony', 'balcony_area', 'orientation', 'deed_type', 'usage_type',
                'occupancy_status', 'latitude', 'longitude', 'public_notes', 'private_notes',
            ];

            $propertyData = collect($data)->only($propertyKeys)->all();
            $propertyData['utilities'] = $this->listValue($data['utilities_text'] ?? null);
            $propertyData['facilities'] = $this->listValue($data['facilities_text'] ?? null);
        }

        $catalog->saveDraft(
            $listing,
            $actor,
            [
                'business_contact_id' => filled($data['business_contact_id'] ?? null)
                    ? (int) $data['business_contact_id']
                    : null,
                'business_category_id' => filled($data['business_category_id'] ?? null)
                    ? (int) $data['business_category_id']
                    : null,
                'visibility' => $data['visibility'],
                'availability_status' => $data['availability_status'],
                'available_from' => $data['available_from'] ?? null,
                'available_until' => $data['available_until'] ?? null,
                'simple_office_mode' => (bool) ($data['simple_office_mode'] ?? false),
            ],
            [
                'title' => $data['title'],
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
            ],
            $propertyData,
        );

        return redirect()
            ->route('businesses.catalog.listings.edit', [$business, $listing])
            ->with('status', __('business_listing.messages.saved'));
    }

    public function preview(Request $request, Business $business, BusinessListing $listing): View
    {
        abort_unless(BusinessAccess::canOperate($request->user(), $business), 403);
        abort_unless((int) $listing->business_id === (int) $business->id, 404);

        $listing->load([
            'businessContact',
            'currentVersion.propertyDetails',
            'currentVersion.media' => fn ($query) => $query->where('visibility', 'public'),
            'currentVersion.media.asset',
            'currentVersion.presentationContent',
            'prices' => fn ($query) => $query->where('visibility', 'public'),
            'prices.monetaryUnit',
        ]);

        return view('businesses.catalog.preview', [
            'business' => $business,
            'listing' => $listing,
            'version' => $listing->currentVersion,
        ]);
    }

    public function uploadMedia(
        Request $request,
        Business $business,
        BusinessListing $listing,
        BusinessListingMediaService $media,
    ): RedirectResponse {
        $this->authorizeManage($request, $business, $listing);

        $data = $request->validate([
            'media' => ['required', 'file', 'max:12288'],
            'rights_status' => ['required', Rule::in(Asset::RIGHTS_STATUSES)],
            'caption' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(['private', 'members', 'public'])],
            'cover' => ['nullable', 'boolean'],
        ]);

        $version = $listing->currentVersion()->firstOrFail();
        $media->attach(
            $business,
            $listing,
            $version,
            $request->user(),
            $request->file('media'),
            $data['rights_status'],
            $data['caption'] ?? null,
            $data['visibility'],
            (bool) ($data['cover'] ?? false),
        );

        return back()->with('status', __('business_listing.messages.media_added'));
    }

    public function updateMedia(
        Request $request,
        Business $business,
        BusinessListing $listing,
        BusinessListingMedia $mediaItem,
        BusinessListingMediaService $media,
    ): RedirectResponse {
        $this->authorizeManage($request, $business, $listing);
        $this->assertMediaBelongs($listing, $mediaItem);

        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0', 'max:10000'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['required', Rule::in(['private', 'members', 'public'])],
            'cover' => ['nullable', 'boolean'],
        ]);

        $media->update(
            $mediaItem,
            $request->user(),
            (int) $data['position'],
            $data['caption'] ?? null,
            $data['visibility'],
            (bool) ($data['cover'] ?? false),
        );

        return back()->with('status', __('business_listing.messages.media_updated'));
    }

    public function destroyMedia(
        Request $request,
        Business $business,
        BusinessListing $listing,
        BusinessListingMedia $mediaItem,
        BusinessListingMediaService $media,
    ): RedirectResponse {
        $this->authorizeManage($request, $business, $listing);
        $this->assertMediaBelongs($listing, $mediaItem);
        $media->remove($mediaItem, $request->user());

        return back()->with('status', __('business_listing.messages.media_removed'));
    }

    public function showMedia(
        Request $request,
        Business $business,
        BusinessListing $listing,
        BusinessListingMedia $mediaItem,
    ): BinaryFileResponse {
        abort_unless(BusinessAccess::canOperate($request->user(), $business), 403);
        abort_unless((int) $listing->business_id === (int) $business->id, 404);
        $this->assertMediaBelongs($listing, $mediaItem);

        $asset = $mediaItem->asset()->firstOrFail();
        abort_unless(Storage::disk($asset->disk)->exists($asset->storage_key), 404);

        return response()->file(Storage::disk($asset->disk)->path($asset->storage_key), [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function syncPresentation(
        Request $request,
        Business $business,
        BusinessListing $listing,
        SyncBusinessListingPresentation $sync,
    ): RedirectResponse {
        $this->authorizeManage($request, $business, $listing);
        $version = $listing->currentVersion()->firstOrFail();
        $content = $sync->execute($listing, $version, $request->user());

        return redirect()
            ->route('contexts.contents.studio', [$business->contextBinding()->with('context')->sole()->context, $content])
            ->with('status', __('business_listing.messages.presentation_synced'));
    }

    private function authorizeManage(Request $request, Business $business, BusinessListing $listing): void
    {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);
        abort_unless((int) $listing->business_id === (int) $business->id, 404);
    }

    private function assertMediaBelongs(BusinessListing $listing, BusinessListingMedia $media): void
    {
        abort_unless(
            $listing->versions()->whereKey($media->business_listing_version_id)->exists(),
            404,
        );
    }

    private function normalizeNumbers(Request $request): void
    {
        $numeric = [
            'land_area', 'construction_area', 'width', 'length', 'frontage_count',
            'built_year', 'building_age_years', 'bedrooms', 'parking_spaces',
            'car_capacity', 'motorbike_capacity', 'floor_number', 'total_floors',
            'units_per_floor', 'storage_area', 'balcony_area', 'latitude', 'longitude',
        ];

        $merge = [];

        foreach ($numeric as $field) {
            if ($request->filled($field)) {
                $merge[$field] = LocalizedNumber::decimal((string) $request->input($field));
            }
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    /** @return list<string> */
    private function listValue(?string $value): array
    {
        if (! filled($value)) {
            return [];
        }

        return collect(preg_split('/[,،\n]+/u', (string) $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string,string> */
    private function availabilityStatuses(): array
    {
        return [
            'available' => __('business_listing.availability.available'),
            'reserved' => __('business_listing.availability.reserved'),
            'under_contract' => __('business_listing.availability.under_contract'),
            'unavailable' => __('business_listing.availability.unavailable'),
            'sold' => __('business_listing.availability.sold'),
            'rented' => __('business_listing.availability.rented'),
            'withdrawn' => __('business_listing.availability.withdrawn'),
        ];
    }
}
