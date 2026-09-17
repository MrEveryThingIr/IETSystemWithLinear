@props(['group', 'space', 'current' => 'chat'])

<div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800">
    <flux:button
        :href="route('groups.spaces.show', [$group, $space])"
        size="sm"
        :variant="$current === 'chat' ? 'primary' : 'ghost'"
    >
        {{ __('ui.content.chat_tab') }}
    </flux:button>
    <flux:button
        :href="route('groups.spaces.contents.index', [$group, $space])"
        size="sm"
        :variant="$current === 'content' ? 'primary' : 'ghost'"
    >
        {{ __('ui.content.tab') }}
    </flux:button>
</div>
