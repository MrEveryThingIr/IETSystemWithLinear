<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\CreateContractFromProposal;
use App\Actions\Contracts\CreateDirectContract;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\Proposal;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\ProposalStatus;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use App\Support\MonetaryUnitCatalog;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('New contract')]
class Create extends Component
{
    #[Url(as: 'proposal')]
    public string $proposalUuid = '';

    #[Url(as: 'relationship')]
    public string $relationshipUuid = '';

    public string $title = '';

    public string $creatorRole = 'party';

    public string $partyLines = '';

    public string $summary = '';

    public string $terms = '';

    public string $notes = '';

    public string $effectiveAt = '';

    public string $timezone = 'UTC';

    public bool $serviceWorkflow = false;

    public string $serviceEmployerUsername = '';

    public string $serviceWorkerUsername = '';

    public string $serviceTitle = '';

    public string $serviceKind = 'service';

    public string $serviceTotalQuantity = '1';

    public string $serviceQuantityPerOccurrence = '1';

    public string $serviceUnit = 'day';

    public string $serviceUnitRate = '';

    public string $serviceMonetaryUnit = 'IRR';

    public string $serviceSettlementCycle = 'weekly';

    public int $servicePaymentDueDays = 0;

    public string $serviceFrequency = 'daily';

    public string $serviceStartsOn = '';

    public string $serviceStartTime = '08:00';

    public int $serviceDurationMinutes = 540;

    public int $serviceInterval = 1;

    /** @var list<int> */
    public array $serviceWeekdays = [];

    public string $serviceSelectedDates = '';

    public string $serviceEndsOn = '';

    public string $serviceOccurrenceLimit = '';

    public int $serviceWindowBeforeMinutes = 0;

    public int $serviceWindowAfterMinutes = 0;

    public string $serviceReminderOffsets = '60,15';

    public bool $serviceAutoCreatePlan = true;

    public bool $serviceAutoRecognizeObligation = true;

    public function mount(): void
    {
        Gate::forUser($this->user())->authorize('create', Contract::class);

        $user = $this->user();
        $this->timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($this->timezone);

        $this->effectiveAt = $now->format('Y-m-d\TH:i');
        $this->serviceStartsOn = $now->format('Y-m-d');
        $this->serviceWeekdays = [$now->isoWeekday()];
        $this->serviceEmployerUsername = (string) $user->username;

        $defaultUnit = array_key_first(MonetaryUnitCatalog::all());
        if (is_string($defaultUnit)) {
            $this->serviceMonetaryUnit = $defaultUnit;
        }

        if ($this->proposalUuid !== '') {
            $proposal = $this->proposal();
            $proposal->loadMissing('parties.actor.user');

            $this->title = $proposal->title;
            $this->serviceTitle = $proposal->title;
            $this->serviceWorkerUsername = (string) $proposal->parties
                ->first(fn ($party): bool => (int) $party->actor_id !== (int) $user->actor?->id)
                ?->actor
                ?->user
                ?->username;

            return;
        }

        if ($this->relationshipUuid !== '') {
            $relationship = $this->relationship();
            $this->title = $relationship->title ?? '';
            $this->serviceTitle = $relationship->title ?? '';

            $currentParticipant = $relationship->participants()
                ->where('actor_id', $user->actor?->id)
                ->first();

            if ($currentParticipant instanceof RelationshipParticipant) {
                $this->creatorRole = $currentParticipant->role;
            }

            $participants = $relationship->participants()
                ->where('status', RelationshipParticipantStatus::Active->value)
                ->where('actor_id', '!=', $user->actor?->id)
                ->with('actor.user')
                ->get();

            $this->partyLines = $participants
                ->map(function (RelationshipParticipant $participant): ?string {
                    $username = $participant->actor->user?->username;

                    return $username !== null
                        ? $username.' | '.$participant->role
                        : null;
                })
                ->filter()
                ->implode("\n");

            $this->serviceWorkerUsername = (string) $participants->first()?->actor?->user?->username;
        }
    }

