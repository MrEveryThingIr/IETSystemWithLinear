<?php

namespace App\Livewire\Actors;

use App\Models\Actor;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Actor')]
class Edit extends Component
{
    public string $userId = '';

    #[Locked]
    public Actor $actor;

    public function mount(Actor $actor): void
    {
        $this->actor = $actor;
        $this->userId = $actor->user_id === null ? '' : (string) $actor->user_id;
    }

    public function save(): void
    {
        $data = $this->validate([
            'userId' => ['nullable', 'integer', 'exists:users,id', Rule::unique('actors', 'user_id')->ignore($this->actor->id)],
        ], ['userId.unique' => 'This user already has an Actor.']);

        $actor = $this->actor->refresh();
        if ($data['userId'] === '' || $data['userId'] === null) {
            $actor->user()->dissociate();
        } else {
            $actor->user()->associate(User::findOrFail($data['userId']));
        }

        try {
            $actor->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['userId' => 'This user already has an Actor.']);
        }

        session()->flash('status', 'Actor updated.');
        $this->redirectRoute('actors.show', ['actor' => $actor->id]);
    }

    public function render(): View
    {
        return view('livewire.actors.edit', [
            'users' => User::query()->where(fn ($query) => $query->whereDoesntHave('actor')->orWhere('id', $this->actor->user_id))->orderBy('username')->get(['id', 'username', 'email']),
        ]);
    }
}
