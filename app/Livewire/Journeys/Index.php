<?php

namespace App\Livewire\Journeys;

use App\DomainJourneyKind;
use App\Models\DomainBlueprintVersion;
use App\Support\DomainBlueprintCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Journeys')]
class Index extends Component
{
    public function render(): View
    {
        $blueprints = app(DomainBlueprintCatalog::class)
            ->all()
            ->map(function ($blueprint): array {
                $version = $blueprint->activeVersionRecord();
                abort_unless($version instanceof DomainBlueprintVersion, 500);

                $href = $version->journey_kind === DomainJourneyKind::Relationship
                    ? route('relationships.create', ['blueprint' => $blueprint->slug])
                    : route('planner.create', ['blueprint' => $blueprint->slug]);

                return compact('blueprint', 'version', 'href');
            });

        return view('livewire.journeys.index', compact('blueprints'));
    }
}
