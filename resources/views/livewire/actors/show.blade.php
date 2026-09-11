<section class="space-y-6">
    <x-app.page-header :title="__('ui.actors.actor_number', ['id' => $actor->id])">
        <x-slot:actions>
            <flux:button :href="route('actors.index')" variant="ghost">{{ __('ui.actors.all') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>
    <dl class="max-w-xl space-y-4">
        <div><dt class="font-medium">{{ __('ui.actors.association') }}</dt><dd>
            @if ($actor->user)
                {{ $actor->user->username }} ({{ $actor->user->email }})
            @else
                <flux:badge>{{ __('ui.actors.accountless') }}</flux:badge>
            @endif
        </dd></div>
        <div><dt class="font-medium">{{ __('ui.actors.created') }}</dt><dd>{{ $actor->created_at?->translatedFormat('Y-m-d H:i:s') }}</dd></div>
        <div><dt class="font-medium">{{ __('ui.actors.updated') }}</dt><dd>{{ $actor->updated_at?->translatedFormat('Y-m-d H:i:s') }}</dd></div>
        <div><dt class="font-medium">{{ __('ui.actors.status') }}</dt><dd><flux:badge :color="$actor->status === 'active' ? 'green' : 'zinc'">{{ __('ui.actors.statuses.'.$actor->status) }}</flux:badge></dd></div>
        @if ($actor->archived_at)
            <div><dt class="font-medium">{{ __('ui.actors.archived_at') }}</dt><dd>{{ $actor->archived_at->translatedFormat('Y-m-d H:i:s') }}</dd></div>
            <div><dt class="font-medium">{{ __('ui.actors.archive_reason') }}</dt><dd>{{ $actor->archive_reason }}</dd></div>
        @endif
    </dl>
    @can('archive', $actor)
        <flux:modal.trigger name="archive-actor"><flux:button variant="danger">{{ __('ui.actors.archive') }}</flux:button></flux:modal.trigger>
        <flux:modal name="archive-actor" class="space-y-6 md:w-96">
            <flux:heading size="lg">{{ __('ui.actors.archive_title', ['id' => $actor->id]) }}</flux:heading>
            <flux:text>{{ __('ui.actors.archive_help') }}</flux:text>
            <flux:textarea wire:model="archiveReason" :label="__('ui.actors.archive_reason')" rows="4" />
            <div class="flex justify-end gap-3">
                <flux:modal.close><flux:button variant="ghost">{{ __('ui.common.cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="archive" wire:loading.attr="disabled" wire:target="archive">{{ __('ui.actors.confirm_archive') }}</flux:button>
            </div>
        </flux:modal>
    @endcan
</section>
