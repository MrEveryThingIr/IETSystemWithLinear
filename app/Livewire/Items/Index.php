<?php

namespace App\Livewire\Items;

use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\User;
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
 * listed by status and always open their canonical Composer, so each fact keeps one authority.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    /** "interest" or "ability": open that form on arrival (from the + launcher). */
    #[Url(as: 'add', except: '')]
    public string $add = '';

    /** Which of my offers and needs to list: published (active), draft, paused or closed. */
    #[Url(as: 'show', except: 'active')]
    public string $show = 'active';

    public function mount(): void
    {
        $this->actor();
        if (! in_array($this->add, ['interest', 'ability'], true)) {
            $this->add = '';
        }
        if (! in_array($this->show, ['active', 'draft', 'paused', 'closed'], true)) {
            $this->show = 'active';
        }
    }

    public function render(FeatureSurfaceAccess $access): View
    {
        $user = $this->user();
        $actor = $this->actor();
        $market = $access->allows($user, 'market');
        $counts = $market ? $this->counts($actor) : [];
        $intents = $market ? $this->intents($actor) : collect();
        $openForm = $this->add;
        // The form opens once; reloading or sharing the link later shows the plain page.
        $this->add = '';

        return view('livewire.items.index', [
            'openForm' => $openForm,
            'market' => $market,
            'counts' => $counts,
            'offers' => $intents->where('kind.value', 'offer')->values(),
            'needs' => $intents->where('kind.value', 'need')->values(),
        ])->title(__('items.title'));
    }

    /** @return Collection<int, ActorProfileIntent> */
    private function intents(Actor $actor): Collection
    {
        return ActorProfileIntent::query()
            ->whereHas('profile', fn ($query) => $query->where('actor_id', $actor->id))
            ->where('status', $this->show)
            ->with('concept.labels')
            ->latest('updated_at')
            ->limit(50)
            ->get();
    }

    /** @return array<string, int> */
    private function counts(Actor $actor): array
    {
        $counts = ActorProfileIntent::query()->whereHas('profile', fn ($query) => $query->where('actor_id', $actor->id))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        return array_map(fn ($total): int => (int) $total, array_merge(['active' => 0, 'draft' => 0, 'paused' => 0, 'closed' => 0], $counts));
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
