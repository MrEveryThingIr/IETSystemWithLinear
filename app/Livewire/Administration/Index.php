<?php

namespace App\Livewire\Administration;

use App\Actions\Administration\GlobalAccess;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Administration')]
class Index extends Component
{
    public string $userId = '';
    public bool $fullAdministration = false;

    /** @var list<string> */
    public array $permissions = [];

    public function updatedUserId(GlobalAccess $access): void
    {
        $this->reset('fullAdministration', 'permissions');
        if ($this->userId !== '') {
            $user = User::findOrFail($this->userId);
            $this->fullAdministration = $access->isFullAdministrator($user);
            $this->permissions = $access->permissionsFor($user);
        }
    }

    public function save(GlobalAccess $access): void
    {
        $data = $this->validate(['userId' => ['required', 'integer', 'exists:users,id']]);
        $access->sync(User::findOrFail($data['userId']), $this->fullAdministration, $this->permissions);
        session()->flash('status', 'Global access updated.');
    }

    public function render(GlobalAccess $access): View
    {
        return view('livewire.administration.index', [
            'users' => User::query()->orderBy('username')->get(['id', 'username', 'email', 'status']),
            'permissionNames' => $access->permissionNames(),
        ]);
    }
}
