<?php

namespace App\Livewire\Deals;

use App\Actions\Deals\ManageDealWork;
use App\Actions\Fulfillments\OpenFulfillmentDispute;
use App\Actions\Fulfillments\ResolveFulfillmentDispute;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Planner\TransitionPlanOccurrence;
use App\CommitmentKind;
use App\FulfillmentReviewDecision;
use App\FulfillmentStatus;
use App\Models\Commitment;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\Fulfillment;
use App\Models\FulfillmentDispute;
use App\Models\PlanOccurrence;
use App\Models\Relationship;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\Support\CommitmentProgress;
use App\Support\Deals\AgreementTerms;
use App\Support\Deals\DealParties;
use App\Support\QuantityAmount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The Work tab of a Deal: the one place where an Agreement's duties are set up, performed, reported and confirmed.
 * It only drives the existing Commitment, Planner and Fulfillment actions; it owns no records of its own.
 */
class WorkPanel extends Component
{
    public Relationship $relationship;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, string> keyed by "<kind>-<id>" */
    public array $text = [];

    public function mount(Relationship $relationship): void
    {
        $this->relationship = $relationship;
        $this->resetForm();
    }

    public function propose(ManageDealWork $work): void
    {
        $this->guarded(function () use ($work): void {
            $f = $this->form;
            $work->propose($this->relationship, $this->user(), [
                'title' => (string) ($f['title'] ?? ''),
                'kind' => (string) ($f['kind'] ?? 'work'),
                'quantity' => (string) ($f['quantity'] ?? ''),
                'unit' => (string) ($f['unit'] ?? ''),
                'cycles' => (int) ($f['cycles'] ?? 1),
                'obligor_party' => (int) ($f['obligor'] ?? 0),
                'beneficiary_party' => (int) ($f['beneficiary'] ?? 0),
                'starts_on' => (string) ($f['starts_on'] ?? ''),
                'start_time' => (string) ($f['start_time'] ?? ''),
                'duration' => (int) ($f['duration'] ?? 0),
                'every' => (int) ($f['every'] ?? 1),
                'period' => (string) ($f['period'] ?? 'day'),
            ]);
            $this->resetForm();
        });
    }

    public function confirm(string $id, ManageDealWork $work): void
    {
        $this->guarded(fn () => $work->confirm($this->relationship, $this->user(), $id));
    }

    public function withdraw(string $id, ManageDealWork $work): void
    {
        $this->guarded(fn () => $work->withdraw($this->relationship, $this->user(), $id));
    }

    /** "I did this": the performer reports one cycle (or the whole one-time duty). Marking it done in the Planner is not enough on its own. */
    public function report(int $commitmentId, ?string $occurrenceUuid, string $quantity, SubmitFulfillment $submit, TransitionPlanOccurrence $planner): void
    {
        $this->guarded(function () use ($commitmentId, $occurrenceUuid, $quantity, $submit, $planner): void {
            $commitment = $this->commitments()->findOrFail($commitmentId);
            Gate::forUser($this->user())->authorize('submit', $commitment);
            $notes = trim($this->text['notes-'.$commitmentId.'-'.($occurrenceUuid ?? 'once')] ?? '');

            DB::transaction(function () use ($commitment, $occurrenceUuid, $quantity, $notes, $submit, $planner): void {
                $occurrence = null;
                if ($occurrenceUuid !== null) {
                    $occurrence = PlanOccurrence::query()->where('uuid', $occurrenceUuid)->firstOrFail();
                    if ($occurrence->status !== PlanOccurrenceStatus::Completed) {
                        $occurrence = $planner->markDone($occurrence, $this->user());
                    }
                }
                $submit->execute($commitment, $this->user(), $quantity, occurrence: $occurrence, notes: $notes !== '' ? $notes : null);
            });
        });
    }

    public function review(int $fulfillmentId, string $decision, ReviewFulfillment $review): void
    {
        $this->guarded(function () use ($fulfillmentId, $decision, $review): void {
            $fulfillment = $this->fulfillment($fulfillmentId);
            $note = trim($this->text['review-'.$fulfillmentId] ?? '');
            $review->execute($fulfillment, $this->user(), FulfillmentReviewDecision::from($decision), $note !== '' ? $note : null);
        });
    }

    /** After "ask for clarification" or "not accepted", the performer sends a corrected report for the same cycle. */
    public function correct(int $fulfillmentId, string $quantity, SubmitFulfillment $submit): void
    {
        $this->guarded(function () use ($fulfillmentId, $quantity, $submit): void {
            $fulfillment = $this->fulfillment($fulfillmentId);
            $note = trim($this->text['notes-fix-'.$fulfillmentId] ?? '');
            $submit->execute($fulfillment->commitment, $this->user(), $quantity, occurrence: $fulfillment->occurrence, notes: $note !== '' ? $note : null, corrects: $fulfillment);
        });
    }

    public function dispute(int $fulfillmentId, OpenFulfillmentDispute $open): void
    {
        $this->guarded(fn () => $open->execute($this->fulfillment($fulfillmentId), $this->user(), trim($this->text['dispute-'.$fulfillmentId] ?? '')));
    }

    public function resolve(int $disputeId, string $resolution, ResolveFulfillmentDispute $resolve): void
    {
        $this->guarded(function () use ($disputeId, $resolution, $resolve): void {
            $dispute = FulfillmentDispute::query()->findOrFail($disputeId);
            $resolve->execute($dispute, $this->user(), FulfillmentStatus::from($resolution), trim($this->text['resolve-'.$disputeId] ?? '') ?: null);
        });
    }

