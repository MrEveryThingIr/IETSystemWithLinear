<?php

namespace App\Http\Controllers\Business;

use App\Actions\Business\SyncBusinessListingPresentation;
use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessListing;
use App\Models\PublicIntakePortal;
use App\Models\PublicRealEstateCase;
use App\Models\User;
use App\Services\Business\BusinessCatalogService;
use App\Support\BusinessAccess;
use App\Support\LocalizedNumber;
use App\Support\MonetaryUnitCatalog;
use App\Support\MoneyAmount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class BusinessCatalogController extends Controller
{
    public function index(Request $request, Business $business): View
    {
        abort_unless(BusinessAccess::canOperate($request->user(), $business), 403);

        $business->load([
            'categories.children',
            'listings.category',
            'listings.currentVersion.propertyDetails',
            'listings.publishedVersion',
            'listings.prices.monetaryUnit',
            'publicIntakePortals',
            'contextBinding.context',
            'defaultMonetaryUnit',
        ]);

        return view('businesses.catalog.index', [
            'business' => $business,
            'canManage' => BusinessAccess::canManage($request->user(), $business),
            'unitCatalog' => MonetaryUnitCatalog::all(),
        ]);
    }

    public function storeCategory(
        Request $request,
        Business $business,
        BusinessCatalogService $catalog,
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $parent = null;
        if (filled($data['parent_id'] ?? null)) {
            $parent = BusinessCategory::query()->findOrFail((int) $data['parent_id']);
            abort_unless((int) $parent->business_id === (int) $business->id, 404);
        }

        $catalog->ensureCategory(
            $business,
            $data['name'],
            filled($data['slug'] ?? null) ? $data['slug'] : $data['name'],
            $parent,
        );

        return back()->with('status', 'Catalog category saved.');
    }

    public function store(Request $request, Business $business, BusinessCatalogService $catalog): RedirectResponse
    {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);

        $data = $request->validate([
            'listing_type' => ['required', Rule::in(['good', 'service', 'property', 'other'])],
            'category_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:220'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'visibility' => ['required', Rule::in(['private', 'members', 'public'])],
        ]);

        $category = null;
        if (filled($data['category_id'] ?? null)) {
            $category = BusinessCategory::query()->findOrFail((int) $data['category_id']);
            abort_unless((int) $category->business_id === (int) $business->id, 404);
        }

        $actor = $request->user()?->actor;
        abort_unless($actor instanceof Actor, 403);

        $listing = $catalog->createListing(
            $business,
            $actor,
            $data['listing_type'],
            [
                'title' => $data['title'],
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
            ],
            $category,
            $data['visibility'],
        );

        if ($business->kind === 'real_estate' && $listing->listing_type === 'property') {
            $listing->update(['simple_office_mode' => true]);
        }

        return redirect()
            ->route('businesses.catalog.listings.edit', [$business, $listing])
            ->with('status', __('business_listing.messages.created'));
    }

    public function storePrice(
        Request $request,
        Business $business,
        BusinessListing $listing,
        BusinessCatalogService $catalog,
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);
        abort_unless((int) $listing->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'unit_code' => ['required', 'string', Rule::in(array_keys(MonetaryUnitCatalog::all()))],
            'price_type' => ['required', 'string', 'max:48'],
            'amount' => ['required', 'string', 'max:40'],
            'basis' => ['nullable', 'string', 'max:80'],
            'visibility' => ['required', Rule::in(['private', 'members', 'public'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = $request->user()?->actor;
        abort_unless($actor instanceof Actor, 403);

        $unitCode = strtoupper($data['unit_code']);
        $unit = MonetaryUnitCatalog::get($unitCode);

        try {
            $amountMinor = MoneyAmount::parse(
                LocalizedNumber::decimal($data['amount']),
                $unit['exponent'],
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'amount' => $exception->getMessage(),
            ]);
        }

        $catalog->addPrice(
            $listing,
            $actor,
            $unitCode,
            $data['price_type'],
            $amountMinor,
            $listing->currentVersion()->first(),
            filled($data['basis'] ?? null) ? trim((string) $data['basis']) : null,
            $data['visibility'],
            filled($data['reason'] ?? null) ? trim((string) $data['reason']) : null,
        );

        return back()->with('status', 'New price version added.');
    }

    public function publish(
        Request $request,
        Business $business,
        BusinessListing $listing,
        BusinessCatalogService $catalog,
        SyncBusinessListingPresentation $presentation,
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);
        abort_unless((int) $listing->business_id === (int) $business->id, 404);

        $actor = $request->user()?->actor;
        abort_unless($actor instanceof Actor, 403);

        $version = $listing->currentVersion()->firstOrFail();
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $presentation->execute($listing, $version, $user, publish: true);
        $catalog->publish($listing, $version->fresh(), $actor);

        return redirect()
            ->route('businesses.catalog.listings.edit', [$business, $listing])
            ->with('status', __('business_listing.messages.published'));
    }

    public function promoteRealEstateCase(
        Request $request,
        Business $business,
        PublicIntakePortal $portal,
        PublicRealEstateCase $case,
        BusinessCatalogService $catalog,
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);
        abort_unless((int) $portal->business_id === (int) $business->id, 404);
        abort_unless((int) $case->public_intake_portal_id === (int) $portal->id, 404);

        $actor = $request->user()?->actor;
        abort_unless($actor instanceof Actor, 403);

        $listing = $catalog->promoteRealEstateOffer($business, $case, $actor);

        return redirect()
            ->route('businesses.catalog.index', $business)
            ->with('status', 'Property case promoted to Business Listing '.$listing->uuid.'.');
    }
}
