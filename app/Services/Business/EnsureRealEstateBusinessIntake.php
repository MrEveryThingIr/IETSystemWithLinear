<?php

namespace App\Services\Business;

use App\Models\Business;
use App\Models\PublicIntakePortal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnsureRealEstateBusinessIntake
{
    public function __construct(
        private readonly BusinessCatalogService $catalog,
    ) {}

    public function execute(
        Business $business,
        ?PublicIntakePortal $existingPortal = null,
    ): PublicIntakePortal {
        if ($business->kind !== 'real_estate') {
            throw ValidationException::withMessages([
                'business' => 'Real Estate intake can only be provisioned for a real-estate Business.',
            ]);
        }

        return DB::transaction(function () use ($business, $existingPortal): PublicIntakePortal {
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $settings = is_array($lockedBusiness->settings) ? $lockedBusiness->settings : [];
            $lockedBusiness->settings = array_replace_recursive($settings, [
                'vertical' => [
                    'key' => 'real_estate',
                    'simple_office_mode' => true,
                ],
            ]);
            $lockedBusiness->save();

            if ($existingPortal instanceof PublicIntakePortal) {
                $portal = PublicIntakePortal::query()
                    ->whereKey($existingPortal->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($portal->type !== 'real_estate') {
                    throw ValidationException::withMessages([
                        'portal' => 'Only a Real Estate intake portal can be attached to a Real Estate Business.',
                    ]);
                }

                if ($portal->business_id !== null && (int) $portal->business_id !== (int) $lockedBusiness->id) {
                    throw ValidationException::withMessages([
                        'portal' => 'This intake portal already belongs to another Business.',
                    ]);
                }

                $portal->update([
                    'business_id' => $lockedBusiness->id,
                    'title' => $lockedBusiness->name,
                ]);
            } else {
                $portal = PublicIntakePortal::query()
                    ->where('business_id', $lockedBusiness->id)
                    ->where('type', 'real_estate')
                    ->lockForUpdate()
                    ->first();

                if (! $portal instanceof PublicIntakePortal) {
                    $portal = PublicIntakePortal::query()->create([
                        'business_id' => $lockedBusiness->id,
                        'type' => 'real_estate',
                        'title' => $lockedBusiness->name,
                        'welcome_heading' => null,
                        'welcome_body' => null,
                        'success_message' => null,
                        'locale' => 'fa',
                        'is_active' => true,
                    ]);
                }
            }

            $properties = $this->catalog->ensureCategory(
                $lockedBusiness,
                'Properties',
                'properties',
            );

            foreach ([
                ['Residential', 'residential'],
                ['Commercial', 'commercial'],
                ['Office', 'office'],
                ['Industrial / Warehouse', 'industrial-warehouse'],
                ['Agricultural / Garden', 'agricultural-garden'],
                ['Land', 'land'],
            ] as [$name, $slug]) {
                $this->catalog->ensureCategory($lockedBusiness, $name, $slug, $properties);
            }

            return $portal->refresh();
        }, attempts: 3);
    }
}
