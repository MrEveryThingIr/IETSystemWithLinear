<?php

namespace App\Actions\Deals;

use App\Actions\Assets\CreateContextAsset;
use App\CommitmentEventType;
use App\Models\Commitment;
use App\Models\CommitmentEvent;
use App\Models\User;
use App\Support\QuantityAmount;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * One day's entry in a duty's work log: when the work started and ended, what happened (progress, problems, why work
 * stopped, what was missing), a rough progress figure and any evidence. It is history, not a claim: nothing here is
 * confirmed, priced or owed. Exact quantities are agreed at an inspection (milestone or end of the work).
 * Stored as an immutable CommitmentEvent, so no new record type exists.
 */
class LogWorkDay
{
    public function __construct(private readonly CreateContextAsset $assets) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function execute(Commitment $commitment, User $user, string $date, string $startTime, string $endTime, ?string $note = null, ?string $estimate = null, array $files = []): CommitmentEvent
    {
        Gate::forUser($user)->authorize('submit', $commitment);

        $zone = TemporalPreferences::timezoneFor($user);
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && strtotime($date) !== false, 422, (string) __('deals.errors.work_log_date'));
        abort_unless(preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime) === 1 && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $endTime) === 1, 422, (string) __('deals.errors.work_log_times'));

        $start = CarbonImmutable::parse($date.' '.$startTime, $zone);
        $end = CarbonImmutable::parse($date.' '.$endTime, $zone);
        abort_unless($end->gt($start), 422, (string) __('deals.errors.work_log_times'));
        abort_if($start->isFuture(), 422, (string) __('deals.errors.work_log_future'));

        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 5000, 422, (string) __('deals.errors.work_log_note'));
        abort_if(count($files) > 5, 422, (string) __('deals.errors.report_files'));

        $estimateValue = null;
        if ($estimate !== null && trim($estimate) !== '') {
            try {
                $estimateValue = QuantityAmount::positive($estimate);
            } catch (InvalidArgumentException) {
                abort(422, (string) __('deals.errors.report_quantity'));
            }
            abort_if(QuantityAmount::compare($estimateValue, (string) $commitment->quantity) > 0, 422, (string) __('deals.errors.estimate_exceeds'));
        }
        abort_if($note === '' && $estimateValue === null && $files === [], 422, (string) __('deals.errors.work_log_empty'));

        return DB::transaction(function () use ($commitment, $user, $date, $start, $end, $zone, $note, $estimateValue, $files): CommitmentEvent {
            $context = $commitment->loadMissing('contractVersion.contract.contextBinding.context')->contractVersion->contract->contextBinding?->context;
            $assetIds = array_map(fn (UploadedFile $file) => $this->assets->execute($context, $user, $file, 'owned')->id, $files);

            return CommitmentEvent::query()->create([
                'commitment_id' => $commitment->id,
                'actor_id' => $user->actor->id,
                'event_type' => CommitmentEventType::WorkLogged,
                'payload' => array_filter([
                    'work_date' => $date,
                    'started_at' => $start->utc()->toIso8601String(),
                    'ended_at' => $end->utc()->toIso8601String(),
                    'timezone' => $zone,
                    'minutes' => (int) $start->diffInMinutes($end),
                    'note' => $note !== '' ? $note : null,
                    'estimate' => $estimateValue,
                    'unit' => $commitment->unit,
                    'asset_ids' => $assetIds ?: null,
                ], fn ($v) => $v !== null),
            ]);
        });
    }
}
