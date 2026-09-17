<?php

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UpdateGroupSpace
{
    public function execute(GroupSpace $space, User $user, string $name, string $accessMode): GroupSpace
    {
        $normalizedName = trim($name);
        abort_if($normalizedName === '' || Str::length($normalizedName) > 120, 422, 'Space name must contain between 1 and 120 characters.');
        abort_unless(in_array($accessMode, ['group', 'restricted'], true), 422, 'Invalid Space access mode.');

        return DB::transaction(function () use ($space, $user, $normalizedName, $accessMode): GroupSpace {
            /** @var Group $lockedGroup */
            $lockedGroup = Group::query()->lockForUpdate()->findOrFail($space->group_id);

            /** @var GroupSpace $lockedSpace */
            $lockedSpace = GroupSpace::query()
                ->where('group_id', $lockedGroup->id)
                ->lockForUpdate()
                ->findOrFail($space->id);
            $lockedSpace->setRelation('group', $lockedGroup);

            Gate::forUser($user)->authorize('manage', $lockedSpace);

            if ($lockedSpace->is_default) {
                abort_if(
                    $normalizedName !== $lockedSpace->name || $accessMode !== 'group',
                    422,
                    'The default General Space cannot be renamed or converted to restricted access.',
                );

                return $lockedSpace;
            }

            $lockedSpace->name = $normalizedName;
            $lockedSpace->slug = $this->uniqueSlug($lockedGroup, $lockedSpace, $normalizedName);
            $lockedSpace->access_mode = $accessMode;
            $lockedSpace->save();

            return $lockedSpace->refresh();
        }, attempts: 3);
    }

    private function uniqueSlug(Group $group, GroupSpace $space, string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'space';
        }

        $slug = $base;
        $suffix = 2;

        while (
            $group->spaces()
                ->where('slug', $slug)
                ->where('id', '!=', $space->id)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
