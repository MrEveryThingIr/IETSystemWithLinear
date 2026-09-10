<section class="space-y-6">
    <x-app.page-header :title="__('ui.actors.actor_number', ['id' => $actor->id])">
        <x-slot:actions>
            <flux:button :href="route('actors.index')" variant="ghost">{{ __('ui.actors.all') }}</flux:button>
            <flux:button :href="route('actors.edit', $actor)">{{ __('ui.groups.edit') }}</flux:button>
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
    </dl>
    <flux:modal.trigger name="delete-actor"><flux:button variant="danger">{{ __('ui.actors.delete') }}</flux:button></flux:modal.trigger>
    <flux:modal name="delete-actor" class="space-y-6 md:w-96">
        <flux:heading size="lg">{{ __('ui.actors.delete_title', ['id' => $actor->id]) }}</flux:heading>
        <flux:text>{{ __('ui.actors.delete_help') }}</flux:text>
        <div class="flex justify-end gap-3">
            <flux:modal.close><flux:button variant="ghost">{{ __('ui.common.cancel') }}</flux:button></flux:modal.close>
            <flux:button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">{{ __('ui.actors.confirm_delete') }}</flux:button>
        </div>
    </flux:modal>
</section>
