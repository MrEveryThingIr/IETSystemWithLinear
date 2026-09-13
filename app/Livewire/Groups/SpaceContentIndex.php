<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\CreateSpaceContent;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use App\Support\SpaceContentFieldRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Space Content')]
class SpaceContentIndex extends Component
{
    public Group $group;

    public GroupSpace $space;

    public string $definitionId = '';

    public string $title = '';

    /** @var array<string, mixed> */
    public array $payload = [];

    public function mount(Group $group, GroupSpace $space): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        Gate::authorize('view', $space);

        $this->group = $group;
        $this->space = $space;
    }

    public function updatedDefinitionId(): void
    {
        $this->payload = [];

        $definition = $this->activeDefinitions()->firstWhere('id', (int) $this->definitionId);
        if (! $definition instanceof SpaceContentDefinition) {
            return;
        }

        $version = $definition->activeVersionRecord();
        if (! $version instanceof SpaceContentDefinitionVersion) {
            return;
        }

        foreach ($version->schema['fields'] ?? [] as $field) {
            if (is_array($field) && ($field['type'] ?? null) === 'boolean' && is_string($field['key'] ?? null)) {
                $this->payload[$field['key']] = false;
            }
        }
    }

    public function create(CreateSpaceContent $createContent): mixed
    {
        $this->validate([
            'definitionId' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'payload' => ['array'],
        ]);

        $definition = $this->space->contentDefinitions()
            ->where('status', '!=', 'archived')
            ->whereKey((int) $this->definitionId)
            ->first();
        abort_unless($definition instanceof SpaceContentDefinition, 404);

        $content = $createContent->execute(
            $this->space,
            $definition,
            $this->user(),
            $this->title,
            $this->payload,
        );

        return $this->redirectRoute('groups.spaces.contents.show', [$this->group, $this->space, $content]);
    }

    public function render(): View
    {
        $registry = app(SpaceContentFieldRegistry::class);
        Gate::forUser($this->user())->authorize('view', $this->space);

        $actor = $this->actor();
        $definitions = $this->activeDefinitions();
        $selectedDefinition = $definitions->firstWhere('id', (int) $this->definitionId);
        $selectedVersion = $selectedDefinition instanceof SpaceContentDefinition
            ? $selectedDefinition->activeVersionRecord()
            : null;

        $publishedContents = $this->space->contents()
            ->where('status', 'published')
            ->whereNotNull('active_revision_id')
            ->with(['activeRevision', 'author.user', 'definition'])
            ->latest('published_at')
            ->latest('id')
            ->get();

        $draftContents = $this->space->contents()
            ->where('author_actor_id', $actor->id)
            ->whereNotNull('draft_revision_id')
            ->with(['draftRevision', 'definition'])
            ->latest('id')
            ->get();

        $fieldComponents = [];
        if ($selectedVersion instanceof SpaceContentDefinitionVersion) {
            foreach ($selectedVersion->schema['fields'] ?? [] as $field) {
                if (is_array($field) && is_string($field['type'] ?? null)) {
                    $fieldComponents[$field['type']] = $registry->componentFor($field['type']);
                }
            }
        }

        return view('livewire.groups.space-content-index', compact(
            'definitions',
            'selectedDefinition',
            'selectedVersion',
            'publishedContents',
            'draftContents',
            'fieldComponents',
        ));
    }

    /** @return Collection<int, SpaceContentDefinition> */
    private function activeDefinitions(): Collection
    {
        return $this->space->contentDefinitions()
            ->where('status', '!=', 'archived')
            ->whereNotNull('active_version_id')
            ->orderBy('name')
            ->get()
            ->filter(fn (SpaceContentDefinition $definition): bool => $definition->activeVersionRecord()?->published_at !== null)
            ->values();
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function actor(): Actor
    {
        $user = User::query()->with('actor')->find($this->user()->id);
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user->actor;
    }
}
