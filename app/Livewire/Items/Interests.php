<?php

namespace App\Livewire\Items;

use App\Actions\Items\SaveInterest;
use App\Models\Actor;
use App\Models\Interest;
use App\Models\User;
use App\Support\Categories\CategoryCatalog;
use App\Support\Parties\Party;
use App\Support\RequestMemo;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Interests: the one editing experience for what a person cares about.
 * Only their holder ever sees this list.
 */
class Interests extends Component
{
    public bool $formOpen = false;

    public ?string $editing = null;

    public ?int $conceptId = null;

    /** @var list<string> */
    public array $purposes = [];

    public string $visibility = Interest::VISIBILITY_PRIVATE;

    public string $note = '';

    public bool $showMore = false;

    public ?string $notice = null;

    public function mount(bool $openForm = false): void
    {
        $this->actor();
        if ($openForm) {
            $this->add();
        }
    }

    public function add(): void
    {
        $this->cancel();
        $this->formOpen = true;
        $this->notice = null;
    }

    public function edit(string $uuid): void
    {
        $interest = $this->find($uuid);
        $this->cancel();
        $this->formOpen = true;
        $this->editing = $interest->uuid;
        $this->conceptId = (int) $interest->concept_id;
        $this->purposes = $interest->purposes;
        $this->visibility = $interest->visibility;
        $this->note = (string) $interest->note;
        $this->showMore = filled($interest->note);
        $this->notice = null;
    }

    public function cancel(): void
    {
        $this->reset('formOpen', 'editing', 'conceptId', 'purposes', 'note', 'showMore');
        $this->visibility = Interest::VISIBILITY_PRIVATE;
        $this->resetErrorBag();
    }

    public function togglePurpose(string $purpose): void
    {
        abort_unless(in_array($purpose, Interest::PURPOSES, true), 422);
        $this->purposes = in_array($purpose, $this->purposes, true)
            ? array_values(array_diff($this->purposes, [$purpose]))
            : Interest::normalizePurposes([...$this->purposes, $purpose]);
        $this->resetErrorBag('purposes');
    }

    /** Choosing a category already listed opens that interest instead of making a second one. */
    public function updatedConceptId(?int $value): void
    {
        if ($value === null || $this->editing !== null) {
            return;
        }
        $existing = Interest::query()->heldBy($this->actor())->where('concept_id', $value)->value('uuid');
        if ($existing !== null) {
            $this->edit((string) $existing);
            $this->notice = __('items.interests.already_have');
        }
    }

    public function save(SaveInterest $save): void
    {
        $input = [
            'concept_id' => $this->conceptId,
            'purposes' => $this->purposes,
            'visibility' => $this->visibility,
            'note' => $this->note,
        ];

        if ($this->editing !== null) {
            $save->update($this->user(), $this->find($this->editing), $input);
        } else {
            $save->create($this->user(), $input);
        }

        $visibility = $this->visibility;
        $this->cancel();
        $this->notice = $visibility === Interest::VISIBILITY_PRIVATE
            ? __('items.interests.saved_private')
            : __('items.interests.saved_profile');
        $this->dispatch('items-changed');
    }

    public function remove(string $uuid, SaveInterest $save): void
    {
        $save->remove($this->user(), $this->find($uuid));
        $this->cancel();
        $this->notice = __('items.interests.removed');
        $this->dispatch('items-changed');
    }

    public function render(CategoryCatalog $catalog): View
    {
        $interests = Interest::query()->heldBy($this->actor())->orderBy('created_at')->orderBy('id')->get();

        return view('livewire.items.interests', [
            'interests' => $interests,
            'labels' => $catalog->labels($interests->pluck('concept_id')->map(fn ($id): int => (int) $id)->all()),
            'ownerKey' => Party::of($this->actor())->key(),
        ]);
    }

    private function find(string $uuid): Interest
    {
        return Interest::query()->heldBy($this->actor())->where('uuid', $uuid)->firstOrFail();
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
