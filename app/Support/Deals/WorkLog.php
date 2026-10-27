<?php

namespace App\Support\Deals;

use App\CommitmentEventType;
use App\Models\Asset;
use App\Models\Commitment;
use App\Models\CommitmentEvent;
use App\Support\QuantityAmount;
use Illuminate\Support\Collection;

/** Reads a duty's work log (its "work_logged" events): the entries, newest day first, and what they add up to. */
final class WorkLog
{
    /**
     * @return array{entries: Collection<int, mixed>, days: int, minutes: int, estimate: string}
     */
    public static function for(Commitment $commitment): array
    {
        $events = CommitmentEvent::query()->where('commitment_id', $commitment->id)
            ->where('event_type', CommitmentEventType::WorkLogged->value)->orderBy('id')->get();
        $assets = Asset::query()->whereIn('id', $events->flatMap(fn ($e) => $e->payload['asset_ids'] ?? [])->unique()->all())->get()->keyBy('id');

        $entries = $events->map(fn (CommitmentEvent $e) => [
            'id' => $e->id,
            'date' => $e->payload['work_date'],
            'started_at' => $e->payload['started_at'],
            'ended_at' => $e->payload['ended_at'],
            'minutes' => (int) ($e->payload['minutes'] ?? 0),
            'note' => $e->payload['note'] ?? null,
            'estimate' => $e->payload['estimate'] ?? null,
            'files' => collect($e->payload['asset_ids'] ?? [])->map(fn ($id) => $assets[$id]->original_filename ?? null)->filter()->values()->all(),
        ])->sortByDesc(fn ($e) => $e['started_at'])->values();

        return [
            'entries' => $entries,
            'days' => $entries->pluck('date')->unique()->count(),
            'minutes' => (int) $entries->sum('minutes'),
            // A rough running total the doer reports for themselves; never an inspection result and never owed on its own.
            'estimate' => $entries->reduce(fn (string $sum, array $e) => $e['estimate'] !== null ? QuantityAmount::add($sum, (string) $e['estimate']) : $sum, QuantityAmount::normalize('0')),
        ];
    }
}
