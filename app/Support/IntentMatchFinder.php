<?php

namespace App\Support;

use App\Models\ActorProfileIntent;
use App\Models\User;
use App\Policies\ActorProfileIntentPolicy;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class IntentMatchFinder
{
    public function __construct(private readonly ActorProfileIntentPolicy $policy) {}

    /**
     * @return Collection<int, IntentMatchResult>
     */
    public function find(User $user, ActorProfileIntent $source, int $limit = 50): Collection
    {
        Gate::forUser($user)->authorize('update', $source);

        $source->loadMissing(['concept', 'profile.actor']);
        abort_unless($source->status === ProfileIntentStatus::Active, 422, 'Only an active Intent can be matched.');

        $opposite = $source->kind === ProfileIntentKind::Need
            ? ProfileIntentKind::Offer
            : ProfileIntentKind::Need;

        return ActorProfileIntent::query()
            ->where('status', ProfileIntentStatus::Active->value)
            ->where('kind', $opposite->value)
            ->whereHas('profile', fn ($query) => $query
                ->where('actor_id', '!=', $source->profile->actor_id))
            ->with(['concept.labels', 'profile.actor.user', 'profile.displayImage.asset'])
            ->latest('updated_at')
            ->limit(500)
            ->get()
            ->filter(fn (ActorProfileIntent $candidate): bool => $this->policy->view($user, $candidate))
            ->map(fn (ActorProfileIntent $candidate): ?IntentMatchResult => $this->compare($source, $candidate))
            ->filter()
            ->sort(function (IntentMatchResult $left, IntentMatchResult $right): int {
                $byAlignment = $right->alignedDimensions <=> $left->alignedDimensions;

                if ($byAlignment !== 0) {
                    return $byAlignment;
                }

                return ($right->intent->updated_at?->getTimestamp() ?? 0)
                    <=> ($left->intent->updated_at?->getTimestamp() ?? 0);
            })
            ->take(max(1, min($limit, 200)))
            ->values();
    }

    public function match(User $user, ActorProfileIntent $source, ActorProfileIntent $candidate): ?IntentMatchResult
    {
        Gate::forUser($user)->authorize('update', $source);

        $source->loadMissing(['concept', 'profile.actor']);
        $candidate->loadMissing(['concept.labels', 'profile.actor.user', 'profile.displayImage.asset']);

        if ($source->status !== ProfileIntentStatus::Active
            || $candidate->status !== ProfileIntentStatus::Active
            || $source->kind === $candidate->kind
            || (int) $source->profile->actor_id === (int) $candidate->profile->actor_id
            || ! $this->policy->view($user, $candidate)) {
            return null;
        }

        return $this->compare($source, $candidate);
    }

    private function compare(ActorProfileIntent $source, ActorProfileIntent $candidate): ?IntentMatchResult
    {
        $sourceConcept = $source->concept->canonical();
        $candidateConcept = $candidate->concept->canonical();

        if (! $sourceConcept->is($candidateConcept)) {
            return null;
        }

        if (! $this->enumCompatible($source->subject_kind->value, $candidate->subject_kind->value)) {
            return null;
        }

        if (! $this->enumCompatible($source->arrangement_kind->value, $candidate->arrangement_kind->value)) {
            return null;
        }

        [$need, $offer] = $source->kind === ProfileIntentKind::Need
            ? [$source, $candidate]
            : [$candidate, $source];

        $reasons = ['concept'];
        $aligned = 1;

        if ($source->subject_kind === $candidate->subject_kind && $source->subject_kind->value !== 'other') {
            $reasons[] = 'subject';
            $aligned++;
        }

        if ($source->arrangement_kind === $candidate->arrangement_kind && $source->arrangement_kind->value !== 'other') {
            $reasons[] = 'arrangement';
            $aligned++;
        }

        if (! $this->quantityCompatible($need, $offer, $reasons, $aligned)) {
            return null;
        }

        if (! $this->placeCompatible('Location', $need->location_text, $offer->location_text, $reasons, $aligned)) {
            return null;
        }

        if (! $this->placeCompatible('Origin', $need->origin_text, $offer->origin_text, $reasons, $aligned)) {
            return null;
        }

        if (! $this->placeCompatible('Destination', $need->destination_text, $offer->destination_text, $reasons, $aligned)) {
            return null;
        }

        if (! $this->dateCompatible($need, $offer, $reasons, $aligned)) {
            return null;
        }

        if (! $this->timeCompatible($need, $offer, $reasons, $aligned)) {
            return null;
        }

        if (! $this->recurrenceCompatible($need, $offer, $reasons, $aligned)) {
            return null;
        }

        if (! $this->cashCompatible($need, $offer, $reasons, $aligned)) {
            return null;
        }

        if ($need->exchange_preference === $offer->exchange_preference) {
            $reasons[] = 'exchange-preference';
            $aligned++;
        }

        return new IntentMatchResult($candidate, $reasons, $aligned);
    }

    /**
     * @param  list<string>  $reasons
     */
    private function quantityCompatible(
        ActorProfileIntent $need,
        ActorProfileIntent $offer,
        array &$reasons,
        int &$aligned,
    ): bool {
        if ($need->quantity === null || $offer->quantity === null) {
            return true;
        }

        $needUnit = $this->normalizedText($need->unit);
        $offerUnit = $this->normalizedText($offer->unit);

        if ($needUnit !== '' && $offerUnit !== '' && $needUnit !== $offerUnit) {
            return false;
        }

        if ($this->scaledInteger((string) $offer->quantity, 4)
            < $this->scaledInteger((string) $need->quantity, 4)) {
            return false;
        }

        $reasons[] = 'quantity';
        $aligned++;

        return true;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function placeCompatible(
        string $label,
        ?string $needValue,
        ?string $offerValue,
        array &$reasons,
        int &$aligned,
    ): bool {
        $need = $this->normalizedText($needValue);
        $offer = $this->normalizedText($offerValue);

        if ($need === '' || $offer === '') {
            return true;
        }

        if (! str_contains($need, $offer) && ! str_contains($offer, $need)) {
            return false;
        }

        $reasons[] = Str::snake($label).'-overlap';
        $aligned++;

        return true;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function dateCompatible(
        ActorProfileIntent $need,
        ActorProfileIntent $offer,
        array &$reasons,
        int &$aligned,
    ): bool {
        if ($need->starts_on === null && $need->ends_on === null) {
            return true;
        }

        if ($offer->starts_on === null && $offer->ends_on === null) {
            return true;
        }

        $needStart = $need->starts_on?->startOfDay();
        $needEnd = $need->ends_on?->endOfDay();
        $offerStart = $offer->starts_on?->startOfDay();
        $offerEnd = $offer->ends_on?->endOfDay();

        if ($needEnd !== null && $offerStart !== null && $needEnd->lt($offerStart)) {
            return false;
        }

        if ($offerEnd !== null && $needStart !== null && $offerEnd->lt($needStart)) {
            return false;
        }

        $reasons[] = 'date-overlap';
        $aligned++;

        return true;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function timeCompatible(
        ActorProfileIntent $need,
        ActorProfileIntent $offer,
        array &$reasons,
        int &$aligned,
    ): bool {
        if ($need->time_window_start === null || $need->time_window_end === null
            || $offer->time_window_start === null || $offer->time_window_end === null
            || $need->timezone !== $offer->timezone) {
            return true;
        }

        $needStart = (string) $need->time_window_start;
        $needEnd = (string) $need->time_window_end;
        $offerStart = (string) $offer->time_window_start;
        $offerEnd = (string) $offer->time_window_end;

        if ($needEnd < $offerStart || $offerEnd < $needStart) {
            return false;
        }

        $reasons[] = 'time-overlap';
        $aligned++;

        return true;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function recurrenceCompatible(
        ActorProfileIntent $need,
        ActorProfileIntent $offer,
        array &$reasons,
        int &$aligned,
    ): bool {
        if ($need->schedule_kind !== $offer->schedule_kind || $need->timezone !== $offer->timezone) {
            return true;
        }

        if ($need->schedule_kind === ProfileIntentScheduleKind::Weekly
            && is_array($need->recurrence_weekdays)
            && is_array($offer->recurrence_weekdays)) {
            if (array_intersect($need->recurrence_weekdays, $offer->recurrence_weekdays) === []) {
                return false;
            }

            $reasons[] = 'weekly-overlap';
            $aligned++;
        }

        if ($need->schedule_kind === ProfileIntentScheduleKind::Monthly
            && $need->recurrence_day_of_month !== null
            && $offer->recurrence_day_of_month !== null) {
            if ($need->recurrence_day_of_month !== $offer->recurrence_day_of_month) {
                return false;
            }

            $reasons[] = 'monthly-aligns';
            $aligned++;
        }

        return true;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function cashCompatible(
        ActorProfileIntent $need,
        ActorProfileIntent $offer,
        array &$reasons,
        int &$aligned,
    ): bool {
        $needHasCash = $need->cash_min !== null || $need->cash_max !== null;
        $offerHasCash = $offer->cash_min !== null || $offer->cash_max !== null;

        if (! $needHasCash || ! $offerHasCash) {
            return true;
        }

        if ($need->currency_code !== null && $offer->currency_code !== null
            && strtoupper($need->currency_code) !== strtoupper($offer->currency_code)) {
            return false;
        }

        if ($need->cash_basis !== null && $offer->cash_basis !== null
            && $need->cash_basis !== $offer->cash_basis) {
            return false;
        }

        $needMin = $need->cash_min !== null ? $this->scaledInteger((string) $need->cash_min, 2) : 0;
        $needMax = $need->cash_max !== null ? $this->scaledInteger((string) $need->cash_max, 2) : PHP_INT_MAX;
        $offerMin = $offer->cash_min !== null ? $this->scaledInteger((string) $offer->cash_min, 2) : 0;
        $offerMax = $offer->cash_max !== null ? $this->scaledInteger((string) $offer->cash_max, 2) : PHP_INT_MAX;

        if ($needMax < $offerMin || $offerMax < $needMin) {
            return false;
        }

        $reasons[] = 'cash-overlap';
        $aligned++;

        return true;
    }

    private function enumCompatible(string $left, string $right): bool
    {
        return $left === $right || $left === 'other' || $right === 'other';
    }

    private function normalizedText(?string $value): string
    {
        return Str::of((string) $value)->lower()->squish()->toString();
    }

    private function scaledInteger(string $value, int $scale): int
    {
        $value = trim($value);
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $factor = $scale === 2 ? 100 : 10000;

        return ((int) $whole * $factor) + (int) $fraction;
    }
}
