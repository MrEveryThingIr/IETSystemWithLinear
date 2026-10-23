<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\Planner\OutcomeShare;
use App\Models\User;
use App\Support\Planner\CommitmentFactors;
use App\Support\Planner\LabAuthority;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Explicit consent contract for future integrations. Never shares notes or changes source domains. */
final class ShareCommitmentOutcomes
{
    /** @param list<int> $planIds */
    public function grant(User $subject, Actor $recipient, string $purpose, array $planIds, CarbonImmutable $from, CarbonImmutable $through, CarbonImmutable $expires): OutcomeShare
    {
        $actor = LabAuthority::actor($subject);
        $recipient = Actor::query()->with('user')->findOrFail($recipient->id);
        abort_unless($recipient->user instanceof User && $recipient->id !== $actor->id, 422);
        LabAuthority::actor($recipient->user);
        abort_unless(trim($purpose) !== '' && mb_strlen($purpose) <= 500 && count($planIds) > 0
            && $expires->isFuture() && $expires->lte(now()->addYear()), 422);
        $snapshot = app(CommitmentFactors::class)->measure($subject, $planIds, $from, $through);
        foreach (Plan::query()->whereIn('id', $planIds)->get() as $plan) {
            Gate::forUser($recipient->user)->authorize('view', $plan);
        }

        return OutcomeShare::query()->create(['subject_actor_id' => $actor->id, 'recipient_actor_id' => $recipient->id,
            'purpose' => trim($purpose), 'plan_ids' => array_values(array_unique($planIds)), 'snapshot' => $snapshot, 'expires_at' => $expires]);
    }

    /** @return array<string, mixed> */
    public function read(User $recipient, OutcomeShare $share, string $purpose): array
    {
        $actor = LabAuthority::actor($recipient);
        $share = OutcomeShare::query()->findOrFail($share->id);
        abort_unless($share->recipient_actor_id === $actor->id && hash_equals($share->purpose, $purpose)
            && $share->revoked_at === null && $share->expires_at->isFuture(), 403);
        $plans = Plan::query()->whereIn('id', $share->plan_ids)->get();
        abort_unless($plans->count() === count($share->plan_ids), 403);
        foreach ($plans as $plan) {
            Gate::forUser($recipient)->authorize('view', $plan);
        }

        return $share->snapshot;
    }

    public function revoke(User $subject, OutcomeShare $share): void
    {
        $share = OutcomeShare::query()->findOrFail($share->id);
        LabAuthority::owns($subject, $share->subject_actor_id);
        if ($share->revoked_at === null) {
            $share->update(['revoked_at' => now()]);
        }
    }
}
