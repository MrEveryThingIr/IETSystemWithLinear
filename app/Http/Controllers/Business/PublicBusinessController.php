<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessListing;
use Illuminate\View\View;

class PublicBusinessController extends Controller
{
    public function show(Business $business): View
    {
        $this->ensurePublic($business);

        $business->load([
            'contactPoints' => fn ($query) => $query
                ->where('visibility', 'public')
                ->orderByDesc('is_primary')
                ->orderBy('id'),
            'addresses' => fn ($query) => $query
                ->where('visibility', 'public')
                ->orderByDesc('is_primary')
                ->orderBy('id'),
            'listings' => fn ($query) => $query
                ->where('visibility', 'public')
                ->whereNotNull('published_version_id')
                ->where('status', 'active')
                ->with([
                    'category',
                    'publishedVersion.propertyDetails',
                    'prices' => fn ($prices) => $prices
                        ->where('visibility', 'public')
                        ->with('monetaryUnit'),
                ])
                ->latest('id'),
            'publicIntakePortals' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('id'),
        ]);

        return view('public-business.show', [
            'business' => $business,
        ]);
    }

    public function listing(Business $business, BusinessListing $listing): View
    {
        $this->ensurePublic($business);

        abort_unless(
            (int) $listing->business_id === (int) $business->id
            && $listing->visibility === 'public'
            && $listing->status === 'active'
            && $listing->published_version_id !== null,
            404,
        );

        $listing->load([
            'category',
            'publishedVersion.propertyDetails',
            'prices' => fn ($prices) => $prices
                ->where('visibility', 'public')
                ->with('monetaryUnit'),
        ]);

        return view('public-business.listing', [
            'business' => $business,
            'listing' => $listing,
            'version' => $listing->publishedVersion,
        ]);
    }

    private function ensurePublic(Business $business): void
    {
        abort_unless(
            $business->status === 'active' && $business->visibility === 'public',
            404,
        );
    }
}
