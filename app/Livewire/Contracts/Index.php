<?php

namespace App\Livewire\Contracts;

use App\Models\Actor;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Contracts')]
class Index extends Component
{
    public function render(): View
    {
        $user = $this->user();

        $contracts = Contract::query()
            ->with([
                'creator.user',
                'relationship',
                'sourceProposalVersion.proposal',
                'versions.parties.actor.user',
            ])
            ->latest('id')
            ->limit(200)
            ->get()
            ->filter(fn (Contract $contract): bool => Gate::forUser($user)->allows('view', $contract))
            ->values();

        return view('livewire.contracts.index', compact('contracts'));
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
