<?php

namespace App\Livewire\Actors;

use App\Models\Actor;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create Actor')]
class Create extends Component
{
    public string $userId = '';

    public function save(): void
    {
        $data = $this->validate([
            'userId' => ['nullable', 'integer', 'exists:users,id', Rule::unique('actors', 'user_id')],
        ], ['userId.unique' => 'This user already has an Actor.']);

        $actor = new Actor;
        if ($data['userId'] === '' || $data['userId'] === null) {
            $actor->user()->dissociate();
        } else {
            $actor->user()->associate(User::findOrFail($data['userId']));
        }

        try {
            $actor->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['userId' => __('ui.messages.user_has_actor')]);
        }

        session()->flash('status', __('ui.messages.actor_created'));
        $this->redirectRoute('actors.show', ['actor' => $actor->id]);
    }

    public function render(): View
    {
        return view('livewire.actors.create', [
            'users' => User::query()->whereDoesntHave('actor')->orderBy('username')->get(['id', 'username', 'email']),
        ]);
    }
}
