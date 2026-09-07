<section class="space-y-6">
    <x-app.page-header title="Edit Actor" description="An Actor may exist without an account." />
    <form wire:submit="save" class="max-w-xl space-y-6">
        <flux:select wire:model="userId" label="User association" description="Only accounts without another Actor can be assigned.">
            <flux:select.option value="">No account</flux:select.option>
            @foreach ($users as $user)
                <flux:select.option :value="$user->id">{{ $user->username }} ({{ $user->email }})</flux:select.option>
            @endforeach
        </flux:select>
        <div class="flex flex-wrap gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save Actor</flux:button>
            <flux:button :href="route('actors.index')" variant="ghost">Cancel</flux:button>
        </div>
    </form>
</section>
