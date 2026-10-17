<?php

namespace App\Livewire\Content;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
use App\Models\User;
use App\Support\ContentBlueprintCatalog;
use App\Support\ContentKinds;
use App\Support\ContentWorkspaceContexts;
use App\Support\ContextPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Create" in plain words: pick what you are making, give it a title, choose
 * where it lives. Resolves to a system Content Blueprint behind the scenes.
 */
class CreateContent extends Component
{
    /** When set, new Content is always created in this Context. */
    public ?string $fixedContextUuid = null;

    public bool $open = false;

    public string $kind = '';

    public string $contextUuid = '';

    public string $title = '';

    /** @var array<string, mixed> */
    public array $payload = [];

    #[On('open-content-create')]
    public function openDialog(?string $kind = null): void
    {
        $this->resetErrorBag();
        $this->reset('kind', 'title', 'payload');
        $this->open = true;
        $this->contextUuid = $this->fixedContextUuid ?? ($this->creatableContexts()->first()->uuid ?? '');

        if ($kind !== null && isset(ContentKinds::KINDS[$kind])) {
            $this->choose($kind);
        }
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetErrorBag();
    }

    public function choose(string $kind): void
    {
        abort_unless(isset(ContentKinds::KINDS[$kind]), 422);
        $this->kind = $kind;
        $this->payload = [];
        $this->resetErrorBag();

        $version = $this->blueprintVersion();
        foreach ($version?->definition_schema['fields'] ?? [] as $field) {
            if (is_array($field) && ($field['type'] ?? null) === 'boolean' && is_string($field['key'] ?? null)) {
                $this->payload[$field['key']] = false;
            }
        }
    }

    public function back(): void
    {
        $this->reset('kind', 'payload');
        $this->resetErrorBag();
    }

    public function updatedContextUuid(): void
    {
        if ($this->kind !== '' && ! $this->blueprintVersion() instanceof ContentBlueprintVersion) {
            $this->addError('kind', __('content_workspace.create.kind_unavailable_here'));
        } else {
            $this->resetErrorBag('kind');
        }
    }

    public function create(CreateContentFromBlueprint $create): mixed
    {
        $this->validate([
            'kind' => ['required', 'string'],
            'contextUuid' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'payload' => ['array'],
        ]);

        $context = $this->selectedContext();
        abort_unless($context instanceof Context, 404);
        Gate::forUser($this->user())->authorize('createContent', $context);

        $version = $this->blueprintVersion();
        if (! $version instanceof ContentBlueprintVersion) {
            $this->addError('kind', __('content_workspace.create.kind_unavailable_here'));

            return null;
        }

        $content = $create->execute($context, $version, $this->user(), $this->title, $this->payload);

        return $this->redirectRoute('contexts.contents.studio', [$context, $content]);
    }

    public function render(): View
    {
        $contexts = $this->open ? $this->creatableContexts() : collect();
        $presenter = app(ContextPresenter::class);
        $version = $this->open && $this->kind !== '' ? $this->blueprintVersion() : null;

        $requiredFields = collect($version?->definition_schema['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && (bool) ($field['required'] ?? false))
            ->values();

        $selected = $contexts->firstWhere('uuid', $this->contextUuid);

        return view('livewire.content.create-content', [
            'kinds' => ContentKinds::KINDS,
            'contexts' => $contexts,
            'contextLabels' => $contexts->mapWithKeys(fn (Context $context): array => [$context->uuid => $presenter->label($context)])->all(),
            'selectedAudience' => $selected instanceof Context ? $presenter->audience($selected) : null,
            'requiredFields' => $requiredFields,
            'moreTemplatesUrl' => $selected instanceof Context ? route('contexts.contents.index', $selected) : null,
        ]);
    }

    private function blueprintVersion(): ?ContentBlueprintVersion
    {
        $slug = ContentKinds::slugFor($this->kind);
        $context = $this->selectedContext();
        if ($slug === null || ! $context instanceof Context) {
            return null;
        }

        $blueprint = app(ContentBlueprintCatalog::class)
            ->availableFor($this->user(), $context)
            ->first(fn (ContentBlueprint $item): bool => $item->scope === ContentBlueprint::SCOPE_SYSTEM && $item->slug === $slug);

        return $blueprint?->activeVersion;
    }

    private function selectedContext(): ?Context
    {
        if ($this->contextUuid === '') {
            return null;
        }

        if ($this->fixedContextUuid !== null) {
            return $this->contextUuid === $this->fixedContextUuid
                ? Context::query()->where('uuid', $this->fixedContextUuid)->first()
                : null;
        }

        return $this->creatableContexts()->firstWhere('uuid', $this->contextUuid);
    }

    /** @return Collection<int, Context> */
    private function creatableContexts(): Collection
    {
        if ($this->fixedContextUuid !== null) {
            $context = Context::query()->with(ContextPresenter::RELATIONS)->where('uuid', $this->fixedContextUuid)->first();

            return $context instanceof Context && Gate::forUser($this->user())->allows('createContent', $context)
                ? collect([$context])
                : collect();
        }

        return app(ContentWorkspaceContexts::class)->creatable($this->user());
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
