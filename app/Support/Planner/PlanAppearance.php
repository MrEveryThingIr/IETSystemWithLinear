<?php

namespace App\Support\Planner;

use App\Models\Plan;
use App\Models\Planner\PlannerLabel;
use Illuminate\Support\Collection;

/**
 * How a plan looks for its viewer: its kind, the label it carries and its
 * colour. Colour precedence: the plan's own colour, then its label's, then
 * its kind's default.
 */
final class PlanAppearance
{
    /** @var Collection<string, PlannerLabel> */
    private Collection $labels;

    /** @param Collection<int, PlannerLabel>|null $labels the viewer's labels */
    public function __construct(?Collection $labels = null)
    {
        $this->labels = ($labels ?? collect())->keyBy('uuid');
    }

    public static function forActor(?int $actorId): self
    {
        return new self($actorId === null ? collect() : PlannerLabel::query()->where('actor_id', $actorId)->orderBy('name')->get());
    }

    /** @return array{kind: ?string, label: ?PlannerLabel, color: string, icon: string} */
    public function of(Plan $plan): array
    {
        $metadata = is_array($plan->metadata) ? $plan->metadata : [];
        $kind = is_string($metadata['kind'] ?? null) && PlanKinds::valid($metadata['kind']) ? $metadata['kind'] : null;
        $label = is_string($metadata['label_uuid'] ?? null) ? $this->labels->get($metadata['label_uuid']) : null;
        $color = PlanKinds::normalizeColor($metadata['color'] ?? null)
            ?? ($label instanceof PlannerLabel ? $label->color : null)
            ?? PlanKinds::color($kind);

        return ['kind' => $kind, 'label' => $label, 'color' => $color, 'icon' => PlanKinds::icon($kind)];
    }

    /** @return Collection<string, PlannerLabel> */
    public function labels(): Collection
    {
        return $this->labels;
    }
}
