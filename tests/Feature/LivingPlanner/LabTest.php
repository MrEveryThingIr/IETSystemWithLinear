<?php

namespace Tests\Feature\LivingPlanner;

use App\Livewire\Planner\Lab;
use App\Models\Actor;
use App\Models\Planner\Purpose;
use App\Models\PlanOccurrence;
use App\Services\Surfaces\FeatureSurfaceGrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LabTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_lab_tabs_render_for_an_active_actor_without_plans(): void
    {
        $user = Actor::factory()->create()->user;
        app(FeatureSurfaceGrantService::class)->sync($user, ['planner'], null);

        foreach (['today', 'routines', 'purposes', 'insights', 'history'] as $tab) {
            Livewire::actingAs($user)->test(Lab::class, ['tab' => $tab])
                ->assertOk()
                ->assertSeeHtml('data-living-planner');
        }
    }

    public function test_unknown_tab_is_not_available(): void
    {
        $user = Actor::factory()->create()->user;
        app(FeatureSurfaceGrantService::class)->sync($user, ['planner'], null);
        Livewire::actingAs($user)
            ->test(Lab::class, ['tab' => 'unknown'])
            ->assertNotFound();
    }

    public function test_personal_activity_creation_uses_configured_execution_policy(): void
    {
        $user = Actor::factory()->create()->user;
        app(FeatureSurfaceGrantService::class)->sync($user, ['planner'], null);
        Livewire::actingAs($user)->test(Lab::class)
            ->set('newTitle', 'A small morning walk')->set('newFrequency', 'once')
            ->set('policyStyle', 'flexible')->set('revisionDate', now()->addDay()->toDateString())
            ->set('revisionTime', '08:00')->set('revisionMinutes', 20)->call('createActivity')->assertHasNoErrors()
            ->assertSee('A small morning walk');
        $occurrence = PlanOccurrence::query()->sole();
        $this->assertSame('flexible', $occurrence->policy_snapshot['window_style']);
        $this->assertSame(20, $occurrence->scheduleRule->duration_minutes);
    }

    public function test_purpose_editing_and_archiving_preserve_private_ownership(): void
    {
        $user = Actor::factory()->create()->user;
        app(FeatureSurfaceGrantService::class)->sync($user, ['planner'], null);
        $component = Livewire::actingAs($user)->test(Lab::class, ['tab' => 'purposes'])
            ->set('purposeTitle', 'Sleep well')->set('purposeTarget', now()->addMonth()->toDateString())->call('createPurpose')->assertHasNoErrors();
        $purpose = Purpose::query()->sole();
        $component->call('editPurpose', $purpose->uuid)->set('purposeTitle', 'Rest consistently')->call('createPurpose')->assertHasNoErrors();
        $this->assertSame('Rest consistently', $purpose->fresh()->title);
        $component->call('archivePurpose', $purpose->uuid)->assertHasNoErrors();
        $this->assertSame('archived', $purpose->fresh()->status);
        $this->assertSame($user->actor->id, $purpose->fresh()->owner_actor_id);
    }

    public function test_unpublished_actor_cannot_open_the_lab(): void
    {
        Livewire::actingAs(Actor::factory()->create()->user)->test(Lab::class)->assertForbidden();
    }
}
