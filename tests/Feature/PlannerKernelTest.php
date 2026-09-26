<?php

namespace Tests\Feature;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\AttachPlanOccurrenceEvidence;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\MaterializePlanOccurrences;
use App\Actions\Planner\TransitionPlan;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Actions\Relationships\CreateRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Concept;
use App\Models\PlanOccurrence;
use App\Models\Relationship;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanScheduleRuleStatus;
use App\PlanStatus;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlannerKernelTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_one_time_plan_materializes_timezone_safe_occurrence_and_reminders(): void
    {
        $bob = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($bob->user);

        $plan = app(CreatePlan::class)->execute(
            $context,
            $bob->user,
            'Dentist appointment',
            timezone: 'Europe/Berlin',
        );

        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $bob->user,
            PlanScheduleFrequency::Once,
            '2026-09-25',
            '10:00',
            60,
            reminderOffsets: [60, 15, 15],
        );

        $occurrence = $rule->occurrences()->sole();

        $this->assertSame('2026-09-25', $occurrence->local_date->format('Y-m-d'));
        $this->assertSame('2026-09-25 08:00:00', $occurrence->scheduled_start_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-25 09:00:00', $occurrence->scheduled_end_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame([15, 60], $rule->reminders()->orderBy('minutes_before')->pluck('minutes_before')->all());
        $this->assertDatabaseHas('plan_participants', [
            'plan_id' => $plan->id,
            'actor_id' => $bob->id,
            'role' => 'owner',
        ]);
    }

    public function test_daily_schedule_preserves_local_clock_time_across_dst_transition(): void
    {
        $bob = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($bob->user);
        $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Nightly study', timezone: 'Europe/Berlin');

        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $bob->user,
            PlanScheduleFrequency::Daily,
            '2026-10-24',
            '08:00',
            45,
            occurrenceLimit: 3,
        );

        $starts = $rule->occurrences()->orderBy('scheduled_start_at')->get()
            ->map(fn (PlanOccurrence $occurrence): string => $occurrence->scheduled_start_at->utc()->format('Y-m-d H:i:s'))
            ->all();

        $this->assertSame([
            '2026-10-24 06:00:00',
            '2026-10-25 07:00:00',
            '2026-10-26 07:00:00',
        ], $starts);
    }

    public function test_weekly_class_and_non_consecutive_workdays_use_the_same_schedule_kernel(): void
    {
        $bob = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($bob->user);

        $classPlan = app(CreatePlan::class)->execute($context, $bob->user, 'Physics class', timezone: 'Europe/Berlin');
        $weekly = app(CreatePlanScheduleRule::class)->execute(
            $classPlan,
            $bob->user,
            PlanScheduleFrequency::Weekly,
            '2026-09-21',
            '18:30',
            90,
            weekdays: [1, 4],
            endsOn: '2026-10-04',
        );

        $this->assertSame(
            ['2026-09-21', '2026-09-24', '2026-09-28', '2026-10-01'],
            $weekly->occurrences()->orderBy('local_date')->pluck('local_date')->map(
                fn ($date): string => CarbonImmutable::parse($date)->format('Y-m-d'),
            )->all(),
        );

        $workPlan = app(CreatePlan::class)->execute($context, $bob->user, 'Selected construction days', timezone: 'Europe/Berlin');
        $selected = app(CreatePlanScheduleRule::class)->execute(
            $workPlan,
            $bob->user,
            PlanScheduleFrequency::SelectedDates,
            '2026-09-25',
            '08:00',
            540,
            selectedDates: ['2026-09-25', '2026-09-27', '2026-10-02'],
        );

        $this->assertSame(
            ['2026-09-25', '2026-09-27', '2026-10-02'],
            $selected->occurrences()->orderBy('local_date')->pluck('local_date')->map(
                fn ($date): string => CarbonImmutable::parse($date)->format('Y-m-d'),
            )->all(),
        );

        foreach ($selected->occurrences as $occurrence) {
            $this->assertSame(540.0, $occurrence->scheduled_start_at->diffInMinutes($occurrence->scheduled_end_at));
        }
    }


    public function test_future_occurrence_cannot_start_before_its_execution_window_and_completion_requires_start(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 18:00:00 UTC');

        try {
            $bob = Actor::factory()->create();
            $context = app(EnsurePersonalContext::class)->execute($bob->user);
            $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Night study', timezone: 'UTC');
            $rule = app(CreatePlanScheduleRule::class)->execute(
                $plan,
                $bob->user,
                PlanScheduleFrequency::Once,
                '2026-09-25',
                '22:00',
                60,
                windowBeforeMinutes: 15,
                windowAfterMinutes: 15,
            );
            $occurrence = $rule->occurrences()->sole();

            $this->assertSame('future', $occurrence->temporalPhase());
            $this->assertFalse($occurrence->canStartAt());

            try {
                app(TransitionPlanOccurrence::class)->start($occurrence, $bob->user);
                $this->fail('Future Occurrence started before its execution window.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }

            CarbonImmutable::setTestNow('2026-09-25 21:45:00 UTC');
            $occurrence = $occurrence->fresh();
            $this->assertSame('ready', $occurrence->temporalPhase());
            $this->assertTrue($occurrence->canStartAt());

            try {
                app(TransitionPlanOccurrence::class)->complete($occurrence, $bob->user);
                $this->fail('Scheduled Occurrence completed without an actual start.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }

            $occurrence = app(TransitionPlanOccurrence::class)->start($occurrence, $bob->user);
            $this->assertSame(PlanOccurrenceStatus::InProgress, $occurrence->status);

            CarbonImmutable::setTestNow('2026-09-25 23:20:00 UTC');
            $occurrence = app(TransitionPlanOccurrence::class)->complete($occurrence, $bob->user);
            $this->assertSame(PlanOccurrenceStatus::Completed, $occurrence->status);
            $this->assertSame('2026-09-25 21:45:00', $occurrence->actual_start_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertSame('2026-09-25 23:20:00', $occurrence->actual_end_at?->utc()->format('Y-m-d H:i:s'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_scheduled_occurrence_projects_future_ready_due_late_and_missed_phases(): void
    {
        $bob = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($bob->user);
        $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Night study', timezone: 'UTC');
        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $bob->user,
            PlanScheduleFrequency::Once,
            '2026-09-25',
            '22:00',
            60,
            windowBeforeMinutes: 15,
            windowAfterMinutes: 15,
        );
        $occurrence = $rule->occurrences()->sole();

        $this->assertSame('future', $occurrence->temporalPhase(CarbonImmutable::parse('2026-09-25 21:44:59 UTC')));
        $this->assertSame('ready', $occurrence->temporalPhase(CarbonImmutable::parse('2026-09-25 21:45:00 UTC')));
        $this->assertSame('due', $occurrence->temporalPhase(CarbonImmutable::parse('2026-09-25 22:30:00 UTC')));
        $this->assertSame('late', $occurrence->temporalPhase(CarbonImmutable::parse('2026-09-25 23:10:00 UTC')));
        $this->assertSame('missed', $occurrence->temporalPhase(CarbonImmutable::parse('2026-09-25 23:15:01 UTC')));
    }

    public function test_relationship_plan_requires_active_relationship_and_does_not_create_contract_authority(): void
    {
        $alice = Actor::factory()->create();
        $bob = Actor::factory()->create();
        $relationship = app(CreateRelationship::class)->execute(
            $alice->user,
            Concept::factory()->create(),
            'client',
            [['actor' => $bob, 'role' => 'worker']],
        );
        $context = $relationship->contextBinding->context;

        try {
            app(CreatePlan::class)->execute($context, $alice->user, 'Pending work');
            $this->fail('Proposed Relationship Context accepted Planner writes.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $relationship = app(RespondToRelationship::class)->execute($relationship, $bob->user, true);
        $eventCount = $relationship->events()->count();

        $plan = app(CreatePlan::class)->execute(
            $relationship->contextBinding->context,
            $alice->user,
            'Riverside work days',
            timezone: 'Europe/Berlin',
            participants: [['actor' => $bob, 'role' => 'worker']],
        );

        app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $alice->user,
            PlanScheduleFrequency::SelectedDates,
            '2026-09-25',
            '08:00',
            540,
            selectedDates: ['2026-09-25', '2026-09-27'],
        );

        $this->assertSame($eventCount, $relationship->fresh()->events()->count());
        $this->assertDatabaseMissing('group_memberships', [
            'actor_id' => $bob->id,
        ]);
        $this->assertSame(2, $plan->participants()->count());
    }

    public function test_occurrence_lifecycle_records_actual_time_and_same_context_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 06:00:00 UTC');

        try {
            $bob = Actor::factory()->create();
            $context = app(EnsurePersonalContext::class)->execute($bob->user);
            $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Study session', timezone: 'Europe/Berlin');
            $rule = app(CreatePlanScheduleRule::class)->execute(
                $plan,
                $bob->user,
                PlanScheduleFrequency::Once,
                '2026-09-25',
                '08:00',
                60,
            );
            $occurrence = $rule->occurrences()->sole();

            $occurrence = app(TransitionPlanOccurrence::class)->start($occurrence, $bob->user);
            $this->assertSame(PlanOccurrenceStatus::InProgress, $occurrence->status);
            $this->assertSame('2026-09-25 06:00:00', $occurrence->actual_start_at?->utc()->format('Y-m-d H:i:s'));

            CarbonImmutable::setTestNow('2026-09-25 07:05:00 UTC');
            $occurrence = app(TransitionPlanOccurrence::class)->complete($occurrence, $bob->user);

            $this->assertSame(PlanOccurrenceStatus::Completed, $occurrence->status);
            $this->assertSame('2026-09-25 07:05:00', $occurrence->actual_end_at?->utc()->format('Y-m-d H:i:s'));
            $this->assertNotNull($occurrence->completed_at);

            $asset = Asset::factory()->create([
                'context_id' => $context->id,
                'group_space_id' => null,
                'uploaded_by_actor_id' => $bob->id,
            ]);

            app(AttachPlanOccurrenceEvidence::class)->execute(
                $occurrence,
                $bob->user,
                assetIds: [$asset->id],
            );

            $this->assertDatabaseHas('plan_occurrence_assets', [
                'plan_occurrence_id' => $occurrence->id,
                'asset_id' => $asset->id,
                'added_by_actor_id' => $bob->id,
            ]);

            $other = Actor::factory()->create();
            $otherContext = app(EnsurePersonalContext::class)->execute($other->user);
            $foreignAsset = Asset::factory()->create([
                'context_id' => $otherContext->id,
                'group_space_id' => null,
                'uploaded_by_actor_id' => $other->id,
            ]);

            try {
                app(AttachPlanOccurrenceEvidence::class)->execute(
                    $occurrence,
                    $bob->user,
                    assetIds: [$foreignAsset->id],
                );
                $this->fail('Cross-Context Planner evidence was accepted.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_occurrence_materialization_is_idempotent_and_plan_cancellation_preserves_history(): void
    {
        $bob = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($bob->user);
        $plan = app(CreatePlan::class)->execute($context, $bob->user, 'Repeat study', timezone: 'UTC');
        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $bob->user,
            PlanScheduleFrequency::Daily,
            '2026-09-25',
            '20:00',
            30,
            occurrenceLimit: 3,
        );

        $before = $rule->occurrences()->count();
        $created = app(MaterializePlanOccurrences::class)->execute(
            $rule,
            CarbonImmutable::parse('2026-09-25 UTC'),
            CarbonImmutable::parse('2026-10-10 UTC'),
        );

        $this->assertSame(3, $before);
        $this->assertSame(0, $created);
        $this->assertSame(3, $rule->occurrences()->count());

        $plan = app(TransitionPlan::class)->execute($plan, $bob->user, PlanStatus::Cancelled);

        $this->assertSame(PlanStatus::Cancelled, $plan->status);
        $this->assertSame(PlanScheduleRuleStatus::Cancelled, $rule->fresh()->status);
        $this->assertSame(3, $plan->occurrences()->where('status', PlanOccurrenceStatus::Cancelled)->count());
        $this->assertGreaterThanOrEqual(2, $plan->events()->count());
    }

    private function activeRelationship(Actor $creator, Actor $invitee): Relationship
    {
        $relationship = app(CreateRelationship::class)->execute(
            $creator->user,
            Concept::factory()->create(),
            'participant',
            [['actor' => $invitee, 'role' => 'participant']],
        );

        return app(RespondToRelationship::class)->execute($relationship, $invitee->user, true);
    }
}
