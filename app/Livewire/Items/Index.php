<?php

namespace App\Livewire\Items;

use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileIntentStatus;
use App\Services\Surfaces\FeatureSurfaceAccess;
use App\Support\RequestMemo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * My items: one home for what I care about, what I can do, and what I offer
 * or need. Interests and abilities are edited here; offers and needs are
 * listed and open their own editor, so each fact keeps one authority.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    /** "interest" or "ability": open that form on arrival (from the + launcher). */
    #[Url(as: 'add', except: '')]
    public string $add = '';

    public function mount(): void
    {
        $this->actor();
        if (! in_array($this->add, ['interest', 'ability'], true)) {
            $this->add = '';
        }
    }

    public function render(FeatureSurfaceAccess $access): View
    {
        $user = $this->user();
        $actor = $this->actor();
        $market = $access->allows($user, 'market');
        $intents = $market ? $this->intents($actor) : collect();
        $openForm = $this->add;
        // The form opens once; reloading or sharing the link later shows the plain page.
        $this->add = '';

        return view('livewire.items.index', [
            'openForm' => $openForm,
            'market' => $market,
            'offers' => $intents->where('kind.value', 'offer')->values(),
            'needs' => $intents->where('kind.value', 'need')->values(),
        ])->title(__('items.title'));
    }

    /** @return Collection<int, ActorProfileIntent> */
    private function intents(Actor $actor): Collection
    {
        return ActorProfileIntent::query()
            ->whereHas('profile', fn ($query) => $query->where('actor_id', $actor->id))
            ->whereIn('status', [ProfileIntentStatus::Active->value, ProfileIntentStatus::Paused->value])
            ->with('concept.labels')
            ->latest('updated_at')
            ->limit(50)
            ->get();
    }

    private function actor(): Actor
    {
        $actor = RequestMemo::user($this->user())?->actor;
        abort_unless($actor instanceof Actor, 403);

        return $actor;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
