<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\ActivateDueContractVersions;
use App\Actions\Contracts\ProposeContractAmendment;
use App\Actions\Financial\ProposeContractSettlementBatch;
use App\Actions\Financial\ProposeReferencedContractSettlementBatch;
use App\Actions\Financial\RespondToContractSettlementBatch;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\ContractSettlementBatch;
use App\Models\ContractVersion;
use App\Models\FinancialObligation;
use App\Models\MonetaryUnit;
use App\Models\User;
use App\Support\ContractFinancialSummary;
use App\Support\LocalizedNumber;
use App\Support\MonetaryUnitCatalog;
use App\Support\MoneyAmount;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Contract')]
class Show extends Component
{
    public Contract $contract;

    public string $amendmentTitle = '';

    public string $amendmentSummary = '';

    public string $amendmentTerms = '';

    public string $amendmentNotes = '';

    public string $versionNote = '';

    public string $effectiveAt = '';

    public string $timezone = 'UTC';

    public string $settlementAmount = '';

    public bool $settlementUseReferenceCash = false;

    public string $settlementReferenceCashAmount = '';

    public string $settlementReferenceCashUnit = 'IRT';

    public string $settlementPerspective = 'paid';

    public string $settlementUnitCode = '';

    public string $settlementPaidAt = '';

    public string $settlementReference = '';

    public string $settlementNote = '';

    /** @var array<int, string> */
    public array $batchRejectionNotes = [];

    public function mount(Contract $contract): void
    {
        Gate::forUser($this->user())->authorize('view', $contract);
        $this->contract = $contract;
        $this->reconcileDueActivation();

        $user = $this->user();
        $this->timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($this->timezone);

        $this->effectiveAt = $now->addDay()->format('Y-m-d\TH:i');
        $this->settlementPaidAt = $now->format('Y-m-d\TH:i');
    }

    public function hydrate(): void
    {
        $this->reconcileDueActivation();
    }

    public function accept(AcceptContractVersion $accept): void
    {
        $this->refreshContract();
        $version = $this->contract->pendingVersionRecord();
        abort_unless($version instanceof ContractVersion, 422);

        $accept->execute($version, $this->user());
        $this->refreshContract();

        session()->flash('status', __('contracts.messages.accepted'));
    }

