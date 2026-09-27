<?php

namespace App\Actions\Contracts;

use App\Actions\Commitments\CreateCommitment;
use App\Actions\Commitments\CreateCommitmentPlan;
use App\ContractVersionStatus;
use App\Models\Commitment;
use App\Models\ContractServiceTerm;
use App\Models\ContractVersion;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BootstrapContractServiceWorkflow
{
    public function __construct(
        private readonly CreateCommitment $commitments,
        private readonly CreateCommitmentPlan $plans,
    ) {}

    public function execute(ContractVersion $version): ?Plan
    {
        return DB::transaction(function () use ($version): ?Plan {
            $version = ContractVersion::query()
                ->with([
                    'contract.creator.user',
                    'serviceTerm.employer.user',
                    'serviceTerm.worker.user',
                    'serviceTerm.commitment.planBinding.plan',
                ])
                ->lockForUpdate()
                ->findOrFail($version->id);

            if ($version->status !== ContractVersionStatus::Active
                || ! $version->serviceTerm instanceof ContractServiceTerm) {
                return null;
            }

            $terms = $version->serviceTerm;
            $creator = $version->contract->creator?->user;
            abort_unless($creator instanceof User, 500, 'Contract automation requires its creator User.');

            $commitment = $terms->commitment;

            if (! $commitment instanceof Commitment) {
                $dueStart = CarbonImmutable::parse(
                    $terms->plan_starts_on->format('Y-m-d').' '.$terms->plan_start_time,
                    $terms->timezone,
                )->utc();

                $dueEnd = $terms->plan_ends_on !== null
                    ? CarbonImmutable::parse($terms->plan_ends_on->format('Y-m-d'), $terms->timezone)->endOfDay()->utc()
                    : null;

                $commitment = $this->commitments->execute(
                    $version->contract,
                    $creator,
                    $terms->worker,
                    $terms->employer,
                    $terms->service_kind,
                    $terms->service_title,
                    $terms->total_quantity,
                    $terms->unit,
                    description: 'Automatically generated from accepted ContractVersion '.$version->version.'.',
                    dueStartAt: $dueStart,
                    dueEndAt: $dueEnd,
                    serviceTerm: $terms,
                );
            }

            if (! $terms->auto_create_plan) {
                return null;
            }

            $commitment->loadMissing('planBinding.plan');

            if ($commitment->planBinding?->plan instanceof Plan) {
                return $commitment->planBinding->plan;
            }

            return $this->plans->execute(
                $commitment,
                $creator,
                $terms->plan_frequency,
                $terms->plan_starts_on->format('Y-m-d'),
                substr($terms->plan_start_time, 0, 5),
                $terms->plan_duration_minutes,
                $terms->plan_interval,
                $terms->plan_weekdays ?? [],
                $terms->plan_selected_dates ?? [],
                $terms->plan_ends_on?->format('Y-m-d'),
                $terms->plan_occurrence_limit,
                $terms->window_before_minutes,
                $terms->window_after_minutes,
                $terms->reminder_offsets ?? [],
            );
        }, attempts: 3);
    }
}
