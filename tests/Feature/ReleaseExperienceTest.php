<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Models\ActorProfile;
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

    public function test_account_menu_renders_profile_identity_without_template_leaks(): void
    {
        config()->set('release.profile', 'full');

        $actor = Actor::factory()->create();
        ActorProfile::factory()->create([
            'actor_id' => $actor->id,
            'display_name' => 'Safdar Developer',
        ]);

        $user = $actor->user;
        $user->forceFill(['locale' => 'fa'])->save();

        $appearanceLabel = (require lang_path('fa/ui.php'))['appearance']['label'];

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Safdar Developer')
            ->assertSee('@'.$user->username)
            ->assertSee($user->email)
            ->assertSee($appearanceLabel)
            ->assertDontSee('{{ $accountUser->username }}', false)
            ->assertDontSee('ui.actions.cancel');
    }

    public function test_persian_publish_surfaces_render_without_raw_translation_keys(): void
    {
        config()->set('release.profile', 'full');

        $actor = Actor::factory()->create();
        $user = $actor->user;
        $user->forceFill(['locale' => 'fa'])->save();

        $faUi = require lang_path('fa/ui.php');

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('proposals.create'))
            ->assertOk()
            ->assertSee($faUi['common']['cancel'])
            ->assertDontSee('ui.actions.cancel');

        $this->withoutVite()
            ->actingAs($user)
            ->get(route('platform.access'))
            ->assertOk()
            ->assertSee($faUi['platform_access']['title'])
            ->assertSee($faUi['platform_access']['create_groups'])
            ->assertDontSee('Platform access')
            ->assertDontSee('Request group-creation access');
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
