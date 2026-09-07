<section class="space-y-6">
    <x-app.page-header :title="'Actor #'.$actor->id">
        <x-slot:actions>
            <flux:button :href="route('actors.index')" variant="ghost">All Actors</flux:button>
            <flux:button :href="route('actors.edit', $actor)">Edit</flux:button>
        </x-slot:actions>
    </x-app.page-header>
    <dl class="max-w-xl space-y-4">
        <div><dt class="font-medium">User association</dt><dd>
            @if ($actor->user)
                {{ $actor->user->username }} ({{ $actor->user->email }})
            @else
                <flux:badge>Accountless</flux:badge>
            @endif
        </dd></div>
        <div><dt class="font-medium">Created</dt><dd>{{ $actor->created_at?->format('Y-m-d H:i:s') }}</dd></div>
        <div><dt class="font-medium">Updated</dt><dd>{{ $actor->updated_at?->format('Y-m-d H:i:s') }}</dd></div>
    </dl>
    <flux:modal.trigger name="delete-actor"><flux:button variant="danger">Delete Actor</flux:button></flux:modal.trigger>
    <flux:modal name="delete-actor" class="space-y-6 md:w-96">
        <flux:heading size="lg">Delete Actor #{{ $actor->id }}?</flux:heading>
        <flux:text>This permanently deletes this participant. The associated account will remain.</flux:text>
        <div class="flex justify-end gap-3">
            <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
            <flux:button variant="danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete">Confirm deletion</flux:button>
        </div>
    </flux:modal>
</section>
