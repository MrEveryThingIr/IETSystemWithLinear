<?php

namespace App\Livewire\Contexts;

use App\Actions\Contexts\CreateContextContent;
use App\Actions\Contexts\CreateContextContentDefinition;
use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Models\Context;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Context Content')]
class ContentIndex extends Component
{
    public Context $context;

    public string $definitionName = '';

    public string $definitionId = '';

    public string $title = '';

    public string $body = '';

    public function mount(Context $context): void
    {
        Gate::forUser($this->user())->authorize('view', $context);
        $this->context = $context;
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
        $this->reset('definitionName');
        session()->flash('status', __('ui.context_content.definition_created'));
    }

    public function createContent(CreateContextContent $create): mixed
    {
        Gate::forUser($this->user())->authorize('createContent', $this->context);

        $this->validate([
            'definitionId' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $definition = $this->activeDefinitions()->firstWhere('id', (int) $this->definitionId);
        abort_unless($definition instanceof SpaceContentDefinition, 404);

        $content = $create->execute(
            $this->context,
            $definition,
            $this->user(),
            $this->title,
            ['body' => $this->body],
        );

        return $this->redirectRoute('contexts.contents.show', [$this->context, $content]);
    }

    public function render(): View
    {
        $user = $this->user();
        $current = Context::query()->findOrFail($this->context->id);
        Gate::forUser($user)->authorize('view', $current);
        $this->context = $current;

        $definitions = $this->activeDefinitions();
        $contents = $current->contents()
            ->with(['author.user', 'activeRevision', 'draftRevision', 'definition'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->filter(fn ($content): bool => Gate::forUser($user)->allows('view', $content))
            ->values();

        $canManageDefinitions = Gate::forUser($user)->allows('manageDefinitions', $current);
        $canCreate = Gate::forUser($user)->allows('createContent', $current);

        return view('livewire.contexts.content-index', compact(
            'definitions',
            'contents',
            'canManageDefinitions',
            'canCreate',
        ));
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

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