    public function proposeAmendment(ProposeContractAmendment $amend): void
    {
        Gate::forUser($this->user())->authorize('amend', $this->contract);

        $data = $this->validate([
            'amendmentTitle' => ['required', 'string', 'max:255'],
            'amendmentSummary' => ['nullable', 'string', 'max:10000'],
            'amendmentTerms' => ['required', 'string', 'max:50000'],
            'amendmentNotes' => ['nullable', 'string', 'max:10000'],
            'versionNote' => ['nullable', 'string', 'max:1000'],
            'effectiveAt' => ['required', 'string', 'max:40'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        $amend->execute(
            $this->contract,
            $this->user(),
            $data['amendmentTitle'],
            $data['amendmentTerms'],
            $this->effectiveInstant(),
            $data['timezone'],
            $data['amendmentSummary'] !== '' ? $data['amendmentSummary'] : null,
            $data['amendmentNotes'] !== '' ? $data['amendmentNotes'] : null,
            $data['versionNote'] !== '' ? $data['versionNote'] : null,
        );

        $this->reset('amendmentTitle', 'amendmentSummary', 'amendmentTerms', 'amendmentNotes', 'versionNote');
        $this->refreshContract();
        session()->flash('status', __('contracts.messages.amendment_proposed'));
    }

    public function proposeCashSettlement(
        ProposeContractSettlementBatch $propose,
        ProposeReferencedContractSettlementBatch $proposeReferenced,
    ): void {
        $this->refreshContract();

        $this->settlementAmount = LocalizedNumber::decimal(str_replace(',', '', $this->settlementAmount));
        $this->settlementReferenceCashAmount = LocalizedNumber::decimal(
            str_replace(',', '', $this->settlementReferenceCashAmount),
        );

        $data = $this->validate([
            'settlementUseReferenceCash' => ['boolean'],
            'settlementAmount' => [
                Rule::requiredIf(! $this->settlementUseReferenceCash),
                'nullable',
                'string',
                'max:40',
            ],
            'settlementReferenceCashAmount' => [
                Rule::requiredIf($this->settlementUseReferenceCash),
                'nullable',
                'string',
                'max:40',
            ],
            'settlementReferenceCashUnit' => [
                Rule::requiredIf($this->settlementUseReferenceCash),
                'nullable',
                Rule::in(array_values(array_filter(
                    array_keys(MonetaryUnitCatalog::all()),
                    fn (string $code): bool => $code !== 'IET',
                ))),
            ],
            'settlementPerspective' => ['required', 'in:paid,received'],
            'settlementUnitCode' => ['required', 'string', 'max:16'],
            'settlementPaidAt' => ['required', 'string', 'max:40'],
            'settlementReference' => ['nullable', 'string', 'max:255'],
            'settlementNote' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($this->settlementUseReferenceCash) {
            abort_unless(
                strtoupper($data['settlementUnitCode']) === 'IET',
                422,
                __('financial.settlement_batch.reference_requires_iet'),
            );

            $referenceMeta = MonetaryUnitCatalog::get($data['settlementReferenceCashUnit']);

            try {
                $referenceAmountMinor = MoneyAmount::parse(
                    $data['settlementReferenceCashAmount'],
                    $referenceMeta['exponent'],
                );
            } catch (InvalidArgumentException) {
                $this->addError('settlementReferenceCashAmount', __('financial.validation.amount'));

                return;
            }

            $proposeReferenced->execute(
                $this->contract,
                $this->user(),
                $data['settlementReferenceCashUnit'],
                $referenceAmountMinor,
                $this->settlementInstant(),
                $data['settlementReference'] !== '' ? $data['settlementReference'] : null,
                $data['settlementNote'] !== '' ? $data['settlementNote'] : null,
                $data['settlementPerspective'],
            );
        } else {
            $unit = MonetaryUnit::query()
                ->where('code', strtoupper($data['settlementUnitCode']))
                ->firstOrFail();

            try {
                $amountMinor = MoneyAmount::parse($data['settlementAmount'], $unit->exponent);
            } catch (InvalidArgumentException) {
                $this->addError('settlementAmount', __('financial.validation.amount'));

                return;
            }

            $propose->execute(
                $this->contract,
                $this->user(),
                $unit,
                $amountMinor,
                $this->settlementInstant(),
                $unit->code === 'IET' ? 'IET' : 'cash',
                $data['settlementReference'] !== '' ? $data['settlementReference'] : null,
                $data['settlementNote'] !== '' ? $data['settlementNote'] : null,
                perspective: $data['settlementPerspective'],
            );
        }

        $this->reset(
            'settlementAmount',
            'settlementReferenceCashAmount',
            'settlementReference',
            'settlementNote',
        );
        $this->refreshContract();
        session()->flash('status', __('financial.messages.batch_proposed'));
    }

    public function confirmSettlementBatch(
        int $batchId,
        RespondToContractSettlementBatch $respond,
    ): void {
        $batch = $this->settlementBatch($batchId);
        $respond->confirm($batch, $this->user());

        $this->refreshContract();
        session()->flash('status', __('financial.messages.batch_confirmed'));
    }

    public function rejectSettlementBatch(
        int $batchId,
        RespondToContractSettlementBatch $respond,
    ): void {
        $batch = $this->settlementBatch($batchId);
        $note = trim($this->batchRejectionNotes[$batchId] ?? '');
        abort_if($note === '', 422, __('financial.settlement_batch.rejection_required'));

        $respond->reject($batch, $this->user(), $note);
        unset($this->batchRejectionNotes[$batchId]);

        $this->refreshContract();
        session()->flash('status', __('financial.messages.batch_rejected'));
    }

    public function render(): View
    {
        $this->refreshContract();

        $user = $this->user();
        $pendingVersion = $this->contract->pendingVersionRecord();
        $activeVersion = $this->contract->activeVersionRecord();

        $pendingVersion?->loadMissing([
            'serviceTerm.employer.user',
            'serviceTerm.worker.user',
            'serviceTerm.monetaryUnit',
            'serviceTerm.referenceMonetaryUnit',
            'serviceTerm.referenceMarketQuote',
            'serviceTerm.ietValuationQuote',
        ]);
        $activeVersion?->loadMissing([
            'serviceTerm.employer.user',
            'serviceTerm.worker.user',
            'serviceTerm.monetaryUnit',
            'serviceTerm.referenceMonetaryUnit',
            'serviceTerm.referenceMarketQuote',
            'serviceTerm.ietValuationQuote',
            'serviceTerm.commitment.planBinding.plan',
        ]);

        $canAccept = $pendingVersion instanceof ContractVersion
            && Gate::forUser($user)->allows('accept', [$this->contract, $pendingVersion]);

        $serviceAmendmentLocked = $activeVersion?->serviceTerm !== null;
        $canAmend = ! $serviceAmendmentLocked
            && Gate::forUser($user)->allows('amend', $this->contract);
        $canCreateCommitment = Gate::forUser($user)->allows('create', [Commitment::class, $this->contract]);

        $commitments = Commitment::query()
            ->whereHas('contractVersion', fn ($query) => $query->where('contract_id', $this->contract->id))
            ->with([
                'contractVersion.serviceTerm.monetaryUnit',
                'serviceTerm.monetaryUnit',
                'serviceTerm.referenceMonetaryUnit',
                'serviceTerm.referenceMarketQuote',
                'serviceTerm.ietValuationQuote',
                'obligor.user',
                'beneficiary.user',
                'planBinding.plan',
                'fulfillments',
            ])
            ->orderBy('id')
            ->get();

        $context = $this->contract->contextBinding?->context;
        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $financialObligations = FinancialObligation::query()
            ->whereHas(
                'contractVersion',
                fn ($query) => $query->where('contract_id', $this->contract->id),
            )
            ->where(function ($query) use ($actor): void {
                $query->where('debtor_actor_id', $actor->id)
                    ->orWhere('creditor_actor_id', $actor->id);
            })
            ->with([
                'fulfillment.commitment.serviceTerm',
                'debtor.user',
                'creditor.user',
                'monetaryUnit',
                'settlements.batch',
            ])
            ->orderBy('recognized_at')
            ->orderBy('id')
            ->get();

        $summaryService = app(ContractFinancialSummary::class);
        $financialSummaries = $financialObligations
            ->pluck('monetaryUnit')
            ->unique('id')
            ->values()
            ->map(fn ($unit): array => [
                'unit' => $unit,
                'summary' => $summaryService->forContractUnit($this->contract, $unit, $actor),
                'daily' => $summaryService->dailyForContractUnit(
                    $this->contract,
                    $unit,
                    TemporalPreferences::timezoneFor($user),
                    $actor,
                ),
            ]);

        $settlementBatches = ContractSettlementBatch::query()
            ->where('contract_id', $this->contract->id)
            ->where(function ($query) use ($actor): void {
                $query->where('debtor_actor_id', $actor->id)
                    ->orWhere('creditor_actor_id', $actor->id);
            })
            ->with([
                'debtor.user',
                'creditor.user',
                'monetaryUnit',
                'referenceMonetaryUnit',
                'referenceMarketQuote',
                'ietValuationQuote',
                'proposedBy.user',
                'settlements.obligation.fulfillment.commitment',
                'settlements.proposedBy.user',
                'settlements.confirmedBy.user',
                'settlements.rejectedBy.user',
            ])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $payableUnits = $financialObligations
            ->filter(fn (FinancialObligation $obligation): bool => (int) $obligation->debtor_actor_id === (int) $actor->id
                && $obligation->availableToSettleMinor() > 0)
            ->pluck('monetaryUnit')
            ->unique('id')
            ->values();

        $receivableUnits = $financialObligations
            ->filter(fn (FinancialObligation $obligation): bool => (int) $obligation->creditor_actor_id === (int) $actor->id
                && $obligation->availableToSettleMinor() > 0)
            ->pluck('monetaryUnit')
            ->unique('id')
            ->values();

        if ($payableUnits->isEmpty() && $receivableUnits->isNotEmpty()) {
            $this->settlementPerspective = 'received';
        } elseif ($receivableUnits->isEmpty() && $payableUnits->isNotEmpty()) {
            $this->settlementPerspective = 'paid';
        }

        $settlementUnits = $this->settlementPerspective === 'received'
            ? $receivableUnits
            : $payableUnits;

        if (! $settlementUnits->contains(
            fn (MonetaryUnit $unit): bool => $unit->code === $this->settlementUnitCode,
        )) {
            $this->settlementUnitCode = $settlementUnits->isNotEmpty()
                ? (string) $settlementUnits->first()->code
                : '';
        }

        if ($this->settlementUnitCode !== 'IET') {
            $this->settlementUseReferenceCash = false;
        }

        if ($canAmend && $this->amendmentTerms === '' && $activeVersion instanceof ContractVersion) {
            $this->amendmentTitle = $activeVersion->termsRevision->title;
            $this->amendmentSummary = (string) ($activeVersion->termsRevision->payload['summary'] ?? '');
            $this->amendmentTerms = (string) ($activeVersion->termsRevision->payload['terms'] ?? '');
            $this->amendmentNotes = (string) ($activeVersion->termsRevision->payload['notes'] ?? '');
        }

        return view('livewire.contracts.show', [
            'actor' => $actor,
            'pendingVersion' => $pendingVersion,
            'activeVersion' => $activeVersion,
            'pendingServiceTerms' => $pendingVersion?->serviceTerm,
            'activeServiceTerms' => $activeVersion?->serviceTerm,
            'canAccept' => $canAccept,
            'canAmend' => $canAmend,
            'serviceAmendmentLocked' => $serviceAmendmentLocked,
            'canCreateCommitment' => $canCreateCommitment,
            'commitments' => $commitments,
            'context' => $context,
            'financialObligations' => $financialObligations,
            'financialSummaries' => $financialSummaries,
            'settlementBatches' => $settlementBatches,
            'payableUnits' => $payableUnits,
            'receivableUnits' => $receivableUnits,
            'settlementUnits' => $settlementUnits,
            'referenceMonetaryUnits' => collect(MonetaryUnitCatalog::all())
                ->reject(fn (array $meta, string $code): bool => $code === 'IET'),
        ]);
    }

    private function reconcileDueActivation(): void
    {
        Gate::forUser($this->user())->authorize('view', $this->contract);

        app(ActivateDueContractVersions::class)->executeForContract($this->contract);
        $this->refreshContract();
    }

    private function refreshContract(): void
    {
        $contract = Contract::query()
            ->with([
                'relationship',
                'sourceProposalVersion.proposal',
                'sourceProposalVersion.termsRevision',
                'creator.user',
                'contextBinding.context',
                'versions.termsRevision.content',
                'versions.proposedBy.user',
                'versions.parties.actor.user',
                'versions.parties.acceptance.acceptedByUser',
                'versions.serviceTerm.employer.user',
                'versions.serviceTerm.worker.user',
                'versions.serviceTerm.monetaryUnit',
                'events.actor.user',
            ])
            ->findOrFail($this->contract->id);

        Gate::forUser($this->user())->authorize('view', $contract);
        $this->contract = $contract;
    }

    private function settlementBatch(int $id): ContractSettlementBatch
    {
        $batch = ContractSettlementBatch::query()
            ->where('contract_id', $this->contract->id)
            ->findOrFail($id);

        Gate::forUser($this->user())->authorize('view', $this->contract);

        return $batch;
    }

    private function effectiveInstant(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->effectiveAt, $this->timezone)->utc();
        } catch (Throwable) {
            $this->addError('effectiveAt', __('validation.date', ['attribute' => __('contracts.create.effective_at')]));
            abort(422, 'Invalid Contract effective time.');
        }
    }

    private function settlementInstant(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse(
                $this->settlementPaidAt,
                TemporalPreferences::timezoneFor($this->user()),
            )->utc();
        } catch (Throwable) {
            $this->addError('settlementPaidAt', __('validation.date', [
                'attribute' => __('financial.settlement.paid_at'),
            ]));
            abort(422, 'Invalid Settlement paid time.');
        }
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
