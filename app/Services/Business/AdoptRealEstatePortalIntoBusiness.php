<?php

namespace App\Services\Business;

use App\Models\Actor;
use App\Models\Business;
use App\Models\BusinessContact;
use App\Models\PublicIntakePortal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdoptRealEstatePortalIntoBusiness
{
    public function __construct(
        private readonly BusinessService $businesses,
        private readonly BusinessCatalogService $catalog,
    ) {}

    public function execute(PublicIntakePortal $portal, User $user): Business
    {
        $portal->loadMissing('business');

        if ($portal->business instanceof Business) {
            return $portal->business;
        }

        $actor = $user->actor;
        if (! $actor instanceof Actor || $actor->status !== 'active') {
            throw ValidationException::withMessages([
                'business' => 'An active Actor is required to adopt this office into Businesses.',
            ]);
        }

        return DB::transaction(function () use ($portal, $actor): Business {
            $lockedPortal = PublicIntakePortal::query()
                ->lockForUpdate()
                ->findOrFail($portal->id);

            if ($lockedPortal->business_id !== null) {
                return Business::query()->findOrFail($lockedPortal->business_id);
            }

            $business = $this->businesses->create($actor, [
                'name' => $lockedPortal->title,
                'kind' => 'real_estate',
                'short_intro' => 'Real-estate office migrated from the established IET office intake channel.',
                'status' => 'active',
                'visibility' => 'private',
                'settings' => [
                    'vertical' => [
                        'key' => 'real_estate',
                        'simple_office_mode' => true,
                    ],
                ],
            ]);

            $lockedPortal->update(['business_id' => $business->id]);

            BusinessContact::query()
                ->where('owner_type', $lockedPortal->getMorphClass())
                ->where('owner_id', $lockedPortal->id)
                ->update([
                    'owner_type' => $business->getMorphClass(),
                    'owner_id' => $business->id,
                ]);

            $properties = $this->catalog->ensureCategory($business, 'Properties', 'properties');

            foreach ([
                ['Residential', 'residential'],
                ['Commercial', 'commercial'],
                ['Office', 'office'],
                ['Industrial / Warehouse', 'industrial-warehouse'],
                ['Agricultural / Garden', 'agricultural-garden'],
                ['Land', 'land'],
            ] as [$name, $slug]) {
                $this->catalog->ensureCategory($business, $name, $slug, $properties);
            }

            $business->load([
                'contextBinding.context',
                'categories',
                'publicIntakePortals',
            ]);

            return $business;
        }, attempts: 3);
    }
}
