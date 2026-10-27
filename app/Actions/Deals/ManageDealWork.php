<?php

namespace App\Actions\Deals;

use App\Actions\Commitments\CreateCommitment;
use App\Actions\Commitments\CreateCommitmentPlan;
use App\CommitmentKind;
use App\Models\Commitment;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\Relationship;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\Support\Deals\DealParties;
use App\Support\QuantityAmount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Work step of a Deal. Either party proposes a duty of the Agreement in force (who does it, for whom, how much, how
 * often); the person who receives it confirms; only then do the existing Commitment (and, for repeated work, Planner)
 * records exist. Pending proposals are private to the Deal and hold no obligation.
 */
class ManageDealWork
{
    public function __construct(
        private readonly CreateCommitment $createCommitment,
        private readonly CreateCommitmentPlan $createPlan,
    ) {}

    /**
     * @param  array<string, mixed>  $input  title, kind, quantity (per cycle), unit, cycles, obligor_party, beneficiary_party, starts_on, start_time, duration, every, period
     * @return array<string, mixed> the pending proposal
     */
    public function propose(Relationship $deal, User $user, array $input): array
    {
        return DB::transaction(function () use ($deal, $user, $input): array {
            $locked = Relationship::query()->lockForUpdate()->findOrFail($deal->id);
            $version = $this->version($locked);
            $obligor = $this->party($version, (int) $input['obligor_party']);
            $beneficiary = $this->party($version, (int) $input['beneficiary_party']);

            abort_if($obligor->is($beneficiary), 422, 'The person doing the work and the person receiving it must be different.');
            $mine = DealParties::representedBy($version->parties, $user, fresh: true);
            abort_unless($mine->contains(fn ($p) => $p->is($obligor) || $p->is($beneficiary)), 403);

            $title = Str::squish((string) $input['title']);
            abort_if($title === '' || mb_strlen($title) > 180, 422, 'The duty needs a short title.');
            $kind = CommitmentKind::from((string) $input['kind']);
            abort_if($kind === CommitmentKind::Payment, 422, 'Money is not set up here.');
            $cycles = (int) $input['cycles'];
            abort_unless($cycles >= 1 && $cycles <= 366, 422, 'Cycles must be between 1 and 366.');
            $unit = Str::squish((string) $input['unit']);
            abort_if($unit === '' || mb_strlen($unit) > 40, 422, 'The duty needs a unit.');
            $perCycle = QuantityAmount::positive($input['quantity']);
            $startsOn = ($input['starts_on'] ?? '') !== '' ? (string) $input['starts_on'] : null;
            abort_if($cycles > 1 && $startsOn === null, 422, 'Repeated work needs a start date.');
            if ($startsOn !== null) {
                abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $startsOn) === 1 && strtotime($startsOn) !== false, 422, 'The start date is not valid.');
                abort_unless(preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($input['start_time'] ?? '')) === 1, 422, 'The start time is not valid.');
                abort_unless((int) ($input['duration'] ?? 0) >= 1 && (int) $input['duration'] <= 10080, 422, 'The duration is not valid.');
            }
            $period = (string) ($input['period'] ?? 'day');
            abort_unless(in_array($period, ['day', 'week', 'month'], true), 422, 'Work can repeat daily, weekly or monthly.');

            $item = [
                'id' => (string) Str::uuid(),
                'version_id' => $version->id,
                'title' => $title,
                'kind' => $kind->value,
                'quantity' => $perCycle,
                'unit' => $unit,
                'cycles' => $cycles,
                'obligor_party' => $obligor->id,
                'beneficiary_party' => $beneficiary->id,
                'starts_on' => $startsOn,
                'start_time' => $startsOn !== null ? $input['start_time'] : null,
                'duration' => $startsOn !== null ? (int) $input['duration'] : null,
                'every' => max(1, (int) ($input['every'] ?? 1)),
                'period' => $period,
                'proposed_by_user_id' => $user->id,
                'proposed_at' => now()->toIso8601String(),
            ];

            $this->store($locked, [...$this->pending($locked), $item]);

