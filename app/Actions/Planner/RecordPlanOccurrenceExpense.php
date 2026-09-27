<?php

namespace App\Actions\Planner;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\Models\Actor;
use App\Models\PlanExpenseEstimate;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\PlanOccurrenceExpense;
use App\Models\User;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use App\Support\MoneyAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordPlanOccurrenceExpense
{
    public function __construct(private readonly EnsureMonetaryUnit $monetaryUnits) {}

    public function execute(
        PlanOccurrence $occurrence,
        User $user,
        string $label,
        string $amount,
        string $unitCode,
        ?PlanExpenseEstimate $estimate = null,
        ?string $note = null,
    ): PlanOccurrenceExpense {
        $current = $this->currentUser($user);
        $label = Str::squish($label);
        $note = trim((string) $note);

        abort_if($label === '' || mb_strlen($label) > 180, 422, 'Planner expense label is invalid.');
        abort_if($note !== '' && mb_strlen($note) > 500, 422, 'Planner expense note is too long.');

        return DB::transaction(function () use (
            $occurrence,
            $current,
            $label,
            $amount,
            $unitCode,
            $estimate,
            $note,
        ): PlanOccurrenceExpense {
            $locked = PlanOccurrence::query()
                ->with('plan')
                ->lockForUpdate()
                ->findOrFail($occurrence->id);

            Gate::forUser($current)->authorize('participate', $locked->plan);
            abort_unless(in_array($locked->status, [
                PlanOccurrenceStatus::InProgress,
                PlanOccurrenceStatus::Completed,
            ], true), 422, 'Actual expenses can only be recorded after an Occurrence has started.');

            $unit = $this->monetaryUnits->execute($unitCode);

            try {
                $amountMinor = MoneyAmount::parse(trim($amount), $unit->exponent);
            } catch (InvalidArgumentException) {
                abort(422, 'Planner expense amount is invalid.');
            }

            abort_if($amountMinor <= 0, 422, 'Planner expense amount must be positive.');

            $lockedEstimate = null;
            if ($estimate instanceof PlanExpenseEstimate) {
                $lockedEstimate = PlanExpenseEstimate::query()
                    ->with('monetaryUnit')
                    ->lockForUpdate()
                    ->findOrFail($estimate->id);

                abort_unless((int) $lockedEstimate->plan_id === (int) $locked->plan_id, 422, 'Expense estimate does not belong to this Plan.');
                abort_unless((int) $lockedEstimate->monetary_unit_id === (int) $unit->id, 422, 'Actual expense must use the same monetary unit as its estimate.');
            }

            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            $expense = PlanOccurrenceExpense::query()->create([
                'plan_occurrence_id' => $locked->id,
                'plan_expense_estimate_id' => $lockedEstimate?->id,
                'monetary_unit_id' => $unit->id,
                'created_by_actor_id' => $actor->id,
                'label' => $label,
                'amount_minor' => $amountMinor,
                'note' => $note === '' ? null : $note,
                'occurred_at' => now(),
            ]);

            PlanOccurrenceEvent::query()->create([
                'plan_occurrence_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => PlanOccurrenceEventType::ExpenseRecorded,
                'payload' => [
                    'expense_uuid' => $expense->uuid,
                    'plan_expense_estimate_uuid' => $lockedEstimate?->uuid,
                    'amount_minor' => $amountMinor,
                    'monetary_unit' => $unit->code,
                ],
            ]);

            return $expense->fresh(['estimate', 'monetaryUnit', 'creator.user']);
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
