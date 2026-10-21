<?php

namespace App\Livewire\Content;

use App\ContextKind;
use App\Livewire\Concerns\ManagesContentLifecycle;
use App\Models\Actor;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceAccess;
use App\Support\ContentKinds;
use App\Support\ContentWorkspaceContexts;
use App\Support\ContextPresenter;
use App\Support\TemporalPreferences;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One practical Content page: everything you wrote or can see, with simple
 * views (drafts, published, shared, learning) and the place it belongs as a
 * filter rather than a concept you must understand first.
 */
#[Layout('layouts.app')]
#[Title('Content')]
class Workspace extends Component
{
    use ManagesContentLifecycle;

    /** @var list<string> */
    public const VIEWS = ['all', 'drafts', 'published', 'shared', 'learning', 'archived'];

    #[Url(as: 'view', except: 'all')]
    public string $view = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'place', except: '')]
    public string $place = '';

    public function mount(): void
    {
        abort_unless(app(FeatureSurfaceAccess::class)->allows($this->user(), 'content'), 403);
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : 'all';
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'place');
        $this->view = 'all';
    }

    public function render(): View
    {
        $user = $this->user();
        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $presenter = app(ContextPresenter::class);
        $contexts = app(ContentWorkspaceContexts::class)->viewable($user);
        $placeContext = $this->place !== '' ? $contexts->firstWhere('uuid', $this->place) : null;
        // Reference places (the system manual) are reading material, not
        // working content: they appear only when chosen explicitly.
        $scopeIds = $placeContext instanceof Context
            ? [$placeContext->id]
            : $contexts->reject(fn (Context $context): bool => in_array($context->kind, [ContextKind::Reference, ContextKind::System], true))->pluck('id')->all();

        $visible = SpaceContent::query()
            ->whereIn('context_id', $scopeIds)
            ->with([
                'author.user',
                'author.profile',
                'activeRevision.assets',
                'draftRevision.assets',
                'blueprintVersion.blueprint',
                'definition',
            ])
            ->latest('updated_at')
            ->limit(300)
            ->get()
            ->filter(fn (SpaceContent $content): bool => Gate::forUser($user)->allows('view', $content))
            ->values();

        $contextsById = $contexts->keyBy('id');
        $editable = $visible
            ->filter(fn (SpaceContent $content): bool => Gate::forUser($user)->allows('update', $content))
            ->pluck('id')
            ->flip();

        $counts = [];
        foreach (self::VIEWS as $view) {
            $counts[$view] = $visible->filter(fn (SpaceContent $content): bool => $this->inView($content, $view, $actor, $editable))->count();
        }

        $needle = mb_strtolower(trim($this->search));
        $items = $visible
            ->filter(fn (SpaceContent $content): bool => $this->inView($content, $this->view, $actor, $editable))
            ->filter(function (SpaceContent $content) use ($needle): bool {
                if ($needle === '') {
                    return true;
                }

                return str_contains(mb_strtolower(implode(' ', [
                    (string) $this->workingRevision($content)?->title,
                    (string) $content->blueprintVersion?->blueprint?->name,
                ])), $needle);
            })
            ->map(fn (SpaceContent $content): array => $this->card($content, $contextsById, $presenter, $actor, $editable))
            ->values();

        return view('livewire.content.workspace', [
            'items' => $items,
            'counts' => $counts,
            'placeOptions' => $contexts->mapWithKeys(fn (Context $context): array => [$context->uuid => $presenter->label($context)])->all(),
            'placeContext' => $placeContext,
            'placeAudience' => $placeContext instanceof Context ? $presenter->audience($placeContext) : null,
        ])->title(__('content_workspace.title'));
    }

    /** @param Collection<int, int> $editable */
    private function inView(SpaceContent $content, string $view, Actor $actor, Collection $editable): bool
    {
        $archived = $content->status === 'archived';

        return match ($view) {
            'archived' => $archived,
            'drafts' => ! $archived && $editable->has($content->id)
                && ($content->status === 'draft' || $content->draft_revision_id !== null),
            'published' => $content->status === 'published',
            'shared' => ! $archived && (int) $content->author_actor_id !== (int) $actor->id,
            'learning' => ! $archived && $content->blueprintVersion?->blueprint?->category === ContentKinds::LEARNING_CATEGORY,
            default => ! $archived,
        };
    }

    /**
     * @param  Collection<int, Context>  $contextsById
     * @param  Collection<int, int>  $editable
     * @return array<string, mixed>
     */
    private function card(SpaceContent $content, Collection $contextsById, ContextPresenter $presenter, Actor $actor, Collection $editable): array
    {
        $context = $contextsById->get($content->context_id) ?? $content->context;
        $canEdit = $editable->has($content->id);
        $revision = $canEdit ? $this->workingRevision($content) : $content->activeRevision;
        $cover = $revision?->assets?->first(fn ($asset): bool => $asset->mediaKind() === 'image');
        $blueprint = $content->blueprintVersion?->blueprint;
        $kind = ContentKinds::kindForSlug($blueprint?->slug);

        $state = match (true) {
            $content->status === 'archived' => 'archived',
            $content->status === 'published' && $content->draft_revision_id !== null && $canEdit => 'published_changes',
            $content->status === 'published' => 'published',
            default => 'draft',
        };

        return [
            'uuid' => $content->uuid,
            'title' => $revision?->title ?: __('ui.content.untitled'),
            'type' => $kind !== null ? __('content_workspace.kinds.'.$kind.'.name') : ($blueprint->name ?? $content->definition->name),
            'place' => $context instanceof Context ? $presenter->label($context) : null,
            'state' => $state,
            'is_public' => $content->public_access === SpaceContent::ACCESS_PUBLIC && $content->status === 'published',
            'updated_at' => $content->updated_at?->copy()->setTimezone(TemporalPreferences::timezoneFor($this->user())),
            'author' => (int) $content->author_actor_id !== (int) $actor->id ? $content->author : null,
            'cover_url' => $cover !== null && $context instanceof Context
                ? route('contexts.contents.assets.show', [$context, $content, $cover])
                : null,
            'url' => $canEdit && $context instanceof Context
                ? route('contexts.contents.studio', [$context, $content])
                : ($context instanceof Context ? route('contexts.contents.show', [$context, $content]) : null),
            'read_url' => $content->status === 'published' && $context instanceof Context
                ? route('contexts.contents.show', [$context, $content])
                : null,
            'can_edit' => $canEdit,
            'can_rename' => $canEdit && $content->status !== 'archived',
            'can_delete' => Gate::forUser($this->user())->allows('delete', $content),
        ];
    }

    private function workingRevision(SpaceContent $content): ?SpaceContentRevision
    {
        return $content->draftRevision ?? $content->activeRevision;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
