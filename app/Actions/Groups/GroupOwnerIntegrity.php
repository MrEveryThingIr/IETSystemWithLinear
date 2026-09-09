<?php

namespace App\Actions\Groups;

use App\Exceptions\CannotLeaveGroupWithoutOwner;
use App\Models\Actor;
use App\Models\Group;
use Closure;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class GroupOwnerIntegrity
{
    /**
     * @template T
     *
     * @param  Closure(): T  $mutation
     * @return T
     */
    public function execute(Group $group, Closure $mutation): mixed
    {
        return DB::transaction(function () use ($group, $mutation): mixed {
            Group::query()->whereKey($group->getKey())->lockForUpdate()->firstOrFail();

            $result = $mutation();

            if (! $this->hasActiveOwner($group)) {
                throw new CannotLeaveGroupWithoutOwner;
            }

            return $result;
        }, 3);
    }

    private function hasActiveOwner(Group $group): bool
    {
        $groupId = (int) $group->getKey();
        $actorMorphClass = (new Actor)->getMorphClass();

        return DB::table('group_memberships')
            ->join('model_has_roles', function (JoinClause $join) use ($actorMorphClass, $groupId): void {
                $join->on('model_has_roles.model_id', '=', 'group_memberships.actor_id')
                    ->where('model_has_roles.model_type', '=', $actorMorphClass)
                    ->where('model_has_roles.group_id', '=', $groupId);
            })
            ->join('roles', function (JoinClause $join) use ($groupId): void {
                $join->on('roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.group_id', '=', $groupId)
                    ->where('roles.name', '=', 'Owner');
            })
            ->where('group_memberships.group_id', $groupId)
            ->where('group_memberships.status', 'active')
            ->exists();
    }
}
