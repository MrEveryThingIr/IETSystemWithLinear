<?php

namespace App\Livewire\Market;

use App\Models\IntentFieldProposal;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** The person's own suggestions and where each one stands. Only their own are ever listed. */
#[Layout('layouts.app')]
class FieldSuggestions extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->actor !== null, 403);

        return view('livewire.market.field-suggestions', [
            'suggestions' => IntentFieldProposal::query()->where('proposed_by_actor_id', $user->actor->id)->latest('id')->limit(100)->get(),
        ])->title(__('composer.suggest.my_title'));
    }
}