    public function save(
        CreateDirectContract $createDirect,
        CreateContractFromProposal $createFromProposal,
    ): mixed {
        $this->validate([
            'effectiveAt' => ['required', 'string', 'max:40'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        $effective = $this->effectiveInstant();
        $serviceTerms = $this->serviceTermsInput();

        if ($this->proposalUuid !== '') {
            $contract = $createFromProposal->execute(
                $this->proposal(),
                $this->user(),
                $effective,
                $this->timezone,
                $serviceTerms,
            );

            return $this->redirectRoute('contracts.show', $contract);
        }

        $data = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'creatorRole' => ['required', 'string', 'max:80'],
            'partyLines' => ['required', 'string', 'max:4000'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'terms' => ['required', 'string', 'max:50000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $partySpecs = $this->partySpecs($data['partyLines']);

        $contract = $createDirect->execute(
            $this->user(),
            $data['title'],
            $partySpecs,
            $data['terms'],
            $effective,
            $this->timezone,
            $data['summary'] !== '' ? $data['summary'] : null,
            $data['notes'] !== '' ? $data['notes'] : null,
            $data['creatorRole'],
            $this->relationshipUuid !== '' ? $this->relationship() : null,
            $serviceTerms,
        );

        return $this->redirectRoute('contracts.show', $contract);
    }

    public function render(): View
    {
        return view('livewire.contracts.create', [
            'proposal' => $this->proposalUuid !== '' ? $this->proposal() : null,
            'relationship' => $this->relationshipUuid !== '' ? $this->relationship() : null,
            'monetaryUnits' => MonetaryUnitCatalog::all(),
            'weekdayOrder' => TemporalPreferences::weekdayOrder($this->user()->locale),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function serviceTermsInput(): ?array
    {
        if (! $this->serviceWorkflow) {
            return null;
        }

        $data = $this->validate([
            'serviceEmployerUsername' => ['required', 'string', 'max:255'],
            'serviceWorkerUsername' => ['required', 'string', 'max:255', 'different:serviceEmployerUsername'],
            'serviceTitle' => ['required', 'string', 'max:180'],
            'serviceKind' => ['required', Rule::in(['work', 'service', 'attendance', 'deliverable'])],
            'serviceTotalQuantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'serviceQuantityPerOccurrence' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'serviceUnit' => ['required', 'string', 'max:40'],
            'serviceUnitRate' => ['required', 'string', 'max:40'],
            'serviceMonetaryUnit' => ['required', Rule::in(array_keys(MonetaryUnitCatalog::all()))],
            'serviceSettlementCycle' => ['required', Rule::in(['per_fulfillment', 'weekly', 'monthly', 'contract_end'])],
            'servicePaymentDueDays' => ['required', 'integer', 'min:0', 'max:3650'],
            'serviceFrequency' => ['required', Rule::in(['once', 'daily', 'weekly', 'selected_dates'])],
            'serviceStartsOn' => ['required', 'date_format:Y-m-d'],
            'serviceStartTime' => ['required', 'date_format:H:i'],
            'serviceDurationMinutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'serviceInterval' => ['required', 'integer', 'min:1', 'max:365'],
            'serviceWeekdays' => ['array'],
            'serviceWeekdays.*' => ['integer', 'between:1,7'],
            'serviceSelectedDates' => ['nullable', 'string', 'max:5000'],
            'serviceEndsOn' => ['nullable', 'date_format:Y-m-d'],
            'serviceOccurrenceLimit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'serviceWindowBeforeMinutes' => ['integer', 'min:0', 'max:10080'],
            'serviceWindowAfterMinutes' => ['integer', 'min:0', 'max:10080'],
            'serviceReminderOffsets' => ['nullable', 'string', 'max:500'],
            'serviceAutoCreatePlan' => ['boolean'],
            'serviceAutoRecognizeObligation' => ['boolean'],
        ]);

        $selectedDates = $this->dates($data['serviceSelectedDates']);
        $reminders = $this->integers($data['serviceReminderOffsets']);

        abort_if(
            $data['serviceFrequency'] === 'weekly' && $data['serviceWeekdays'] === [],
            422,
            __('contracts.service.validation.weekday_required'),
        );
        abort_if(
            $data['serviceFrequency'] === 'selected_dates' && $selectedDates === [],
            422,
            __('contracts.service.validation.selected_dates_required'),
        );
        abort_if(
            $data['serviceSettlementCycle'] === 'contract_end' && $data['serviceEndsOn'] === '',
            422,
            __('contracts.service.validation.contract_end_required'),
        );

        return [
            'employer_username' => $data['serviceEmployerUsername'],
            'worker_username' => $data['serviceWorkerUsername'],
            'service_title' => $data['serviceTitle'],
            'service_kind' => $data['serviceKind'],
            'total_quantity' => $data['serviceTotalQuantity'],
            'quantity_per_occurrence' => $data['serviceQuantityPerOccurrence'],
            'unit' => $data['serviceUnit'],
            'unit_rate' => $data['serviceUnitRate'],
            'monetary_unit_code' => $data['serviceMonetaryUnit'],
            'settlement_cycle' => $data['serviceSettlementCycle'],
            'payment_due_days' => $data['servicePaymentDueDays'],
            'auto_create_plan' => $data['serviceAutoCreatePlan'],
            'auto_recognize_obligation' => $data['serviceAutoRecognizeObligation'],
            'plan_frequency' => $data['serviceFrequency'],
            'plan_starts_on' => $data['serviceStartsOn'],
            'plan_start_time' => $data['serviceStartTime'],
            'plan_duration_minutes' => $data['serviceDurationMinutes'],
            'plan_interval' => $data['serviceInterval'],
            'plan_weekdays' => $data['serviceWeekdays'],
            'plan_selected_dates' => $selectedDates,
            'plan_ends_on' => $data['serviceEndsOn'] !== '' ? $data['serviceEndsOn'] : null,
            'plan_occurrence_limit' => $data['serviceOccurrenceLimit'] !== ''
                ? (int) $data['serviceOccurrenceLimit']
                : null,
            'window_before_minutes' => $data['serviceWindowBeforeMinutes'],
            'window_after_minutes' => $data['serviceWindowAfterMinutes'],
            'reminder_offsets' => $reminders,
            'timezone' => $this->timezone,
        ];
    }

    /**
     * @return list<array{actor: Actor, role: string, required: bool}>
     */
    private function partySpecs(string $raw): array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', trim($raw)) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values();

        abort_if($lines->isEmpty() || $lines->count() > 19, 422, 'Provide between one and nineteen other Contract parties.');

        $parsed = $lines->map(function (string $line): array {
            [$username, $role] = array_pad(array_map('trim', explode('|', $line, 2)), 2, 'party');

            return [
                'username' => $username,
                'role' => $role !== '' ? $role : 'party',
            ];
        });

        $usernames = $parsed->pluck('username')->filter()->unique()->values();
        abort_unless($usernames->count() === $parsed->count(), 422, 'Contract party usernames must be unique.');

        $actors = Actor::query()
            ->where('status', 'active')
            ->whereHas('user', fn (Builder $query) => $query
                ->where('status', 'active')
                ->whereNotNull('email_verified_at')
                ->whereIn('username', $usernames->all()))
            ->with('user')
            ->get()
            ->keyBy(fn (Actor $actor): string => (string) $actor->user?->username);

        if ($actors->count() !== $usernames->count()) {
            $this->addError('partyLines', __('contracts.create.parties_not_found'));
            abort(422, __('contracts.create.parties_not_found'));
        }

        return $parsed
            ->map(function (array $spec) use ($actors): array {
                $actor = $actors->get($spec['username']);
                abort_unless($actor instanceof Actor, 422);

                return [
                    'actor' => $actor,
                    'role' => $spec['role'],
                    'required' => true,
                ];
            })
            ->all();
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

    private function proposal(): Proposal
    {
        $proposal = Proposal::query()
            ->with('parties.actor.user')
            ->where('uuid', $this->proposalUuid)
            ->firstOrFail();

        Gate::forUser($this->user())->authorize('view', $proposal);
        abort_unless($proposal->status === ProposalStatus::Accepted, 422);

        return $proposal;
    }

    private function relationship(): Relationship
    {
        $relationship = Relationship::query()
            ->where('uuid', $this->relationshipUuid)
            ->firstOrFail();

        Gate::forUser($this->user())->authorize('participate', $relationship);
        abort_unless($relationship->status === RelationshipStatus::Active, 422);

        return $relationship;
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
            ->filter(fn (int $item): bool => $item >= 0)
            ->unique()
            ->values()
            ->all();
    }

    private function user(): User
    {
        $user = request()->user();

        abort_unless(
            $user instanceof User
            && $user->actor instanceof Actor
            && $user->status === 'active'
            && $user->email_verified_at !== null
            && $user->actor->status === 'active',
            403,
        );

        return $user;
    }
}
