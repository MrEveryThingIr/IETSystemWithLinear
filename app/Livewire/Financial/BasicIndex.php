<?php

namespace App\Livewire\Financial;

use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Obligations')]
class BasicIndex extends Component
{
    public function render(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);
        $actor = $user->actor;

        $obligations = FinancialObligation::query()
            ->with(['debtor.user', 'creditor.user', 'monetaryUnit', 'settlements'])
            ->where(function ($query) use ($actor): void {
                $query->where('debtor_actor_id', $actor->id)
                    ->orWhere('creditor_actor_id', $actor->id);
            })
            ->latest('recognized_at')
            ->limit(100)
            ->get();

        return view('livewire.financial.basic-index', compact('obligations', 'actor'));
    }
}
