<?php

namespace Database\Factories\Farsi;

use App\Models\GroupMembership;
use App\Models\GroupRoleChangeRequest;
use Database\Factories\GroupRoleChangeRequestFactory as BaseGroupRoleChangeRequestFactory;
use Spatie\Permission\Models\Role;

class GroupRoleChangeRequestFactory extends BaseGroupRoleChangeRequestFactory
{
    protected $model = GroupRoleChangeRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_id' => GroupMembershipFactory::new(),
            'group_id' => fn (array $attributes): int => GroupMembership::query()->findOrFail($attributes['membership_id'])->group_id,
            'requested_role_id' => fn (array $attributes): int => Role::query()->create([
                'group_id' => $attributes['group_id'],
                'name' => 'نقش درخواستی '.fake('fa_IR')->unique()->numerify('#####'),
                'guard_name' => 'web',
            ])->id,
            'status' => 'pending',
            'reviewed_by_actor_id' => null,
            'reviewed_at' => null,
            'review_locked_at' => null,
        ];
    }
}
