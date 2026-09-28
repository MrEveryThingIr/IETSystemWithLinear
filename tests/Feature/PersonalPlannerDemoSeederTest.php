<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceExpense;
use App\Models\PlanOccurrencePrerequisiteCheck;
use App\Models\PlanScheduleRule;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanStatus;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PersonalPlannerDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalPlannerDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_covers_personal_planner_states_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();
        $this->assertNotNull($user->actor);

        $plans = Plan::query()
            ->with(['context.personalBinding', 'occurrences', 'scheduleRules'])
            ->where('origin_type', 'demo.personal_planner')
            ->get();

        $this->assertCount(8, $plans);
        $this->assertTrue($plans->every(
            fn (Plan $plan): bool => $plan->context->personalBinding?->actor_id === $user->actor?->id,
        ));

        $planStatuses = $plans->pluck('status')->map(
            fn (PlanStatus $status): string => $status->value,
        )->unique();

        foreach (PlanStatus::cases() as $status) {
            $this->assertTrue($planStatuses->contains($status->value), "Missing Plan state {$status->value}.");
        }

        $occurrenceStatuses = PlanOccurrence::query()
            ->whereHas('plan', fn ($query) => $query->where('origin_type', 'demo.personal_planner'))
            ->pluck('status')
            ->unique();

        foreach (PlanOccurrenceStatus::cases() as $status) {
            $this->assertTrue(
                $occurrenceStatuses->contains($status->value),
                "Missing occurrence state {$status->value}.",
            );
        }

        $frequencies = PlanScheduleRule::query()
            ->whereHas('plan', fn ($query) => $query->where('origin_type', 'demo.personal_planner'))
            ->pluck('frequency')
            ->unique();

        foreach (PlanScheduleFrequency::cases() as $frequency) {
            $this->assertTrue(
                $frequencies->contains($frequency->value),
                "Missing schedule frequency {$frequency->value}.",
            );
        }

        $focus = $plans->firstWhere('title', 'Current focus block — organize next actions');
        $this->assertNotNull($focus);
        $this->assertSame(
            PlanOccurrenceStatus::InProgress,
            $focus?->occurrences->first()?->status,
        );

        $missed = $plans->firstWhere('title', 'Sort the personal document inbox');
        $missedOccurrence = $missed?->occurrences->first();
        $this->assertNotNull($missedOccurrence);
        $this->assertSame(PlanOccurrenceStatus::Scheduled, $missedOccurrence?->status);
        $this->assertSame('passed', $missedOccurrence?->executionPhase());

        $groceries = $plans->firstWhere('title', 'Groceries and simple meal preparation');
        $this->assertNotNull($groceries);
        $this->assertGreaterThanOrEqual(1, $groceries?->expenseEstimates()->count() ?? 0);
        $this->assertGreaterThanOrEqual(
            1,
            PlanOccurrenceExpense::query()
                ->whereHas('occurrence.plan', fn ($query) => $query->whereKey($groceries?->id))
                ->count(),
        );
        $this->assertGreaterThanOrEqual(
            1,
            PlanOccurrencePrerequisiteCheck::query()
                ->whereHas('occurrence.plan', fn ($query) => $query->whereKey($groceries?->id))
                ->whereNotNull('completed_at')
                ->count(),
        );

        $futureGroceries = $groceries?->occurrences()
            ->where('status', PlanOccurrenceStatus::Scheduled)
            ->orderBy('scheduled_start_at')
            ->first();
        $this->assertNotNull($futureGroceries);
        $this->assertGreaterThanOrEqual(1, $futureGroceries?->remainingRequiredPrerequisites() ?? 0);

        $planCount = $plans->count();
        $occurrenceCount = PlanOccurrence::query()
            ->whereHas('plan', fn ($query) => $query->where('origin_type', 'demo.personal_planner'))
            ->count();

        $this->seed(PersonalPlannerDemoSeeder::class);

        $this->assertSame(
            $planCount,
            Plan::query()->where('origin_type', 'demo.personal_planner')->count(),
        );
        $this->assertSame(
            $occurrenceCount,
            PlanOccurrence::query()
                ->whereHas('plan', fn ($query) => $query->where('origin_type', 'demo.personal_planner'))
                ->count(),
        );
    }
}
