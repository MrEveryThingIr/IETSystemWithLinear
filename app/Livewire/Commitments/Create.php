<?php

namespace App\Livewire\Commitments;

use App\Actions\Commitments\CreateCommitment;
use App\CommitmentKind;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('New commitment')]
class Create extends Component
{
    public Contract $contract;

    public string $title = '';

    public string $description = '';

    public string $kind = 'work';

    public string $obligorUsername = '';

    public string $beneficiaryUsername = '';

    public string $quantity = '1';

    public string $unit = 'day';

    public string $dueStart = '';

    public string $dueEnd = '';

    public function mount(Contract $contract): void
    {
        $user = $this->user();
        Gate::forUser($user)->authorize('create', [Commitment::class, $contract]);
        $this->contract = $contract;

        $version = $contract->activeVersionRecord();
        abort_unless($version instanceof ContractVersion, 422, 'Active ContractVersion is missing.');
        $version->loadMissing('parties.actor.user');

        $actorId = $user->actor?->id;
        $other = $version->parties
            ->first(fn (ContractVersionParty $party): bool => (int) $party->actor_id !== (int) $actorId);

        if ($other instanceof ContractVersionParty) {
            $this->obligorUsername = (string) $other->actor->user?->username;
            $this->beneficiaryUsername = (string) $user->username;
        }
    }

    public function save(CreateCommitment $create): mixed
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'kind' => ['required', 'in:work,deliverable,attendance,service,payment,resource,other'],
            'obligorUsername' => ['required', 'string', 'max:255'],
            'beneficiaryUsername' => ['required', 'string', 'max:255', 'different:obligorUsername'],
            'quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'unit' => ['required', 'string', 'max:40'],
            'dueStart' => ['nullable', 'date'],
            'dueEnd' => ['nullable', 'date'],
        ]);

        $version = $this->contract->activeVersionRecord();
        abort_unless($version instanceof ContractVersion, 422, 'Active ContractVersion is missing.');

        $parties = ContractVersionParty::query()
            ->where('contract_version_id', $version->id)
            ->with('actor.user')
            ->get()
            ->keyBy(fn (ContractVersionParty $party): string => (string) $party->actor->user?->username);

        $obligorParty = $parties->get($data['obligorUsername']);
        $beneficiaryParty = $parties->get($data['beneficiaryUsername']);

        if (! $obligorParty instanceof ContractVersionParty
            || ! $beneficiaryParty instanceof ContractVersionParty) {
            $this->addError('obligorUsername', __('commitments.create.party_error'));

            return null;
        }

        $commitment = $create->execute(
            $this->contract,
            $this->user(),
            $obligorParty->actor,
            $beneficiaryParty->actor,
            CommitmentKind::from($data['kind']),
            $data['title'],
            $data['quantity'],
            $data['unit'],
            $data['description'] !== '' ? $data['description'] : null,
            $this->instant($data['dueStart']),
            $this->instant($data['dueEnd']),
        );

        return $this->redirectRoute('commitments.show', $commitment);
    }

    public function render(): View
    {
        $version = $this->contract->activeVersionRecord();
        abort_unless($version instanceof ContractVersion, 422);

        $version->loadMissing('parties.actor.user');

        return view('livewire.commitments.create', [
            'version' => $version,
            'parties' => $version->parties,
            'kinds' => CommitmentKind::cases(),
        ]);
    }

    private function instant(string $value): ?CarbonImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $this->user()->timezone ?: 'UTC')->utc();
        } catch (Throwable) {
            abort(422, 'Invalid Commitment due time.');
        }
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
