<?php

namespace App\Livewire\Content;

use App\Actions\Content\PlacePublishedContent;
use App\Actions\Content\RemoveContentPlacement;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\ContextKind;
use App\Models\ConceptAssertion;
use App\Models\ContentPlacement;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Content Library')]
class Library extends Component
{
    public string $search = '';

    public string $blueprint = '';

    public string $concept = '';

    public string $placingContentUuid = '';

    public string $targetContextUuid = '';

    public function startPlacement(string $contentUuid): void
    {
        $content = SpaceContent::query()
            ->with('context')
            ->where('uuid', $contentUuid)
            ->firstOrFail();

        Gate::forUser($this->user())->authorize('view', $content->context);
        Gate::forUser($this->user())->authorize('view', $content);

        abort_unless($content->status === 'published', 422);

        $this->placingContentUuid = $content->uuid;
        $this->targetContextUuid = '';
        $this->resetErrorBag();
    }

    public function cancelPlacement(): void
    {
        $this->reset('placingContentUuid', 'targetContextUuid');
        $this->resetErrorBag();
    }

    public function place(PlacePublishedContent $place): void
    {
        $this->validate([
            'placingContentUuid' => ['required', 'uuid'],
            'targetContextUuid' => ['required', 'uuid'],
        ]);

        $content = SpaceContent::query()->where('uuid', $this->placingContentUuid)->firstOrFail();
        $target = Context::query()->where('uuid', $this->targetContextUuid)->firstOrFail();

        $place->execute($content, $target, $this->user());

        $this->reset('placingContentUuid', 'targetContextUuid');
        session()->flash('status', __('library.placed'));
    }

