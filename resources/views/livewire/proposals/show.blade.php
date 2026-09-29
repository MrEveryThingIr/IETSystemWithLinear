<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="$proposal->title" :description="__('proposals.show.help')">
        <x-slot:actions>
            <flux:badge>{{ __('proposals.status.'.$proposal->status->value) }}</flux:badge>
        </x-slot:actions>
    </x-app.page-header>

    @if ($proposal->status->value === 'accepted')
        @php($derivedContract = $currentVersion->derivedContract)
        <flux:callout>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>{{ __('proposals.show.no_contract') }}</span>
                @if ($derivedContract)
                    <flux:button :href="route('contracts.show', $derivedContract)" variant="primary">
                        {{ __('contracts.actions.open_contract') }}
                    </flux:button>
                @else
                    <flux:button :href="route('contracts.create', ['proposal' => $proposal->uuid])" variant="primary">
                        {{ __('contracts.actions.create_from_proposal') }}
                    </flux:button>
                @endif
            </div>
        </flux:callout>
    @else
        <flux:callout>{{ __('proposals.show.no_contract') }}</flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <flux:heading size="lg">
                            {{ __('proposals.show.current_version', ['version' => $currentVersion->version]) }}
                        </flux:heading>
                        <flux:text>
                            {{ __('proposals.show.proposed_by', [
                                'username' => $currentVersion->proposedBy->user?->username ?? __('relationships.unknown_actor'),
                            ]) }}
                        </flux:text>
                    </div>
                    <flux:badge color="zinc">
                        {{ __('proposals.show.sealed_terms') }}
                    </flux:badge>
                </div>

                @if (($currentVersion->termsRevision->payload['summary'] ?? '') !== '')
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                            {{ __('proposals.create.summary') }}
                        </div>
                        <div class="mt-2 whitespace-pre-wrap" dir="auto">{{ $currentVersion->termsRevision->payload['summary'] }}</div>
                    </div>
                @endif

                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                        {{ __('proposals.create.terms') }}
                    </div>
                    <div class="mt-2 whitespace-pre-wrap rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950" dir="auto">{{ $currentVersion->termsRevision->payload['terms'] ?? '' }}</div>
                </div>

                @if (($currentVersion->termsRevision->payload['notes'] ?? '') !== '')
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                            {{ __('proposals.create.notes') }}
                        </div>
                        <div class="mt-2 whitespace-pre-wrap" dir="auto">{{ $currentVersion->termsRevision->payload['notes'] }}</div>
                    </div>
                @endif

                <div class="flex flex-wrap gap-2 text-xs text-zinc-500">
                    <span>REV-{{ str_pad((string) $currentVersion->termsRevision->id, 6, '0', STR_PAD_LEFT) }}</span>
                    <span>·</span>
                    <span>{{ $currentVersion->termsRevision->manifest_hash }}</span>
                    @if ($context)
                        <span>·</span>
                        <a
                            href="{{ route('contexts.contents.show', [$context, $currentVersion->termsRevision->content]) }}"
                            class="font-medium hover:underline"
                        >
                            {{ __('proposals.show.open_terms') }}
                        </a>
                    @endif
                </div>
            </flux:card>

            @if ($canRespond)
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('proposals.show.respond') }}</flux:heading>
                        <flux:text>{{ __('proposals.show.respond_help') }}</flux:text>
                    </div>

                    <flux:textarea
                        wire:model="responseNote"
                        :label="__('proposals.show.response_note')"
                        rows="3"
                    />

                    <div class="flex flex-wrap gap-2">
                        <flux:button wire:click="accept" variant="primary">
                            {{ __('proposals.actions.accept') }}
                        </flux:button>
                        <flux:button wire:click="requestChanges" variant="ghost">
                            {{ __('proposals.actions.request_changes') }}
                        </flux:button>
                        <flux:button wire:click="reject" variant="danger">
                            {{ __('proposals.actions.reject') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endif

            @if ($canParticipate)
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading size="lg">{{ __('proposals.show.propose_revision') }}</flux:heading>
                        <flux:text>{{ __('proposals.show.propose_revision_help') }}</flux:text>
                    </div>

                    <form wire:submit="proposeVersion" class="space-y-4">
                        <flux:input wire:model="revisionTitle" :label="__('proposals.create.proposal_title')" maxlength="255" />
                        <flux:textarea wire:model="revisionSummary" :label="__('proposals.create.summary')" rows="3" />
                        <flux:textarea wire:model="revisionTerms" :label="__('proposals.create.terms')" rows="10" />
                        <flux:textarea wire:model="revisionNotes" :label="__('proposals.create.notes')" rows="3" />
                        <flux:input wire:model="versionNote" :label="__('proposals.show.version_note')" maxlength="1000" />

                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary">
                                {{ __('proposals.actions.propose_version') }}
                            </flux:button>
                        </div>
                    </form>
                </flux:card>
            @endif

            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('proposals.show.version_history') }}</flux:heading>

                <div class="space-y-4">
                    @foreach ($proposal->versions->sortByDesc('version') as $version)
                        <article wire:key="proposal-version-{{ $version->uuid }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="font-semibold">
                                    {{ __('proposals.show.version_label', ['version' => $version->version]) }}
                                </div>
                                <div class="text-xs text-zinc-500">
                                    @if ($version->proposed_at)<x-app.local-datetime :value="$version->proposed_at" />@endif
                                </div>
                            </div>

                            @if ($version->note)
                                <div class="mt-2 text-sm" dir="auto">{{ $version->note }}</div>
                            @endif

                            <div class="mt-3 space-y-2">
                                @foreach ($proposal->parties as $proposalParty)
                                    @php($decision = $version->decisions->firstWhere('proposal_party_id', $proposalParty->id))
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span>
                                            {{ $proposalParty->actor->user?->username ?? __('relationships.unknown_actor') }}
                                            · {{ $proposalParty->role }}
                                        </span>
                                        @if ($decision)
                                            <flux:badge color="zinc">
                                                {{ __('proposals.decision.'.$decision->decision->value) }}
                                            </flux:badge>
                                        @else
                                            <flux:badge color="zinc">{{ __('proposals.decision.pending') }}</flux:badge>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading>{{ __('proposals.show.parties') }}</flux:heading>
                @foreach ($proposal->parties as $proposalParty)
                    <div wire:key="proposal-party-{{ $proposalParty->uuid }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <x-app.actor-identity :actor="$proposalParty->actor" size="sm" />
                        <div class="mt-2 text-xs text-zinc-500">
                            {{ $proposalParty->role }}
                            @if ($proposalParty->required)
                                · {{ __('proposals.show.required_party') }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </flux:card>

            @if ($context)
                <flux:card class="space-y-3">
                    <flux:heading>{{ __('proposals.show.negotiation_workspace') }}</flux:heading>
                    <flux:text>{{ __('proposals.show.workspace_help') }}</flux:text>
                    <div class="grid gap-2">
                        <flux:button :href="route('contexts.conversation', $context)" variant="primary">
                            {{ __('collaboration.tabs.conversation') }}
                        </flux:button>
                        <flux:button :href="route('contexts.timeline', $context)" variant="ghost">
                            {{ __('collaboration.tabs.timeline') }}
                        </flux:button>
                        <flux:button :href="route('contexts.contents.index', $context)" variant="ghost">
                            {{ __('collaboration.tabs.content') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endif

            @if ($proposal->relationship)
                <flux:card class="space-y-2">
                    <flux:heading>{{ __('proposals.source_relationship') }}</flux:heading>
                    <flux:button :href="route('relationships.show', $proposal->relationship)" variant="ghost" class="w-full">
                        {{ $proposal->relationship->title ?: 'REL-'.str_pad((string) $proposal->relationship->id, 6, '0', STR_PAD_LEFT) }}
                    </flux:button>
                </flux:card>
            @endif

            @if ($canCancel)
                <flux:card class="space-y-3">
                    <flux:text>{{ __('proposals.show.cancel_help') }}</flux:text>
                    <flux:button wire:click="cancel" variant="danger" class="w-full">
                        {{ __('proposals.actions.cancel') }}
                    </flux:button>
                </flux:card>
            @endif
        </div>
    </div>

    <flux:callout>{{ __('proposals.show.conversation_boundary') }}</flux:callout>
</section>
