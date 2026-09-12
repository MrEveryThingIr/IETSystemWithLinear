<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\CreateGroup;
use App\Models\Actor;
use App\Models\Group;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create Group')]
class Create extends Component
{
    public string $name = '';

    public string $description = '';

    public string $timezone = 'UTC';

    public function mount(): void
    {
        $this->timezone = request()->user()?->timezone ?: 'UTC';
    }

    public function save(CreateGroup $createGroup): void
    {
        Gate::authorize('create', Group::class);
        $data = $this->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'timezone' => ['required', 'timezone']]);
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);
        $group = $createGroup->execute($user->actor, $data['name'], $data['description'] ?: null, $data['timezone']);
        $this->redirectRoute('groups.show', $group);
    }

    public function render(): View
    {
        Gate::authorize('create', Group::class);

        return view('livewire.groups.create', ['timezones' => timezone_identifiers_list()]);
    }
}
