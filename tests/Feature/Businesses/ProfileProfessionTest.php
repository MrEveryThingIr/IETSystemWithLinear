<?php

namespace Tests\Feature\Businesses;

use App\Models\ActorProfession;
use App\Models\Profession;
use App\Models\User;
use Database\Seeders\ProfessionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class ProfileProfessionTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_user_can_register_profession_without_joining_a_group(): void
    {
        $this->seed(ProfessionSeeder::class);

        $user = User::factory()->create();
        $actor = $user->actor()->create();
        $this->publishSurfaces($user, ['profile']);

        $plumber = Profession::query()->where('code', 'plumber')->sole();

        $this->actingAs($user)
            ->post(route('profile.professions.store'), [
                'profession_id' => $plumber->getKey(),
                'level' => 'advanced',
                'years_experience' => 8,
                'visibility' => 'public',
                'is_primary' => 1,
            ])
            ->assertRedirect();

        $record = ActorProfession::query()->sole();

        $this->assertSame($actor->getKey(), $record->actor_id);
        $this->assertSame($plumber->getKey(), $record->profession_id);
        $this->assertSame('advanced', $record->level);
        $this->assertTrue($record->is_primary);
    }
}
