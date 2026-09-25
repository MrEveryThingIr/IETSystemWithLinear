<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('contracts.title')" :description="__('contracts.help')">
        <x-slot:actions>
            <flux:button :href="route('contracts.create')" variant="primary">
                {{ __('contracts.create.title') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>{{ __('contracts.boundary') }}</flux:callout>

    @if ($contracts->isEmpty())
        <x-app.empty-state :title="__('contracts.empty')" :description="__('contracts.empty_help')" />
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($contracts as $contract)
                @php($active = $contract->activeVersionRecord())
                @php($pending = $contract->pendingVersionRecord())
                <flux:card wire:key="contract-{{ $contract->uuid }}" class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ $contract->title }}</flux:heading>
                            <flux:text>
                                {{ $contract->creator->user?->username ?? __('relationships.unknown_actor') }}
                            </flux:text>
                        </div>
                        <flux:badge>{{ __('contracts.status.'.$contract->status->value) }}</flux:badge>
                    </div>

                    <div class="flex flex-wrap gap-2 text-sm text-zinc-500">
                        @if ($active)
                            <span>{{ __('contracts.show.current_version', ['version' => $active->version]) }} · {{ __('contracts.version_status.'.$active->status->value) }}</span>
                        @elseif ($pending)
                            <span>{{ __('contracts.show.current_version', ['version' => $pending->version]) }} · {{ __('contracts.version_status.'.$pending->status->value) }}</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @php($latest = $pending ?: $active ?: $contract->versions->sortByDesc('version')->first())
                        @if ($latest)
                            @foreach ($latest->parties as $party)
                                <flux:badge color="zinc">
                                    {{ $party->actor->user?->username ?? __('relationships.unknown_actor') }} · {{ $party->role }}
                                </flux:badge>
                            @endforeach
                        @endif
                    </div>

                    <flux:button :href="route('contracts.show', $contract)" variant="ghost" class="w-full">
                        {{ __('contracts.actions.open_contract') }}
                    </flux:button>
                </flux:card>
            @endforeach
        </div>
    @endif
</section>
