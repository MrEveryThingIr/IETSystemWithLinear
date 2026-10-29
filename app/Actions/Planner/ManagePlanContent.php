<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanContentLink;
use App\Models\PlanOccurrence;
use App\Models\SpaceContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Links Content to a Plan or to one of its occurrences, and removes the link. Linking only records a pointer: it never
 * completes an occurrence, never publishes the Content and never changes who can read either. A person may link their own
 * Content (they must be able to edit it) to a Plan they take part in.
 */
class ManagePlanContent
{
    public function link(User $user, Plan $plan, SpaceContent $content, ?PlanOccurrence $occurrence = null): PlanContentLink
    {
        Gate::forUser($user)->authorize('participate', $plan);
        Gate::forUser($user)->authorize('update', $content);
        abort_if($occurrence !== null && (int) $occurrence->plan_id !== (int) $plan->id, 404);
        $actor = User::query()->with('actor')->findOrFail($user->id)->actor;
        abort_unless($actor instanceof Actor, 403);

        return DB::transaction(function () use ($plan, $content, $occurrence, $actor): PlanContentLink {
            Plan::query()->lockForUpdate()->findOrFail($plan->id);

            return PlanContentLink::query()->firstOrCreate(
                ['plan_id' => $plan->id, 'plan_occurrence_id' => $occurrence?->id, 'space_content_id' => $content->id],
                ['linked_by_actor_id' => $actor->id],
            );
        }, 3);
    }

    /** Whoever linked it, the Content's editors and the Plan's managers may take the link away. */
    public function unlink(User $user, PlanContentLink $link): void
    {
        $link->loadMissing('plan', 'content');
        $actor = User::query()->with('actor')->findOrFail($user->id)->actor;
        abort_unless(
            ($actor instanceof Actor && (int) $link->linked_by_actor_id === (int) $actor->id)
            || Gate::forUser($user)->allows('manage', $link->plan)
            || Gate::forUser($user)->allows('update', $link->content),
            403,
        );
        $link->delete();
    }
}
