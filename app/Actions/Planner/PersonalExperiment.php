<?php

namespace App\Actions\Planner;

use App\Models\Plan;
use App\Models\Planner\Experiment;
use App\Models\Planner\ExperimentAdjustment;
use App\Models\Planner\ExperimentObservation;
use App\Models\Planner\Purpose;
use App\Models\User;
use App\Support\Planner\LabAuthority;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Private, optional self-experimentation; no source Plan, agreement or target is changed here. */
final class PersonalExperiment
{
    /** @param array<string, mixed> $input */
    public function create(User $user, array $input, ?Plan $plan = null, ?Purpose $purpose = null): Experiment
    {
        $actor = LabAuthority::actor($user);
        abort_unless(($plan === null) !== ($purpose === null), 422, 'Associate one plan, routine or purpose.');
        if ($plan !== null) {
            Gate::forUser($user)->authorize('view', $plan);
        }
        if ($purpose !== null) {
            LabAuthority::owns($user, $purpose->owner_actor_id);
        }
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:180'], 'hypothesis' => ['required', 'string', 'max:3000'],
            'selected_change' => ['required', 'string', 'max:3000'],
            'metric' => ['required', 'in:active_minutes,completed_count,start_delay_minutes,difficulty,custom'],
            'baseline' => ['nullable', 'numeric', 'between:-1000000,1000000'], 'target' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'cue' => ['nullable', 'string', 'max:2000'], 'planned_response' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        abort_if(CarbonImmutable::parse($data['starts_on'])->diffInDays(CarbonImmutable::parse($data['ends_on'])) > 366, 422);

        return Experiment::query()->create([
            'title' => $data['title'], 'hypothesis' => $data['hypothesis'], 'selected_change' => $data['selected_change'],
            'metric' => $data['metric'], 'baseline' => $data['baseline'] ?? null, 'target' => $data['target'] ?? null,
            'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'cue' => $data['cue'] ?? null,
            'planned_response' => $data['planned_response'] ?? null,
            'owner_actor_id' => $actor->id, 'plan_id' => $plan?->id, 'purpose_id' => $purpose?->id,
            'timezone' => TemporalPreferences::timezoneFor($user)]);
    }

