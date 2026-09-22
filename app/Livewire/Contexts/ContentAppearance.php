<?php

namespace App\Livewire\Contexts;

use App\Actions\Groups\SaveSpaceContentRenderTemplate;
use App\Actions\Groups\ToggleSpaceContentRenderTemplateFavorite;
use App\Actions\Groups\UpdateSpaceContentPresentation;
use App\Models\Actor;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRenderTemplate;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentPresentation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Appearance')]
class ContentAppearance extends Component
{
    public Context $context;

    public SpaceContent $content;

    public string $templateSource = 'builtin:article';

    /** @var array<string, mixed> */
    public array $presentation = [];

    public string $templateName = '';

    public function mount(Context $context, SpaceContent $content): void
    {
        abort_unless((int) $content->context_id === (int) $context->id, 404);
        Gate::forUser($this->user())->authorize('update', $content);

        $this->context = $context;
        $this->content = $content;
        $this->reload();
    }

    public function chooseTemplate(string $source, SpaceContentPresentation $presentations): void
    {
        if (str_starts_with($source, 'custom:')) {
            $template = SpaceContentRenderTemplate::query()
                ->where('context_id', $this->context->id)
                ->where('uuid', substr($source, 7))
                ->where('status', SpaceContentRenderTemplate::STATUS_ACTIVE)
                ->first();
            abort_unless($template instanceof SpaceContentRenderTemplate, 404);
            $this->templateSource = $source;
            $this->presentation = $template->tokens;

            return;
        }

        $key = str_starts_with($source, 'builtin:') ? substr($source, 8) : $source;
        abort_unless(in_array($key, $presentations->keys(), true), 422);
        $this->templateSource = 'builtin:'.$key;
        $this->presentation = $presentations->defaults($key);
    }

    public function save(UpdateSpaceContentPresentation $update): void
    {
        $this->content = $update->execute($this->content, $this->user(), $this->templateSource, $this->presentation);
        $this->reload();
        session()->flash('status', __('presentation.saved'));
    }

    public function saveAsTemplate(SaveSpaceContentRenderTemplate $save, SpaceContentPresentation $presentations): void
    {
        $this->validate(['templateName' => ['required', 'string', 'max:120']]);
        $baseKey = str_starts_with($this->templateSource, 'builtin:')
            ? substr($this->templateSource, 8)
            : $this->currentBaseKey();
        abort_unless(in_array($baseKey, $presentations->keys(), true), 422);

        $template = $save->execute($this->content, $this->user(), $this->templateName, $baseKey, $this->presentation);
        $this->templateSource = 'custom:'.$template->uuid;
        $this->templateName = '';
        session()->flash('status', __('presentation.template_saved'));
    }

    public function toggleFavorite(string $uuid, ToggleSpaceContentRenderTemplateFavorite $toggle): void
    {
        $template = SpaceContentRenderTemplate::query()
            ->where('context_id', $this->context->id)
            ->where('uuid', $uuid)
            ->firstOrFail();
        $toggle->execute($template, $this->user());
    }

    public function render(SpaceContentPresentation $presentations): View
    {
        $revision = $this->editableRevision();
        $version = $revision->definitionVersion()->firstOrFail();
        $fields = collect($version->schema['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['key'] ?? null))
            ->values();

        $actor = $this->actor();
        $customTemplates = SpaceContentRenderTemplate::query()
            ->where('context_id', $this->context->id)
            ->where('status', SpaceContentRenderTemplate::STATUS_ACTIVE)
            ->withCount(['favoritedBy as is_favorite' => fn ($query) => $query->where('actors.id', $actor->id)])
            ->orderByDesc('is_favorite')
            ->orderBy('name')
            ->get();

        $builtIns = $presentations->builtIns();

        return view('livewire.contexts.content-appearance', compact(
            'revision',
            'fields',
            'customTemplates',
            'builtIns',
        ));
    }

    private function reload(): void
    {
        $revision = $this->editableRevision();
        $this->templateSource = is_string($revision->render_template_uuid) && $revision->render_template_uuid !== ''
            ? 'custom:'.$revision->render_template_uuid
            : 'builtin:'.$revision->render_template_key;
        $this->presentation = $revision->presentation ?? [];
    }

    private function currentBaseKey(): string
    {
        if (!str_starts_with($this->templateSource, 'custom:')) {
            return 'article';
        }

        $template = SpaceContentRenderTemplate::query()
            ->where('context_id', $this->context->id)
            ->where('uuid', substr($this->templateSource, 7))
            ->first();

        return $template instanceof SpaceContentRenderTemplate ? $template->base_key : 'article';
    }

    private function editableRevision(): SpaceContentRevision
    {
        $current = $this->content->fresh();
        abort_unless($current instanceof SpaceContent, 404);
        Gate::forUser($this->user())->authorize('update', $current);
        $this->content = $current;
        $revision = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 404);

        return $revision;
    }

    private function actor(): Actor
    {
        $user = User::query()->with('actor')->find($this->user()->id);
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user->actor;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
