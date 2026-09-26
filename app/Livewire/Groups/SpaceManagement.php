<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\ArchiveGroupSpace;
use App\Actions\Groups\ArchiveSpaceContentDefinition;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\RemoveGroupSpaceParticipant;
use App\Actions\Groups\ReviseSpaceContentDefinition;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Actions\Groups\UpdateGroupSpace;
use App\Actions\Groups\UpdateSpaceContentDefinition;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContentDefinition;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Manage Spaces')]
class SpaceManagement extends Component
{
    public Group $group;

    public string $newSpaceName = '';

    public string $newSpaceAccessMode = 'restricted';

    public ?int $selectedSpaceId = null;

    public ?int $editingSpaceId = null;

    public string $editingSpaceName = '';

    public string $editingSpaceAccessMode = 'group';

    public string $actorSearch = '';

    public string $participantActorId = '';

    public string $participantAccess = 'allow';

    public string $participantRole = 'participant';

    public ?int $editingDefinitionId = null;

    public string $definitionName = '';

    public string $definitionDescription = '';

    /** @var array<int, array<string, mixed>> */
    public array $definitionFields = [];

    public function mount(Group $group): void
    {
        $this->group = $group;
        $this->authorizeScreen();
        $this->resetDefinitionBuilder();
    }

    public function createSpace(CreateGroupSpace $createSpace): void
    {
        $data = $this->validate([
            'newSpaceName' => ['required', 'string', 'max:120'],
            'newSpaceAccessMode' => ['required', 'string', 'in:group,restricted'],
        ]);

        $space = $createSpace->execute(
            $this->group,
            $this->user(),
            $data['newSpaceName'],
            $data['newSpaceAccessMode'],
        );

        $this->selectedSpaceId = $space->id;
        $this->reset('newSpaceName');
        $this->newSpaceAccessMode = 'restricted';
        session()->flash('status', __('ui.spaces.created'));
    }

    public function selectSpace(int $spaceId): void
    {
        $space = $this->groupSpace($spaceId);
        Gate::forUser($this->user())->authorize('manage', $space);
        $this->selectedSpaceId = $space->id;
        $this->resetParticipantForm();
        $this->resetDefinitionBuilder();
    }

    public function beginEdit(int $spaceId): void
    {
        $space = $this->groupSpace($spaceId);
        Gate::forUser($this->user())->authorize('manage', $space);

        $this->editingSpaceId = $space->id;
        $this->editingSpaceName = $space->name;
        $this->editingSpaceAccessMode = $space->access_mode;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingSpaceId', 'editingSpaceName');
        $this->editingSpaceAccessMode = 'group';
    }

    public function updateSpace(UpdateGroupSpace $updateSpace): void
    {
        $data = $this->validate([
            'editingSpaceId' => ['required', 'integer'],
            'editingSpaceName' => ['required', 'string', 'max:120'],
            'editingSpaceAccessMode' => ['required', 'string', 'in:group,restricted'],
        ]);

        $space = $this->groupSpace((int) $data['editingSpaceId']);
        $updateSpace->execute(
            $space,
            $this->user(),
            $data['editingSpaceName'],
            $data['editingSpaceAccessMode'],
        );

        $this->cancelEdit();
        session()->flash('status', __('ui.spaces.updated'));
    }

    public function archiveSpace(int $spaceId, ArchiveGroupSpace $archiveSpace): void
    {
        $space = $this->groupSpace($spaceId);
        $archiveSpace->execute($space, $this->user());

        if ($this->selectedSpaceId === $space->id) {
            $this->selectedSpaceId = null;
        }

        if ($this->editingSpaceId === $space->id) {
            $this->cancelEdit();
        }

        session()->flash('status', __('ui.spaces.archived'));
    }

    public function chooseActor(int $actorId): void
    {
        Actor::query()->findOrFail($actorId);
        $this->participantActorId = (string) $actorId;
    }

    public function setParticipant(SetGroupSpaceParticipant $setParticipant): void
    {
        $data = $this->validate([
            'selectedSpaceId' => ['required', 'integer'],
            'participantActorId' => ['required', 'integer'],
            'participantAccess' => ['required', 'string', 'in:allow,deny'],
            'participantRole' => ['required', 'string', 'in:participant,manager'],
        ]);

        if ($data['participantAccess'] === 'deny' && $data['participantRole'] === 'manager') {
            $this->addError('participantRole', __('ui.spaces.denied_manager_invalid'));

            return;
        }

        $space = $this->groupSpace((int) $data['selectedSpaceId']);
        $actor = Actor::query()->findOrFail((int) $data['participantActorId']);

        $setParticipant->execute(
            $space,
            $actor,
            $this->user(),
            $data['participantAccess'],
            $data['participantRole'],
        );

        $this->resetParticipantForm();
        session()->flash('status', __('ui.spaces.participant_set'));
    }

    public function promoteParticipant(int $actorId, SetGroupSpaceParticipant $setParticipant): void
    {
        $space = $this->selectedSpace();
        $actor = Actor::query()->findOrFail($actorId);

        $setParticipant->execute($space, $actor, $this->user(), 'allow', 'manager');

        session()->flash('status', __('ui.spaces.participant_promoted'));
    }

