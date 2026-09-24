@props(['group', 'space', 'current' => 'chat'])

@php($context = $space->contextBinding?->context)

<div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800">
    <flux:button
        :href="route('groups.spaces.show', [$group, $space])"
        size="sm"
        :variant="$current === 'chat' ? 'primary' : 'ghost'"
    >
        {{ __('ui.content.chat_tab') }}
    </flux:button>
    @if ($context)
        <flux:button
            :href="route('planner.index', ['context' => $context->uuid])"
            size="sm"
            :variant="$current === 'planner' ? 'primary' : 'ghost'"
        >
            {{ __('planner.title') }}
        </flux:button>
        <flux:button
            :href="route('contexts.timeline', $context)"
            size="sm"
            :variant="$current === 'timeline' ? 'primary' : 'ghost'"
        >
            {{ __('collaboration.tabs.timeline') }}
        </flux:button>
    @endif
    <flux:button
        :href="route('groups.spaces.contents.index', [$group, $space])"
        size="sm"
        :variant="$current === 'content' ? 'primary' : 'ghost'"
    >
        {{ __('ui.content.tab') }}
    </flux:button>
</div>
