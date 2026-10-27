<?php

namespace App\Support\Work;

use App\FulfillmentStatus;
use App\Models\Commitment;
use App\Models\User;
use App\Support\HomeTodayProjection;
use App\Support\QuantityAmount;

/**
 * The top of the Deals page: what needs me in my deals, and what is due under
 * them. These used to live on the Work page, which now opens Deals.
 */
final class DealsOverview
{
    private const ATTENTION_KINDS = ['relationship', 'proposal', 'contract', 'fulfillment', 'settlement'];

    public function __construct(private readonly HomeTodayProjection $today) {}

    /** @return list<array{title: string, meta: string, href: string}> */
    public function attention(User $user): array
    {
        return $this->today->build($user)['waitingOnMe']
            ->filter(fn ($item): bool => in_array($item->kind, self::ATTENTION_KINDS, true))
            ->take(5)
            ->map(fn ($item): array => ['title' => (string) $item->title, 'meta' => (string) $item->summary, 'href' => (string) $item->url])
            ->values()
            ->all();
    }

    /** @return list<array{title: string, meta: string, href: string}> */
    public function due(User $user): array
    {
        $actorId = (int) $user->actor?->id;
        if ($actorId === 0) {
            return [];
        }

        return Commitment::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('obligor_actor_id', $actorId)->orWhere('beneficiary_actor_id', $actorId))
            ->with(['planBinding', 'fulfillments:id,commitment_id,quantity,status', 'fulfillments.review'])
            ->latest('updated_at')
            ->limit(6)
            ->get()
            ->map(function (Commitment $commitment) use ($actorId): array {
                $accepted = $commitment->fulfillments->where('status', FulfillmentStatus::Accepted)
                    ->reduce(fn (string $sum, $fulfillment): string => QuantityAmount::add($sum, $fulfillment->confirmedQuantity()), QuantityAmount::normalize('0'));
                $waiting = $commitment->fulfillments->whereIn('status', [FulfillmentStatus::Submitted, FulfillmentStatus::ClarificationRequested])->count();

                return [
                    'title' => (string) $commitment->title,
                    'meta' => implode(' · ', array_filter([
                        (int) $commitment->obligor_actor_id === $actorId ? __('work.hub.i_do') : __('work.hub.for_me'),
                        __('work.hub.progress', ['done' => self::quantity($accepted), 'total' => self::quantity((string) $commitment->quantity), 'unit' => (string) $commitment->unit]),
                        $commitment->planBinding ? __('work.hub.planned') : __('work.hub.not_planned'),
                        $waiting > 0 ? trans_choice('work.hub.waiting_review', $waiting, ['count' => $waiting]) : null,
                    ])),
                    'href' => route('commitments.show', $commitment),
                ];
            })
            ->values()
            ->all();
    }

    private static function quantity(string $value): string
    {
        $normalized = QuantityAmount::normalize($value);

        return str_contains($normalized, '.') ? rtrim(rtrim($normalized, '0'), '.') : $normalized;
    }
}
