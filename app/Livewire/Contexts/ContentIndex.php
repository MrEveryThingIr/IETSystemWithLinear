<?php

namespace App\Livewire\Contexts;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Contexts\CreateContextContent;
use App\Actions\Contexts\CreateContextContentDefinition;
use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use App\Support\ContentBlueprintCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Content')]
class ContentIndex extends Component
{
    public Context $context;

    public bool $creatorOpen = false;

    public string $blueprintSearch = '';

    public string $blueprintVersionId = '';

    public string $title = '';

    /** @var array<string, mixed> */
    public array $payload = [];

    public string $definitionName = '';

    public string $definitionId = '';

    public string $definitionTitle = '';

    /** @var array<string, mixed> */
    public array $definitionPayload = [];

    public function mount(Context $context): void
    {
        Gate::forUser($this->user())->authorize('view', $context);
        $this->context = $context;
    }

    public function openCreator(): void
    {
        Gate::forUser($this->user())->authorize('createContent', $this->context);
        $this->creatorOpen = true;
        $this->resetCreator();
    }

    public function cancelCreator(): void
    {
        $this->creatorOpen = false;
        $this->resetCreator();
    }

    public function selectBlueprint(int $versionId): void
    {
        Gate::forUser($this->user())->authorize('createContent', $this->context);

        $blueprint = $this->blueprints()->first(
            fn (ContentBlueprint $item): bool => (int) $item->active_version_id === $versionId,
        );
        abort_unless($blueprint instanceof ContentBlueprint, 404);

        $this->blueprintVersionId = (string) $versionId;
        $this->title = '';
        $this->payload = [];

        $version = $blueprint->activeVersion;
        if ($version instanceof ContentBlueprintVersion) {
            $this->initializeBooleanFields($version, $this->payload);
        }
    }

    public function backToBlueprints(): void
    {
        $this->reset('blueprintVersionId', 'title', 'payload');
        $this->resetErrorBag();
    }

    public function createFromBlueprint(CreateContentFromBlueprint $create): mixed
    {
        Gate::forUser($this->user())->authorize('createContent', $this->context);

        $this->validate([
            'blueprintVersionId' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'payload' => ['array'],
        ]);

        $blueprint = $this->blueprints()->first(
            fn (ContentBlueprint $item): bool => (int) $item->active_version_id === (int) $this->blueprintVersionId,
        );
        abort_unless($blueprint instanceof ContentBlueprint, 404);

        $version = $blueprint->activeVersion;
        abort_unless($version instanceof ContentBlueprintVersion, 404);

        $content = $create->execute(
            $this->context,
            $version,
            $this->user(),
            $this->title,
            $this->payload,
        );

        $this->creatorOpen = false;
        $this->resetCreator();

        return $this->redirectRoute('contexts.contents.studio', [$this->context, $content]);
    }

    public function createDefinition(
        CreateContextContentDefinition $create,
        ActivateSpaceContentDefinition $activate,
    ): void {
        Gate::forUser($this->user())->authorize('manageDefinitions', $this->context);

        $this->validate([
            'definitionName' => ['required', 'string', 'max:120'],
        ]);

        $definition = $create->execute(
            $this->context,
            $this->user(),
            $this->definitionName,
            null,
            [[
                'key' => 'body',
                'label' => __('ui.context_content.body'),
                'type' => 'long_text',
                'required' => true,
                'help' => null,
                'options' => [],
            ]],
        );

        $definition = $activate->execute($definition, $this->user());
        $this->definitionId = (string) $definition->id;
        $this->definitionTitle = '';
        $this->definitionPayload = [];
        $this->reset('definitionName');
        session()->flash('status', __('ui.context_content.definition_created'));
    }

