<section class="space-y-6">
    <x-app.page-header title="Create Group" description="Start a private space. You will become its owner." />

    <form wire:submit="save" class="max-w-xl space-y-6">
        <flux:input wire:model="name" label="Group name" placeholder="e.g. The Lantern Guild" autofocus />
        <flux:textarea wire:model="description" label="Description" description="Optional. Explain the purpose of this group." rows="5" />
        <div class="flex flex-wrap gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Create Group</flux:button>
            <flux:button :href="route('groups.index')" variant="ghost">Cancel</flux:button>
        </div>
    </form>
</section>
