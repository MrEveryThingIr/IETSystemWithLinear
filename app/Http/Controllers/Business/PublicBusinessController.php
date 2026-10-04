<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessListing;
use Illuminate\Support\Collection;
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
            'featuredBusinesses' => $this->featuredBusinesses($business),
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

        $business->load([
            'contactPoints' => fn ($query) => $query->where('visibility', 'public'),
            'addresses' => fn ($query) => $query->where('visibility', 'public'),
            'publicIntakePortals' => fn ($query) => $query->where('is_active', true),
        ]);

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

    private function featuredBusinesses(Business $business): Collection
    {
        $ids = collect(data_get($business->settings, 'public_site.featured_business_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id !== (int) $business->getKey())
            ->unique()
            ->take(12)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $available = Business::query()
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->where('visibility', 'public')
            ->get(['id', 'uuid', 'slug', 'name', 'short_intro', 'kind'])
            ->keyBy('id');

        return $ids
            ->map(fn (int $id) => $available->get($id))
            ->filter()
            ->values();
    }

    private function ensurePublic(Business $business): void
    {
        abort_unless(
            $business->status === 'active' && $business->visibility === 'public',
            404,
        );
    }
}