    public function denyParticipant(int $actorId, SetGroupSpaceParticipant $setParticipant): void
    {
        $space = $this->selectedSpace();
        $actor = Actor::query()->findOrFail($actorId);

        $setParticipant->execute($space, $actor, $this->user(), 'deny', 'participant');

        session()->flash('status', __('ui.spaces.participant_denied'));
    }

    public function removeParticipant(int $actorId, RemoveGroupSpaceParticipant $removeParticipant): void
    {
        $space = $this->selectedSpace();
        $actor = Actor::query()->findOrFail($actorId);

        $removeParticipant->execute($space, $actor, $this->user());

        session()->flash('status', __('ui.spaces.participant_removed'));
    }

    public function newDefinition(): void
    {
        $this->selectedSpace();
        $this->resetDefinitionBuilder();
    }

    public function editDefinition(int $definitionId, ReviseSpaceContentDefinition $reviseDefinition): void
    {
        $definition = $this->selectedSpaceDefinition($definitionId);
        $definition = $reviseDefinition->execute($definition, $this->user());

        $version = $definition->currentVersionRecord();
        abort_unless($version->published_at === null, 422, 'Published Content Definition versions are immutable.');

        $this->editingDefinitionId = $definition->id;
        $this->definitionName = $definition->name;
        $this->definitionDescription = $definition->description ?? '';
        $this->definitionFields = collect($version->schema['fields'] ?? [])
            ->map(fn (array $field): array => [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => (bool) $field['required'],
                'help' => $field['help'] ?? '',
                'options_text' => collect($field['options'] ?? [])
                    ->map(fn (array $option): string => $option['value'].'|'.$option['label'])
                    ->implode("\n"),
            ])
            ->all();
    }

    public function addDefinitionField(): void
    {
        $this->definitionFields[] = $this->blankDefinitionField();
    }

    public function removeDefinitionField(int $index): void
    {
        if (! array_key_exists($index, $this->definitionFields)) {
            return;
        }

        unset($this->definitionFields[$index]);
        $this->definitionFields = array_values($this->definitionFields);

        if ($this->definitionFields === []) {
            $this->definitionFields[] = $this->blankDefinitionField();
        }
    }

    public function moveDefinitionField(int $index, int $direction): void
    {
        abort_unless(in_array($direction, [-1, 1], true), 422);
        $target = $index + $direction;

        if (! isset($this->definitionFields[$index], $this->definitionFields[$target])) {
            return;
        }

        [$this->definitionFields[$index], $this->definitionFields[$target]] = [
            $this->definitionFields[$target],
            $this->definitionFields[$index],
        ];
    }

    public function saveDefinition(
        CreateSpaceContentDefinition $createDefinition,
        UpdateSpaceContentDefinition $updateDefinition,
    ): void {
        $data = $this->validate([
            'definitionName' => ['required', 'string', 'max:120'],
            'definitionDescription' => ['nullable', 'string', 'max:2000'],
            'definitionFields' => ['required', 'array', 'min:1', 'max:50'],
            'definitionFields.*.key' => ['required', 'string', 'max:64'],
            'definitionFields.*.label' => ['required', 'string', 'max:120'],
            'definitionFields.*.type' => ['required', 'string'],
            'definitionFields.*.required' => ['required', 'boolean'],
            'definitionFields.*.help' => ['nullable', 'string', 'max:500'],
            'definitionFields.*.options_text' => ['nullable', 'string', 'max:10000'],
        ]);

        $fields = $this->definitionPayload($data['definitionFields']);
        $user = $this->user();

        if ($this->editingDefinitionId === null) {
            $definition = $createDefinition->execute(
                $this->selectedSpace(),
                $user,
                $data['definitionName'],
                $data['definitionDescription'] ?: null,
                $fields,
            );
            $this->editingDefinitionId = $definition->id;
            session()->flash('status', __('ui.content.definition_created'));
        } else {
            $definition = $this->selectedSpaceDefinition($this->editingDefinitionId);
            $updateDefinition->execute(
                $definition,
                $user,
                $data['definitionName'],
                $data['definitionDescription'] ?: null,
                $fields,
            );
            session()->flash('status', __('ui.content.definition_updated'));
        }

        $this->resetDefinitionBuilder();
    }

    public function activateDefinition(int $definitionId, ActivateSpaceContentDefinition $activateDefinition): void
    {
        $definition = $this->selectedSpaceDefinition($definitionId);
        $activateDefinition->execute($definition, $this->user());
        $this->resetDefinitionBuilder();
        session()->flash('status', __('ui.content.definition_activated'));
    }

    public function archiveDefinition(int $definitionId, ArchiveSpaceContentDefinition $archiveDefinition): void
    {
        $definition = $this->selectedSpaceDefinition($definitionId);
        $archiveDefinition->execute($definition, $this->user());
        $this->resetDefinitionBuilder();
        session()->flash('status', __('ui.content.definition_archived'));
    }

