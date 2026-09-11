<section class="space-y-6">
    <x-app.page-header :title="__('ui.actors.title')" :description="__('ui.actors.overview')">
        <x-slot:actions><flux:button :href="route('actors.create')" variant="primary" icon="plus">{{ __('ui.actors.create') }}</flux:button></x-slot:actions>
    </x-app.page-header>
    @if ($actors->isEmpty())
        <x-app.empty-state :title="__('ui.actors.none')" :description="__('ui.actors.none_help')" />
    @else
        <flux:table :paginate="$actors">
            <flux:table.columns>
                <flux:table.column>{{ __('ui.actors.actor') }}</flux:table.column>
                <flux:table.column>{{ __('ui.actors.association') }}</flux:table.column>
                <flux:table.column>{{ __('ui.actors.created') }}</flux:table.column>
                <flux:table.column><span class="sr-only">{{ __('ui.actors.actions') }}</span></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($actors as $actor)
                    <flux:table.row :key="$actor->id">
                        <flux:table.cell><flux:link :href="route('actors.show', $actor)">#{{ $actor->id }}</flux:link></flux:table.cell>
                        <flux:table.cell>
                            @if ($actor->user)
                                {{ $actor->user->username }} <flux:text size="sm">{{ $actor->user->email }}</flux:text>
                            @else
                                <flux:badge>{{ __('ui.actors.accountless') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $actor->created_at?->translatedFormat('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell><flux:button :href="route('actors.edit', $actor)" size="sm" variant="ghost">{{ __('ui.groups.edit') }}</flux:button></flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</section>
