<?php

namespace App\Actions\Content;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Group;
use App\Models\SpaceContent;
use App\Models\User;
use App\Support\ContentInteractionSettings;
use App\Support\ContentRolePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** The content's author decides what each group role may do on it. */
class UpdateContentRoleRules
{
    public function __construct(
        private readonly ContentInteractionSettings $settings,
        private readonly ContentRolePolicy $policy,
        private readonly GroupRoleProvisioner $roles,
    ) {}

    /** @param array<int|string, mixed> $rules role id => {kinds: list<string>, endorse: bool}; empty clears all rules */
    public function execute(SpaceContent $content, User $user, array $rules): SpaceContent
    {
        return DB::transaction(function () use ($content, $user, $rules): SpaceContent {
            /** @var SpaceContent $current */
            $current = SpaceContent::query()->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);

            $group = $this->policy->group($current);
            abort_unless($group instanceof Group, 422, 'Role rules apply only to content shared in a group.');

            $groupRoleIds = $this->roles->roles($group)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $normalized = array_intersect_key(
                $this->settings->normalizeRoleRules($rules),
                array_flip($groupRoleIds),
            );

            $settings = $this->settings->for($current);
            unset($settings['role_rules']);
            if ($normalized !== []) {
                $settings['role_rules'] = $normalized;
            }

            $current->update(['interaction_settings' => $settings]);

            return $current->refresh();
        });
    }
}
