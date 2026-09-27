<?php

namespace App\Actions\Planner;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanEvent;
use App\Models\PlanExpenseEstimate;
use App\Models\PlanPrerequisite;
use App\Models\User;
use App\PlanEventType;
use App\PlanStatus;
use App\Support\MoneyAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ConfigurePlanReadiness
{
    public function __construct(private readonly EnsureMonetaryUnit $monetaryUnits) {}

    /**
     * @param  list<array{title: string, required?: bool}>  $prerequisites
     * @param  list<array{label: string, amount: string, unit_code: string}>  $expenseEstimates
     */
    public function execute(
        Plan $plan,
        User $user,
        array $prerequisites = [],
        array $expenseEstimates = [],
    ): Plan {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('manage', $plan);

        abort_if(count($prerequisites) > 50, 422, 'A Plan may have at most fifty prerequisites.');
        abort_if(count($expenseEstimates) > 50, 422, 'A Plan may have at most fifty expense estimates.');

        return DB::transaction(function () use ($plan, $current, $prerequisites, $expenseEstimates): Plan {
            $locked = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            Gate::forUser($current)->authorize('manage', $locked);
            abort_unless($locked->status === PlanStatus::Active, 422, 'Only an active Plan can receive readiness configuration.');
            abort_if(
                $locked->prerequisites()->exists() || $locked->expenseEstimates()->exists(),
                422,
                'Plan readiness configuration already exists.',
            );

            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            foreach ($prerequisites as $index => $item) {
                $title = Str::squish((string) ($item['title'] ?? ''));
                abort_if($title === '' || mb_strlen($title) > 240, 422, 'Plan prerequisite title is invalid.');

                PlanPrerequisite::query()->create([
                    'plan_id' => $locked->id,
                    'created_by_actor_id' => $actor->id,
                    'title' => $title,
                    'is_required' => (bool) ($item['required'] ?? true),
                    'sort_order' => $index,
                ]);
            }

            $estimateUnits = [];

            foreach ($expenseEstimates as $index => $item) {
                $label = Str::squish((string) ($item['label'] ?? ''));
                $unitCode = strtoupper(trim((string) ($item['unit_code'] ?? '')));
                $amount = trim((string) ($item['amount'] ?? ''));

                abort_if($label === '' || mb_strlen($label) > 180, 422, 'Plan expense estimate label is invalid.');

                $unit = $this->monetaryUnits->execute($unitCode);

                try {
                    $amountMinor = MoneyAmount::parse($amount, $unit->exponent);
                } catch (InvalidArgumentException) {
                    abort(422, 'Plan expense estimate amount is invalid.');
                }

                abort_if($amountMinor <= 0, 422, 'Plan expense estimate amount must be positive.');

                PlanExpenseEstimate::query()->create([
                    'plan_id' => $locked->id,
                    'created_by_actor_id' => $actor->id,
                    'monetary_unit_id' => $unit->id,
                    'label' => $label,
                    'amount_minor' => $amountMinor,
                    'sort_order' => $index,
                ]);

                $estimateUnits[] = $unit->code;
            }

            if ($prerequisites !== [] || $expenseEstimates !== []) {
                PlanEvent::query()->create([
                    'plan_id' => $locked->id,
                    'actor_id' => $actor->id,
                    'event_type' => PlanEventType::ReadinessConfigured,
                    'payload' => [
                        'prerequisite_count' => count($prerequisites),
                        'expense_estimate_count' => count($expenseEstimates),
                        'expense_units' => array_values(array_unique($estimateUnits)),
                    ],
                ]);
            }

            return $locked->fresh([
                'prerequisites',
                'expenseEstimates.monetaryUnit',
                'events',
            ]);
        }, attempts: 3);
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }
}
