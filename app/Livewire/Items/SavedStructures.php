<?php

namespace App\Livewire\Items;

use App\Models\IntentDetailTemplate;
use App\Models\User;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * My saved templates (whole compositions) and field groups, with preview, rename and delete.
 * They hold structure only; starting a post from one opens the Composer, which applies it.
 */
class SavedStructures extends Component
{
    public string $renaming = '';

    public string $renameTo = '';

    public function startRename(string $uuid): void
    {
        $this->renaming = $uuid;
        $this->renameTo = $this->owned($uuid)->name;
    }

    public function saveRename(): void
    {
        $this->validate(['renameTo' => ['required', 'string', 'max:120']]);
        $this->owned($this->renaming)->update(['name' => trim($this->renameTo)]);
        $this->cancelRename();
    }

    public function cancelRename(): void
    {
        $this->renaming = $this->renameTo = '';
    }

    public function delete(string $uuid): void
    {
        $template = $this->owned($uuid);
        // Groups nested inside become top-level groups; nothing else is lost with it.
        IntentDetailTemplate::query()->where('parent_id', $template->id)->update(['parent_id' => null]);
        $template->delete();
    }

    public function render(): View
    {
        $all = $this->mine();

        return view('livewire.items.saved-structures', [
            'templates' => $all->where('kind', 'composition')->values(),
            'groups' => $all->where('kind', 'group')->values(),
        ]);
    }

    private function owned(string $uuid): IntentDetailTemplate
    {
        $template = $this->mine()->firstWhere('uuid', $uuid);
        abort_unless($template instanceof IntentDetailTemplate, 403);

        return $template;
    }

    /** @return Collection<int, IntentDetailTemplate> */
    private function mine(): Collection
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $actors = [];
        $businesses = [];
        foreach (app(PartyAuthority::class)->partiesFor($user) as $entry) {
            match ($entry['party']->kind) {
                Party::ACTOR => $actors[] = $entry['party']->id,
                Party::BUSINESS => $businesses[] = $entry['party']->id,
                default => null,
            };
        }

        return IntentDetailTemplate::query()
            ->where(fn ($query) => $query->whereIn('owner_actor_id', $actors)->orWhereIn('owner_business_id', $businesses))
            ->orderBy('name')->get();
    }
}
