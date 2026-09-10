<section class="mx-auto max-w-3xl space-y-4 px-4 py-4 sm:px-0">
    <x-app.page-header :title="$group->name.' agreement acceptance'" />
    <flux:card class="space-y-2">
        <flux:heading size="lg">Review required agreements</flux:heading>
        <flux:text>Accept each current agreement before participating in this group.</flux:text>
    </flux:card>
    @forelse($versions as $version)
        <flux:card class="space-y-4">
            <flux:heading>{{ $version->agreement->name }} · version {{ $version->version }}</flux:heading>
            <div class="whitespace-pre-wrap break-words text-sm leading-6">{{ $version->content }}</div>
            <flux:button wire:click="accept({{ $version->id }})" variant="primary" class="w-full sm:w-auto">Accept this version</flux:button>
        </flux:card>
    @empty
        <flux:card class="space-y-3">
            <flux:text>All current agreements have been accepted. You can return to the group.</flux:text>
            <flux:button :href="route('groups.show', $group)" variant="primary">Open group</flux:button>
        </flux:card>
    @endforelse
</section>
