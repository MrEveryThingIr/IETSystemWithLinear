<?php

namespace Database\Seeders;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\ConfigurePlanReadiness;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Actions\Planner\RecordPlanOccurrenceExpense;
use App\Actions\Planner\SetPlanOccurrencePrerequisite;
use App\Actions\Planner\TransitionPlan;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\Models\Context;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanStatus;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;

class PersonalPlannerDemoSeeder extends Seeder
{
    private const ORIGIN_TYPE = 'demo.personal_planner';

    /** @var array<string, string> */
    private const IDS = [
        'morning' => '10000000-0000-4000-8000-000000000001',
        'evening' => '10000000-0000-4000-8000-000000000002',
        'focus' => '10000000-0000-4000-8000-000000000003',
        'review' => '10000000-0000-4000-8000-000000000004',
        'groceries' => '10000000-0000-4000-8000-000000000005',
        'walking' => '10000000-0000-4000-8000-000000000006',
        'early_riser' => '10000000-0000-4000-8000-000000000007',
        'missed' => '10000000-0000-4000-8000-000000000008',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::query()
            ->with('actor')
            ->where('email', 'test@example.com')
            ->first();

        if (! $user instanceof User || $user->actor === null) {
            return;
        }

        $context = app(EnsurePersonalContext::class)->execute($user);
        $timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($timezone)->startOfMinute();

        $this->seedMorningFoundation($context, $user, $timezone, $now);
        $this->seedEveningReplacement($context, $user, $timezone, $now);
        $this->seedCurrentFocus($context, $user, $timezone, $now);
        $this->seedWeeklyReview($context, $user, $timezone, $now);
        $this->seedGroceriesAndBudget($context, $user, $timezone, $now);
        $this->seedWalkingChallenge($context, $user, $timezone, $now);
        $this->seedCancelledExperiment($context, $user, $timezone, $now);
        $this->seedPassedButUnresolvedTask($context, $user, $timezone, $now);
    }

    private function seedMorningFoundation(
        Context $context,
        User $user,
        string $timezone,
        CarbonImmutable $now,
    ): void {
        if ($this->existing($context, self::IDS['morning'])) {
            return;
        }

        $plan = $this->createPlan(
            $context,
            $user,
            self::IDS['morning'],
            'Morning foundation routine',
            'Build a reliable start to the day: water, light movement, review today\'s priorities, and begin deliberately instead of reactively.',
            $timezone,
            [
                'life_area' => 'health_and_direction',
                'purpose' => 'stable_morning_routine',
                'tracking_style' => 'habit_consistency',
            ],
        );

        app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $user,
            PlanScheduleFrequency::Daily,
            $now->subDays(14)->toDateString(),
            '07:00',
            45,
            occurrenceLimit: 40,
            windowBeforeMinutes: 20,
            windowAfterMinutes: 30,
            reminderOffsets: [15],
        );

