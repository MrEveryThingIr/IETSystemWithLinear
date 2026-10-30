@php
    extract(\App\Support\ContentReaderStyle::variables($revision));
    $assetUrlFor = fn ($asset) => route('contexts.contents.assets.show', [$context, $content, $asset]);
    $downloadUrlFor = fn ($asset) => route('contexts.contents.assets.download', [$context, $content, $asset]);
    $blocks = $revision->composition_mode === \App\Models\SpaceContentRevision::COMPOSITION_BLOCKS
        ? $revision->blocks()->get()
        : collect();
    $mediaByPlacement = $revision->assets->keyBy(fn ($asset) => (string) $asset->pivot->uuid);
    $relationships = $revision->containedRelationships()->with(['childContent.activeRevision'])->get();
    $readerPublishedAt = $viewingEvidenceReferenceUuid !== null
        ? $revision->manifest_sealed_at
        : $content->published_at;
@endphp

<section
    id="content-reader-{{ $content->uuid }}"
    class="min-h-screen space-y-6 px-1 pb-16"
    style="--content-bg: {{ $readerBackground }}; --content-surface: {{ $readerSurface }}; --content-text: {{ $readerText }}; --content-muted: {{ $readerMuted }}; --content-accent: {{ $readerAccent }}; --content-border: {{ $readerBorder }}; background: var(--content-bg); color: var(--content-text);"
