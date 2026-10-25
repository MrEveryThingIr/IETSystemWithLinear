<?php

namespace Tests\Feature\Businesses;

use App\Livewire\Profile\Capabilities;
use App\Models\Actor;
use App\Models\ActorProfession;
use App\Models\Capability;
use App\Models\PlatformAccessGrant;
use App\Models\Profession;
use App\Models\User;
use App\PlatformRole;
use Database\Seeders\ProfessionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class ProfileProfessionTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    public function test_user_can_register_a_skill_without_joining_a_group(): void
    {
        $this->seed(ProfessionSeeder::class);
        // The shared categories are recorded as created by the platform administrator.
        $admin = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($admin->user)->create(['role' => PlatformRole::Superadmin]);

        $user = User::factory()->create();
        $actor = $user->actor()->create();
        $this->publishSurfaces($user, ['profile']);
        $plumber = Profession::query()->where('code', 'plumber')->sole();

        // The old form no longer writes; skills are capabilities now.
        $this->actingAs($user)
            ->post(route('profile.professions.store'), ['profession_id' => $plumber->getKey(), 'level' => 'advanced'])
            ->assertRedirect(route('profile.professions.index'));
        $this->assertSame(0, ActorProfession::query()->count());

        $this->actingAs($user)->get(route('profile.professions.index'))->assertRedirect(route('items.index').'#abilities');
        $this->actingAs($user)->get(route('items.index'))->assertOk();
        $plumbing = (int) DB::table('concepts')->where('slug', 'housing.plumbing')->value('id');
        $this->assertGreaterThan(0, $plumbing);
        $this->assertSame($plumbing, (int) $plumber->fresh()->concept_id);

        Livewire::actingAs($user)->test(Capabilities::class, ['embedded' => true])
            ->call('add')
            ->set('conceptId', $plumbing)
            ->set('level', 'advanced')
            ->set('yearsExperience', '8')
            ->set('visibility', 'public')
            ->set('isPrimary', true)
            ->call('save')
            ->assertHasNoErrors();

        $record = Capability::query()->sole();
        $this->assertSame($actor->getKey(), (int) $record->holder_actor_id);
        $this->assertSame('advanced', $record->level);
        $this->assertTrue($record->is_primary);
    }
}
