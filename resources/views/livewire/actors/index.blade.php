<section class="space-y-6">
    <x-app.page-header title="Actors" description="Domain participants and their account associations.">
        <x-slot:actions><flux:button :href="route('actors.create')" variant="primary" icon="plus">Create Actor</flux:button></x-slot:actions>
    </x-app.page-header>
    @if ($actors->isEmpty())
        <x-app.empty-state title="No Actors yet" description="Create an accountless participant or associate an available account." />
    @else
        <flux:table :paginate="$actors">
            <flux:table.columns>
                <flux:table.column>Actor</flux:table.column>
                <flux:table.column>User association</flux:table.column>
                <flux:table.column>Created</flux:table.column>
                <flux:table.column><span class="sr-only">Actions</span></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($actors as $actor)
                    <flux:table.row :key="$actor->id">
                        <flux:table.cell><flux:link :href="route('actors.show', $actor)">#{{ $actor->id }}</flux:link></flux:table.cell>
                        <flux:table.cell>
                            @if ($actor->user)
                                {{ $actor->user->username }} <flux:text size="sm">{{ $actor->user->email }}</flux:text>
                            @else
                                <flux:badge>Accountless</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $actor->created_at?->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell><flux:button :href="route('actors.edit', $actor)" size="sm" variant="ghost">Edit</flux:button></flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