>

    <div id="annotation-marker-payload-{{ $content->uuid }}" class="hidden" data-marker-payload='@json($rangeMarkers)'></div>

    <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 pt-4">
        <flux:button :href="route('experience.content')" variant="ghost" size="sm" icon="arrow-left" class="rtl:[&_svg]:rotate-180">
            {{ __('reader.back_to_content') }}
        </flux:button>

        <div class="flex flex-wrap items-center gap-2">
            @if ($canAnnotate)
                <x-app.annotation-target-menu
                    target-type="revision"
                    :target-uuid="$revision->uuid"
                    :label="__('interactions.anchor.entire_edition')"
                />
            @endif
            @php
                $canCreateEvidence = $canEnterStudio && ! $previewingDraft && ! ($legacyEvidence || $viewingEvidenceReferenceUuid || $viewingRevisionUuid);
            @endphp
            @if ($revision->hasVerifiableManifest() || $canCreateEvidence)
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" size="sm" icon="information-circle" data-reader-details>{{ __('reader.details') }}</flux:button>
                    <flux:menu class="min-w-60">
                        @if ($revision->hasVerifiableManifest())
                            <flux:menu.item icon="link" :href="route('contexts.contents.revisions.show', [$context, $content, $revision])">{{ __('reader.revision_permalink') }}</flux:menu.item>
                        @endif
                        @if ($canCreateEvidence)
                            <flux:menu.item icon="check-badge" wire:click="createRevisionEvidence">{{ __('ui.context_content.create_evidence_reference') }}</flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            @endif
            @if ($canShareOutside && ! $previewingDraft)
                <flux:button x-on:click="$dispatch('open-drawer', 'share')" variant="ghost" size="sm" icon="share" data-reader-share>
                    {{ __('public_content.share.button') }}
                    @if ($content->public_access === \App\Models\SpaceContent::ACCESS_PUBLIC)
                        <flux:badge size="sm" color="green" class="ms-1">{{ __('public_content.workspace_badge') }}</flux:badge>
                    @endif
                </flux:button>
            @endif
            @if ($canEnterStudio)
                <flux:button :href="route('contexts.contents.studio', [$context, $content])" variant="primary" size="sm">
                    {{ $previewingDraft ? __('reader.back_to_editor') : __('reader.edit_in_studio') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if ($previewingDraft)
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="warning" data-reader-draft-preview>
                <div class="font-medium">{{ __('reader.draft_preview_title') }}</div>
                <div class="mt-1 text-sm">{{ __('reader.draft_preview_help') }}</div>
            </flux:callout>
        </div>
    @endif

    @if ($viewingEvidenceReferenceUuid)
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="info">
                <div class="font-medium">{{ __('ui.context_content.historical_evidence') }}</div>
                <div class="mt-1 text-sm">{{ __('ui.context_content.historical_evidence_help') }}</div>
                <details class="mt-2 text-xs">
                    <summary class="cursor-pointer">{{ __('reader.provenance') }}</summary>
                    <div class="mt-1 break-all font-mono">{{ $viewingEvidenceReferenceUuid }}</div>
                </details>
            </flux:callout>
        </div>
    @endif

    @if ($viewingRevisionUuid)
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="info">
                <div class="font-medium">{{ __('reader.fixed_edition_title') }}</div>
                <div class="mt-1 text-sm">{{ __('reader.fixed_edition_help') }}</div>
                <details class="mt-2 text-xs">
                    <summary class="cursor-pointer">{{ __('reader.provenance') }}</summary>
                    <div class="mt-1 break-all font-mono">{{ $viewingRevisionUuid }}</div>
                </details>
            </flux:callout>
        </div>
    @endif

    @if ($evidenceReferenceUuid)
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="success">
                {{ __('ui.context_content.evidence_reference_created') }}
                <details class="mt-2 text-xs">
                    <summary class="cursor-pointer">{{ __('reader.provenance') }}</summary>
                    <div class="mt-1 break-all font-mono">{{ $evidenceReferenceUuid }}</div>
                </details>
            </flux:callout>
        </div>
    @endif

    @if ($legacyEvidence && ! $previewingDraft)
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="warning">
                <div class="font-medium">{{ __('reader.legacy_title') }}</div>
                <div class="mt-1 text-sm">{{ __('reader.legacy_help') }}</div>
            </flux:callout>
        </div>
    @endif

    @if (session('interaction-status'))
        <div class="mx-auto w-full max-w-7xl">
            <flux:callout variant="success">{{ session('interaction-status') }}</flux:callout>
        </div>
    @endif

    <div class="mx-auto grid w-full max-w-7xl gap-8 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <article class="min-w-0">
            <header class="mx-auto {{ $contentWidthClass }} border-b pb-6" style="border-color: var(--content-border)" data-reader-header>
                @if ($courseNavigation)
                    <a href="{{ $courseNavigation['parent']['url'] }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium hover:underline" style="color: var(--content-accent)" data-reader-course>
                        <span dir="auto">{{ $courseNavigation['parent']['title'] }}</span>
                        <span style="color: var(--content-muted)">· {{ __('reader.position_of', ['position' => $courseNavigation['position'], 'total' => $courseNavigation['total']]) }}</span>
                    </a>
                @endif

                <h1 class="text-3xl sm:text-4xl {{ $headingClass }}" dir="auto">{{ $revision->title }}</h1>
                <x-content.plan-links :content="$content" />

                <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm" style="color: var(--content-muted)">
                    <x-app.actor-identity :actor="$content->author" size="xs" />
                    @if ($readerPublishedAt && ! $previewingDraft)
                        <span aria-hidden="true">·</span>
                        <x-app.local-date :value="\Carbon\CarbonImmutable::parse($readerPublishedAt)->setTimezone(\App\Support\TemporalPreferences::timezoneFor(auth()->user()))" />
                    @endif
                    <span aria-hidden="true">·</span>
                    <span dir="auto" data-reader-place>{{ $placeLabel }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ $kindLabel }}</span>
                </div>

                @if ($canEnterStudio)
                    <details class="mt-3 text-xs" style="color: var(--content-muted)" data-reader-provenance>
                        <summary class="cursor-pointer select-none">{{ __('reader.provenance') }}</summary>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @unless ($legacyEvidence)
                                <flux:badge size="sm">{{ __('reader.verified_edition') }}</flux:badge>
                            @endunless
                            <flux:badge size="sm">{{ __('presentation.preset.'.$revision->render_template_key) }}</flux:badge>
                            <span>{{ __('ui.content.revision_number', ['revision' => $revision->revision]) }}</span>
                            @if ($readerPublishedAt)
                                <span>· {{ __('reader.published_label') }} <x-app.local-datetime :value="$readerPublishedAt" /></span>
                            @endif
                        </div>
                        @if ($revision->manifest_hash)
                            <div class="mt-1 break-all font-mono">manifest: {{ $revision->manifest_hash }}</div>
                        @endif
                    </details>
                @endif
            </header>

            <div class="mx-auto mt-8 {{ $contentWidthClass }} space-y-7">
                @include('content.partials.reader-body')

                @if ($interactionDefinitions->isNotEmpty())
                    <section class="space-y-4 border-t pt-7" style="border-color: var(--content-border)" id="structured-interactions">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-xl {{ $headingClass }}">{{ __('structured_interactions.section_title') }}</h2>
                                <p class="mt-1 text-sm" style="color: var(--content-muted)">{{ __('structured_interactions.section_help') }}</p>
                            </div>
                            @if ($canReviewInteractions)
                                <flux:button :href="route('contexts.submissions.index', $context)" size="sm" variant="ghost" class="w-full sm:w-auto">
                                    {{ __('structured_interactions.review_submissions') }}
                                </flux:button>
                            @endif
                        </div>

                        <div class="space-y-4">
                            @foreach ($interactionDefinitions as $interactionDefinition)
                                <livewire:interactions.submission-card
                                    :definition="$interactionDefinition"
                                    :key="'submission-card-'.$interactionDefinition->uuid"
                                />
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($courseNavigation && ($courseNavigation['previous'] || $courseNavigation['next']))
                    <nav class="grid gap-3 border-t pt-6 sm:grid-cols-2" style="border-color: var(--content-border)" aria-label="{{ __('reader.course_navigation') }}" data-reader-course-nav>
                        <div>
                            @if ($courseNavigation['previous'])
                                <a href="{{ $courseNavigation['previous']['url'] }}" class="block rounded-xl border p-3 hover:opacity-80" style="border-color: var(--content-border)">
                                    <span class="block text-xs" style="color: var(--content-muted)">← {{ __('reader.previous') }}</span>
                                    <span class="block font-medium" dir="auto">{{ $courseNavigation['previous']['title'] }}</span>
                                </a>
                            @endif
                        </div>
                        <div>
                            @if ($courseNavigation['next'])
                                <a href="{{ $courseNavigation['next']['url'] }}" class="block rounded-xl border p-3 text-end hover:opacity-80" style="border-color: var(--content-border)">
                                    <span class="block text-xs" style="color: var(--content-muted)">{{ __('reader.next') }} →</span>
                                    <span class="block font-medium" dir="auto">{{ $courseNavigation['next']['title'] }}</span>
                                </a>
                            @endif
                        </div>
                    </nav>
                @endif

                <section class="space-y-5 border-t pt-7" style="border-color: var(--content-border)" id="discussion">
                    @if (session('annotation-disposition-status'))
                        <flux:callout variant="success">{{ session('annotation-disposition-status') }}</flux:callout>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl {{ $headingClass }}">{{ __('interactions.title') }}</h2>
                            <p class="mt-1 text-sm" style="color: var(--content-muted)">{{ __('interactions.edition_help') }}</p>
                        </div>
                        @if ($canAnnotate)
                            <x-app.annotation-target-menu target-type="revision" :target-uuid="$revision->uuid" :label="__('interactions.anchor.entire_edition')" />
                        @endif
                    </div>

                    @error('interaction')
                        <flux:callout variant="warning">{{ $message }}</flux:callout>
                    @enderror
                    @if (! $canInteract)
                        <flux:callout>{{ __('interactions.verified_only') }}</flux:callout>
                    @endif

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="me-1 text-sm font-medium">{{ __('interactions.reactions') }}</span>
                        @foreach ($reactionTypes as $reactionType)
                            @php
                                $reactionActive = in_array($reactionType, $viewerReactions, true);
                                $reactionCount = $reactionCounts[$reactionType] ?? 0;
                            @endphp
                            <flux:button wire:key="reaction-{{ $reactionType }}" wire:click="toggleReaction('{{ $reactionType }}')" wire:loading.attr="disabled" wire:target="toggleReaction" size="sm" :variant="$reactionActive ? 'primary' : 'ghost'" :disabled="! $canReact">
                                {{ __('interactions.reaction.'.$reactionType) }} · {{ $reactionCount }}
                            </flux:button>
                        @endforeach
                    </div>

                    <details class="rounded-xl border p-4" style="border-color: var(--content-border); background: var(--content-surface)">
                        <summary class="cursor-pointer font-medium">{{ __('interactions.browse_discussion') }} · {{ $annotations->count() }}</summary>
                        <div class="mt-5 space-y-5">
                            @forelse ($annotations as $annotation)
                                <article wire:key="annotation-{{ $annotation->uuid }}" class="space-y-3 rounded-xl border p-4" style="border-color: var(--content-border); background: var(--content-bg)">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-app.actor-identity :actor="$annotation->author" size="xs" />
                                            <flux:badge size="sm">{{ __('interactions.kind.'.$annotation->kind) }}</flux:badge>
                                            @if ($annotation->visibility === 'private')
                                                <flux:badge size="sm">🔒 {{ __('interactions.visibility_value.private') }}</flux:badge>
                                            @endif
                                        </div>
                                        <div class="text-xs" style="color: var(--content-muted)"><x-app.local-datetime :value="$annotation->created_at" /></div>
                                    </div>
                                    @php
                                        $latestDisposition = $annotation->latestDisposition;
                                        $isFeedback = in_array($annotation->kind, ['question', 'correction', 'idea'], true);
                                        $canIncorporate = $activeRevision instanceof \App\Models\SpaceContentRevision
                                            && $activeRevision->revision > $revision->revision
                                            && $activeRevision->hasVerifiableManifest();
                                    @endphp

                                    @if ($latestDisposition)
                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                            <span class="font-medium" style="color: var(--content-muted)">{{ __('interactions.disposition.label') }}</span>
                                            <flux:badge size="sm">{{ __('interactions.disposition.status.'.$latestDisposition->status) }}</flux:badge>
                                            @if ($latestDisposition->incorporatedRevision)
                                                <a
                                                    href="{{ route('contexts.contents.revisions.show', [$context, $content, $latestDisposition->incorporatedRevision]) }}"
                                                    class="font-medium underline underline-offset-4"
                                                >
                                                    {{ __('interactions.disposition.incorporated_revision', ['revision' => $latestDisposition->incorporatedRevision->revision]) }}
                                                </a>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($annotation->body)
                                        <div class="whitespace-pre-wrap break-words text-sm leading-7" dir="auto">{{ $annotation->body }}</div>
                                    @endif
                                    @if ($annotation->kind === 'answer')
                                        @include('livewire.contexts.partials.answer-verdict', ['answer' => $annotation])
                                    @endif

                                    @if ($canResolveFeedback && $isFeedback)
                                        <div class="flex flex-wrap gap-2 rounded-lg border p-2" style="border-color: var(--content-border)">
                                            <span class="self-center text-xs font-medium" style="color: var(--content-muted)">
                                                {{ __('interactions.disposition.maintainer_actions') }}
                                            </span>
                                            @foreach (['reviewed', 'accepted', 'rejected', 'superseded'] as $dispositionStatus)
                                                <flux:button
                                                    wire:click="recordAnnotationDisposition('{{ $annotation->uuid }}', '{{ $dispositionStatus }}')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="recordAnnotationDisposition"
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    {{ __('interactions.disposition.action.'.$dispositionStatus) }}
                                                </flux:button>
                                            @endforeach
                                            @if ($canIncorporate)
                                                <flux:button
                                                    wire:click="recordAnnotationDisposition('{{ $annotation->uuid }}', 'incorporated')"
                                                    wire:loading.attr="disabled"
                                                    wire:target="recordAnnotationDisposition"
                                                    size="sm"
                                                    variant="primary"
                                                >
                                                    {{ __('interactions.disposition.action.incorporated') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    @endif
                                    @foreach ($annotation->assets as $attachment)
                                        @php
                                            $attachmentUrl = route('contexts.contents.assets.show', [$context, $content, $attachment]);
                                            $attachmentDownload = route('contexts.contents.assets.download', [$context, $content, $attachment]);
                                        @endphp
                                        <div class="rounded-lg border p-2" style="border-color: var(--content-border)">
                                            @if ($attachment->processing_status !== 'ready')
                                                <div class="text-sm">{{ __('interactions.attachment_processing') }}</div>
                                            @elseif ($attachment->mediaKind() === 'image')
                                                <img src="{{ $attachmentUrl }}" alt="{{ $attachment->pivot->caption ?: $attachment->original_filename }}" class="max-h-56 rounded object-contain" />
                                            @elseif ($attachment->mediaKind() === 'audio')
                                                <audio controls preload="metadata" class="w-full" src="{{ $attachmentUrl }}"></audio>
                                            @elseif ($attachment->mediaKind() === 'video')
                                                <video controls preload="metadata" class="max-h-64 w-full rounded bg-black" src="{{ $attachmentUrl }}"></video>
                                            @else
                                                <a href="{{ $attachmentDownload }}" class="font-medium" dir="auto">{{ $attachment->original_filename }}</a>
                                            @endif
                                        </div>
                                    @endforeach

                                    @if ($canAnnotate && in_array($annotation->kind === 'question' ? 'answer' : 'reply', $canReplyKinds, true))
                                        <button type="button" data-reply-annotation="{{ $annotation->uuid }}" class="text-sm font-medium underline underline-offset-4" style="color: var(--content-accent)">
                                            {{ $annotation->kind === 'question' ? __('interactions.answer') : __('interactions.reply') }}
                                        </button>
                                    @endif

                                    @if ($annotation->replies->isNotEmpty())
                                        <div class="space-y-3 border-s-2 ps-4" style="border-color: var(--content-border)">
                                            @foreach ($annotation->replies as $reply)
                                                <div wire:key="reply-{{ $reply->uuid }}" class="space-y-2 rounded-lg p-3" style="background: var(--content-surface)">
                                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                                        <div class="flex items-center gap-2">
                                                            <x-app.actor-identity :actor="$reply->author" size="xs" />
                                                            <flux:badge size="sm">{{ __('interactions.kind.'.$reply->kind) }}</flux:badge>
                                                        </div>
                                                        <div class="text-xs" style="color: var(--content-muted)"><x-app.local-datetime :value="$reply->created_at" /></div>
                                                    </div>
                                                    @if ($reply->body)
                                                        <div class="whitespace-pre-wrap break-words text-sm leading-6" dir="auto">{{ $reply->body }}</div>
                                                    @endif
                                                    @php
                                                        $viewerEndorsed = $reply->endorsements->contains('actor_id', auth()->user()?->actor?->id);
                                                    @endphp
                                                    @if ($reply->endorsements->isNotEmpty() || $canEndorse)
                                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                                            @if ($reply->endorsements->isNotEmpty())
                                                                <flux:badge color="green" size="sm" icon="check-badge">{{ __('classroom.roles.endorsed_by', ['names' => $reply->endorsements->map(fn ($endorsement) => $endorsement->actor?->user?->username)->filter()->join(', ')]) }}</flux:badge>
                                                            @endif
                                                            @if ($canEndorse && $canInteract)
                                                                <button type="button" wire:click="toggleEndorsement('{{ $reply->uuid }}')" class="font-medium underline underline-offset-4" style="color: var(--content-accent)">
                                                                    {{ $viewerEndorsed ? __('classroom.roles.withdraw_endorsement') : __('classroom.roles.endorse') }}
                                                                </button>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @empty
                                <div class="text-sm" style="color: var(--content-muted)">{{ __('interactions.no_comments') }}</div>
                            @endforelse
                        </div>
                    </details>
                </section>
            </div>
        </article>

        <aside class="space-y-4 self-start xl:sticky xl:top-6">
            @if ($roleRulesGroup)
                <flux:card class="space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <flux:heading>{{ __('classroom.roles.title') }}</flux:heading>
                            <flux:text class="text-sm">{{ $roleRulesActive ? __('classroom.roles.active_help') : __('classroom.roles.open_help') }}</flux:text>
                        </div>
                        @unless ($editingRoleRules)
                            <flux:button size="sm" variant="ghost" wire:click="openRoleRules">{{ __('classroom.roles.edit') }}</flux:button>
                        @endunless
                    </div>
                    @if ($editingRoleRules)
                        <form wire:submit="saveRoleRules" class="space-y-4">
                            @foreach ($roleRulesRoles as $role)
                                <fieldset wire:key="role-rule-{{ $role->id }}" class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <legend class="px-1 text-sm font-semibold">{{ $role->name }}</legend>
                                    <div class="grid grid-cols-2 gap-1">
                                        @foreach (\App\Models\SpaceContentAnnotation::KINDS as $kind)
                                            <label class="flex items-center gap-2 text-sm">
                                                <input type="checkbox" wire:model="roleRules.{{ $role->id }}.kinds" value="{{ $kind }}" class="rounded border-zinc-300">
                                                {{ __('interactions.kind.'.$kind) }}
                                            </label>
                                        @endforeach
                                    </div>
                                    <label class="flex items-center gap-2 text-sm font-medium">
                                        <input type="checkbox" wire:model="roleRules.{{ $role->id }}.endorse" class="rounded border-zinc-300">
                                        {{ __('classroom.roles.can_endorse') }}
                                    </label>
                                </fieldset>
                            @endforeach
                            <div class="flex flex-wrap gap-2">
                                <flux:button type="submit" size="sm" variant="primary">{{ __('classroom.roles.save') }}</flux:button>
                                <flux:button type="button" size="sm" variant="ghost" wire:click="$set('editingRoleRules', false)">{{ __('classroom.roles.cancel') }}</flux:button>
                                @if ($roleRulesActive)
                                    <flux:button type="button" size="sm" variant="danger" wire:click="clearRoleRules">{{ __('classroom.roles.clear') }}</flux:button>
                                @endif
                            </div>
                        </form>
                    @endif
                </flux:card>
            @endif
            @if ($canAnnotate)
                <flux:card class="space-y-3">
                    <div>
                        <flux:heading>{{ __('interactions.markers_title') }}</flux:heading>
                        <flux:text class="text-sm">{{ __('interactions.markers_help') }}</flux:text>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([
                            'all' => __('interactions.marker_filter.all'),
                            'mine' => __('interactions.marker_filter.mine'),
                            'questions' => __('interactions.marker_filter.questions'),
                            'hidden' => __('interactions.marker_filter.hidden'),
                        ] as $filter => $label)
                            <flux:button wire:click="setMarkerFilter('{{ $filter }}')" size="sm" :variant="$markerFilter === $filter ? 'primary' : 'ghost'">{{ $label }}</flux:button>
                        @endforeach
                    </div>
                </flux:card>
            @endif

            @if (count($outline) > 0)
                <flux:card class="space-y-3">
                    <div>
                        <flux:heading>{{ __('reader.outline') }}</flux:heading>
                        <flux:text class="text-sm">{{ __('reader.outline_help') }}</flux:text>
                    </div>
                    <x-app.context-content-outline-tree :items="$outline" :context="$context" />
                </flux:card>
            @endif

            @if ($canAnnotate && $relationships->isNotEmpty())
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('interactions.annotate_outline') }}</flux:heading>
                    @foreach ($relationships as $relationship)
                        @php $childRevision = $relationship->childContent->activeRevision; @endphp
                        <div class="flex items-center justify-between gap-2 rounded-lg border p-2" style="border-color: var(--content-border)">
                            <span class="min-w-0 truncate text-sm" dir="auto">{{ $childRevision?->title ?? __('reader.outline') }}</span>
                            <x-app.annotation-target-menu target-type="relationship" :target-uuid="$relationship->uuid" :label="$childRevision?->title ?? __('reader.outline')" />
                        </div>
                    @endforeach
                </flux:card>
            @endif
        </aside>
    </div>

    @if ($canAnnotate && count($selectedTargets) > 0)
        <div class="fixed bottom-4 start-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-full border px-3 py-2 shadow-xl" style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)">
            <span class="text-sm font-medium">{{ trans_choice('interactions.selected_count', count($selectedTargets), ['count' => count($selectedTargets)]) }}</span>
            <flux:button wire:click="openSelectionComposer('note', 24, 80)" size="sm" variant="primary">{{ __('interactions.quick.note') }}</flux:button>
            <flux:button wire:click="openSelectionComposer('question', 24, 80)" size="sm" variant="ghost">{{ __('interactions.quick.question') }}</flux:button>
            <flux:button wire:click="clearTargetSelection" size="sm" variant="ghost">{{ __('interactions.clear_selection') }}</flux:button>
        </div>
    @endif

    <div
        id="annotation-selection-toolbar-{{ $content->uuid }}"
        wire:ignore
        class="fixed z-50 hidden rounded-xl border p-1.5 shadow-2xl max-sm:inset-x-3 max-sm:bottom-3 sm:w-max sm:max-w-[min(32rem,calc(100vw-1.5rem))]"
        style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)"
        role="toolbar"
        aria-label="{{ __('interactions.selection_actions') }}"
        data-selection-toolbar
        data-action-map='@json($sectionActionMap)'
    >
        <div class="flex flex-wrap items-center gap-1" data-selection-primary>
            @foreach (array_unique([...array_merge(...array_column($sectionActionMap, 'primary')), ...array_merge(...array_column($sectionActionMap, 'more'))]) as $purpose)
                @continue($purpose === 'advanced')
                <button type="button" data-selection-purpose="{{ $purpose }}" class="hidden rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-black/5 focus:bg-black/5 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10">{{ __('interactions.quick.'.$purpose) }}</button>
            @endforeach
            <button type="button" data-selection-select class="rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-black/5 focus:bg-black/5 focus:outline-none dark:hover:bg-white/10 dark:focus:bg-white/10">{{ __('interactions.quick.add_to_selection') }}</button>
            <button type="button" data-selection-more-toggle aria-expanded="false" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-black/5 focus:outline-none dark:hover:bg-white/10">{{ __('interactions.quick.more') }} <flux:icon name="chevron-down" class="size-3.5 opacity-60" /></button>
        </div>
        <div class="mt-1 hidden flex-wrap gap-1 border-t pt-1" style="border-color: var(--content-border)" data-selection-more>
            <button type="button" data-selection-purpose="advanced" class="rounded-lg px-2.5 py-1.5 text-xs font-medium hover:bg-black/5 focus:outline-none dark:hover:bg-white/10">{{ __('interactions.quick.advanced') }}</button>
        </div>
    </div>

    @if ($canAnnotate)
        {{-- Placed by the reader script next to what it is about and kept inside the screen; a bottom sheet on phones. --}}
        <section
            id="annotation-context-composer-{{ $content->uuid }}"
            class="fixed z-50 w-[24rem] max-w-[calc(100vw-1.5rem)] overflow-y-auto overscroll-contain rounded-2xl border p-4 shadow-2xl max-sm:inset-x-0 max-sm:bottom-0 max-sm:max-h-[85dvh] max-sm:w-auto max-sm:max-w-none max-sm:rounded-b-none max-sm:pb-[max(1rem,env(safe-area-inset-bottom))] {{ $annotationComposerOpen ? '' : 'hidden' }}"
            style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)"
            data-annotation-composer
            data-fallback-x="{{ $annotationComposerX }}"
            data-fallback-y="{{ $annotationComposerY }}"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="font-semibold">{{ __('interactions.quick.'.$annotationComposerMode) }}</div>
                    <div class="mt-1 text-xs" style="color: var(--content-muted)">
                        {{ $annotationVisibility === 'private' ? '🔒 '.__('interactions.visibility_value.private') : __('interactions.visibility_value.space') }}
                    </div>
                </div>
                <button type="button" data-close-composer class="rounded-full px-2 py-1 text-sm focus:outline-none focus:ring-2">×</button>
            </div>

            <div class="mt-3 flex max-h-20 flex-wrap gap-1 overflow-auto">
                @foreach ($annotationAnchors as $index => $anchor)
                    @if ($annotationParentUuid)
                        <span class="rounded-full border px-2 py-1 text-[11px]" style="border-color: var(--content-border)">{{ $anchor['selector']['label'] ?? __('interactions.anchor.'.$anchor['target_type']) }}</span>
                    @else
                        <button type="button" wire:click="removeAnnotationAnchor({{ $index }})" class="rounded-full border px-2 py-1 text-[11px]" style="border-color: var(--content-border)">{{ $anchor['selector']['label'] ?? __('interactions.anchor.'.$anchor['target_type']) }} ×</button>
                    @endif
                @endforeach
            </div>

            @if ($annotationComposerMode === 'advanced')
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <flux:select wire:model="annotationKind" :label="__('interactions.role')" :disabled="$annotationParentUuid !== null">
                        @foreach ($annotationKinds as $kind)<option value="{{ $kind }}">{{ __('interactions.kind.'.$kind) }}</option>@endforeach
                    </flux:select>
                    <flux:select wire:model="annotationVisibility" :label="__('interactions.visibility')" :disabled="$annotationParentUuid !== null">
                        @foreach ($annotationVisibilities as $visibility)<option value="{{ $visibility }}">{{ __('interactions.visibility_value.'.$visibility) }}</option>@endforeach
                    </flux:select>
                </div>
            @elseif (! $annotationParentUuid && $annotationComposerMode !== 'answer')
                <div class="mt-3 flex justify-end">
                    <button type="button" wire:click="$set('annotationVisibility', '{{ $annotationVisibility === 'private' ? 'space' : 'private' }}')" class="text-xs underline underline-offset-4" style="color: var(--content-muted)">
                        {{ $annotationVisibility === 'private' ? __('interactions.make_space_visible') : __('interactions.make_private') }}
                    </button>
                </div>
            @endif

            @if (! in_array($annotationComposerMode, ['file', 'voice'], true))
                <div class="mt-3">
                    <textarea
                        wire:model="annotationBody"
                        rows="{{ in_array($annotationComposerMode, ['remember', 'note', 'translate'], true) ? 2 : 3 }}"
                        maxlength="5000"
                        placeholder="{{ __('interactions.placeholder.'.$annotationComposerMode) }}"
                        class="w-full rounded-xl border bg-transparent px-3 py-2 text-sm focus:outline-none focus:ring-2"
                        style="border-color: var(--content-border)"
                        dir="auto"
                    ></textarea>
                    @error('annotationBody')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                </div>
            @endif

            @if (in_array($annotationComposerMode, ['answer', 'reply'], true) && $annotationMedium === 'text')
                <div class="mt-2 flex gap-2">
                    <button type="button" wire:click="setComposerMedium('file')" class="text-xs underline underline-offset-4">{{ __('interactions.quick.file') }}</button>
                    <button type="button" wire:click="setComposerMedium('voice')" class="text-xs underline underline-offset-4">{{ __('interactions.quick.voice') }}</button>
                </div>
            @endif

            @if ($annotationComposerMode === 'advanced')
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (['text', 'file', 'voice'] as $medium)
                        <flux:button wire:click="setComposerMedium('{{ $medium }}')" size="sm" :variant="$annotationMedium === $medium ? 'primary' : 'ghost'">{{ __('interactions.medium.'.$medium) }}</flux:button>
                    @endforeach
                </div>
            @endif

            <div class="mt-3 {{ in_array($annotationMedium, ['file', 'advanced'], true) || $annotationComposerMode === 'file' ? '' : 'hidden' }}">
                <input type="file" wire:model="annotationUpload" class="block w-full text-sm" />
                @error('annotationUpload')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                @if ($annotationUpload)
                    <div class="mt-2 space-y-2">
                        <flux:select wire:model="annotationRightsStatus" :label="__('media.rights_status')">
                            @foreach ($rightsStatuses as $status)<option value="{{ $status }}">{{ __('media.rights.'.$status) }}</option>@endforeach
                        </flux:select>
                        <flux:input wire:model="annotationCaption" :label="__('media.caption')" maxlength="1000" />
                    </div>
                @endif
            </div>

            <div class="mt-3 {{ in_array($annotationMedium, ['voice', 'advanced'], true) || $annotationComposerMode === 'voice' ? '' : 'hidden' }}">
                <div id="annotation-audio-recorder-{{ $content->uuid }}" wire:ignore class="flex flex-wrap items-center gap-2 rounded-xl border p-3" style="border-color: var(--content-border)">
                    <flux:button id="annotation-audio-start-{{ $content->uuid }}" type="button" size="sm" variant="primary">{{ __('media.start_recording') }}</flux:button>
                    <flux:button id="annotation-audio-stop-{{ $content->uuid }}" type="button" size="sm" variant="danger" class="hidden">{{ __('media.stop_recording') }}</flux:button>
                    <span id="annotation-audio-status-{{ $content->uuid }}" class="hidden text-xs" style="color: var(--content-muted)"></span>
                    <span id="annotation-audio-timer-{{ $content->uuid }}" class="hidden text-xs">00:00</span>
                </div>
                <p id="annotation-audio-error-{{ $content->uuid }}" class="mt-1 hidden text-xs text-red-600"></p>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                @if ($annotationComposerMode !== 'advanced')
                    <button type="button" wire:click="$set('annotationComposerMode', 'advanced')" class="text-xs underline underline-offset-4" style="color: var(--content-muted)">{{ __('interactions.quick.more') }}</button>
                @endif
                <flux:button wire:click="postAnnotation" wire:loading.attr="disabled" wire:target="postAnnotation,annotationUpload" size="sm" variant="primary">
                    {{ in_array($annotationComposerMode, ['answer', 'reply'], true) ? __('interactions.post_'.$annotationComposerMode) : __('interactions.save') }}
                </flux:button>
            </div>
        </section>
    @endif

    @if ($previewAnnotations->isNotEmpty())
        <aside
            id="annotation-preview-panel-{{ $content->uuid }}"
            class="fixed end-4 top-20 z-50 max-h-[70vh] w-[24rem] max-w-[calc(100vw-1.5rem)] overflow-auto overscroll-contain rounded-2xl border p-4 shadow-2xl max-sm:inset-x-0 max-sm:bottom-0 max-sm:top-auto max-sm:max-h-[80dvh] max-sm:w-auto max-sm:max-w-none max-sm:rounded-b-none"
            style="border-color: var(--content-border); background: var(--content-surface); color: var(--content-text)"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="font-semibold">{{ __('interactions.marker_preview') }}</div>
                <flux:button wire:click="closeMarkerPreview" size="sm" variant="ghost">×</flux:button>
            </div>
            <div class="mt-4 space-y-3">
                @foreach ($previewAnnotations as $annotation)
                    <article class="rounded-xl border p-3" style="border-color: var(--content-border)">
                        <div class="flex flex-wrap items-center gap-2 text-xs" style="color: var(--content-muted)">
                            <x-app.actor-identity :actor="$annotation->author" size="xs" />
                            <flux:badge size="sm">{{ __('interactions.kind.'.$annotation->kind) }}</flux:badge>
                            @if ($annotation->visibility === 'private')<span>🔒</span>@endif
                        </div>
                        @if ($annotation->body)<div class="mt-2 whitespace-pre-wrap text-sm" dir="auto">{{ \Illuminate\Support\Str::limit($annotation->body, 320) }}</div>@endif
                        @if ($annotation->kind === 'answer')
                            @include('livewire.contexts.partials.answer-verdict', ['answer' => $annotation])
                        @endif
                        @if ($canAnnotate && $annotation->kind !== 'answer')
                            <button type="button" data-reply-annotation="{{ $annotation->uuid }}" class="mt-2 text-xs font-medium underline underline-offset-4" style="color: var(--content-accent)">{{ $annotation->kind === 'question' ? __('interactions.answer') : __('interactions.reply') }}</button>
                        @endif
                    </article>
                @endforeach
            </div>
        </aside>
    @endif
    @if ($canShareOutside)
        <x-app.drawer name="share" :title="__('public_content.share.title')" width="sm:max-w-lg">
            <livewire:content.share-panel :content="$content" lazy :key="'share-panel-'.$content->uuid" />
        </x-app.drawer>
    @endif