    /** @param array<string, mixed> $input */
    public function observe(User $user, Experiment $experiment, string $key, array $input, ?ExperimentObservation $supersedes = null): ExperimentObservation
    {
        $experiment = $this->authorize($user, $experiment);
        abort_unless(strlen($key) >= 8 && strlen($key) <= 100, 422);
        $data = Validator::make($input, [
            'observed_at' => ['required', 'date'], 'value' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'difficulty' => ['nullable', 'integer', 'between:1,5'],
            'cue' => ['nullable', 'string', 'max:2000'], 'obstacle' => ['nullable', 'string', 'max:2000'],
            'response' => ['nullable', 'string', 'max:2000'], 'reflection' => ['nullable', 'string', 'max:5000'],
            'context' => ['nullable', 'array', 'max:10'], 'context.*' => ['string', 'max:200'],
        ])->validate();
        $moment = CarbonImmutable::parse($data['observed_at'], $experiment->timezone)->utc();
        $start = CarbonImmutable::parse($experiment->starts_on->toDateString(), $experiment->timezone)->startOfDay();
        $end = CarbonImmutable::parse($experiment->ends_on->toDateString(), $experiment->timezone)->endOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        abort_if($moment->gt(now()) || $moment->gt($end) || $moment->lt($start->subDays($days)), 422, 'Observe within this experiment or its equally long preceding baseline period.');
        $data['observed_at'] = $moment->toIso8601String();
        ksort($data);
        $fingerprint = hash('sha256', json_encode([$data, $supersedes?->id], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($experiment, $key, $data, $fingerprint, $supersedes): ExperimentObservation {
            Experiment::query()->lockForUpdate()->findOrFail($experiment->id);
            $existing = ExperimentObservation::query()->where('experiment_id', $experiment->id)->where('retry_key', $key)->first();
            if ($existing !== null) {
                abort_unless(hash_equals($existing->fingerprint, $fingerprint), 409);

                return $existing;
            }
            if ($supersedes !== null) {
                $original = ExperimentObservation::query()->findOrFail($supersedes->id);
                abort_unless($original->experiment_id === $experiment->id, 403);
                abort_if(ExperimentObservation::query()->where('supersedes_id', $original->id)->exists(), 409);
            }

            return ExperimentObservation::query()->create([
                'observed_at' => $data['observed_at'], 'value' => $data['value'] ?? null, 'difficulty' => $data['difficulty'] ?? null,
                'cue' => $data['cue'] ?? null, 'obstacle' => $data['obstacle'] ?? null, 'response' => $data['response'] ?? null,
                'reflection' => $data['reflection'] ?? null, 'context' => $data['context'] ?? null,
                'experiment_id' => $experiment->id, 'retry_key' => $key,
                'fingerprint' => $fingerprint, 'supersedes_id' => $supersedes?->id]);
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    public function insights(User $user, Experiment $experiment): array
    {
        $experiment = $this->authorize($user, $experiment);
        $rows = ExperimentObservation::query()->where('experiment_id', $experiment->id)
            ->whereNotIn('id', ExperimentObservation::query()->where('experiment_id', $experiment->id)->whereNotNull('supersedes_id')->select('supersedes_id'))->get();
        $start = CarbonImmutable::parse($experiment->starts_on->toDateString(), $experiment->timezone)->startOfDay()->utc();
        $baseline = $rows->filter(fn ($row): bool => $row->observed_at->lt($start))->whereNotNull('value');
        $current = $rows->filter(fn ($row): bool => $row->observed_at->gte($start))->whereNotNull('value');
        $baselineMean = $baseline->isEmpty() ? null : round($baseline->avg('value'), 3);
        $currentMean = $current->isEmpty() ? null : round($current->avg('value'), 3);

        return ['metric' => $experiment->metric, 'baseline_n' => $baseline->count(), 'observed_n' => $current->count(),
            'baseline_mean' => $baselineMean, 'observed_mean' => $currentMean,
            'difference' => $baselineMean === null || $currentMean === null ? null : round($currentMean - $baselineMean, 3),
            'declared_baseline' => $experiment->baseline, 'declared_target' => $experiment->target,
            'confidence' => 'self_reported', 'interpretation' => 'Observed association only; these observations do not prove causation. Missing observations are not zero.',
        ];
    }

    public function propose(User $user, Experiment $experiment, string $proposal): ExperimentAdjustment
    {
        $experiment = $this->authorize($user, $experiment);
        abort_unless(trim($proposal) !== '' && mb_strlen($proposal) <= 3000, 422);

        return ExperimentAdjustment::query()->create(['experiment_id' => $experiment->id, 'proposal' => trim($proposal)]);
    }

    public function decide(User $user, ExperimentAdjustment $adjustment, bool $approve): ExperimentAdjustment
    {
        return DB::transaction(function () use ($user, $adjustment, $approve): ExperimentAdjustment {
            $row = ExperimentAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            $this->authorize($user, Experiment::query()->findOrFail($row->experiment_id));
            $decision = $approve ? 'approved' : 'rejected';
            if ($row->decision !== null) {
                abort_unless($row->decision === $decision, 409);

                return $row;
            }
            $row->update(['decision' => $decision, 'decided_at' => now()]);

            // Approval records intent. A separate explicit authorized Plan revision is still required.
            return $row;
        }, attempts: 3);
    }

    public function authorize(User $user, Experiment $experiment): Experiment
    {
        $current = Experiment::query()->findOrFail($experiment->id);
        LabAuthority::owns($user, $current->owner_actor_id);

        return $current;
    }
}