    public function removePlacement(string $placementUuid, RemoveContentPlacement $remove): void
    {
        $placement = ContentPlacement::query()->where('uuid', $placementUuid)->firstOrFail();
        $remove->execute($placement, $this->user());

        session()->flash('status', __('library.removed'));
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'blueprint', 'concept');
    }

    public function render(): View
    {
        $user = $this->user();

        $contents = SpaceContent::query()
            ->where('status', 'published')
            ->whereNotNull('active_revision_id')
            ->with([
                'context.personalBinding.actor.user',
                'context.groupSpaceBinding.groupSpace.group',
                'context.admissionBinding.admission.group',
                'context.referenceBinding',
                'author.user',
                'activeRevision.assets',
                'blueprintVersion.blueprint',
                'placements.context.personalBinding.actor.user',
                'placements.context.groupSpaceBinding.groupSpace.group',
                'placements.context.admissionBinding.admission.group',
                'placements.context.referenceBinding',
            ])
            ->latest('published_at')
            ->limit(300)
            ->get()
            ->filter(function (SpaceContent $content) use ($user): bool {
                $revision = $content->activeRevision;

                return $revision instanceof SpaceContentRevision
                    && $revision->hasVerifiableManifest()
                    && Gate::forUser($user)->allows('view', $content);
            })
            ->values();

        $conceptLabels = $this->conceptLabels($contents);

        $needle = mb_strtolower(trim($this->search));
        $conceptNeedle = mb_strtolower(trim($this->concept));
        $blueprint = trim($this->blueprint);

        $contents = $contents
            ->filter(function (SpaceContent $content) use ($needle, $conceptNeedle, $blueprint, $conceptLabels): bool {
                $revision = $content->activeRevision;
                $contentBlueprint = $content->blueprintVersion?->blueprint;

                if ($blueprint !== '' && $contentBlueprint?->slug !== $blueprint) {
                    return false;
                }

                if ($needle !== '') {
                    $haystack = mb_strtolower(implode(' ', [
                        (string) $revision?->title,
                        (string) $contentBlueprint?->name,
                        (string) $contentBlueprint?->category,
                    ]));

                    if (! str_contains($haystack, $needle)) {
                        return false;
                    }
                }

                if ($conceptNeedle !== '') {
                    $labels = mb_strtolower(implode(' ', $conceptLabels[$content->id] ?? []));

                    if (! str_contains($labels, $conceptNeedle)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $blueprintOptions = $contents
            ->map(fn (SpaceContent $content): ?array => $content->blueprintVersion?->blueprint
                ? [
                    'slug' => $content->blueprintVersion->blueprint->slug,
                    'name' => $content->blueprintVersion->blueprint->name,
                ]
                : null)
            ->filter()
            ->unique('slug')
            ->sortBy('name')
            ->values();

        $targetContexts = $this->manageableContexts();
        $targetContextLabels = $targetContexts
            ->mapWithKeys(fn (Context $context): array => [$context->uuid => $this->contextLabel($context)])
            ->all();

        $sourceContextLabels = $contents
            ->mapWithKeys(fn (SpaceContent $content): array => [$content->context->uuid => $this->contextLabel($content->context)])
            ->all();

        $placeableContentIds = $contents
            ->filter(fn (SpaceContent $content): bool => Gate::forUser($user)->allows('view', $content->context)
                && $targetContexts->contains(fn (Context $context): bool => (int) $context->id !== (int) $content->context_id))
            ->pluck('id')
            ->all();

        return view('livewire.content.library', compact(
            'contents',
            'blueprintOptions',
            'conceptLabels',
            'targetContexts',
            'targetContextLabels',
            'sourceContextLabels',
            'placeableContentIds',
        ));
    }

    /** @param Collection<int, SpaceContent> $contents @return array<int, list<string>> */
    private function conceptLabels(Collection $contents): array
    {
        if ($contents->isEmpty()) {
            return [];
        }

        $revisionToContent = $contents
            ->filter(fn (SpaceContent $content): bool => $content->active_revision_id !== null)
            ->mapWithKeys(fn (SpaceContent $content): array => [(int) $content->active_revision_id => $content->id]);

        $contentIds = $contents->pluck('id')->all();
        $revisionIds = $revisionToContent->keys()->all();

        $assertions = ConceptAssertion::query()
            ->with('concept.labels')
            ->whereIn('visibility', [
                ConceptAssertionVisibility::Inherited->value,
                ConceptAssertionVisibility::Public->value,
            ])
            ->where(function ($query) use ($contentIds, $revisionIds): void {
                $query->where(function ($query) use ($contentIds): void {
                    $query->where('subject_type', ConceptAssertionSubject::SpaceContent->value)
                        ->whereIn('subject_id', $contentIds);
                })->orWhere(function ($query) use ($revisionIds): void {
                    $query->where('subject_type', ConceptAssertionSubject::SpaceContentRevision->value)
                        ->whereIn('subject_id', $revisionIds);
                });
            })
            ->get();

        $labels = [];

        foreach ($assertions as $assertion) {
            $contentId = $assertion->subject_type === ConceptAssertionSubject::SpaceContent
                ? (int) $assertion->subject_id
                : (int) ($revisionToContent[(int) $assertion->subject_id] ?? 0);

            if ($contentId === 0) {
                continue;
            }

            $label = $assertion->concept->displayLabel();

            if (! in_array($label, $labels[$contentId] ?? [], true)) {
                $labels[$contentId][] = $label;
            }
        }

        return $labels;
    }

    /** @return Collection<int, Context> */
    private function manageableContexts(): Collection
    {
        $user = $this->user();

        return Context::query()
            ->with([
                'personalBinding.actor.user',
                'groupSpaceBinding.groupSpace.group',
                'admissionBinding.admission.group',
                'referenceBinding',
            ])
            ->latest('id')
            ->limit(300)
            ->get()
            ->filter(fn (Context $context): bool => Gate::forUser($user)->allows('manageContent', $context))
            ->values();
    }

    private function contextLabel(Context $context): string
    {
        return match ($context->kind) {
            ContextKind::Personal => __('library.context.personal'),
            ContextKind::GroupSpace => trim(implode(' · ', array_filter([
                $context->groupSpaceBinding?->groupSpace?->group?->name,
                $context->groupSpaceBinding?->groupSpace?->name,
            ]))) ?: __('library.context.group_space'),
            ContextKind::Admission => __('library.context.admission', [
                'group' => $context->admissionBinding?->admission?->group?->name ?? '#'.$context->admissionBinding?->admission_id,
            ]),
            ContextKind::Reference => __('library.context.reference', [
                'key' => $context->referenceBinding?->key ?? $context->uuid,
            ]),
        };
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
