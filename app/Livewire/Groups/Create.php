<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\CreateGroup;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create Group')]
class Create extends Component
{
    public string $name = '';
    public string $description = '';
    public function save(CreateGroup $createGroup): void
    {
        $data = $this->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000']]);
        $group = $createGroup->execute(auth()->user()->actor, $data['name'], $data['description'] ?: null);
        $this->redirectRoute('groups.show', $group);
    }
    public function render(): View { return view('livewire.groups.create'); }
}
