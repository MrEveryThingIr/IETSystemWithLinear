<?php

namespace App\Livewire\Administration;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Users')]
class Users extends Component
{
    public function updateStatus(int $userId, string $status): void
    {
        $data = validator(['status' => $status], ['status' => ['required', Rule::in(['active', 'suspended', 'closed'])]])->validate();
        User::findOrFail($userId)->update($data);
        session()->flash('status', 'User status updated.');
    }

    public function render(): View
    {
        return view('livewire.administration.users', [
            'users' => User::query()->with('actor')->orderBy('username')->paginate(20),
        ]);
    }
}
