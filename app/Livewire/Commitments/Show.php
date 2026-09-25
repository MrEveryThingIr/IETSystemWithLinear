<?php

namespace App\Livewire\Commitments;

use App\Actions\Commitments\CreateCommitmentPlan;
use App\Actions\Financial\RecognizeFulfillmentFinancialObligation;
use App\Actions\Fulfillments\OpenFulfillmentDispute;
use App\Actions\Fulfillments\ResolveFulfillmentDispute;
use App\Actions\Fulfillments\ReviewFulfillment;
use App\Actions\Fulfillments\SubmitFulfillment;
use App\FulfillmentReviewDecision;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Fulfillment;
use App\Models\FulfillmentDispute;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\Support\CommitmentProgress;
use App\Support\MonetaryUnitCatalog;
use App\Support\MoneyAmount;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
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

    /** @var array<int, string> */
    public array $financialAmounts = [];

    /** @var array<int, string> */
    public array $financialUnits = [];

    public function mount(Commitment $commitment): void
    {
        Gate::forUser($this->user())->authorize('view', $commitment);
        $this->commitment = $commitment;

        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->planStartsOn = CarbonImmutable::now($timezone)->format('Y-m-d');
        $this->fulfillmentQuantity = $commitment->quantity;
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
        $this->fulfillmentQuantity = app(CommitmentProgress::class)->remainingQuantity($this->commitment);
        $this->refreshCommitment();
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

    public function recognizeFinancialObligation(
        int $fulfillmentId,
        RecognizeFulfillmentFinancialObligation $recognize,
    ): void {
        $fulfillment = Fulfillment::query()
            ->with(['commitment', 'financialObligation'])
            ->findOrFail($fulfillmentId);

        abort_unless((int) $fulfillment->commitment_id === (int) $this->commitment->id, 404);
        abort_if($fulfillment->financialObligation !== null, 422, 'This Fulfillment already has a Financial Obligation.');

        $unitCode = $this->financialUnits[$fulfillmentId] ?? 'IRR';
        $meta = MonetaryUnitCatalog::get($unitCode);
        $amountText = trim($this->financialAmounts[$fulfillmentId] ?? '');

        try {
            $amountMinor = MoneyAmount::parse($amountText, $meta['exponent']);
        } catch (InvalidArgumentException) {
            $this->addError('financialAmounts.'.$fulfillmentId, __('financial.validation.amount'));

            return;
        }

        abort_if($amountMinor <= 0, 422, 'Financial Obligation amount must be positive.');

        $recognize->execute(
            $fulfillment,
            $this->user(),
            $unitCode,
            $amountMinor,
            description: 'Accepted Fulfillment for '.$this->commitment->title,
        );

        unset(
            $this->financialAmounts[$fulfillmentId],
            $this->financialUnits[$fulfillmentId],
        );

        $this->refreshCommitment();
        session()->flash('status', __('financial.messages.recognized'));
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
            'canRecognizeFinancial' => (int) $user->actor?->id === (int) $this->commitment->beneficiary_actor_id,
            'monetaryUnits' => MonetaryUnitCatalog::all(),
            'plan' => $plan,
            'completedOccurrences' => $completedOccurrences,
            'timezone' => TemporalPreferences::timezoneFor($user),
        ]);
    }

    private function refreshCommitment(): void
    {
        $commitment = Commitment::query()
            ->with([
                'contractVersion.contract.contextBinding.context',
                'contractVersion.termsRevision',
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
                'fulfillments.financialObligation.monetaryUnit',
                'fulfillments.financialObligation.settlements',
                'events.actor.user',
            ])
            ->findOrFail($this->commitment->id);

        Gate::forUser($this->user())->authorize('view', $commitment);
        $this->commitment = $commitment;

        foreach ($commitment->fulfillments as $fulfillment) {
            if ($fulfillment->status === FulfillmentStatus::Accepted
                && $fulfillment->financialObligation === null) {
                $this->financialUnits[$fulfillment->id] ??= 'IRR';
            }
        }
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

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
