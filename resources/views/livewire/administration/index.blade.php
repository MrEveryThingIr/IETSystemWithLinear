<section class="space-y-6">
    <x-app.page-header title="Administration" description="Manage global access separately from group roles. Group ownership and member safeguards remain unchanged." />

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <flux:card class="max-w-3xl space-y-5">
        <flux:heading size="lg">Global access</flux:heading>
        <flux:text>Full administrators can manage users, groups, actors, and global access. Partial administrators receive only the selected permissions.</flux:text>
        <form wire:submit="save" class="space-y-5">
            <flux:select wire:model.live="userId" label="User">
                <flux:select.option value="">Choose a user</flux:select.option>
                @foreach ($users as $user)
                    <flux:select.option :value="$user->id">{{ $user->username }} — {{ $user->email }} ({{ $user->status }})</flux:select.option>
                @endforeach
            </flux:select>

            @if ($userId !== '')
                <flux:checkbox wire:model.live="fullAdministration" label="Full global administrator" description="Grants every global administration permission." />
                <fieldset @class(['space-y-3', 'opacity-50' => $fullAdministration]) @disabled($fullAdministration)>
                    <flux:heading size="sm">Partial permissions</flux:heading>
                    @foreach ($permissionNames as $permissionName)
                        <flux:checkbox wire:model="permissions" :value="$permissionName" :label="$permissionName" />
                    @endforeach
                </fieldset>
                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save global access</flux:button>
                </div>
            @endif
        </form>
    </flux:card>
</section>
