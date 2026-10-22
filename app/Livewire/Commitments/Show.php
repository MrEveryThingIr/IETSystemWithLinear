<?php

namespace App\Livewire\Commitments;

use App\Actions\Commitments\BindExistingPlan;
use App\Actions\Commitments\CreateCommitmentPlan;
use App\Actions\Fulfillments\OpenFulfillmentDispute;
use App\Actions\Fulfillments\ResolveFulfillmentDispute;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\Actions\Planner\SavePlanTemplate;
use App\Actions\Work\ProposeWorkBookkeepingEntry;
use App\FulfillmentReviewDecision;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Bookkeeping\Ledger;
use App\Models\Bookkeeping\Transaction;
use App\Models\Commitment;
use App\Models\Fulfillment;
use App\Models\FulfillmentDispute;
use App\Models\PlanOccurrence;
use App\Models\PlanTemplate;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\Support\CommitmentProgress;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\QuantityAmount;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Commitment')]
class Show extends Component
{
    public Commitment $commitment;

    public string $planFrequency = 'once';

    public string $planStartsOn = '';

    public string $planStartTime = '08:00';

    public int $planDurationMinutes = 60;

    public int $planOccurrenceLimit = 1;

    public string $planSelectedDates = '';

    public string $planReminderOffsets = '15';

    /** How to schedule this work: new, template, existing (a plan already in this deal) or none. */
    public string $planChoice = 'new';

    public string $templateUuid = '';

    public string $existingPlanUuid = '';

    public string $templateName = '';

    public string $templateOwner = '';

    /** @var array<int, string> fulfillment id => chosen shared book uuid */
    public array $bookChoice = [];

    public string $fulfillmentOccurrenceUuid = '';

    public string $fulfillmentQuantity = '1';

    public string $fulfillmentNotes = '';

    /** @var array<int, string> */
    public array $reviewNotes = [];

    /** @var array<int, string> */
    public array $correctionQuantities = [];

    /** @var array<int, string> */
    public array $correctionNotes = [];

    /** @var array<int, string> */
    public array $disputeReasons = [];

    /** @var array<int, string> */
    public array $resolutionNotes = [];

    public function mount(Commitment $commitment): void
    {
        Gate::forUser($this->user())->authorize('view', $commitment);
        $commitment->loadMissing(['serviceTerm.monetaryUnit']);
        $this->commitment = $commitment;

        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->planStartsOn = CarbonImmutable::now($timezone)->format('Y-m-d');
        $this->fulfillmentQuantity = $this->suggestedFulfillmentQuantity();
    }

    public function createPlan(CreateCommitmentPlan $create): void
    {
        Gate::forUser($this->user())->authorize('manage', $this->commitment);

        $data = $this->validate([
            'planFrequency' => ['required', 'in:once,daily,selected_dates'],
            'planStartsOn' => ['required', 'date_format:Y-m-d'],
            'planStartTime' => ['required', 'date_format:H:i'],
            'planDurationMinutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'planOccurrenceLimit' => ['required', 'integer', 'min:1', 'max:1000'],
            'planSelectedDates' => ['nullable', 'string', 'max:5000'],
            'planReminderOffsets' => ['nullable', 'string', 'max:500'],
        ]);

        $frequency = PlanScheduleFrequency::from($data['planFrequency']);
        $selectedDates = $this->dates($data['planSelectedDates']);

        $create->execute(
            $this->commitment,
            $this->user(),
            $frequency,
            $data['planStartsOn'],
            $data['planStartTime'],
            $data['planDurationMinutes'],
            selectedDates: $selectedDates,
            occurrenceLimit: $frequency === PlanScheduleFrequency::Daily
                ? $data['planOccurrenceLimit']
                : ($frequency === PlanScheduleFrequency::Once ? 1 : null),
            reminderOffsets: $this->integers($data['planReminderOffsets']),
        );

        $this->refreshCommitment();
        session()->flash('status', __('commitments.messages.plan_created'));
    }