    public function render(ManageDealWork $work, CommitmentProgress $progress): View
    {
        $this->relationship->refresh(); // actions work on their own locked copy
        $version = $this->version();
        $mine = $version instanceof ContractVersion ? DealParties::representedBy($version->parties, $this->user())->pluck('id')->all() : [];
        $actorOf = fn (ContractVersionParty $party): string => $party->business->name ?? $party->group->name ?? $party->actor->user->username ?? '';
        $labels = $version instanceof ContractVersion ? $version->parties->mapWithKeys(fn ($p) => [$p->id => $actorOf($p)])->all() : [];

        $activeId = $version instanceof ContractVersion ? (int) $version->id : null;

        $pending = collect($work->pending($this->relationship))->map(fn (array $i) => $i + [
            'obligor_name' => $this->nameOf($i['obligor_party'], $labels),
            'beneficiary_name' => $this->nameOf($i['beneficiary_party'], $labels),
            'can_confirm' => in_array($i['beneficiary_party'], $mine, true) && $activeId === $i['version_id'],
            'can_withdraw' => $work->mayWithdraw($this->user(), $i),
            'stale' => $activeId !== $i['version_id'],
        ]);

        $duties = $this->commitments()->with(['obligor.user', 'beneficiary.user', 'contractVersion.contract.sourceProposalVersion', 'planBinding.plan.occurrences', 'fulfillments.review', 'fulfillments.dispute', 'fulfillments.occurrence'])->orderBy('id')->get()
            ->map(fn (Commitment $c) => $this->duty($c, $progress));

        return view('livewire.deals.work-panel', [
            'version' => $version,
            'labels' => $labels,
            'mine' => $mine,
            'pending' => $pending,
            'duties' => $duties,
            'kinds' => array_values(array_filter(CommitmentKind::cases(), fn ($k) => $k !== CommitmentKind::Payment)),
            'user' => $this->user(),
        ]);
    }

    /** @return array<string, mixed> */
    private function duty(Commitment $c, CommitmentProgress $progress): array
    {
        $user = $this->user();
        $terms = AgreementTerms::of($c->contractVersion);
        $verifier = $terms['completion']['confirmed_by'] ?? 'beneficiary';
        $occurrences = $c->planBinding?->plan->occurrences->sortBy('scheduled_start_at')->values() ?? collect();
        $perCycle = $occurrences->isNotEmpty() ? QuantityAmount::fromScaledInt(intdiv(QuantityAmount::toScaledInt($c->quantity), $occurrences->count())) : QuantityAmount::subtractFloorZero($c->quantity, $progress->acceptedQuantity($c));
        $live = fn (?int $occId) => $c->fulfillments->where('plan_occurrence_id', $occId)->where('status', '!=', FulfillmentStatus::Corrected)->sortByDesc('id')->first();

        $rows = $occurrences->isNotEmpty()
            ? $occurrences->map(fn (PlanOccurrence $o) => ['occurrence' => $o, 'fulfillment' => $live($o->id)])
            : collect([['occurrence' => null, 'fulfillment' => $c->fulfillments->where('status', '!=', FulfillmentStatus::Corrected)->sortByDesc('id')->first()]]);

        return [
            'commitment' => $c,
            'verifier' => $verifier,
            'perCycle' => $perCycle,
            'accepted' => $progress->acceptedQuantity($c),
            'rows' => $rows,
            'isObligor' => Gate::forUser($user)->allows('submit', $c),
            'verified_by_receiver' => $verifier !== 'none',
        ];
    }

    /** Party names of a proposal made under an earlier version come from that version's own party rows. */
    private function nameOf(int $partyId, array $labels): string
    {
        if (isset($labels[$partyId])) {
            return $labels[$partyId];
        }
        $party = ContractVersionParty::query()->with(['actor.user', 'business', 'group'])->find($partyId);

        return $party->business->name ?? $party->group->name ?? $party->actor->user->username ?? '';
    }

    private function version(): ?ContractVersion
    {
        $version = $this->relationship->contracts()->latest('id')->first()?->activeVersionRecord();
        $version?->loadMissing('contract', 'parties.actor.user', 'parties.business', 'parties.group');

        return $version;
    }

    /** All Commitments of this Deal's Agreement, whichever version they were set up under. */
    private function commitments()
    {
        return Commitment::query()->whereHas('contractVersion.contract', fn ($q) => $q->where('relationship_id', $this->relationship->id));
    }

    private function fulfillment(int $id): Fulfillment
    {
        $fulfillment = Fulfillment::query()->with(['commitment', 'occurrence'])->findOrFail($id);
        abort_unless($this->commitments()->whereKey($fulfillment->commitment_id)->exists(), 404);

        return $fulfillment;
    }

    private function resetForm(): void
    {
        $this->form = ['title' => '', 'kind' => 'work', 'quantity' => '1', 'unit' => '', 'cycles' => 1, 'obligor' => '', 'beneficiary' => '', 'starts_on' => '', 'start_time' => '09:00', 'duration' => 60, 'every' => 1, 'period' => 'day'];
    }

    private function guarded(callable $call): void
    {
        $this->resetErrorBag('work');
        try {
            $call();
        } catch (HttpException $e) {
            abort_if(in_array($e->getStatusCode(), [403, 404], true), $e->getStatusCode());
            $this->addError('work', $e->getMessage() !== '' ? $e->getMessage() : __('deals.work.failed'));
        } catch (\InvalidArgumentException $e) {
            $this->addError('work', $e->getMessage());
        }
    }

    private function user(): User
    {
        return auth()->user();
    }
}
