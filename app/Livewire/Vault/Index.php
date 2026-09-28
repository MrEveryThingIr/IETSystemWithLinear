<?php

namespace App\Livewire\Vault;

use App\Models\PersonalSecret;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Vault')]
class Index extends Component
{
    public string $kind = 'login';

    public string $title = '';

    public string $identifier = '';

    public string $secret = '';

    public string $url = '';

    public string $notes = '';

    /** @var list<int> */
    public array $revealed = [];

    public function save(): void
    {
        $data = $this->validate([
            'kind' => ['required', Rule::in(['login', 'email', 'phone', 'account', 'note'])],
            'title' => ['required', 'string', 'max:180'],
            'identifier' => ['nullable', 'string', 'max:1000'],
            'secret' => ['nullable', 'string', 'max:10000'],
            'url' => ['nullable', 'url', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        PersonalSecret::query()->create([
            'user_id' => $this->user()->id,
            'kind' => $data['kind'],
            'title' => trim($data['title']),
            'identifier' => trim($data['identifier']) !== '' ? trim($data['identifier']) : null,
            'secret' => $data['secret'] !== '' ? $data['secret'] : null,
            'url' => trim($data['url']) !== '' ? trim($data['url']) : null,
            'notes' => trim($data['notes']) !== '' ? trim($data['notes']) : null,
        ]);

        $this->reset(['title', 'identifier', 'secret', 'url', 'notes']);
        $this->kind = 'login';

        session()->flash('status', __('vault.saved'));
    }

    public function toggleReveal(int $id): void
    {
        $this->ownedSecret($id);

        if (in_array($id, $this->revealed, true)) {
            $this->revealed = array_values(array_filter(
                $this->revealed,
                fn (int $candidate): bool => $candidate !== $id,
            ));

            return;
        }

        $this->revealed[] = $id;
        $this->revealed = array_values(array_unique($this->revealed));
    }

    public function delete(int $id): void
    {
        $secret = $this->ownedSecret($id);
        $secret->delete();

        $this->revealed = array_values(array_filter(
            $this->revealed,
            fn (int $candidate): bool => $candidate !== $id,
        ));

        session()->flash('status', __('vault.deleted'));
    }

    public function render(): View
    {
        $items = PersonalSecret::query()
            ->where('user_id', $this->user()->id)
            ->latest('id')
            ->get();

        return view('livewire.vault.index', [
            'items' => $items,
        ]);
    }

    private function ownedSecret(int $id): PersonalSecret
    {
        return PersonalSecret::query()
            ->where('user_id', $this->user()->id)
            ->findOrFail($id);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