        $this->finishRecentPastOccurrences($plan, $user, 9, 4);
    }

    private function seedEveningReplacement(
        Context $context,
        User $user,
        string $timezone,
        CarbonImmutable $now,
    ): void {
        if ($this->existing($context, self::IDS['evening'])) {
            return;
        }

        $plan = $this->createPlan(
            $context,
            $user,
            self::IDS['evening'],
            'Replace late-night scrolling with reading',
            'A habit-replacement experiment: put the phone away, read for twenty minutes, write tomorrow\'s first task, then shut the day down.',
            $timezone,
            [
                'life_area' => 'habits',
                'purpose' => 'replace_bad_habit',
                'replaces' => 'late_night_scrolling',
            ],
        );

        app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $user,
            PlanScheduleFrequency::Daily,
            $now->subDays(10)->toDateString(),
            '21:30',
            35,
            occurrenceLimit: 24,
            windowBeforeMinutes: 30,
            windowAfterMinutes: 60,
            reminderOffsets: [30, 5],
        );

        $this->finishRecentPastOccurrences($plan, $user, 6, 3);

        app(TransitionPlan::class)->execute($plan->fresh(), $user, PlanStatus::Paused);
    }

    private function seedCurrentFocus(
        Context $context,
        User $user,
        string $timezone,
        CarbonImmutable $now,
    ): void {
        if ($this->existing($context, self::IDS['focus'])) {
            return;
        }

        $plan = $this->createPlan(
            $context,
            $user,
            self::IDS['focus'],
            'Current focus block — organize next actions',
            'A live focus session so the Planner immediately shows an in-progress occurrence and readiness checks after a fresh seed.',
            $timezone,
            [
                'life_area' => 'work',
                'purpose' => 'focused_execution',
            ],
        );

        app(ConfigurePlanReadiness::class)->execute(
            $plan,
            $user,
            prerequisites: [
                ['title' => 'Choose one concrete outcome for this block', 'required' => true],
                ['title' => 'Silence non-essential notifications', 'required' => false],
            ],
        );

        $start = $now->subMinutes(10);
        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $user,
            PlanScheduleFrequency::Once,
            $start->toDateString(),
            $start->format('H:i'),
            60,
            windowBeforeMinutes: 15,
            windowAfterMinutes: 20,
            reminderOffsets: [10],
        );

        $occurrence = $rule->occurrences()->sole();
        $required = $plan->fresh('prerequisites')->prerequisites->firstWhere('is_required', true);

        $this->at($now, function () use ($occurrence, $required, $user): void {
            if ($required !== null) {
                app(SetPlanOccurrencePrerequisite::class)->execute($occurrence, $required, $user, true);
            }

            app(TransitionPlanOccurrence::class)->start($occurrence->fresh(), $user);
        });
    }

    private function seedWeeklyReview(
        Context $context,
        User $user,
        string $timezone,
        CarbonImmutable $now,
    ): void {
        if ($this->existing($context, self::IDS['review'])) {
            return;
        }

        $plan = $this->createPlan(
            $context,
            $user,
            self::IDS['review'],
            'Weekly life review and next-week design',
            'Review what worked, what was skipped, what should stop, and reserve time for the next week before it becomes crowded.',
            $timezone,
            [
                'life_area' => 'reflection',
                'purpose' => 'weekly_review',
            ],
        );

        app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $user,
            PlanScheduleFrequency::Weekly,
            $now->subDays(14)->toDateString(),
            '19:00',
            60,
            weekdays: [7],
            occurrenceLimit: 20,
            windowBeforeMinutes: 60,
            windowAfterMinutes: 120,
            reminderOffsets: [120, 15],
        );

        $this->finishRecentPastOccurrences($plan, $user, 2);
    }

    private function seedGroceriesAndBudget(
        Context $context,
        User $user,
        string $timezone,
        CarbonImmutable $now,
    ): void {
        if ($this->existing($context, self::IDS['groceries'])) {
            return;
        }

        $plan = $this->createPlan(
            $context,
            $user,
            self::IDS['groceries'],
            'Groceries and simple meal preparation',
            'A repeatable personal task with readiness checks and expected-versus-actual spending, demonstrating Planner cost observations without turning Planner into Accounting.',
            $timezone,
            [
                'life_area' => 'home_and_finance',
                'purpose' => 'planned_household_spend',
            ],
        );

        app(ConfigurePlanReadiness::class)->execute(
            $plan,
            $user,
            prerequisites: [
                ['title' => 'Write the grocery list', 'required' => true],
                ['title' => 'Check what is already at home', 'required' => true],
                ['title' => 'Bring reusable bags', 'required' => false],
            ],
            expenseEstimates: [
                ['label' => 'Groceries budget', 'amount' => '80.00', 'unit_code' => 'USD'],
            ],
        );

        $past = $now->subDays(2)->setTime(18, 0);
        $futureA = $now->addDays(5)->setTime(18, 0);
        $futureB = $now->addDays(12)->setTime(18, 0);

        $rule = app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $user,
            PlanScheduleFrequency::SelectedDates,
            $past->toDateString(),
            '18:00',
            75,
            selectedDates: [
                $past->toDateString(),
                $futureA->toDateString(),
                $futureB->toDateString(),
            ],
            windowBeforeMinutes: 30,
            windowAfterMinutes: 45,
            reminderOffsets: [60],
        );

        $occurrence = $rule->occurrences()
            ->whereDate('local_date', $past->toDateString())
            ->firstOrFail();

        $plan = $plan->fresh(['prerequisites', 'expenseEstimates']);
        $startMoment = $occurrence->scheduled_start_at->addMinutes(2);

        $this->at($startMoment, function () use ($occurrence, $plan, $user): void {
            foreach ($plan->prerequisites->where('is_required', true) as $prerequisite) {
                app(SetPlanOccurrencePrerequisite::class)->execute(
                    $occurrence->fresh(),
                    $prerequisite,
                    $user,
                    true,
                );
            }

            app(TransitionPlanOccurrence::class)->start($occurrence->fresh(), $user);
        });

        $estimate = $plan->expenseEstimates->first();

        $this->at($startMoment->addMinutes(35), function () use ($occurrence, $estimate, $user): void {
            app(RecordPlanOccurrenceExpense::class)->execute(
                $occurrence->fresh(),
                $user,
                'Groceries actually paid',
                '72.40',
                'USD',
                $estimate,
                'Stayed under the planned grocery budget.',
            );
        });

        $this->at($startMoment->addMinutes(70), function () use ($occurrence, $user): void {
            app(TransitionPlanOccurrence::class)->complete($occurrence->fresh(), $user);
        });
    }

    private function seedWalkingChallenge(
        Context $context,
        User $user,
        string $timezone,
        CarbonImmutable $now,
    ): void {
        if ($this->existing($context, self::IDS['walking'])) {
            return;
        }

        $plan = $this->createPlan(
            $context,
            $user,
            self::IDS['walking'],
            'Seven-session walking consistency challenge',
            'A small finished purpose that shows how a finite personal plan can contain both successful and skipped sessions and then be closed.',
            $timezone,
            [
                'life_area' => 'health',
                'purpose' => 'finite_consistency_challenge',
            ],
        );

        $dates = collect(range(9, 3))
            ->map(fn (int $daysAgo): string => $now->subDays($daysAgo)->toDateString())
            ->values()
            ->all();

        app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $user,
            PlanScheduleFrequency::SelectedDates,
            $dates[0],
            '17:30',
            40,
            selectedDates: $dates,
            reminderOffsets: [30],
        );

        $this->finishAllPastOccurrences($plan, $user, 5);

        app(TransitionPlan::class)->execute($plan->fresh(), $user, PlanStatus::Completed);
    }

    private function seedCancelledExperiment(
        Context $context,
        User $user,
        string $timezone,
        CarbonImmutable $now,
    ): void {
        if ($this->existing($context, self::IDS['early_riser'])) {
            return;
        }

        $plan = $this->createPlan(
            $context,
            $user,
            self::IDS['early_riser'],
            '5:00 AM wake-up experiment',
            'An intentionally abandoned experiment. It demonstrates that stopping a plan is valid evidence too; the goal is not to force every experiment to succeed.',
            $timezone,
            [
                'life_area' => 'habits',
                'purpose' => 'experiment_then_stop',
            ],
        );

        $dates = [
            $now->subDays(5)->toDateString(),
            $now->subDays(3)->toDateString(),
            $now->addDays(2)->toDateString(),
        ];

        app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $user,
            PlanScheduleFrequency::SelectedDates,
            $dates[0],
            '05:00',
            30,
            selectedDates: $dates,
            reminderOffsets: [480, 15],
        );

        $past = $plan->occurrences()
            ->where('scheduled_start_at', '<', $now->utc())
            ->orderBy('scheduled_start_at')
            ->get();

        if ($first = $past->first()) {
            $this->completeOccurrence($first, $user);
        }

        if ($second = $past->skip(1)->first()) {
            $this->skipOccurrence($second, $user);
        }

        app(TransitionPlan::class)->execute($plan->fresh(), $user, PlanStatus::Cancelled);
    }

    private function seedPassedButUnresolvedTask(
        Context $context,
        User $user,
        string $timezone,
        CarbonImmutable $now,
    ): void {
        if ($this->existing($context, self::IDS['missed'])) {
            return;
        }

        $plan = $this->createPlan(
            $context,
            $user,
            self::IDS['missed'],
            'Sort the personal document inbox',
            'This deliberately remains scheduled after its execution window passes, so the UI has a realistic overdue/passed item to inspect.',
            $timezone,
            [
                'life_area' => 'organization',
                'purpose' => 'show_passed_unresolved_state',
            ],
        );

        app(CreatePlanScheduleRule::class)->execute(
            $plan,
            $user,
            PlanScheduleFrequency::Once,
            $now->subDays(2)->toDateString(),
            '14:00',
            30,
            windowBeforeMinutes: 10,
            windowAfterMinutes: 20,
            reminderOffsets: [60],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createPlan(
        Context $context,
        User $user,
        string $originUuid,
        string $title,
        string $description,
        string $timezone,
        array $metadata,
    ): Plan {
        return app(CreatePlan::class)->execute(
            $context,
            $user,
            $title,
            $description,
            $timezone,
            originType: self::ORIGIN_TYPE,
            originUuid: $originUuid,
            metadata: array_merge($metadata, [
                'demo' => true,
                'demo_scope' => 'personal_planner',
            ]),
        );
    }

    private function existing(Context $context, string $originUuid): bool
    {
        return Plan::query()
            ->where('context_id', $context->id)
            ->where('origin_type', self::ORIGIN_TYPE)
            ->where('origin_uuid', $originUuid)
            ->exists();
    }

    private function finishRecentPastOccurrences(
        Plan $plan,
        User $user,
        int $limit,
        int $skipEvery = 0,
    ): void {
        $occurrences = $plan->occurrences()
            ->where('status', PlanOccurrenceStatus::Scheduled)
            ->where('scheduled_start_at', '<', now()->utc())
            ->latest('scheduled_start_at')
            ->limit($limit)
            ->get()
            ->sortBy('scheduled_start_at')
            ->values();

        foreach ($occurrences as $index => $occurrence) {
            if ($skipEvery > 0 && ($index + 1) % $skipEvery === 0) {
                $this->skipOccurrence($occurrence, $user);

                continue;
            }

            $this->completeOccurrence($occurrence, $user);
        }
    }

    private function finishAllPastOccurrences(
        Plan $plan,
        User $user,
        int $skipAt = 0,
    ): void {
        $occurrences = $plan->occurrences()
            ->where('status', PlanOccurrenceStatus::Scheduled)
            ->orderBy('scheduled_start_at')
            ->get();

        foreach ($occurrences as $index => $occurrence) {
            if ($skipAt > 0 && $index + 1 === $skipAt) {
                $this->skipOccurrence($occurrence, $user);

                continue;
            }

            $this->completeOccurrence($occurrence, $user);
        }
    }

    private function completeOccurrence(PlanOccurrence $occurrence, User $user): void
    {
        $start = $occurrence->window_start_at->addMinute();

        $this->at($start, function () use ($occurrence, $user): void {
            app(TransitionPlanOccurrence::class)->start($occurrence->fresh(), $user);
        });

        $this->at(
            $occurrence->scheduled_end_at->subMinute(),
            function () use ($occurrence, $user): void {
                app(TransitionPlanOccurrence::class)->complete($occurrence->fresh(), $user);
            },
        );
    }

    private function skipOccurrence(PlanOccurrence $occurrence, User $user): void
    {
        $this->at(
            $occurrence->scheduled_start_at,
            function () use ($occurrence, $user): void {
                app(TransitionPlanOccurrence::class)->skip($occurrence->fresh(), $user);
            },
        );
    }

    private function at(CarbonImmutable $moment, Closure $callback): mixed
    {
        CarbonImmutable::setTestNow($moment);

        try {
            return $callback();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
