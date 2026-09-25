<section class="mx-auto max-w-7xl space-y-6">
    <x-app.page-header :title="__('journeys.title')" :description="__('journeys.subtitle')" />

    <flux:callout>
        {{ __('journeys.boundary') }}
    </flux:callout>

    <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
        @foreach ($blueprints as $item)
            @php($blueprint = $item['blueprint'])
            @php($version = $item['version'])

            <flux:card wire:key="journey-{{ $blueprint->uuid }}" class="flex h-full flex-col gap-4">
                <div class="space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading size="lg">{{ $blueprint->name }}</flux:heading>
                        <flux:badge color="zinc">
                            {{ __('journeys.version', ['version' => $version->version]) }}
                        </flux:badge>
                    </div>
                    <flux:text>{{ $blueprint->description }}</flux:text>
                </div>

                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                        {{ __('journeys.recommended_capabilities') }}
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($version->capabilities as $capability)
                            <flux:badge color="zinc">{{ __('journeys.capabilities.'.$capability) }}</flux:badge>
                        @endforeach
                    </div>
                </div>

                @if ($version->content_blueprint_slugs !== [])
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">
                            {{ __('journeys.content_templates') }}
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($version->content_blueprint_slugs as $slug)
                                <flux:badge color="zinc">{{ $slug }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-auto pt-2">
                    <flux:button :href="$item['href']" variant="primary" class="w-full">
                        {{ __('journeys.start') }}
                    </flux:button>
                </div>
            </flux:card>
        @endforeach
    </div>
</section>