</section>

@if ($canAnnotate)
    @script
    <script>
        (() => {
            const suffix = @js((string) $content->uuid);
            const root = document.getElementById(`content-reader-${suffix}`)
            const selectionToolbar = document.getElementById(`annotation-selection-toolbar-${suffix}`)
            const composer = document.getElementById(`annotation-context-composer-${suffix}`)
            let pendingAnchor = null
            let pendingPosition = { x: 24, y: 80 }
            let observer = null
            let highlightTimer = null

            const codePointLength = value => Array.from(value || '').length
            const cpToUtf16 = (value, offset) => Array.from(value || '').slice(0, offset).join('').length
            const clamp = (value, min, max) => Math.max(min, Math.min(max, value))

            const targetAnchor = element => {
                if (!element) return null
                const type = element.dataset.targetType
                const selector = { label: element.dataset.targetLabel || '' }
                if (type === 'field') return { target_type: 'field', target_uuid: null, field_key: element.dataset.fieldKey || null, selector }
                if (type === 'block') return { target_type: 'block', target_uuid: element.dataset.targetUuid || null, field_key: null, selector }
                if (type === 'asset') return { target_type: 'asset', target_uuid: element.dataset.targetUuid || null, field_key: null, selector }
                if (type === 'relationship') return { target_type: 'relationship', target_uuid: element.dataset.targetUuid || null, field_key: null, selector }
                if (type === 'revision') return { target_type: 'revision', target_uuid: element.dataset.targetUuid || null, field_key: null, selector }
                return null
            }

            const positionNear = rect => {
                if (!rect) return { x: 24, y: 80 }
                const width = 384
                return {
                    x: clamp(Math.round(rect.left), 12, Math.max(12, window.innerWidth - width - 12)),
                    y: clamp(Math.round(rect.bottom + 10), 12, Math.max(12, window.innerHeight - 260)),
                }
            }

            // Floating panels: placed next to what they are about, flipped
            // above when there is no room below, always kept inside the
            // screen. On phones they are bottom sheets (CSS) and not moved.
            const isSheet = () => window.matchMedia('(max-width: 639px)').matches
            const rectOf = source => {
                if (!source) return null
                if (typeof source.getBoundingClientRect !== 'function') return null
                if (source instanceof Element && !source.isConnected) return null
                const rect = source.getBoundingClientRect()
                return rect.width === 0 && rect.height === 0 && rect.top === 0 ? null : rect
            }
            const place = (el, rect, gap = 8) => {
                if (!el || el.classList.contains('hidden')) return
                if (isSheet()) {
                    if (el.style.left || el.style.top) { el.style.left = ''; el.style.top = ''; el.style.maxHeight = '' }
                    return
                }
                if (!rect) return
                const margin = 12
                const vw = document.documentElement.clientWidth
                const vh = window.innerHeight
                const maxHeight = `${vh - margin * 2}px`
                if (el.style.maxHeight !== maxHeight) el.style.maxHeight = maxHeight
                const width = el.offsetWidth
                const height = el.offsetHeight
                const rtl = getComputedStyle(el).direction === 'rtl'
                const left = clamp(rtl ? rect.right - width : rect.left, margin, Math.max(margin, vw - width - margin))
                let top = rect.bottom + gap
                if (top + height > vh - margin) {
                    const above = rect.top - gap - height
                    top = above >= margin ? above : Math.max(margin, vh - height - margin)
                }
                const x = `${Math.round(left)}px`
                const y = `${Math.round(top)}px`
                if (el.style.left !== x) el.style.left = x
                if (el.style.top !== y) el.style.top = y
            }

            let composerSource = null
            const placeComposer = () => {
                if (!composer || composer.classList.contains('hidden')) return
                const rect = rectOf(composerSource)
                    ?? new DOMRect(Number(composer.dataset.fallbackX || 24), Number(composer.dataset.fallbackY || 80) - 10, 0, 0)
                place(composer, rect, 10)
            }
            const openMenus = () => Array.from(document.querySelectorAll('details[data-context-menu][open]'))
            const placeMenu = details => {
                const panel = details.querySelector(':scope > [data-floating-panel]')
                place(panel, rectOf(details.querySelector(':scope > summary')), 6)
            }
            const closeMenus = (except = null) => openMenus().forEach(menu => { if (menu !== except) menu.removeAttribute('open') })

            // Which actions the selection toolbar offers depends on the role
            // of the section the text was selected in (see SectionSemantics).
            const actionMap = (() => { try { return JSON.parse(selectionToolbar?.dataset.actionMap || '{}') } catch (_) { return {} } })()
            const configureToolbar = role => {
                if (!selectionToolbar) return
                const actions = actionMap[role] ?? actionMap[''] ?? { primary: [], more: [] }
                const primaryBox = selectionToolbar.querySelector('[data-selection-primary]')
                const moreBox = selectionToolbar.querySelector('[data-selection-more]')
                const selectButton = primaryBox.querySelector('[data-selection-select]')
                const buttons = Array.from(selectionToolbar.querySelectorAll('[data-selection-purpose]'))
                buttons.forEach(button => button.classList.add('hidden'))
                actions.primary.forEach(purpose => {
                    const button = buttons.find(item => item.dataset.selectionPurpose === purpose)
                    if (button) { button.classList.remove('hidden'); primaryBox.insertBefore(button, selectButton) }
                })
                ;[...actions.more.filter(purpose => purpose !== 'advanced'), 'advanced'].forEach(purpose => {
                    const button = buttons.find(item => item.dataset.selectionPurpose === purpose)
                    if (button) { button.classList.remove('hidden'); moreBox.appendChild(button) }
                })
                const first = primaryBox.querySelector('[data-selection-purpose]:not(.hidden)')
                buttons.forEach(button => button.classList.toggle('font-semibold', role !== '' && button === first))
                moreBox.classList.add('hidden')
                moreBox.classList.remove('flex')
                selectionToolbar.querySelector('[data-selection-more-toggle]')?.setAttribute('aria-expanded', 'false')
            }
            let selectionRange = null

            const hideSelectionToolbar = () => {
                selectionToolbar?.classList.add('hidden')
                pendingAnchor = null
            }

            const segmentForNode = node => {
                const element = node?.nodeType === Node.ELEMENT_NODE ? node : node?.parentElement
                return element?.closest?.('[data-annotation-text]') ?? null
            }

            const offsetWithin = (segment, container, offset) => {
                const range = document.createRange()
                range.selectNodeContents(segment)
                try {
                    range.setEnd(container, offset)
                } catch (_) {
                    return null
                }
                return codePointLength(range.toString())
            }

            const captureSelection = () => {
                if (!selectionToolbar || !root) return
                const selection = window.getSelection()
                if (!selection || selection.isCollapsed || !selection.rangeCount) {
                    hideSelectionToolbar()
                    return
                }

                const exact = selection.toString()
                if (!exact.trim() || codePointLength(exact) > 2000) {
                    hideSelectionToolbar()
                    return
                }

                const range = selection.getRangeAt(0)
                const startSegment = segmentForNode(range.startContainer)
                const endSegment = segmentForNode(range.endContainer)
                if (!startSegment || !endSegment || !root.contains(startSegment) || !root.contains(endSegment)) {
                    hideSelectionToolbar()
                    return
                }

                const startTarget = startSegment.dataset.anchorTarget
                const endTarget = endSegment.dataset.anchorTarget
                const startUuid = startSegment.dataset.targetUuid || ''
                const endUuid = endSegment.dataset.targetUuid || ''
                const startField = startSegment.dataset.fieldKey || ''
                const endField = endSegment.dataset.fieldKey || ''
                if (startTarget !== endTarget || startUuid !== endUuid || startField !== endField) {
                    hideSelectionToolbar()
                    return
                }

                const startLocal = offsetWithin(startSegment, range.startContainer, range.startOffset)
                const endLocal = offsetWithin(endSegment, range.endContainer, range.endOffset)
                if (startLocal === null || endLocal === null) {
                    hideSelectionToolbar()
                    return
                }

                const start = Number(startSegment.dataset.anchorBase || 0) + startLocal
                const end = Number(endSegment.dataset.anchorBase || 0) + endLocal
                if (end <= start) {
                    hideSelectionToolbar()
                    return
                }

                const selector = { exact, start, end, label: exact.trim().slice(0, 80) }
                pendingAnchor = startTarget === 'field'
                    ? { target_type: 'text', target_uuid: null, field_key: startField, selector }
                    : { target_type: 'block', target_uuid: startUuid, field_key: null, selector }

                const rect = range.getBoundingClientRect()
                pendingPosition = positionNear(rect)
                selectionRange = range.cloneRange()
                const role = startSegment.closest('[data-section-role]')?.dataset.sectionRole || ''
                if (selectionToolbar.dataset.role !== role || selectionToolbar.classList.contains('hidden')) {
                    selectionToolbar.dataset.role = role
                    configureToolbar(role)
                }
                selectionToolbar.classList.remove('hidden')
                place(selectionToolbar, rect, 10)
            }

            const openComposer = (purpose, anchor, position = pendingPosition, source = selectionRange) => {
                if (!anchor) return
                composerSource = source
                closeMenus()
                hideSelectionToolbar()
                window.getSelection()?.removeAllRanges()
                $wire.call('openContextComposer', purpose, anchor, position.x, position.y)
            }

            const addToSelection = anchor => {
                if (!anchor) return
                hideSelectionToolbar()
                window.getSelection()?.removeAllRanges()
                $wire.call('addTargetToSelection', anchor)
            }

            const parseMarkerPayload = () => {
                const payload = document.getElementById(`annotation-marker-payload-${suffix}`)?.dataset.markerPayload
                if (!payload) return []
                try { return JSON.parse(payload) } catch (_) { return [] }
            }

            const textNodes = element => {
                const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT)
                const nodes = []
                let node
                while ((node = walker.nextNode())) nodes.push(node)
                return nodes
            }

            const pointAt = (element, cpOffset) => {
                let remaining = cpOffset
                for (const node of textNodes(element)) {
                    const length = codePointLength(node.data)
                    if (remaining <= length) return { node, offset: cpToUtf16(node.data, remaining) }
                    remaining -= length
                }
                return null
            }

            const unwrapHighlights = () => {
                root?.querySelectorAll('mark[data-content-annotation-mark]').forEach(mark => {
                    mark.replaceWith(document.createTextNode(mark.textContent || ''))
                    mark.parentNode?.normalize?.()
                })
            }

            const applyHighlights = () => {
                if (!root) return
                observer?.disconnect()
                unwrapHighlights()

                const markers = parseMarkerPayload().sort((a, b) => Number(b.start || 0) - Number(a.start || 0))
                for (const marker of markers) {
                    const selector = marker.target_type === 'text'
                        ? `[data-annotation-text][data-anchor-target="field"][data-field-key="${CSS.escape(marker.field_key || '')}"]`
                        : `[data-annotation-text][data-anchor-target="block"][data-target-uuid="${CSS.escape(marker.target_uuid || '')}"]`
                    const segments = Array.from(root.querySelectorAll(selector))
                    for (const segment of segments) {
                        const base = Number(segment.dataset.anchorBase || 0)
                        const length = codePointLength(segment.textContent || '')
                        const localStart = Math.max(0, Number(marker.start) - base)
                        const localEnd = Math.min(length, Number(marker.end) - base)
                        if (localEnd <= localStart || localStart >= length || localEnd <= 0) continue

                        const startPoint = pointAt(segment, localStart)
                        const endPoint = pointAt(segment, localEnd)
                        if (!startPoint || !endPoint) continue

                        const range = document.createRange()
                        range.setStart(startPoint.node, startPoint.offset)
                        range.setEnd(endPoint.node, endPoint.offset)
                        const mark = document.createElement('mark')
                        mark.dataset.contentAnnotationMark = '1'
                        mark.dataset.annotationUuids = JSON.stringify(marker.annotation_uuids || [])
                        mark.tabIndex = 0
                        mark.setAttribute('role', 'button')
                        mark.setAttribute('aria-label', @js(__('interactions.open_annotations')))
                        mark.style.background = marker.private
                            ? 'color-mix(in srgb, var(--content-accent) 14%, transparent)'
                            : 'color-mix(in srgb, var(--content-accent) 24%, transparent)'
                        mark.style.borderBottom = marker.question
                            ? '2px dotted var(--content-accent)'
                            : '2px solid color-mix(in srgb, var(--content-accent) 55%, transparent)'
                        mark.style.color = 'inherit'
                        mark.style.cursor = 'pointer'
                        try { range.surroundContents(mark) } catch (_) { /* keep reading surface intact */ }
                    }
                }

                observer?.observe(root, { childList: true, subtree: true })
            }

            if (root?.dataset.contextualAnnotationsReady !== '1') {
                root.dataset.contextualAnnotationsReady = '1'

                const scheduleSelection = () => window.setTimeout(captureSelection, 30)
                document.addEventListener('pointerup', scheduleSelection)
                document.addEventListener('keyup', event => {
                    if (event.key === 'Shift' || event.shiftKey) scheduleSelection()
                })
                document.addEventListener('selectionchange', () => window.setTimeout(captureSelection, 80))

                document.addEventListener('click', event => {
                    const marker = event.target.closest?.('[data-marker-preview], mark[data-content-annotation-mark]')
                    if (marker) {
                        let uuids = []
                        try { uuids = JSON.parse(marker.dataset.markerPreview || marker.dataset.annotationUuids || '[]') } catch (_) {}
                        if (Array.isArray(uuids) && uuids.length) $wire.call('openMarkerPreview', uuids)
                        return
                    }

                    const selectionPurpose = event.target.closest?.('[data-selection-purpose]')
                    if (selectionPurpose && pendingAnchor) {
                        openComposer(selectionPurpose.dataset.selectionPurpose, pendingAnchor)
                        return
                    }
                    if (event.target.closest?.('[data-selection-select]') && pendingAnchor) {
                        addToSelection(pendingAnchor)
                        return
                    }

                    if (event.target.closest?.('[data-selection-more-toggle]')) {
                        const moreBox = selectionToolbar?.querySelector('[data-selection-more]')
                        const expanded = moreBox?.classList.contains('hidden')
                        moreBox?.classList.toggle('hidden', !expanded)
                        moreBox?.classList.toggle('flex', expanded)
                        event.target.closest('[data-selection-more-toggle]').setAttribute('aria-expanded', expanded ? 'true' : 'false')
                        if (selectionRange) place(selectionToolbar, selectionRange.getBoundingClientRect(), 10)
                        return
                    }

                    const purposeButton = event.target.closest?.('[data-context-purpose]')
                    if (purposeButton) {
                        const menu = purposeButton.closest('[data-annotation-target-menu]')
                        const anchor = targetAnchor(menu)
                        const source = menu?.closest('[data-annotation-target]') ?? menu ?? purposeButton
                        openComposer(purposeButton.dataset.contextPurpose, anchor, positionNear(source.getBoundingClientRect()), source)
                        menu?.querySelectorAll('details[open]').forEach(details => details.removeAttribute('open'))
                        return
                    }
                    const selectButton = event.target.closest?.('[data-context-select]')
                    if (selectButton) {
                        const menu = selectButton.closest('[data-annotation-target-menu]')
                        addToSelection(targetAnchor(menu))
                        menu?.querySelectorAll('details[open]').forEach(details => details.removeAttribute('open'))
                        return
                    }

                    const reply = event.target.closest?.('[data-reply-annotation]')
                    if (reply) {
                        const position = positionNear(reply.getBoundingClientRect())
                        composerSource = reply.closest('article') ?? reply
                        $wire.call('openReplyComposer', reply.dataset.replyAnnotation, position.x, position.y)
                    }
                })

                document.addEventListener('keydown', event => {
                    const marker = event.target.closest?.('mark[data-content-annotation-mark]')
                    if (marker && (event.key === 'Enter' || event.key === ' ')) {
                        event.preventDefault()
                        let uuids = []
                        try { uuids = JSON.parse(marker.dataset.annotationUuids || '[]') } catch (_) {}
                        if (uuids.length) $wire.call('openMarkerPreview', uuids)
                        return
                    }
                    if (event.key === 'Escape') {
                        if (openMenus().length) { closeMenus(); return }
                        hideSelectionToolbar()
                        if (composer && !composer.classList.contains('hidden')) {
                            const body = composer.querySelector('textarea')?.value?.trim() || ''
                            const file = composer.querySelector('input[type="file"]')?.files?.length || 0
                            if (!body && !file) $wire.call('clearAnnotationComposer')
                            else if (window.confirm(@js(__('interactions.discard_draft_confirm')))) $wire.call('clearAnnotationComposer')
                        }
                    }
                })

                // Menus: one open at a time, placed on open, closed by an outside tap.
                document.addEventListener('toggle', event => {
                    const details = event.target
                    if (!(details instanceof HTMLDetailsElement)) return
                    if (details.matches('[data-context-menu]') && details.open) {
                        closeMenus(details)
                        hideSelectionToolbar()
                        placeMenu(details)
                    } else if (details.matches('[data-menu-more]')) {
                        const menu = details.closest('details[data-context-menu]')
                        if (menu) placeMenu(menu)
                    }
                }, true)
                document.addEventListener('pointerdown', event => {
                    if (!event.target.closest?.('details[data-context-menu]')) closeMenus()
                })
                const reflow = () => {
                    openMenus().forEach(placeMenu)
                    placeComposer()
                    if (selectionRange && selectionToolbar && !selectionToolbar.classList.contains('hidden')) place(selectionToolbar, selectionRange.getBoundingClientRect(), 10)
                }
                // Keep the text selection while tapping the toolbar.
                selectionToolbar?.addEventListener('mousedown', event => event.preventDefault())
                window.addEventListener('resize', reflow, { passive: true })
                window.addEventListener('scroll', () => window.requestAnimationFrame(reflow), { passive: true, capture: true })
                if (composer) {
                    // Livewire re-renders the composer (mode, files, errors): keep it placed.
                    new ResizeObserver(() => placeComposer()).observe(composer)
                    new MutationObserver(() => window.requestAnimationFrame(placeComposer)).observe(composer, { attributes: true, attributeFilter: ['class', 'style'] })
                }

                document.addEventListener('pointerdown', event => {
                    if (!composer || composer.classList.contains('hidden')) return
                    if (composer.contains(event.target) || event.target.closest?.('[data-context-menu], #annotation-selection-toolbar-'+suffix)) return
                    const body = composer.querySelector('textarea')?.value?.trim() || ''
                    const file = composer.querySelector('input[type="file"]')?.files?.length || 0
                    if (!body && !file) $wire.call('clearAnnotationComposer')
                })

                document.querySelector('[data-close-composer]')?.addEventListener('click', () => {
                    const body = composer?.querySelector('textarea')?.value?.trim() || ''
                    const file = composer?.querySelector('input[type="file"]')?.files?.length || 0
                    if (!body && !file) $wire.call('clearAnnotationComposer')
                    else if (window.confirm(@js(__('interactions.discard_draft_confirm')))) $wire.call('clearAnnotationComposer')
                })
            }

            const outsideText = '[data-selection-toolbar], [data-annotation-composer], [data-floating-panel], [data-context-menu], #annotation-preview-panel-' + suffix
            observer = new MutationObserver(mutations => {
                // Menus and the composer changing must not re-draw highlights
                // (that would drop the reader's current text selection).
                const relevant = mutations.some(mutation => {
                    const element = mutation.target.nodeType === Node.ELEMENT_NODE ? mutation.target : mutation.target.parentElement
                    return !element?.closest?.(outsideText)
                })
                if (!relevant) return
                clearTimeout(highlightTimer)
                highlightTimer = setTimeout(applyHighlights, 25)
            })
            observer.observe(root, { childList: true, subtree: true })
            applyHighlights()

            const recorderRoot = document.getElementById(`annotation-audio-recorder-${suffix}`)
            if (!recorderRoot || recorderRoot.dataset.recorderReady === '1') return
            recorderRoot.dataset.recorderReady = '1'

            const start = document.getElementById(`annotation-audio-start-${suffix}`)
            const stop = document.getElementById(`annotation-audio-stop-${suffix}`)
            const status = document.getElementById(`annotation-audio-status-${suffix}`)
            const timerLabel = document.getElementById(`annotation-audio-timer-${suffix}`)
            const errorLabel = document.getElementById(`annotation-audio-error-${suffix}`)
            let recorder = null
            let stream = null
            let chunks = []
            let elapsed = 0
            let timer = null

            const show = (element, visible) => element?.classList.toggle('hidden', !visible)
            const setError = message => {
                if (!errorLabel) return
                errorLabel.textContent = message || ''
                show(errorLabel, Boolean(message))
            }
            const updateTimer = () => {
                if (!timerLabel) return
                timerLabel.textContent = `${Math.floor(elapsed / 60).toString().padStart(2, '0')}:${(elapsed % 60).toString().padStart(2, '0')}`
            }
            const cleanup = () => {
                clearInterval(timer)
                timer = null
                stream?.getTracks().forEach(track => track.stop())
                stream = null
            }

            start?.addEventListener('click', async () => {
                setError('')
                if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
                    setError(@js(__('media.recorder_unavailable')))
                    return
                }
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: true })
                    const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4']
                    const mimeType = candidates.find(type => MediaRecorder.isTypeSupported(type)) ?? ''
                    recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined)
                    chunks = []
                    elapsed = 0
                    updateTimer()
                    recorder.addEventListener('dataavailable', event => { if (event.data.size > 0) chunks.push(event.data) })
                    recorder.addEventListener('stop', () => {
                        const type = recorder?.mimeType || 'audio/webm'
                        const extension = type.includes('mp4') ? 'm4a' : (type.includes('ogg') ? 'ogg' : 'webm')
                        const blob = new Blob(chunks, { type })
                        cleanup()
                        recorder = null
                        chunks = []
                        if (!blob.size) {
                            setError(@js(__('media.recording_error')))
                            show(start, true); show(stop, false); show(status, false); show(timerLabel, false)
                            return
                        }
                        const file = new File([blob], `voice-note-${Date.now()}.${extension}`, { type })
                        if (status) {
                            status.textContent = @js(__('interactions.uploading_voice_note'));
                        }
                        show(status, true); show(timerLabel, false); show(stop, false)
                        $wire.upload('annotationUpload', file, () => {
                            $wire.call('markAnnotationRecordingReady').then(() => {
                                if (status) {
                                    status.textContent = @js(__('interactions.voice_note_ready'));
                                }
                                show(start, true)
                            })
                        }, () => {
                            setError(@js(__('media.recording_error')))
                            show(start, true); show(status, false)
                        })
                    }, { once: true })
                    recorder.start()
                    show(start, false); show(stop, true); show(status, true); show(timerLabel, true)
                    if (status) {
                        status.textContent = @js(__('media.recording'));
                    }
                    timer = setInterval(() => { elapsed++; updateTimer() }, 1000)
                } catch (_) {
                    cleanup()
                    recorder = null
                    setError(@js(__('media.recording_error')))
                    show(start, true); show(stop, false); show(status, false); show(timerLabel, false)
                }
            })

            stop?.addEventListener('click', () => {
                if (!recorder || recorder.state === 'inactive') return
                clearInterval(timer)
                recorder.stop()
            })
        })()
    </script>
    @endscript
@endif