    public function updatedDefinitionId(): void
    {
        $this->definitionPayload = [];

        $definition = $this->activeDefinitions()->firstWhere('id', (int) $this->definitionId);
        $version = $definition instanceof SpaceContentDefinition
            ? $definition->activeVersionRecord()
            : null;

        if ($version instanceof SpaceContentDefinitionVersion) {
            $this->initializeBooleanFields($version, $this->definitionPayload);
        }
    }

    public function createFromDefinition(CreateContextContent $create): mixed
    {
        Gate::forUser($this->user())->authorize('createContent', $this->context);

        $this->validate([
            'definitionId' => ['required', 'integer'],
            'definitionTitle' => ['required', 'string', 'max:255'],
            'definitionPayload' => ['array'],
        ]);

        $definition = $this->activeDefinitions()->firstWhere('id', (int) $this->definitionId);
        abort_unless($definition instanceof SpaceContentDefinition, 404);

        $content = $create->execute(
            $this->context,
            $definition,
            $this->user(),
            $this->definitionTitle,
            $this->definitionPayload,
        );

        return $this->redirectRoute('contexts.contents.studio', [$this->context, $content]);
    }

    public function render(): View
    {
        $user = $this->user();
        $current = Context::query()->findOrFail($this->context->id);
        Gate::forUser($user)->authorize('view', $current);
        $this->context = $current;

        $blueprints = $this->blueprints();
        $selectedBlueprint = $blueprints->first(
            fn (ContentBlueprint $item): bool => (int) $item->active_version_id === (int) $this->blueprintVersionId,
        );
        $selectedBlueprintVersion = $selectedBlueprint?->activeVersion;

        $definitions = $this->activeDefinitions();
        $selectedDefinition = $definitions->firstWhere('id', (int) $this->definitionId);
        $selectedDefinitionVersion = $selectedDefinition instanceof SpaceContentDefinition
            ? $selectedDefinition->activeVersionRecord()
            : null;

        $contents = $current->contents()
            ->with([
                'author.user',
                'activeRevision.assets',
                'draftRevision.assets',
                'definition',
                'blueprintVersion.blueprint',
            ])
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn ($content): bool => Gate::forUser($user)->allows('view', $content))
            ->values();

        $canManageDefinitions = Gate::forUser($user)->allows('manageDefinitions', $current);
        $canCreate = Gate::forUser($user)->allows('createContent', $current);

        return view('livewire.contexts.content-index', compact(
            'blueprints',
            'selectedBlueprint',
            'selectedBlueprintVersion',
            'definitions',
            'selectedDefinition',
            'selectedDefinitionVersion',
            'contents',
            'canManageDefinitions',
            'canCreate',
        ));
    }

    /** @return Collection<int, ContentBlueprint> */
    private function blueprints(): Collection
    {
        return app(ContentBlueprintCatalog::class)->availableFor(
            $this->user(),
            $this->context,
            $this->blueprintSearch,
        );
    }

    /** @return Collection<int, SpaceContentDefinition> */
    private function activeDefinitions(): Collection
    {
        return $this->context->contentDefinitions()
            ->where('status', 'active')
            ->whereNotNull('active_version_id')
            ->orderBy('name')
            ->get()
            ->filter(fn (SpaceContentDefinition $definition): bool => $definition->activeVersionRecord() instanceof SpaceContentDefinitionVersion)
            ->values();
    }

    private function resetCreator(): void
    {
        $this->reset('blueprintSearch', 'blueprintVersionId', 'title', 'payload');
        $this->resetErrorBag();
    }

    /** @param array<string, mixed> $target */
    private function initializeBooleanFields(
        ContentBlueprintVersion|SpaceContentDefinitionVersion $version,
        array &$target,
    ): void {
        $schema = $version instanceof ContentBlueprintVersion
            ? $version->definition_schema
            : $version->schema;

        foreach ($schema['fields'] ?? [] as $field) {
            if (is_array($field)
                && ($field['type'] ?? null) === 'boolean'
                && is_string($field['key'] ?? null)) {
                $target[$field['key']] = false;
            }
        }
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
