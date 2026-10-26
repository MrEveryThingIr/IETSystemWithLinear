<section class="mx-auto max-w-4xl space-y-4">
    <x-app.page-header :title="__('collaboration.conversation.title')" :description="__('collaboration.conversation.help')">
        <x-slot:actions>
            <flux:button :href="route('contexts.timeline', $context)" variant="ghost">{{ __('collaboration.tabs.timeline') }}</flux:button>
            <flux:button :href="route('contexts.contents.index', $context)" variant="ghost">{{ __('collaboration.tabs.content') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>{{ __('collaboration.conversation.non_authority') }}</flux:callout>

    <div class="h-[36rem] overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" data-context-thread>
        <livewire:messages.thread :conversation="$conversation" :embedded="true" :key="'ctx-thread-'.$conversation->uuid" />
    </div>
</section>
