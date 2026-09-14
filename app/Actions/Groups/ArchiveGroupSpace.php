<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveGroupSpace
{
    public function execute(GroupSpace $space, User $user): GroupSpace
    {
        return DB::transaction(function () use ($space, $user): GroupSpace {
            /** @var Group $lockedGroup */
            $lockedGroup = Group::query()->lockForUpdate()->findOrFail($space->group_id);

            /** @var GroupSpace $lockedSpace */
            $lockedSpace = GroupSpace::query()
                ->where('group_id', $lockedGroup->id)
                ->lockForUpdate()
                ->findOrFail($space->id);
            $lockedSpace->setRelation('group', $lockedGroup);

            Gate::forUser($user)->authorize('manage', $lockedSpace);
            abort_if($lockedSpace->is_default, 422, 'The default General Space cannot be archived.');

            $lockedSpace->status = 'archived';
            $lockedSpace->save();

            return $lockedSpace->refresh();
        }, attempts: 3);
    }
}
