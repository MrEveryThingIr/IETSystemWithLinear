@props(['title'])

@php
    $manualTopic = request()->query('manual') === '1'
        ? 'feedback'
        : app(\App\Support\SystemManualHelpMap::class)
            ->topicForRoute(request()->route()?->getName());
@endphp

<flux:header class="gap-3 border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" :aria-label="__('ui.navigation.open')" />
    <flux:text class="min-w-0 truncate font-medium text-zinc-900 dark:text-white">{{ $title }}</flux:text>
    <flux:spacer />
    <flux:button :href="route('manual', ['topic' => $manualTopic])" variant="ghost" size="sm" icon="question-mark-circle">
        <span class="hidden sm:inline">{{ __('ui.navigation.help') }}</span>
    </flux:button>
    <x-app.locale-switcher />
    <x-app.user-menu />
</flux:header>
