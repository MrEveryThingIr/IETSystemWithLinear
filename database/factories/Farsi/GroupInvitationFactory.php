<?php

namespace Database\Factories\Farsi;

use App\Models\GroupInvitation;
use Database\Factories\GroupInvitationFactory as BaseGroupInvitationFactory;
use Illuminate\Support\Str;

class GroupInvitationFactory extends BaseGroupInvitationFactory
{
    protected $model = GroupInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => GroupFactory::new(),
            'invited_by_actor_id' => ActorFactory::new(),
            'email' => null,
            'content' => fake('fa_IR')->realText(120),
            'token' => Str::random(64),
            'expires_at' => now()->addWeeks(2),
            'max_uses' => 5,
            'uses_count' => 0,
            'revoked_at' => null,
        ];
    }
}