            return $item;
        });
    }

    /** Either side may take a proposal back or decline it; nothing existed, so nothing is undone. */
    public function withdraw(Relationship $deal, User $user, string $id): void
    {
        DB::transaction(function () use ($deal, $user, $id): void {
            $locked = Relationship::query()->lockForUpdate()->findOrFail($deal->id);
            $item = collect($this->pending($locked))->firstWhere('id', $id);
            abort_if($item === null, 404);

            abort_unless($this->mayWithdraw($user, $item, fresh: true), 403);

            $this->store($locked, collect($this->pending($locked))->reject(fn ($i) => $i['id'] === $id)->values()->all());
        });
    }

    /** The receiving party confirms: this is the moment the duty becomes a Commitment. Confirming again changes nothing. */
    public function confirm(Relationship $deal, User $user, string $id): Commitment
    {
        return DB::transaction(function () use ($deal, $user, $id): Commitment {
            $locked = Relationship::query()->lockForUpdate()->findOrFail($deal->id);
            $item = collect($this->pending($locked))->firstWhere('id', $id);
            abort_if($item === null, 404);

            $version = $this->version($locked, (int) $item['version_id']);
            $obligor = $this->party($version, (int) $item['obligor_party']);
            $beneficiary = $this->party($version, (int) $item['beneficiary_party']);
            abort_unless(DealParties::representedBy([$beneficiary], $user, fresh: true)->isNotEmpty(), 403);

            $key = $version->id.':'.$item['id'];
            $commitment = Commitment::query()->where('derivation_key', $key)->first();

            if (! $commitment instanceof Commitment) {
                $cycles = (int) $item['cycles'];
                $total = QuantityAmount::fromScaledInt(QuantityAmount::toScaledInt($item['quantity']) * $cycles);

                $commitment = $this->createCommitment->execute(
                    $version->contract,
                    $user,
                    $obligor->actor,
                    $beneficiary->actor,
                    CommitmentKind::from($item['kind']),
                    $item['title'],
                    $total,
                    $item['unit'],
                    derivationKey: $key,
                    asDealParty: true,
                );

                if ($item['starts_on'] !== null) {
                    $start = CarbonImmutable::parse($item['starts_on']);
                    [$frequency, $weekdays, $monthDay] = match (true) {
                        $cycles === 1 => [PlanScheduleFrequency::Once, [], null],
                        $item['period'] === 'week' => [PlanScheduleFrequency::Weekly, [$start->dayOfWeekIso], null],
                        $item['period'] === 'month' => [PlanScheduleFrequency::Monthly, [], $start->day],
                        default => [PlanScheduleFrequency::Daily, [], null],
                    };

                    $this->createPlan->execute(
                        $commitment,
                        $user,
                        $frequency,
                        $item['starts_on'],
                        $item['start_time'],
                        (int) $item['duration'],
                        interval: (int) $item['every'],
                        weekdays: $weekdays,
                        occurrenceLimit: $cycles,
                        monthDay: $monthDay,
                        asDealParty: true,
                    );
                }
            }

            $this->store($locked, collect($this->pending($locked))->reject(fn ($i) => $i['id'] === $id)->values()->all());

            return $commitment;
        });
    }

    /** Either party of the version the duty was proposed under may take it back, even after that version was superseded. */
    public function mayWithdraw(User $user, array $item, bool $fresh = false): bool
    {
        $version = ContractVersion::query()->with('parties')->find($item['version_id']);

        return $version !== null
            && DealParties::representedBy($version->parties, $user, $fresh)
                ->contains(fn ($p) => in_array($p->id, [$item['obligor_party'], $item['beneficiary_party']], true));
    }

    /** @return list<array<string, mixed>> */
    public function pending(Relationship $deal): array
    {
        return array_values($deal->metadata['work_setup'] ?? []);
    }

    /** @param  list<array<string, mixed>>  $items */
    private function store(Relationship $deal, array $items): void
    {
        $metadata = $deal->metadata ?? [];
        unset($metadata['work_setup']);
        if ($items !== []) {
            $metadata['work_setup'] = $items;
        }
        $deal->forceFill(['metadata' => $metadata])->save();
    }

    /** The Agreement in force. A proposal made under an earlier version cannot be confirmed under a later one. */
    private function version(Relationship $deal, ?int $expected = null): ContractVersion
    {
        $contract = $deal->contracts()->latest('id')->first();
        $version = $contract?->activeVersionRecord();
        abort_if($version === null, 422, 'There is no agreement in force to plan work from.');
        abort_if($expected !== null && $expected !== (int) $version->id, 422, 'The agreement changed after this was proposed. Propose the work again.');
        $version->loadMissing('contract', 'parties.actor.user');

        return $version;
    }

    private function party(ContractVersion $version, int $id): ContractVersionParty
    {
        $party = $version->parties->firstWhere('id', $id);
        abort_if($party === null, 422, 'That party is not part of this agreement.');

        return $party;
    }
}