    public function submitFulfillment(SubmitFulfillment $submit): void
    {
        Gate::forUser($this->user())->authorize('submit', $this->commitment);

        $data = $this->validate([
            'fulfillmentOccurrenceUuid' => ['nullable', 'uuid'],
            'fulfillmentQuantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'fulfillmentNotes' => ['nullable', 'string', 'max:10000'],
        ]);

        $occurrence = $data['fulfillmentOccurrenceUuid'] !== ''
            ? PlanOccurrence::query()->where('uuid', $data['fulfillmentOccurrenceUuid'])->firstOrFail()
            : null;

        $submit->execute(
            $this->commitment,
            $this->user(),
            $data['fulfillmentQuantity'],
            occurrence: $occurrence,
            notes: $data['fulfillmentNotes'] !== '' ? $data['fulfillmentNotes'] : null,
        );

        $this->reset('fulfillmentOccurrenceUuid', 'fulfillmentNotes');
        $this->refreshCommitment();
        $this->fulfillmentQuantity = $this->suggestedFulfillmentQuantity();
        session()->flash('status', __('commitments.messages.submitted'));
    }

    public function review(int $fulfillmentId, string $decision, ReviewFulfillment $review): void
    {
        $fulfillment = Fulfillment::query()->findOrFail($fulfillmentId);
        Gate::forUser($this->user())->authorize('review', $fulfillment);

        $review->execute(
            $fulfillment,
            $this->user(),
            FulfillmentReviewDecision::from($decision),
            ($this->reviewNotes[$fulfillmentId] ?? '') !== ''
                ? $this->reviewNotes[$fulfillmentId]
                : null,
        );

        unset($this->reviewNotes[$fulfillmentId]);
        $this->refreshCommitment();
        session()->flash('status', __('commitments.messages.reviewed'));
    }

    public function correct(int $fulfillmentId, SubmitFulfillment $submit): void
    {
        $fulfillment = Fulfillment::query()->with('occurrence')->findOrFail($fulfillmentId);
        Gate::forUser($this->user())->authorize('correct', $fulfillment);

        $quantity = $this->correctionQuantities[$fulfillmentId] ?? $fulfillment->quantity;
        $notes = $this->correctionNotes[$fulfillmentId] ?? '';

        $submit->execute(
            $this->commitment,
            $this->user(),
            $quantity,
            occurrence: $fulfillment->occurrence,
            notes: $notes !== '' ? $notes : null,
            corrects: $fulfillment,
        );

        unset($this->correctionQuantities[$fulfillmentId], $this->correctionNotes[$fulfillmentId]);
        $this->refreshCommitment();
        session()->flash('status', __('commitments.messages.corrected'));
    }

    public function dispute(int $fulfillmentId, OpenFulfillmentDispute $open): void
    {
        $fulfillment = Fulfillment::query()->findOrFail($fulfillmentId);
        Gate::forUser($this->user())->authorize('openDispute', $fulfillment);

        $reason = trim($this->disputeReasons[$fulfillmentId] ?? '');
        abort_if($reason === '', 422, 'Dispute reason is required.');

        $open->execute($fulfillment, $this->user(), $reason);

        unset($this->disputeReasons[$fulfillmentId]);
        $this->refreshCommitment();
        session()->flash('status', __('commitments.messages.disputed'));
    }

    public function resolveDispute(
        int $disputeId,
        string $resolution,
        ResolveFulfillmentDispute $resolve,
    ): void {
        $dispute = FulfillmentDispute::query()->findOrFail($disputeId);
        Gate::forUser($this->user())->authorize('resolve', $dispute);

        $resolve->execute(
            $dispute,
            $this->user(),
            FulfillmentStatus::from($resolution),
            ($this->resolutionNotes[$disputeId] ?? '') !== ''
                ? $this->resolutionNotes[$disputeId]
                : null,
        );

        unset($this->resolutionNotes[$disputeId]);
        $this->refreshCommitment();
        session()->flash('status', __('commitments.messages.resolved'));
    }

