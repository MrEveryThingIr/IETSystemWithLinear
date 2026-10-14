<?php

namespace App\Support;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Actor;
use App\Models\Group;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * What each group role may do on a piece of content, as defined by the
 * content's author (interaction_settings.role_rules). Without rules every
 * participant may use every annotation kind and only editors may endorse.
 */
class ContentRolePolicy
{
    public function __construct(
        private readonly ContentInteractionSettings $settings,
        private readonly GroupRoleProvisioner $roles,
    ) {}

    public function group(SpaceContent $content): ?Group
    {
        $content->loadMissing('space.group');

        return $content->space?->group;
    }

    public function isRestricted(SpaceContent $content): bool
    {
        return $this->settings->roleRules($content) !== [];
    }

    /** @return list<string> annotation kinds this user may add (top-level and replies) */
    public function allowedKinds(SpaceContent $content, User $user): array
    {
        if (! $this->isRestricted($content) || Gate::forUser($user)->allows('update', $content)) {
            return SpaceContentAnnotation::KINDS;
        }

        $kinds = [];
        foreach ($this->heldRules($content, $user) as $rule) {
            $kinds = [...$kinds, ...$rule['kinds']];
        }

        return array_values(array_intersect(SpaceContentAnnotation::KINDS, array_unique($kinds)));
    }

    public function mayUseKind(SpaceContent $content, User $user, string $kind): bool
    {
        return in_array($kind, $this->allowedKinds($content, $user), true);
    }

    /** Endorsing an answer is the teacher's "this is right" vote. */
    public function canEndorse(SpaceContent $content, User $user): bool
    {
        if (Gate::forUser($user)->allows('update', $content)) {
            return true;
        }

        foreach ($this->heldRules($content, $user) as $rule) {
            if ($rule['endorse']) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{kinds: list<string>, endorse: bool}> */
    private function heldRules(SpaceContent $content, User $user): array
    {
        $group = $this->group($content);
        $actor = $user->actor;
        if (! $group instanceof Group || ! $actor instanceof Actor) {
            return [];
        }

        $rules = $this->settings->roleRules($content);

        return array_values(array_intersect_key($rules, array_flip($this->roles->roleIds($actor, $group))));
    }
}
