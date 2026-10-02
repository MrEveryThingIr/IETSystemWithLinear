<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessListing;
use App\Models\PublicIntakePortal;
use App\Models\PublicRealEstateCase;
use App\Services\Business\BusinessCatalogService;
use App\Support\BusinessAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessCatalogController extends Controller
{
    public function index(Request $request, Business $business): View
    {
        abort_unless(BusinessAccess::canView($request->user(), $business), 403);

        $business->load([
            'categories.children',
            'listings.currentVersion.propertyDetails',
            'listings.publishedVersion',
            'listings.prices.monetaryUnit',
            'publicIntakePortals',
            'contextBinding.context',
        ]);

        return view('businesses.catalog.index', [
            'business' => $business,
            'canManage' => BusinessAccess::canManage($request->user(), $business),
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

        $catalog->createListing(
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

        return back()->with('status', 'Listing draft created.');
    }

    public function publish(
        Request $request,
        Business $business,
        BusinessListing $listing,
        BusinessCatalogService $catalog,
    ): RedirectResponse {
        abort_unless(BusinessAccess::canManage($request->user(), $business), 403);
        abort_unless((int) $listing->business_id === (int) $business->id, 404);

        $actor = $request->user()?->actor;
        abort_unless($actor instanceof Actor, 403);

        $version = $listing->currentVersion()->firstOrFail();
        $catalog->publish($listing, $version, $actor);

        return back()->with('status', 'Listing version published and frozen.');
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