    public function applyTemplate(CreateCommitmentPlan $create): void
    {
        Gate::forUser($this->user())->authorize('manage', $this->commitment);
        $this->validate([
            'templateUuid' => ['required', 'string'],
            'planStartsOn' => ['required', 'date_format:Y-m-d'],
        ], [], ['templateUuid' => __('work.templates.choose')]);
        $template = PlanTemplate::query()->ownedByAny($this->templateParties())->where('uuid', $this->templateUuid)->firstOrFail();

        $create->execute(
            $this->commitment,
            $this->user(),
            $template->frequency,
            $this->planStartsOn,
            $template->start_time,
            $template->duration_minutes,
            $template->interval,
            $template->weekdays ?? [],
            [],
            null,
            $template->occurrence_limit ?? ($template->frequency === PlanScheduleFrequency::Once ? 1 : null),
            $template->window_before_minutes,
            $template->window_after_minutes,
            $template->reminder_offsets ?? [],
            $template->timing_mode,
            $template->month_day,
        );

        $this->refreshCommitment();
        session()->flash('status', __('commitments.messages.plan_created'));
    }

    public function bindExisting(BindExistingPlan $bind): void
    {
        $plan = $bind->candidates($this->user(), $this->commitment)->firstWhere('uuid', $this->existingPlanUuid);
        if ($plan === null) {
            $this->addError('existingPlanUuid', __('work.plans.choose_existing'));

            return;
        }
        $bind->execute($this->user(), $this->commitment, $plan);
        $this->refreshCommitment();
        session()->flash('status', __('work.plans.connected'));
    }

    public function saveTemplate(SavePlanTemplate $save): void
    {
        $plan = $this->commitment->planBinding?->plan;
        abort_unless($plan !== null, 422);
        $owner = $this->templateParties()[0];
        foreach ($this->templateParties() as $party) {
            if ($party->key() === $this->templateOwner) {
                $owner = $party;
            }
        }
        $save->fromPlan($this->user(), $plan, $this->templateName !== '' ? $this->templateName : $plan->title, $owner);
        $this->reset('templateName');
        session()->flash('status', __('work.templates.saved'));
    }

    public function proposeEntry(int $fulfillmentId, ProposeWorkBookkeepingEntry $propose): void
    {
        $fulfillment = $this->commitment->fulfillments()->whereKey($fulfillmentId)->firstOrFail();
        $ledger = $propose->booksFor($this->user(), $this->commitment)->firstWhere('uuid', $this->bookChoice[$fulfillmentId] ?? '');
        if ($ledger === null) {
            $this->addError('bookChoice.'.$fulfillmentId, __('work.books.choose'));

            return;
        }
        $propose->execute($this->user(), $fulfillment, $ledger);
        $this->refreshCommitment();
        session()->flash('status', __('work.books.proposed'));
    }

    public function createBook(ProposeWorkBookkeepingEntry $propose): void
    {
        $ledger = $propose->createSharedBook($this->user(), $this->commitment);
        foreach ($this->commitment->fulfillments as $fulfillment) {
            $this->bookChoice[$fulfillment->id] = $ledger->uuid;
        }
        session()->flash('status', __('work.books.created'));
    }

    /** @return list<Party> */
    private function templateParties(): array
    {
        return app(PartyAuthority::class)->partiesFor($this->user())->pluck('party')->all();
    }

    public function render(): View
    {
        $this->refreshCommitment();

        $user = $this->user();
        $progress = app(CommitmentProgress::class);
        $binding = $this->commitment->planBinding;
        $plan = $binding?->plan;

        $completedOccurrences = $plan?->occurrences
            ->where('status', PlanOccurrenceStatus::Completed)
            ->values() ?? collect();

        return view('livewire.commitments.show', [
            'acceptedQuantity' => $progress->acceptedQuantity($this->commitment),
            'remainingQuantity' => $progress->remainingQuantity($this->commitment),
            'isSatisfied' => $progress->isSatisfied($this->commitment),
            'canManage' => Gate::forUser($user)->allows('manage', $this->commitment),
            'canSubmit' => Gate::forUser($user)->allows('submit', $this->commitment),
            'plan' => $plan,
            'completedOccurrences' => $completedOccurrences,
            'timezone' => TemporalPreferences::timezoneFor($user),
            'templates' => ! $plan && Gate::forUser($user)->allows('manage', $this->commitment)
                ? PlanTemplate::query()->ownedByAny($this->templateParties())->orderBy('name')->get()
                : collect(),
            'existingPlans' => ! $plan ? app(BindExistingPlan::class)->candidates($user, $this->commitment) : collect(),
            'templateOwners' => $plan ? app(PartyAuthority::class)->partiesFor($user) : collect(),
            'work' => $this->workBookkeeping($user),
        ]);
    }

