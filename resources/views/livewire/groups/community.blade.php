<section class="mx-auto max-w-7xl space-y-6">
    <x-app.page-header :title="$group->name" :description="__('community.help')">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('groups.show', $group)" variant="ghost">{{ __('community.governance') }}</flux:button>
                <flux:button :href="route('groups.index')" variant="ghost">{{ __('ui.common.all_groups') }}</flux:button>
            </div>
        </x-slot:actions>
    </x-app.page-header>

    <x-app.group-space-tabs :group="$group" community />

    <flux:callout>{{ __('community.boundary') }}</flux:callout>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($spaces as $space)
            @php($context = $space->contextBinding?->context)
            <flux:card class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg"># {{ $space->name }}</flux:heading>
                        <flux:text>{{ __('community.space_'.$space->access_mode) }}</flux:text>
                    </div>
                    @if ($space->is_default)
                        <flux:badge>{{ __('community.default_space') }}</flux:badge>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button :href="route('groups.spaces.show', [$group, $space])" size="sm" variant="ghost">
                        {{ __('community.conversation') }}
                    </flux:button>
                    <flux:button :href="route('groups.spaces.contents.index', [$group, $space])" size="sm" variant="ghost">
                        {{ __('community.content') }}
                    </flux:button>
                    @if ($context)
                        <flux:button :href="route('planner.index', ['context' => $context->uuid])" size="sm" variant="ghost">
                            {{ __('community.activities') }}
                        </flux:button>
                        <flux:button :href="route('contexts.timeline', $context)" size="sm" variant="ghost">
                            {{ __('community.timeline') }}
                        </flux:button>
                        @can('reviewInteractions', $context)
                            <flux:button :href="route('contexts.submissions.index', $context)" size="sm" variant="ghost">
                                {{ __('community.submissions') }}
                            </flux:button>
                        @endcan
                    @endif
                </div>
            </flux:card>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-app.empty-state :title="__('community.no_spaces')" :description="__('community.no_spaces_help')" />
            </div>
        @endforelse
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('community.people') }}</flux:heading>
                <flux:text>{{ __('community.people_help') }}</flux:text>
            </div>

            <div class="space-y-3">
                @foreach ($memberships as $membership)
                    <div class="flex flex-col gap-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                        <x-app.actor-identity :actor="$membership->actor" size="sm" />
                        <flux:badge>{{ $membershipRoles->get($membership->id) ?: __('community.member') }}</flux:badge>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('community.needs_offers') }}</flux:heading>
                <flux:text>{{ __('community.needs_offers_help') }}</flux:text>
            </div>

            <div class="space-y-3">
                @forelse ($intents as $intent)
                    <article class="rounded-xl border border-zinc-200 p-3 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge :color="$intent->kind->value === 'need' ? 'amber' : 'green'">
                                {{ __('intents.kinds.'.$intent->kind->value) }}
                            </flux:badge>
                            <flux:badge color="zinc">{{ $intent->concept->displayLabel() }}</flux:badge>
                        </div>
                        <div class="mt-2 font-medium" dir="auto">{{ $intent->title ?: $intent->concept->displayLabel() }}</div>
                        <div class="mt-2 text-sm text-zinc-500">
                            @can('view', $intent->profile)
                                <a href="{{ route('profiles.show', $intent->profile) }}" class="hover:underline">
                                    {{ $intent->profile->display_name ?: $intent->profile->actor->user?->username }}
                                </a>
                            @else
                                {{ __('community.visible_intent_private_profile') }}
                            @endcan
                        </div>
                    </article>
                @empty
                    <x-app.empty-state :title="__('community.no_intents')" />
                @endforelse
            </div>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('community.content_title') }}</flux:heading>
            <flux:text>{{ __('community.content_help') }}</flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($contents as $content)
                @php($revision = $content->activeRevision)
                <article class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex flex-wrap gap-2">
                        @if ($content->space)
                            <flux:badge color="zinc"># {{ $content->space->name }}</flux:badge>
                        @endif
                        @if ($content->blueprintVersion?->blueprint)
                            <flux:badge>{{ $content->blueprintVersion->blueprint->name }}</flux:badge>
                        @endif
                    </div>
                    <a
                        href="{{ route('contexts.contents.show', [$content->context, $content]) }}"
                        class="mt-3 block font-semibold hover:underline"
                        dir="auto"
                    >
                        {{ $revision?->title ?: __('community.untitled_content') }}
                    </a>
                </article>
            @empty
                <div class="md:col-span-2 xl:col-span-3">
                    <x-app.empty-state :title="__('community.no_content')" />
                </div>
            @endforelse
        </div>
    </flux:card>

    <div class="grid gap-6 xl:grid-cols-2">
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('community.plans') }}</flux:heading>
                <flux:text>{{ __('community.plans_help') }}</flux:text>
            </div>
            <div class="space-y-3">
                @forelse ($plans as $plan)
                    <a href="{{ route('planner.show', $plan) }}" class="block rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                        <div class="font-medium" dir="auto">{{ $plan->title }}</div>
                        <div class="mt-1 text-xs text-zinc-500">{{ __('planner.status.'.$plan->status->value) }}</div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('community.no_plans')" />
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('community.relationships') }}</flux:heading>
                <flux:text>{{ __('community.relationships_help') }}</flux:text>
            </div>
            <div class="space-y-3">
                @forelse ($relationships as $relationship)
                    <a href="{{ route('relationships.show', $relationship) }}" class="block rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge>{{ __('ui.status.'.$relationship->status->value) }}</flux:badge>
                            <span class="font-medium" dir="auto">{{ $relationship->title ?: $relationship->purposeConcept->displayLabel() }}</span>
                        </div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('community.no_relationships')" />
                @endforelse
            </div>
        </flux:card>
    </div>

    @if ($submissions->isNotEmpty())
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('community.review_queue') }}</flux:heading>
                <flux:text>{{ __('community.review_queue_help') }}</flux:text>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($submissions as $submission)
                    <a
                        href="{{ route('contexts.submissions.show', [$submission->context, $submission]) }}"
                        class="rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                    >
                        <div class="font-medium" dir="auto">
                            {{ $submission->definitionVersion->definition->name }}
                        </div>
                        <div class="mt-1 text-sm text-zinc-500">
                            {{ $submission->submitter->user?->username ?? __('community.participant') }}
                        </div>
                    </a>
                @endforeach
            </div>
        </flux:card>
    @endif
</section>
