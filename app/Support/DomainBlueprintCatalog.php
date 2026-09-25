<?php

namespace App\Support;

use App\Actions\DomainBlueprints\EnsureSystemDomainBlueprints;
use App\DomainJourneyKind;
use App\Models\DomainBlueprint;
use App\Models\DomainBlueprintVersion;
use Illuminate\Support\Collection;

class DomainBlueprintCatalog
{
    public function __construct(private readonly EnsureSystemDomainBlueprints $ensure) {}

    /** @return Collection<int, DomainBlueprint> */
    public function all(?DomainJourneyKind $kind = null): Collection
    {
        $this->ensure->execute();

        return DomainBlueprint::query()
            ->where('status', DomainBlueprint::STATUS_ACTIVE)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->filter(function (DomainBlueprint $blueprint) use ($kind): bool {
                $version = $blueprint->activeVersionRecord();

                return $version instanceof DomainBlueprintVersion
                    && ($kind === null || $version->journey_kind === $kind);
            })
            ->values();
    }

    public function version(string $slug, ?DomainJourneyKind $expectedKind = null): DomainBlueprintVersion
    {
        $this->ensure->execute();

        $blueprint = DomainBlueprint::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $version = $blueprint->activeVersionRecord();
        abort_unless($version instanceof DomainBlueprintVersion, 422, 'Domain Blueprint has no active version.');

        if ($expectedKind instanceof DomainJourneyKind) {
            abort_unless($version->journey_kind === $expectedKind, 422, 'Domain Blueprint is not valid for this journey.');
        }

        return $version->load('blueprint');
    }
}
