<?php

namespace App\Actions\Contracts;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\CommitmentKind;
use App\ContractEventType;
use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\ContractEvent;
use App\Models\ContractServiceTerm;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\Support\MoneyAmount;
use App\Support\QuantityAmount;
use DateTimeZone;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ConfigureContractServiceTerms
{
    public function __construct(private readonly EnsureMonetaryUnit $monetaryUnits) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function executeFromInput(
        ContractVersion $version,
        User $user,
        array $input,
    ): ContractServiceTerm {
        $version = ContractVersion::query()
            ->with('parties.actor.user')
            ->findOrFail($version->id);

        $parties = $version->parties
            ->filter(fn (ContractVersionParty $party): bool => $party->actor->user instanceof User)
            ->keyBy(fn (ContractVersionParty $party): string => (string) $party->actor->user?->username);

        $employer = $parties->get((string) ($input['employer_username'] ?? ''))?->actor;
        $worker = $parties->get((string) ($input['worker_username'] ?? ''))?->actor;

        abort_unless($employer instanceof Actor && $worker instanceof Actor, 422, 'Service employer and worker must be exact ContractVersion parties.');

        return $this->execute(
            $version,
            $user,
            $employer,
            $worker,
            (string) ($input['service_title'] ?? ''),
            CommitmentKind::from((string) ($input['service_kind'] ?? CommitmentKind::Service->value)),
            (string) ($input['total_quantity'] ?? '1'),
            (string) ($input['quantity_per_occurrence'] ?? '1'),
            (string) ($input['unit'] ?? 'unit'),
            (string) ($input['unit_rate'] ?? ''),
            (string) ($input['monetary_unit_code'] ?? ''),
            (string) ($input['settlement_cycle'] ?? ContractServiceTerm::SETTLEMENT_PER_FULFILLMENT),
            (int) ($input['payment_due_days'] ?? 0),
            PlanScheduleFrequency::from((string) ($input['plan_frequency'] ?? PlanScheduleFrequency::Once->value)),
            (string) ($input['plan_starts_on'] ?? ''),
            (string) ($input['plan_start_time'] ?? ''),
            (int) ($input['plan_duration_minutes'] ?? 60),
            (int) ($input['plan_interval'] ?? 1),
            array_values((array) ($input['plan_weekdays'] ?? [])),
            array_values((array) ($input['plan_selected_dates'] ?? [])),
            ($input['plan_ends_on'] ?? null) !== '' ? ($input['plan_ends_on'] ?? null) : null,
            ($input['plan_occurrence_limit'] ?? null) !== null && $input['plan_occurrence_limit'] !== ''
                ? (int) $input['plan_occurrence_limit']
                : null,
            (int) ($input['window_before_minutes'] ?? 0),
            (int) ($input['window_after_minutes'] ?? 0),
            array_values((array) ($input['reminder_offsets'] ?? [])),
            (string) ($input['timezone'] ?? $version->effective_timezone),
            (bool) ($input['auto_create_plan'] ?? true),
            (bool) ($input['auto_recognize_obligation'] ?? true),
        );
    }

    /**
     * @param  list<int>  $weekdays
     * @param  list<string>  $selectedDates
     * @param  list<int>  $reminderOffsets
     */
    public function execute(
        ContractVersion $version,
        User $user,
        Actor $employer,
        Actor $worker,
        string $serviceTitle,
        CommitmentKind $serviceKind,
        string|int $totalQuantity,
        string|int $quantityPerOccurrence,
        string $unit,
        string $unitRate,
        string $monetaryUnitCode,
        string $settlementCycle,
        int $paymentDueDays,
        PlanScheduleFrequency $planFrequency,
        string $planStartsOn,
        string $planStartTime,
        int $planDurationMinutes,
        int $planInterval = 1,
        array $weekdays = [],
        array $selectedDates = [],
        ?string $planEndsOn = null,
        ?int $planOccurrenceLimit = null,
        int $windowBeforeMinutes = 0,
        int $windowAfterMinutes = 0,
        array $reminderOffsets = [],
        ?string $timezone = null,
        bool $autoCreatePlan = true,
        bool $autoRecognizeObligation = true,
    ): ContractServiceTerm {
        $current = $this->currentUser($user);

        $serviceTitle = Str::squish($serviceTitle);
        abort_if($serviceTitle === '' || mb_strlen($serviceTitle) > 180, 422, 'Service title must be between 1 and 180 characters.');

        $unit = Str::squish($unit);
        abort_if($unit === '' || mb_strlen($unit) > 40, 422, 'Service unit must be between 1 and 40 characters.');

        abort_unless(
            in_array($settlementCycle, [
                ContractServiceTerm::SETTLEMENT_PER_FULFILLMENT,
                ContractServiceTerm::SETTLEMENT_WEEKLY,
                ContractServiceTerm::SETTLEMENT_MONTHLY,
                ContractServiceTerm::SETTLEMENT_CONTRACT_END,
            ], true),
            422,
            'Settlement cycle is invalid.',
        );

        abort_if($paymentDueDays < 0 || $paymentDueDays > 3650, 422, 'Payment due days are invalid.');
        abort_if($planDurationMinutes < 1 || $planDurationMinutes > 10080, 422, 'Plan duration is invalid.');
        abort_if($planInterval < 1 || $planInterval > 365, 422, 'Plan interval is invalid.');
        abort_if($windowBeforeMinutes < 0 || $windowBeforeMinutes > 10080, 422, 'Early execution window is invalid.');
        abort_if($windowAfterMinutes < 0 || $windowAfterMinutes > 10080, 422, 'Late execution window is invalid.');

        try {
            $normalizedTotal = QuantityAmount::positive($totalQuantity);
            $normalizedPerOccurrence = QuantityAmount::positive($quantityPerOccurrence);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        abort_if(
            QuantityAmount::compare($normalizedPerOccurrence, $normalizedTotal) > 0,
            422,
            'Quantity per occurrence cannot exceed total service quantity.',
        );

        $monetaryUnit = $this->monetaryUnits->execute($monetaryUnitCode);

        try {
            $unitRateMinor = MoneyAmount::parse($unitRate, $monetaryUnit->exponent);
        } catch (InvalidArgumentException) {
            abort(422, 'Service unit rate is invalid.');
        }

        abort_if($unitRateMinor <= 0, 422, 'Service unit rate must be positive.');

        $timezone ??= $version->effective_timezone;
        try {
            $timezone = (new DateTimeZone($timezone))->getName();
        } catch (Exception) {
            abort(422, 'Service schedule timezone is invalid.');
        }

        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $planStartsOn) === 1, 422, 'Service plan start date is invalid.');
        abort_unless(preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $planStartTime) === 1, 422, 'Service plan start time is invalid.');
        abort_if($planEndsOn !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $planEndsOn) !== 1, 422, 'Service plan end date is invalid.');

        $weekdays = collect($weekdays)
            ->map(fn ($day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $selectedDates = collect($selectedDates)
            ->map(fn ($date): string => trim((string) $date))
            ->filter(fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $reminderOffsets = collect($reminderOffsets)
            ->map(fn ($offset): int => (int) $offset)
            ->filter(fn (int $offset): bool => $offset >= 0 && $offset <= 525600)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if ($planOccurrenceLimit === null) {
            $totalScaled = QuantityAmount::toScaledInt($normalizedTotal);
            $perOccurrenceScaled = QuantityAmount::toScaledInt($normalizedPerOccurrence);
            $planOccurrenceLimit = intdiv(
                $totalScaled + $perOccurrenceScaled - 1,
                $perOccurrenceScaled,
            );
        }

        abort_if($planOccurrenceLimit < 1 || $planOccurrenceLimit > 10000, 422, 'Service occurrence limit is invalid.');

        if ($planFrequency === PlanScheduleFrequency::Once) {
            $planOccurrenceLimit = 1;
        }

        return DB::transaction(function () use (
            $version,
            $current,
            $employer,
            $worker,
            $serviceTitle,
            $serviceKind,
            $normalizedTotal,
            $normalizedPerOccurrence,
            $unit,
            $monetaryUnit,
            $unitRateMinor,
            $settlementCycle,
            $paymentDueDays,
            $autoCreatePlan,
            $autoRecognizeObligation,
            $planFrequency,
            $planStartsOn,
            $planStartTime,
            $planDurationMinutes,
            $planInterval,
            $weekdays,
            $selectedDates,
            $planEndsOn,
            $planOccurrenceLimit,
            $windowBeforeMinutes,
            $windowAfterMinutes,
            $reminderOffsets,
            $timezone,
        ): ContractServiceTerm {
            $locked = ContractVersion::query()
                ->with('parties.acceptance')
                ->lockForUpdate()
                ->findOrFail($version->id);

            abort_unless(
                $locked->status === ContractVersionStatus::Proposed,
                422,
                'Structured service terms must be fixed before the ContractVersion is fully accepted.',
            );
            abort_if(
                ContractServiceTerm::query()->where('contract_version_id', $locked->id)->exists(),
                422,
                'This ContractVersion already has structured service terms.',
            );

            abort_if(
                $locked->parties->contains(
                    fn (ContractVersionParty $party): bool => $party->acceptance !== null,
                ),
                422,
                'Structured service terms must be fixed before any party accepts the ContractVersion.',
            );

            $employer = Actor::query()->with('user')->lockForUpdate()->findOrFail($employer->id);
            $worker = Actor::query()->with('user')->lockForUpdate()->findOrFail($worker->id);

            abort_if((int) $employer->id === (int) $worker->id, 422, 'Employer and worker must be different Actors.');

            foreach ([$employer, $worker] as $party) {
                abort_unless(
                    $party->status === 'active'
                    && $party->user instanceof User
                    && $party->user->status === 'active'
                    && $party->user->email_verified_at !== null
                    && $locked->parties->contains(fn (ContractVersionParty $versionParty): bool => (int) $versionParty->actor_id === (int) $party->id),
                    422,
                    'Employer and worker must both be active verified parties of the exact ContractVersion.',
                );
            }

            $terms = ContractServiceTerm::query()->create([
                'contract_version_id' => $locked->id,
                'employer_actor_id' => $employer->id,
                'worker_actor_id' => $worker->id,
                'monetary_unit_id' => $monetaryUnit->id,
                'service_title' => $serviceTitle,
                'service_kind' => $serviceKind,
                'total_quantity' => $normalizedTotal,
                'quantity_per_occurrence' => $normalizedPerOccurrence,
                'unit' => $unit,
                'unit_rate_minor' => $unitRateMinor,
                'settlement_cycle' => $settlementCycle,
                'payment_due_days' => $paymentDueDays,
                'auto_create_plan' => $autoCreatePlan,
                'auto_recognize_obligation' => $autoRecognizeObligation,
                'plan_frequency' => $planFrequency,
                'plan_starts_on' => $planStartsOn,
                'plan_start_time' => $planStartTime,
                'plan_duration_minutes' => $planDurationMinutes,
                'plan_interval' => $planInterval,
                'plan_weekdays' => $weekdays,
                'plan_selected_dates' => $selectedDates,
                'plan_ends_on' => $planEndsOn,
                'plan_occurrence_limit' => $planOccurrenceLimit,
                'window_before_minutes' => $windowBeforeMinutes,
                'window_after_minutes' => $windowAfterMinutes,
                'reminder_offsets' => $reminderOffsets,
                'timezone' => $timezone,
            ]);

            ContractEvent::query()->create([
                'contract_id' => $locked->contract_id,
                'contract_version_id' => $locked->id,
                'actor_id' => $current->actor->id,
                'event_type' => ContractEventType::ServiceTermsConfigured,
                'payload' => [
                    'contract_service_term_uuid' => $terms->uuid,
                    'employer_actor_id' => $employer->id,
                    'worker_actor_id' => $worker->id,
                    'service_kind' => $serviceKind->value,
                    'total_quantity' => $normalizedTotal,
                    'quantity_per_occurrence' => $normalizedPerOccurrence,
                    'unit' => $unit,
                    'unit_rate_minor' => $unitRateMinor,
                    'monetary_unit_code' => $monetaryUnit->code,
                    'settlement_cycle' => $settlementCycle,
                    'plan_frequency' => $planFrequency->value,
                    'timezone' => $timezone,
                ],
            ]);

            return $terms->fresh([
                'contractVersion.contract',
                'employer.user',
                'worker.user',
                'monetaryUnit',
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
