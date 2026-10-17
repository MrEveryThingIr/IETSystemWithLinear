<?php

namespace App\Support;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Models\Actor;
use App\Models\Context;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The places a person works with Content: their personal space, the group
 * spaces and businesses they belong to, and anywhere they have authored.
 *
 * Every Context is still checked with the Context policy; this class only
 * decides which ones are worth checking, so the Content page never has to
 * scan every Context in the system.
 */
final class ContentWorkspaceContexts
{
    public function __construct(private readonly EnsurePersonalContext $personal) {}

    /** @return Collection<int, Context> */
    public function viewable(User $user): Collection
    {
        $actor = $user->actor;
        if (! $actor instanceof Actor) {
            return collect();
        }

        $personal = $this->personal->execute($user);

        return Context::query()
            ->with(ContextPresenter::RELATIONS)
            ->where(function (Builder $query) use ($actor, $personal): void {
                $query->whereKey($personal->id)
                    ->orWhereHas('groupSpaceBinding.groupSpace.group.memberships', fn (Builder $membership) => $membership
                        ->where('actor_id', $actor->id)
                        ->where('status', 'active'))
                    ->orWhereHas('businessBinding.business.memberships', fn (Builder $membership) => $membership
                        ->where('actor_id', $actor->id)
                        ->where('status', 'active'))
                    ->orWhereIn('id', fn ($authored) => $authored
                        ->select('context_id')
                        ->from('space_contents')
                        ->where('author_actor_id', $actor->id)
                        ->whereNotNull('context_id'));
            })
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->filter(fn (Context $context): bool => Gate::forUser($user)->allows('view', $context))
            ->sortBy(fn (Context $context): string => ($context->id === $personal->id ? '0' : '1').mb_strtolower(app(ContextPresenter::class)->label($context)))
            ->values();
    }

    /** @return Collection<int, Context> */
    public function creatable(User $user): Collection
    {
        return $this->viewable($user)
            ->filter(fn (Context $context): bool => Gate::forUser($user)->allows('createContent', $context))
            ->values();
    }
}