    private function refreshCommitment(): void
    {
        $commitment = Commitment::query()
            ->with([
                'contractVersion.contract.contextBinding.context',
                'contractVersion.termsRevision',
                'serviceTerm.employer.user',
                'serviceTerm.worker.user',
                'serviceTerm.monetaryUnit',
                'creator.user',
                'obligor.user',
                'beneficiary.user',
                'planBinding.plan.occurrences.assets',
                'planBinding.plan.occurrences.evidenceReferences.revision',
                'fulfillments.submitter.user',
                'fulfillments.occurrence',
                'fulfillments.assets',
                'fulfillments.evidenceReferences.revision',
                'fulfillments.review.reviewer.user',
                'fulfillments.correction',
                'fulfillments.dispute.openedBy.user',
                'fulfillments.dispute.resolvedBy.user',
                'events.actor.user',
            ])
            ->findOrFail($this->commitment->id);

        Gate::forUser($this->user())->authorize('view', $commitment);
        $this->commitment = $commitment;
    }

    /**
     * For each accepted fulfillment: the live book entry (if any) and whether one can be proposed.
     *
     * @return array{isParty: bool, books: Collection<int, Ledger>, entries: array<int, Transaction|null>, amounts: array<int, int|null>}
     */
    private function workBookkeeping(User $user): array
    {
        $propose = app(ProposeWorkBookkeepingEntry::class);
        $actorId = (int) $user->actor?->id;
        $isParty = in_array($actorId, [(int) $this->commitment->obligor_actor_id, (int) $this->commitment->beneficiary_actor_id], true);
        $accepted = $this->commitment->fulfillments->where('status', FulfillmentStatus::Accepted);
        $entries = $accepted->isEmpty() ? collect() : Transaction::query()
            ->where('source_type', ProposeWorkBookkeepingEntry::SOURCE_TYPE)
            ->whereIn('source_uuid', $accepted->pluck('uuid'))
            ->whereIn('status', ['pending', 'confirmed', 'disputed'])
            ->with('ledger')
            ->get()
            ->keyBy('source_uuid');

        return [
            'isParty' => $isParty,
            'books' => $isParty && $accepted->isNotEmpty() ? $propose->booksFor($user, $this->commitment) : collect(),
            'entries' => $accepted->mapWithKeys(fn (Fulfillment $f): array => [$f->id => $entries->get($f->uuid)])->all(),
            'amounts' => $accepted->mapWithKeys(fn (Fulfillment $f): array => [$f->id => $propose->amountFor($f)])->all(),
        ];
    }

    /** @return list<string> */
    private function dates(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', trim($value)) ?: [])
            ->map(fn (string $date): string => trim($date))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<int> */
    private function integers(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', trim($value)) ?: [])
            ->filter(fn (string $item): bool => $item !== '')
            ->map(fn (string $item): int => (int) $item)
            ->unique()
            ->values()
            ->all();
    }

    private function suggestedFulfillmentQuantity(): string
    {
        $remaining = app(CommitmentProgress::class)->remainingQuantity($this->commitment);
        $perOccurrence = $this->commitment->serviceTerm?->quantity_per_occurrence;

        if ($perOccurrence === null) {
            return $remaining;
        }

        return QuantityAmount::compare($remaining, $perOccurrence) <= 0
            ? $remaining
            : (string) $perOccurrence;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
