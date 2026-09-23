<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Models\PlatformAccessGrant;
use App\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_alpha_keeps_ordinary_navigation_focused_on_intents_and_profile(): void
    {
        config()->set('release.profile', 'office_alpha');

        $actor = Actor::factory()->create();

        $this->withoutVite()
            ->actingAs($actor->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('intents.index'), false)
            ->assertSee(route('profile.edit'), false)
            ->assertDontSee(route('groups.index'), false)
            ->assertDontSee(route('contexts.personal'), false)
            ->assertDontSee(route('actors.index'), false);
    }

    public function test_office_alpha_still_exposes_access_invitation_management_to_authorized_admins(): void
    {
        config()->set('release.profile', 'office_alpha');

        $administrator = Actor::factory()->create();
        PlatformAccessGrant::factory()->for($administrator->user)->create([
            'role' => PlatformRole::Superadmin,
        ]);

        $this->withoutVite()
            ->actingAs($administrator->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('platform.access-invitations'), false);
    }

    public function test_full_profile_restores_advanced_navigation_without_changing_routes(): void
    {
        config()->set('release.profile', 'full');

        $actor = Actor::factory()->create();

        $this->withoutVite()
            ->actingAs($actor->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('groups.index'), false)
            ->assertSee(route('manual'), false)
            ->assertSee(route('contexts.personal'), false);

        $this->actingAs($actor->user)
            ->get(route('groups.index'))
            ->assertOk();
    }
}
