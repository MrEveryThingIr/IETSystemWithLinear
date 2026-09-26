<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentEvidenceReference;
use App\Actions\Contexts\CreateContextContent;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\Livewire\Planner\Create as PlannerCreate;
use App\Livewire\Planner\Index as PlannerIndex;
use App\Livewire\Planner\Show as PlannerShow;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Concept;
use App\Models\Plan;
use App\Models\Relationship;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\RelationshipStatus;
use App\Support\ContextTimeline;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PlannerExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_plan_is_created_and_visible_in_today_list_and_calendar_views(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 06:00:00 UTC');

        try {
            $bob = Actor::factory()->create();
            $bob->user->forceFill(['timezone' => 'Europe/Berlin'])->save();

            Livewire::actingAs($bob->user)
                ->test(PlannerCreate::class)
                ->set('title', 'Dentist appointment')
                ->set('frequency', 'once')
                ->set('startsOn', '2026-09-25')
                ->set('startTime', '10:00')
                ->set('durationMinutes', 60)
                ->set('reminderOffsets', '60, 15')
                ->call('save')
                ->assertHasNoErrors();

            $plan = Plan::query()->with('occurrences')->sole();
            $this->assertSame('Dentist appointment', $plan->title);
            $this->assertSame(1, $plan->occurrences->count());

            Livewire::actingAs($bob->user)
                ->test(PlannerIndex::class)
                ->assertSee('Dentist appointment')
                ->set('view', 'list')
                ->assertSee('Dentist appointment')
                ->set('view', 'calendar')
                ->set('month', '2026-09')
                ->assertSee('Dentist appointment');

            $this->actingAs($bob->user)
                ->get(route('planner.show', $plan))
                ->assertOk()
                ->assertSee('Dentist appointment');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_active_relationship_can_seed_selected_workdays_without_changing_relationship_authority(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $relationship = $this->activeRelationship($alice, $bob);
        $relationshipEvents = $relationship->events()->count();
        $context = $relationship->contextBinding->context;

        Livewire::actingAs($alice->user)
            ->withQueryParams(['context' => $context->uuid])
            ->test(PlannerCreate::class)
            ->assertSet('contextUuid', $context->uuid)
            ->set('title', 'Riverside selected workdays')
            ->set('frequency', 'selected_dates')
            ->set('selectedDates', '2026-09-25, 2026-09-27, 2026-10-02')
            ->set('startTime', '08:00')
            ->set('durationMinutes', 540)
            ->set('reminderOffsets', '60')
            ->call('save')
            ->assertHasNoErrors();

        $plan = Plan::query()->with(['participants', 'occurrences'])->sole();

        $this->assertSame('relationship', $plan->origin_type);
        $this->assertSame($relationship->uuid, $plan->origin_uuid);
        $this->assertSame(2, $plan->participants->count());
        $this->assertSame(3, $plan->occurrences->count());
        $this->assertSame($relationshipEvents, $relationship->fresh()->events()->count());
        $this->assertSame(RelationshipStatus::Active, $relationship->fresh()->status);
        $this->assertDatabaseCount('group_memberships', 0);

        $this->actingAs($alice->user)
            ->get(route('relationships.show', $relationship))
            ->assertOk()
            ->assertSee(route('planner.index', ['context' => $context->uuid]), false);
    }

    public function test_occurrence_execution_and_existing_context_evidence_are_available_from_plan_page(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 06:00:00 UTC');

        try {
            $bob = Actor::factory()->create();
            $context = app(EnsurePersonalContext::class)->execute($bob->user);
            $plan = app(CreatePlan::class)->execute(
                $context,
                $bob->user,
                'Study session',
                timezone: 'Europe/Berlin',
            );
            $rule = app(CreatePlanScheduleRule::class)->execute(
                $plan,
                $bob->user,
                PlanScheduleFrequency::Once,
                '2026-09-25',
                '08:00',
                60,
            );
            $occurrence = $rule->occurrences()->sole();

            $asset = Asset::factory()->create([
                'context_id' => $context->id,
                'group_space_id' => null,
                'uploaded_by_actor_id' => $bob->id,
                'original_filename' => 'study-proof.jpg',
            ]);

            $definition = SpaceContentDefinition::factory()->create([
                'context_id' => $context->id,
                'group_space_id' => null,
                'created_by_actor_id' => $bob->id,
                'status' => 'active',
            ]);
            SpaceContentDefinitionVersion::factory()->published()->create([
                'space_content_definition_id' => $definition->id,
                'created_by_actor_id' => $bob->id,
            ]);
            $content = app(CreateContextContent::class)->execute(
                $context,
                $definition,
                $bob->user,
                'Study evidence note',
                ['summary' => 'Exact evidence'],
            );
            $content = app(PublishSpaceContent::class)->execute($content, $bob->user);
            $revision = $content->activeRevisionRecord();
            $this->assertNotNull($revision);
            $reference = app(CreateContentEvidenceReference::class)->execute(
                $content,
                $revision,
                $bob->user,
            );

            Livewire::actingAs($bob->user)
                ->test(PlannerShow::class, ['plan' => $plan])
                ->call('chooseEvidenceOccurrence', $occurrence->id)
                ->set('assetIds', [$asset->id])
                ->set('evidenceReferenceIds', [$reference->id])
                ->call('attachEvidence')
                ->assertHasNoErrors()
                ->assertSee('study-proof.jpg')
                ->assertSee('Study evidence note')
                ->call('startOccurrence', $occurrence->id);

            $this->assertSame(PlanOccurrenceStatus::InProgress, $occurrence->fresh()->status);

            CarbonImmutable::setTestNow('2026-09-25 07:10:00 UTC');

            Livewire::actingAs($bob->user)
                ->test(PlannerShow::class, ['plan' => $plan])
                ->call('completeOccurrence', $occurrence->id)
                ->assertHasNoErrors();

            $occurrence = $occurrence->fresh();
            $this->assertSame(PlanOccurrenceStatus::Completed, $occurrence->status);
            $this->assertSame('2026-09-25 06:00:00', $occurrence->actual_start_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertSame('2026-09-25 07:10:00', $occurrence->actual_end_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertDatabaseHas('plan_occurrence_assets', [
                'plan_occurrence_id' => $occurrence->id,
                'asset_id' => $asset->id,
            ]);
            $this->assertDatabaseHas('plan_occurrence_evidence_references', [
                'plan_occurrence_id' => $occurrence->id,
                'content_evidence_reference_id' => $reference->id,
            ]);

            $timeline = app(ContextTimeline::class)->entries($context, $bob->user);
            $this->assertTrue($timeline->contains(fn ($entry): bool => $entry->kind === 'planner'));
            $this->assertTrue($timeline->contains(
                fn ($entry): bool => str_contains($entry->url, route('planner.show', $plan)),
            ));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_calendar_can_seed_an_exact_future_date_and_minute_without_materializing_a_fake_start(): void
    {
        $bob = Actor::factory()->create();
        $bob->user->forceFill([
            'timezone' => 'Europe/Berlin',
            'locale' => 'fa',
        ])->save();

        Livewire::actingAs($bob->user)
            ->withQueryParams([
                'view' => 'calendar',
                'date' => '2037-05-17',
            ])
            ->test(PlannerIndex::class)
            ->assertSet('view', 'calendar')
            ->assertSet('selectedDate', '2037-05-17')
            ->assertSet('month', '2037-05');

        Livewire::actingAs($bob->user)
            ->withQueryParams([
                'date' => '2037-05-17',
                'time' => '22:17',
            ])
            ->test(PlannerCreate::class)
            ->assertSet('startsOn', '2037-05-17')
            ->assertSet('startTime', '22:17')
            ->set('title', 'Long-horizon life plan')
            ->set('frequency', 'once')
            ->set('durationMinutes', 60)
            ->call('save')
            ->assertHasNoErrors();

        $occurrence = Plan::query()->with('occurrences')->sole()->occurrences->sole();

        $this->assertSame('2037-05-17', $occurrence->local_date->format('Y-m-d'));
        $this->assertSame(PlanOccurrenceStatus::Scheduled, $occurrence->status);
        $this->assertNull($occurrence->actual_start_at);
        $this->assertNull($occurrence->actual_end_at);
    }

    public function test_planner_can_upload_and_attach_new_evidence_from_occurrence_page(): void
    {
        Storage::fake('local');

        $bob = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($bob->user);
        $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Evidence-ready plan', timezone: 'UTC');
        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $bob->user,
            PlanScheduleFrequency::Once,
            '2026-09-25',
            '08:00',
            60,
        );
        $occurrence = $rule->occurrences()->sole();
        $upload = UploadedFile::fake()->create('proof.txt', 1, 'text/plain');

        Livewire::actingAs($bob->user)
            ->test(PlannerShow::class, ['plan' => $plan])
            ->call('chooseEvidenceOccurrence', $occurrence->id)
            ->set('evidenceUpload', $upload)
            ->set('evidenceUploadRightsStatus', 'owned')
            ->call('attachEvidence')
            ->assertHasNoErrors()
            ->assertSee('proof.txt');

        $asset = Asset::query()->where('context_id', $context->id)->where('original_filename', 'proof.txt')->sole();

        $this->assertDatabaseHas('plan_occurrence_assets', [
            'plan_occurrence_id' => $occurrence->id,
            'asset_id' => $asset->id,
            'added_by_actor_id' => $bob->id,
        ]);
    }

    public function test_outsider_cannot_open_relationship_plan(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $relationship = $this->activeRelationship($alice, $bob);

        $plan = app(CreatePlan::class)->execute(
            $relationship->contextBinding->context,
            $alice->user,
            'Private relationship activity',
            participants: [['actor' => $bob, 'role' => 'worker']],
        );

        $this->actingAs($outsider->user)
            ->get(route('planner.show', $plan))
            ->assertForbidden();

        $this->actingAs($bob->user)
            ->get(route('planner.show', $plan))
            ->assertOk();
    }

    private function activeRelationship(Actor $creator, Actor $invitee): Relationship
    {
        $relationship = app(CreateRelationship::class)->execute(
            $creator->user,
            Concept::factory()->create(),
            'client',
            [['actor' => $invitee, 'role' => 'worker']],
        );

        return app(RespondToRelationship::class)->execute($relationship, $invitee->user, true);
    }
}
