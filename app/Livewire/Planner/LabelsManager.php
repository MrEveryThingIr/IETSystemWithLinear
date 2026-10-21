<?php

namespace App\Livewire\Planner;

use App\Actions\Planner\ManagePlannerLabels;
use App\Models\Planner\PlannerLabel;
use App\Models\User;
use App\Support\Planner\PlanKinds;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Rename, recolour, add and remove one's own plan labels. */
class LabelsManager extends Component
{
    /** @var array<string, array{name: string, color: string}> */
    public array $edits = [];

    public string $newName = '';

    public string $newColor = '#3b82f6';

    public function mount(): void
    {
        $this->load();
    }

    public function add(ManagePlannerLabels $labels): void
    {
        $this->validate(['newName' => ['required', 'string', 'max:60']]);
        $labels->create($this->user(), $this->newName, $this->newColor);
        $this->reset('newName');
        $this->load();
    }

    public function saveLabel(string $uuid, ManagePlannerLabels $labels): void
    {
        $label = $this->label($uuid);
        $labels->update($label, $this->user(), (string) ($this->edits[$uuid]['name'] ?? $label->name), (string) ($this->edits[$uuid]['color'] ?? $label->color));
        $this->load();
    }

    public function remove(string $uuid, ManagePlannerLabels $labels): void
    {
        $labels->delete($this->label($uuid), $this->user());
        $this->load();
    }

    public function render(): View
    {
        return view('livewire.planner.labels-manager', ['palette' => PlanKinds::PALETTE]);
    }

    private function load(): void
    {
        $this->edits = PlannerLabel::query()->where('actor_id', $this->user()->actor?->id)->orderBy('name')->get()
            ->mapWithKeys(fn (PlannerLabel $label): array => [$label->uuid => ['name' => $label->name, 'color' => $label->color]])
            ->all();
    }

    private function label(string $uuid): PlannerLabel
    {
        return PlannerLabel::query()->where('uuid', $uuid)->where('actor_id', $this->user()->actor?->id)->firstOrFail();
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
