<section class="space-y-6">
    <x-app.page-header title="Groups" description="Private spaces where members collaborate on stories and agreements.">
        <x-slot:actions><flux:button :href="route('groups.create')" variant="primary" icon="plus">Create Group</flux:button></x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @if ($memberships->isEmpty())
        <x-app.empty-state title="No groups yet" description="Create a private group to begin inviting members." />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($memberships as $membership)
                <flux:card class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading size="lg">{{ $membership->group->name }}</flux:heading>
                        <flux:badge>{{ ucfirst($membership->role) }}</flux:badge>
                    </div>
                    <flux:text>{{ $membership->group->description ?: 'No description yet.' }}</flux:text>
                </flux:card>
            @endforeach
        </div>
    @endif
</section>
