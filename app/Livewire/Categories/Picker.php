<?php

namespace App\Livewire\Categories;

use App\Actions\Categories\CreatePrivateCategory;
use App\Actions\Categories\ReviewCategorySuggestions;
use App\Models\Concept;
use App\Models\User;
use App\Support\Categories\CategoryCatalog;
use App\Support\Categories\CategoryKind;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Category picker used inside forms: <livewire:categories.picker wire:model="conceptId" owner="actor:12" />
 *
 * Search first; otherwise the 16 shared areas, then one level at a time.
 * People can add their own category under any level and suggest it for
 * everyone. Only the owner's own private categories are ever shown.
 */
class Picker extends Component
{
    #[Modelable]
    public ?int $value = null;

    /** "actor:12", "business:3" or "group:4" — whose private categories to show and create. */
    #[Locked]
    public string $owner = '';

    /** @var list<string> */
    #[Locked]
    public array $kinds = [];

    #[Locked]
    public bool $allowCreate = true;

    public bool $open = false;

    public string $search = '';

    public ?int $browsing = null;

    public string $newCategory = '';

    public bool $suggest = false;

    public string $suggestNote = '';

    public ?string $status = null;

    /** @param list<string> $kinds */
    public function mount(?int $value = null, ?string $owner = null, array $kinds = [], bool $allowCreate = true): void
    {
        $this->value = $value;
        $this->owner = $owner ?? '';
        $this->kinds = array_values(array_intersect($kinds, CategoryKind::values()));
        $this->allowCreate = $allowCreate;
        $this->open = $value === null;
    }

    public function choose(int $conceptId, CategoryCatalog $catalog): void
    {
        abort_unless($catalog->isUsableBy($conceptId, $this->ownerParty()), 404);
        $this->value = $conceptId;
        $this->open = false;
        $this->search = '';
        $this->status = null;
    }

    public function browse(?int $conceptId, CategoryCatalog $catalog): void
    {
        if ($conceptId !== null) {
            abort_unless($catalog->isUsableBy($conceptId, $this->ownerParty()), 404);
        }
        $this->browsing = $conceptId;
        $this->search = '';
        $this->resetErrorBag();
    }

    public function change(): void
    {
        $this->open = true;
    }

    public function clear(): void
    {
        $this->value = null;
        $this->open = true;
    }

    public function addCategory(CreatePrivateCategory $create, ReviewCategorySuggestions $suggestions): void
    {
        abort_unless($this->allowCreate && $this->browsing !== null, 422);
        $user = $this->user();
        $owner = $this->ownerParty();
        abort_unless($owner instanceof Party, 422);

        $concept = $create->execute($user, $owner, $this->browsing, $this->newCategory, $this->kinds);
        if ($this->suggest && ! app(CategoryCatalog::class)->isShared((int) $concept->id)) {
            $suggestions->suggest($user, $concept, $this->suggestNote);
            $this->status = (string) __('categories.picker.suggested');
        } else {
            $this->status = (string) __('categories.picker.added');
        }

        $this->value = (int) $concept->id;
        $this->reset('newCategory', 'suggest', 'suggestNote');
        $this->open = false;
    }

    public function render(CategoryCatalog $catalog): View
    {
        // Cleared from outside (the form was reset): show the choices again.
        if ($this->value === null) {
            $this->open = true;
        }
        $owner = $this->ownerParty();
        $selectedPath = $this->value !== null ? $catalog->path($this->value, $owner) : [];
        $browsingPath = $this->browsing !== null ? $catalog->path($this->browsing, $owner) : [];
        $searching = mb_strlen(trim($this->search)) >= 2;

        return view('livewire.categories.picker', [
            'selectedPath' => $selectedPath,
            'selectedPrivate' => $this->value !== null && ! $catalog->isShared($this->value),
            'browsingPath' => $browsingPath,
            'results' => ! $this->open ? [] : ($searching
                ? $catalog->search($this->search, $owner, $this->kinds)
                : ($this->browsing === null ? $catalog->areas() : $catalog->children($this->browsing, $owner, $this->kinds))),
            'searching' => $searching,
            'canCreate' => $this->allowCreate && $owner instanceof Party && $this->browsing !== null && ! $searching,
        ]);
    }

    private function ownerParty(): ?Party
    {
        if ($this->owner === '' || ! str_contains($this->owner, ':')) {
            return null;
        }
        [$kind, $id] = explode(':', $this->owner, 2);
        $party = Party::make($kind, (int) $id);
        // The owner is fixed by the page; it must still be one the viewer may act for.
        abort_unless(app(PartyAuthority::class)->canActFor($this->user(), $party), 403);

        return $party;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public static function conceptLabel(?int $conceptId): string
    {
        return $conceptId === null ? '' : (string) Concept::query()->find($conceptId)?->displayLabel();
    }
}