    public function render(): View
    {
        $user = $this->user();
        $manageableSpaces = $this->manageableSpaces($user);
        abort_if($manageableSpaces->isEmpty() && ! Gate::forUser($user)->allows('manageSpaces', $this->group), 403);

        $selectedSpace = $this->resolveSelectedSpace($manageableSpaces);
        $participants = $selectedSpace
            ? $selectedSpace->participants()
                ->with(['actor.user', 'grantedBy.user'])
                ->orderBy('id')
                ->get()
            : collect();
        $actorResults = $this->actorResults();
        $canCreateSpaces = Gate::forUser($user)->allows('manageSpaces', $this->group);
        $contentDefinitions = $selectedSpace
            ? $selectedSpace->contentDefinitions()->with('versions')->orderBy('name')->get()
            : collect();

        return view('livewire.groups.space-management', compact(
            'manageableSpaces',
            'selectedSpace',
            'participants',
            'actorResults',
            'canCreateSpaces',
            'contentDefinitions',
        ));
    }

    private function authorizeScreen(): void
    {
        $user = $this->user();

        if (Gate::forUser($user)->allows('manageSpaces', $this->group)) {
            return;
        }

        abort_unless($this->manageableSpaces($user)->isNotEmpty(), 403);
    }

    /** @return Collection<int, GroupSpace> */
    private function manageableSpaces(User $user): Collection
    {
        return $this->group->spaces()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->filter(fn (GroupSpace $space): bool => Gate::forUser($user)->allows('manage', $space))
            ->values();
    }

    /** @param Collection<int, GroupSpace> $manageableSpaces */
    private function resolveSelectedSpace(Collection $manageableSpaces): ?GroupSpace
    {
        $selected = $this->selectedSpaceId === null
            ? null
            : $manageableSpaces->firstWhere('id', $this->selectedSpaceId);

        if (! $selected instanceof GroupSpace) {
            $selected = $manageableSpaces->first();
            $this->selectedSpaceId = $selected?->id;
        }

        return $selected;
    }

    private function selectedSpace(): GroupSpace
    {
        abort_unless($this->selectedSpaceId !== null, 422, 'Choose a Space first.');

        $space = $this->groupSpace($this->selectedSpaceId);
        Gate::forUser($this->user())->authorize('manageParticipants', $space);

        return $space;
    }

    private function groupSpace(int $spaceId): GroupSpace
    {
        $space = $this->group->spaces()
            ->where('status', 'active')
            ->whereKey($spaceId)
            ->first();

        abort_unless($space instanceof GroupSpace, 404);

        return $space;
    }

    /** @return Collection<int, Actor> */
    private function actorResults(): Collection
    {
        $search = trim($this->actorSearch);

        if ($search === '') {
            return collect();
        }

        return Actor::query()
            ->with('user')
            ->where(function ($query) use ($search): void {
                if (ctype_digit($search)) {
                    $query->whereKey((int) $search);
                }

                $query->orWhereHas('user', fn ($userQuery) => $userQuery
                    ->where('username', 'like', '%'.$search.'%'));
            })
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    private function resetParticipantForm(): void
    {
        $this->reset('actorSearch', 'participantActorId');
        $this->participantAccess = 'allow';
        $this->participantRole = 'participant';
    }

    private function selectedSpaceDefinition(int $definitionId): SpaceContentDefinition
    {
        $space = $this->selectedSpace();
        $definition = $space->contentDefinitions()->whereKey($definitionId)->first();
        abort_unless($definition instanceof SpaceContentDefinition, 404);
        Gate::forUser($this->user())->authorize('manage', $definition);

        return $definition;
    }

    /**
     * @param  array<int, array<string, mixed>>  $builderFields
     * @return array<int, array<string, mixed>>
     */
    private function definitionPayload(array $builderFields): array
    {
        return collect(array_values($builderFields))->map(function (array $field): array {
            $type = (string) ($field['type'] ?? 'short_text');
            $options = [];

            if ($type === 'select') {
                $lines = preg_split('/\R/', (string) ($field['options_text'] ?? '')) ?: [];
                $options = collect($lines)
                    ->map(fn (string $line): string => trim($line))
                    ->filter()
                    ->map(function (string $line): array {
                        [$value, $label] = array_pad(explode('|', $line, 2), 2, null);
                        $value = trim($value);
                        $label = trim($label ?? $value);

                        return ['value' => $value, 'label' => $label];
                    })
                    ->values()
                    ->all();
            }

            return [
                'key' => (string) ($field['key'] ?? ''),
                'label' => (string) ($field['label'] ?? ''),
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
                'help' => ($field['help'] ?? '') !== '' ? (string) $field['help'] : null,
                'options' => $options,
            ];
        })->all();
    }

    private function resetDefinitionBuilder(): void
    {
        $this->editingDefinitionId = null;
        $this->definitionName = '';
        $this->definitionDescription = '';
        $this->definitionFields = [$this->blankDefinitionField()];
    }

    /** @return array{key: string, label: string, type: string, required: bool, help: string, options_text: string} */
    private function blankDefinitionField(): array
    {
        return [
            'key' => '',
            'label' => '',
            'type' => 'short_text',
            'required' => false,
            'help' => '',
            'options_text' => '',
        ];
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
