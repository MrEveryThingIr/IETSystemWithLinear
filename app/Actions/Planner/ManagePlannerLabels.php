<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\Planner\PlannerLabel;
use App\Models\User;
use App\Support\Planner\PlanKinds;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A person's own plan labels ("Gym", "Clients") with colours, and the label,
 * kind and colour of a plan. Labels are private: they only change how plans
 * look to their owner.
 */
class ManagePlannerLabels
{
    public function create(User $user, string $name, string $color): PlannerLabel
    {
        $actor = $this->actor($user);
        $name = Str::squish($name);
        $color = PlanKinds::normalizeColor($color) ?? PlanKinds::DEFAULT_COLOR;
        if ($name === '' || mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['newLabelName' => __('planner.labels.invalid')]);
        }

        return PlannerLabel::query()->firstOrCreate(
            ['actor_id' => $actor->id, 'name' => $name],
            ['color' => $color],
        );
    }

    public function update(PlannerLabel $label, User $user, string $name, string $color): PlannerLabel
    {
        abort_unless((int) $label->actor_id === (int) $this->actor($user)->id, 403);
        $name = Str::squish($name);
        if ($name === '' || mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['labelName' => __('planner.labels.invalid')]);
        }
        $label->update(['name' => $name, 'color' => PlanKinds::normalizeColor($color) ?? $label->color]);

        return $label;
    }

    public function delete(PlannerLabel $label, User $user): void
    {
        abort_unless((int) $label->actor_id === (int) $this->actor($user)->id, 403);
        $label->delete();
    }

    /** Kind, label and colour of a plan ('' clears a value). */
    public function setAppearance(Plan $plan, User $user, ?string $kind, ?string $labelUuid, ?string $color): Plan
    {
        Gate::forUser($user)->authorize('manage', $plan);
        $actor = $this->actor($user);
        $metadata = is_array($plan->metadata) ? $plan->metadata : [];

        if ($kind !== null) {
            $metadata['kind'] = PlanKinds::valid($kind) ? $kind : null;
        }
        if ($labelUuid !== null) {
            $metadata['label_uuid'] = $labelUuid !== '' && PlannerLabel::query()->where('uuid', $labelUuid)->where('actor_id', $actor->id)->exists()
                ? $labelUuid
                : null;
        }
        if ($color !== null) {
            $metadata['color'] = PlanKinds::normalizeColor($color);
        }

        $plan->forceFill(['metadata' => array_filter($metadata, fn (mixed $value): bool => $value !== null)])->save();

        return $plan;
    }

    private function actor(User $user): Actor
    {
        $actor = User::query()->with('actor')->findOrFail($user->id)->actor;
        abort_unless($actor instanceof Actor, 403);

        return $actor;
    }
}
