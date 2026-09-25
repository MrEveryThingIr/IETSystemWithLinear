<?php

namespace App\Support;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\ActorProfileIntent;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\SpaceContent;
use App\Models\Submission;
use App\Models\User;
use App\Policies\ActorProfileIntentPolicy;
use App\ProfileIntentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class GroupCommunityProjection
{
    public function __construct(
        private readonly ActorProfileIntentPolicy $intentPolicy,
        private readonly GroupRoleProvisioner $roles,
    ) {}

    /**
     * @return array{
     *   spaces: Collection<int, GroupSpace>,
     *   memberships: Collection<int, GroupMembership>,
     *   membershipRoles: Collection<int, string>,
     *   contents: Collection<int, SpaceContent>,
     *   intents: Collection<int, ActorProfileIntent>,
     *   plans: Collection<int, Plan>,
     *   submissions: Collection<int, Submission>,
     *   relationships: Collection<int, Relationship>
     * }
     */
    public function build(Group $group, User $user): array
    {
        Gate::forUser($user)->authorize('view', $group);
        $current = $user->fresh(['actor']);
        abort_unless($current instanceof User && $current->actor !== null, 403);

        $spaces = $group->spaces()
            ->where('status', 'active')
            ->with('contextBinding.context')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->filter(fn ($space): bool => Gate::forUser($current)->allows('view', $space))
            ->values();

        $contextIds = $spaces
            ->map(fn ($space): ?int => $space->contextBinding?->context_id)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $memberships = $group->memberships()
            ->where('status', 'active')
            ->with(['actor.user', 'actor.profile'])
            ->orderBy('id')
            ->get();

        $memberActorIds = $memberships->pluck('actor_id')->map(fn (mixed $id): int => (int) $id)->values();
        $otherMemberActorIds = $memberActorIds
            ->reject(fn (int $id): bool => $id === (int) $current->actor->id)
            ->values();

        $membershipRoles = $memberships->mapWithKeys(
            fn (GroupMembership $membership): array => [
                $membership->id => $this->roles
                    ->roleNames($membership->actor, $group)
                    ->join(', '),
            ],
        );

        $contents = $contextIds->isEmpty()
            ? collect()
            : SpaceContent::query()
                ->whereIn('context_id', $contextIds->all())
                ->where('status', 'published')
                ->with([
                    'context',
                    'activeRevision',
                    'author.user',
                    'blueprintVersion.blueprint',
                    'space',
                ])
                ->latest('published_at')
                ->limit(60)
                ->get()
                ->filter(fn (SpaceContent $content): bool => Gate::forUser($current)->allows('view', $content))
                ->take(12)
                ->values();

        $intents = $memberActorIds->isEmpty()
            ? collect()
            : ActorProfileIntent::query()
                ->where('status', ProfileIntentStatus::Active->value)
                ->whereHas('profile', fn ($query) => $query->whereIn('actor_id', $memberActorIds->all()))
                ->with(['concept.labels', 'profile.actor.user', 'profile.displayImage.asset'])
                ->latest('updated_at')
                ->limit(100)
                ->get()
                ->filter(fn (ActorProfileIntent $intent): bool => $this->intentPolicy->view($current, $intent))
                ->take(12)
                ->values();

        $plans = $contextIds->isEmpty()
            ? collect()
            : Plan::query()
                ->whereIn('context_id', $contextIds->all())
                ->with(['context', 'creator.user', 'participants.actor.user'])
                ->latest('updated_at')
                ->limit(60)
                ->get()
                ->filter(fn (Plan $plan): bool => Gate::forUser($current)->allows('view', $plan))
                ->take(12)
                ->values();

        $reviewableContextIds = $spaces
            ->map(fn ($space) => $space->contextBinding?->context)
            ->filter()
            ->filter(fn ($context): bool => Gate::forUser($current)->allows('reviewInteractions', $context))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $submissions = $reviewableContextIds->isEmpty()
            ? collect()
            : Submission::query()
                ->whereIn('context_id', $reviewableContextIds->all())
                ->where('status', Submission::STATUS_SUBMITTED)
                ->with(['context', 'submitter.user', 'definitionVersion.definition'])
                ->latest('submitted_at')
                ->limit(60)
                ->get()
                ->filter(fn (Submission $submission): bool => Gate::forUser($current)->allows('view', $submission))
                ->take(12)
                ->values();

        $relationships = $otherMemberActorIds->isEmpty()
            ? collect()
            : Relationship::query()
                ->whereHas('participants', fn ($query) => $query->where('actor_id', $current->actor->id))
                ->whereHas('participants', fn ($query) => $query->whereIn('actor_id', $otherMemberActorIds->all()))
                ->with(['purposeConcept.labels', 'participants.actor.user'])
                ->latest('updated_at')
                ->limit(60)
                ->get()
                ->filter(fn (Relationship $relationship): bool => Gate::forUser($current)->allows('view', $relationship))
                ->take(12)
                ->values();

        return compact(
            'spaces',
            'memberships',
            'membershipRoles',
            'contents',
            'intents',
            'plans',
            'submissions',
            'relationships',
        );
    }
}
