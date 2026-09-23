<?php
namespace Database\Factories;
use App\Models\AccessInvitation;
use App\Models\Actor;
use Illuminate\Database\Eloquent\Factories\Factory;
/** @extends Factory<AccessInvitation> */
class AccessInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invited_by_actor_id'=>Actor::factory(),
            'email'=>fake()->unique()->safeEmail(),
            'token'=>AccessInvitation::issueToken(),
            'expires_at'=>now()->addDays(14),
            'max_uses'=>1,
            'uses_count'=>0,
        ];
    }
}
