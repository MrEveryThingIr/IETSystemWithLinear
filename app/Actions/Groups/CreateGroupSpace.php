<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateGroupSpace
{
    public function execute(Group $group, User $user, string $name, string $accessMode): GroupSpace
    {
        $normalizedName = trim($name);
        $this->assertInput($normalizedName, $accessMode);

        return DB::transaction(function () use ($group, $user, $normalizedName, $accessMode): GroupSpace {
            /** @var Group $lockedGroup */
            $lockedGroup = Group::query()->lockForUpdate()->findOrFail($group->id);
            $actor = $this->currentActor($user);

            Gate::forUser($user)->authorize('manageSpaces', $lockedGroup);

            return GroupSpace::query()->create([
                'group_id' => $lockedGroup->id,
                'created_by_actor_id' => $actor->id,
                'name' => $normalizedName,
                'slug' => $this->uniqueSlug($lockedGroup, $normalizedName),
                'kind' => 'chat',
                'access_mode' => $accessMode,
                'status' => 'active',
                'is_default' => false,
            ]);
        }, attempts: 3);
    }

    private function currentActor(User $user): Actor
    {
        $current = User::query()->with('actor')->findOrFail($user->id);

        abort_unless(
            $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current->actor;
    }

    private function assertInput(string $name, string $accessMode): void
    {
        abort_if($name === '' || Str::length($name) > 120, 422, 'Space name must contain between 1 and 120 characters.');
        abort_unless(in_array($accessMode, ['group', 'restricted'], true), 422, 'Invalid Space access mode.');
    }

    private function uniqueSlug(Group $group, string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'space';
        }

        $slug = $base;
        $suffix = 2;

        while ($group->spaces()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
