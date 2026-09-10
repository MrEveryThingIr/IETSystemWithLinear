<section class="mx-auto max-w-3xl space-y-4 px-4 py-4 sm:px-0">
    <x-app.page-header :title="__('ui.agreements.acceptance_title', ['group' => $group->name])" />
    <flux:card class="space-y-2">
        <flux:heading size="lg">{{ __('ui.agreements.review_required') }}</flux:heading>
        <flux:text>{{ __('ui.agreements.accept_before_participating') }}</flux:text>
    </flux:card>
    @forelse($versions as $version)
        <flux:card class="space-y-4">
            <flux:heading>{{ $version->agreement->name }} &middot; {{ __('ui.common.version', ['version' => $version->version]) }}</flux:heading>
            <div class="whitespace-pre-wrap break-words text-sm leading-6" dir="auto">{{ $version->content }}</div>
            <flux:button wire:click="accept({{ $version->id }})" variant="primary" class="w-full sm:w-auto">{{ __('ui.agreements.accept_this_version') }}</flux:button>
        </flux:card>
    @empty
        <flux:card class="space-y-3">
            <flux:text>{{ __('ui.agreements.all_accepted') }}</flux:text>
            <flux:button :href="route('groups.show', $group)" variant="primary">{{ __('ui.groups.open') }}</flux:button>
        </flux:card>
    @endforelse
</section>
