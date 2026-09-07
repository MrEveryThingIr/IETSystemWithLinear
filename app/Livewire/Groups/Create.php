<?php

namespace App\Livewire\Groups;

use App\Models\Group;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Create Group')]
class Create extends Component
{
    public string $name = '';

    public string $description = '';

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $group = DB::transaction(function () use ($data): Group {
            $actor = auth()->user()->actor;
            $group = Group::create([
                'name' => $data['name'],
                'description' => $data['description'] ?: null,
                'created_by_actor_id' => $actor->id,
            ]);

            $group->memberships()->create([
                'actor_id' => $actor->id,
                'role' => 'owner',
                'status' => 'active',
            ]);

            return $group;
        });

        session()->flash('status', "{$group->name} created. You are its owner.");
        $this->redirectRoute('groups.index');
    }

    public function render(): View
    {
        return view('livewire.groups.create');
    }
}
