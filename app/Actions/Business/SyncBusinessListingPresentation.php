<?php

namespace App\Actions\Business;

use App\Actions\Contexts\CreateContextContent;
use App\Actions\Contexts\CreateContextContentDefinition;
use App\Actions\Contexts\EnsureBusinessContext;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Models\BusinessListing;
use App\Models\BusinessListingVersion;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\MoneyAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncBusinessListingPresentation
{
    public function __construct(
        private readonly EnsureBusinessContext $contexts,
        private readonly CreateContextContentDefinition $createDefinition,
        private readonly CreateContextContent $createContent,
        private readonly ReviseSpaceContent $reviseContent,
        private readonly PublishSpaceContent $publishContent,
    ) {}

    public function execute(
        BusinessListing $listing,
        BusinessListingVersion $version,
        User $user,
        bool $publish = false,
    ): SpaceContent {
        abort_unless((int) $version->business_listing_id === (int) $listing->id, 404);
        abort_if($version->published_at !== null, 422, 'Published Listing versions are immutable.');

        $listing->loadMissing('business');
        $context = $this->contexts->execute($listing->business);
        $definition = $this->definition($context, $user);

        $version->loadMissing([
            'propertyDetails',
            'media.asset',
            'listing.prices.monetaryUnit',
            'presentationContent',
        ]);

        $payload = $this->payload($listing, $version);
        $content = $version->presentationContent;

        if ($content instanceof SpaceContent) {
            abort_unless((int) $content->context_id === (int) $context->id, 500);
            $content = $this->reviseContent->execute(
                $content,
                $user,
                $version->title,
                $payload,
            );
        } else {
            $content = $this->createContent->execute(
                $context,
                $definition,
                $user,
                $version->title,
                $payload,
            );

            $version->update(['presentation_content_id' => $content->id]);
        }

        $content = $this->replacePresentationMedia($content, $version);

        if ($publish) {
            $content = $this->publishContent->execute($content, $user);
        }

        return $content->fresh([
            'activeRevision.assets',
            'draftRevision.assets',
        ]);
    }

    private function definition(Context $context, User $user): SpaceContentDefinition
    {
        $definition = SpaceContentDefinition::query()
            ->where('context_id', $context->id)
            ->where('slug', 'business-listing-presentation')
            ->first();

        if ($definition instanceof SpaceContentDefinition) {
            abort_if($definition->status === 'archived', 422, 'Listing presentation Content Definition is archived.');

            return $definition;
        }

        $definition = $this->createDefinition->execute(
            $context,
            $user,
            'Business Listing Presentation',
            'Generated presentation for canonical Business Listing data.',
            [
                ['key' => 'listing_type', 'label' => 'Listing type', 'type' => 'short_text', 'required' => true],
                ['key' => 'summary', 'label' => 'Summary', 'type' => 'long_text', 'required' => false],
                ['key' => 'description', 'label' => 'Description', 'type' => 'long_text', 'required' => false],
                ['key' => 'public_facts', 'label' => 'Details', 'type' => 'long_text', 'required' => false],
                ['key' => 'price_summary', 'label' => 'Price', 'type' => 'long_text', 'required' => false],
                ['key' => 'source_version', 'label' => 'Source version', 'type' => 'short_text', 'required' => true],
            ],
        );

        $version = $definition->draftVersionRecord();
        abort_unless($version instanceof SpaceContentDefinitionVersion, 500);
        $version->publish();

        $definition->applyLifecycle([
            'status' => 'active',
            'current_version' => $version->version,
            'active_version_id' => $version->id,
            'draft_version_id' => null,
        ]);

        return $definition->fresh();
    }

    /** @return array<string, mixed> */
    private function payload(BusinessListing $listing, BusinessListingVersion $version): array
    {
        $facts = [];

        if ($version->propertyDetails !== null) {
            $property = $version->propertyDetails;

            foreach ([
                'transaction_mode' => 'Transaction',
                'property_class' => 'Property class',
                'property_subtype' => 'Property type',
                'public_area' => 'Area',
                'land_area' => 'Land area',
                'construction_area' => 'Construction area',
                'bedrooms' => 'Bedrooms',
                'building_condition' => 'Condition',
                'floor_number' => 'Floor',
                'total_floors' => 'Total floors',
                'has_elevator' => 'Elevator',
                'has_parking' => 'Parking',
                'has_storage' => 'Storage',
                'has_balcony' => 'Balcony',
                'orientation' => 'Orientation',
                'deed_type' => 'Deed',
                'usage_type' => 'Usage',
            ] as $field => $label) {
                $value = $property->getAttribute($field);

                if ($value === null || $value === '') {
                    continue;
                }

                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }

                $facts[] = $label.': '.$value;
            }

            if (filled($property->public_notes)) {
                $facts[] = trim((string) $property->public_notes);
            }
        }

        $prices = $listing->prices()
            ->where('business_listing_version_id', $version->id)
            ->where('visibility', 'public')
            ->with('monetaryUnit')
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->unique(fn ($price): string => $price->price_type.'|'.$price->monetary_unit_id)
            ->map(function ($price): string {
                $amount = MoneyAmount::format(
                    (int) $price->amount_minor,
                    (int) $price->monetaryUnit->exponent,
                );

                return Str::headline($price->price_type).': '.$amount.' '.$price->monetaryUnit->code
                    .($price->basis ? ' / '.$price->basis : '');
            })
            ->values()
            ->all();

        return [
            'listing_type' => $listing->listing_type,
            'summary' => $version->short_description,
            'description' => $version->description,
            'public_facts' => $facts === [] ? null : implode("\n", $facts),
            'price_summary' => $prices === [] ? null : implode("\n", $prices),
            'source_version' => 'ListingVersion '.$version->uuid.' · v'.$version->version_number,
        ];
    }

    private function replacePresentationMedia(
        SpaceContent $content,
        BusinessListingVersion $version,
    ): SpaceContent {
        $draft = $content->draftRevisionRecord();
        abort_unless($draft instanceof SpaceContentRevision, 500);

        DB::transaction(function () use ($draft, $version): void {
            DB::table('space_content_revision_assets')
                ->where('space_content_revision_id', $draft->id)
                ->delete();

            $media = $version->media()
                ->where('visibility', 'public')
                ->with('asset')
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            foreach ($media as $position => $item) {
                DB::table('space_content_revision_assets')->insert([
                    'uuid' => (string) Str::uuid(),
                    'space_content_revision_id' => $draft->id,
                    'asset_id' => $item->asset_id,
                    'role' => $item->role === 'cover' ? 'cover' : 'inline',
                    'position' => $position,
                    'caption' => $item->caption,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }, attempts: 3);

        return $content->refresh();
    }
}
