<section class="space-y-6">
    <x-app.page-header title="Groups" description="Private spaces where members collaborate on stories and agreements.">
        <x-slot:actions><flux:button :href="route('groups.create')" variant="primary" icon="plus">Create Group</flux:button></x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success" class="break-all">{{ session('status') }}</flux:callout>
    @endif

    @if ($groups->isEmpty())
        <x-app.empty-state title="No groups yet" description="Create a private group to begin inviting members." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($groups as $group)
                <flux:card class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading size="lg">{{ $group->name }}</flux:heading>
                        <flux:badge>{{ $roles[$group->id] }}</flux:badge>
                    </div>
                    <flux:text>{{ $group->description ?: 'No description yet.' }}</flux:text>
                    <flux:button :href="route('groups.show', $group)" class="w-full sm:w-auto" size="sm" variant="primary">Open group</flux:button>
                </flux:card>
            @endforeach
        </div>
    @endif
</section>
