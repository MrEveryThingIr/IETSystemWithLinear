@php
    $nodesById = collect($map['nodes'])->keyBy('id');
    $clientMap = [
        ...$map,
        'ui' => [
            'explore' => __('system_map.inspector.explore'),
            'explore_help' => __('system_map.inspector.explore_help'),
            'starting_point' => __('system_map.inspector.starting_point'),
            'starting_point_help' => __('system_map.inspector.starting_point_help'),
            'implemented' => __('system_map.status.implemented'),
            'direction' => __('system_map.status.direction'),
            'open_area' => __('system_map.inspector.open_area'),
            'human_purpose' => __('system_map.inspector.human_purpose'),
            'truth' => __('system_map.inspector.truth'),
            'review_questions' => __('system_map.inspector.review_questions'),
            'documentation' => __('system_map.inspector.documentation'),
            'code_anchors' => __('system_map.inspector.code_anchors'),
            'connections' => __('system_map.inspector.connections'),
        ],
    ];
@endphp

<section
    class="mx-auto max-w-[1800px] space-y-5"
    data-system-map
    data-initial-lens="{{ is_string($initialLens) ? $initialLens : '' }}"
>
    <x-app.page-header :title="__('system_map.title')" :description="__('system_map.description')">
        <x-slot:actions>
            <flux:button :href="route('manual')" variant="ghost" icon="book-open">
                {{ __('system_map.actions.manual') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>
        <div class="space-y-1">
            <div class="font-medium">{{ __('system_map.callout.title') }}</div>
            <div class="text-sm">{{ __('system_map.callout.body') }}</div>
        </div>
    </flux:callout>

    <div class="flex flex-wrap gap-2">
        <flux:button type="button" size="sm" variant="ghost" data-system-map-preset="north_star">
            {{ __('system_map.lenses.north_star') }}
        </flux:button>
        <flux:button type="button" size="sm" variant="ghost" data-system-map-preset="finance">
            {{ __('system_map.lenses.finance') }}
        </flux:button>
        <flux:button type="button" size="sm" variant="ghost" data-system-map-preset="content">
            {{ __('system_map.lenses.content') }}
        </flux:button>
        <flux:button type="button" size="sm" variant="ghost" data-system-map-preset="community">
            {{ __('system_map.lenses.community') }}
        </flux:button>
        <flux:button type="button" size="sm" variant="ghost" data-system-map-preset="execution">
            {{ __('system_map.lenses.execution') }}
        </flux:button>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_23rem]">
        <flux:card class="min-w-0 space-y-3 p-3 sm:p-4">
            <div class="grid gap-3 md:grid-cols-[minmax(14rem,1fr)_12rem_11rem_auto] md:items-end">
                <flux:input
                    type="search"
                    data-system-map-search
                    :label="__('system_map.controls.search')"
                    :placeholder="__('system_map.controls.search_placeholder')"
                />

                <flux:select data-system-map-group :label="__('system_map.controls.module')">
                    <option value="all">{{ __('system_map.controls.all_modules') }}</option>
                    @foreach ($map['groups'] as $group)
                        <option value="{{ $group['id'] }}">{{ $group['label'] }}</option>
                    @endforeach
                </flux:select>

                <flux:select data-system-map-status :label="__('system_map.controls.status')">
                    <option value="all">{{ __('system_map.controls.all_statuses') }}</option>
                    <option value="implemented">{{ __('system_map.status.implemented') }}</option>
                    <option value="direction">{{ __('system_map.status.direction') }}</option>
                </flux:select>

                <div class="flex flex-wrap gap-1">
                    <flux:button type="button" size="sm" variant="ghost" data-system-map-zoom-out aria-label="{{ __('system_map.controls.zoom_out') }}">−</flux:button>
                    <flux:button type="button" size="sm" variant="ghost" data-system-map-zoom-in aria-label="{{ __('system_map.controls.zoom_in') }}">+</flux:button>
                    <flux:button type="button" size="sm" variant="ghost" data-system-map-fit>{{ __('system_map.controls.fit') }}</flux:button>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-500">
                <div class="flex flex-wrap items-center gap-3">
                    <span>
                        <strong class="text-zinc-900 dark:text-zinc-100" data-system-map-result-count>{{ count($map['nodes']) }}</strong>
                        {{ __('system_map.controls.visible_nodes') }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        {{ __('system_map.status.implemented') }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="size-2 rounded-full border border-dashed border-amber-500"></span>
                        {{ __('system_map.status.direction') }}
                    </span>
                </div>

                <button type="button" class="font-medium hover:text-zinc-900 dark:hover:text-white" data-system-map-clear-focus hidden>
                    {{ __('system_map.controls.clear_focus') }}
                </button>
            </div>

            <div class="relative h-[72vh] min-h-[620px] overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
                <div
                    class="absolute inset-0 overflow-auto overscroll-contain cursor-grab"
                    data-system-map-viewport
                    tabindex="0"
                    aria-label="{{ __('system_map.canvas_label') }}"
                >
                    <div class="relative" data-system-map-host>
                        <div
                            class="absolute start-0 top-0 origin-top-left"
                            style="width: {{ $map['width'] }}px; height: {{ $map['height'] }}px;"
                            data-system-map-scene
                        >
                            @foreach ($map['groups'] as $group)
                                <div
                                    class="absolute rounded-3xl border border-zinc-200/80 bg-white/60 p-5 shadow-sm backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-900/50"
                                    style="left: {{ $group['x'] }}px; top: {{ $group['y'] }}px; width: {{ $group['width'] }}px; height: {{ $group['height'] }}px;"
                                >
                                    <div class="max-w-md">
                                        <div class="text-sm font-semibold">{{ $group['label'] }}</div>
                                        <div class="mt-1 text-xs leading-5 text-zinc-500">{{ $group['summary'] }}</div>
                                    </div>
                                </div>
                            @endforeach

                            <svg
                                class="pointer-events-none absolute inset-0 z-10 size-full overflow-visible"
                                viewBox="0 0 {{ $map['width'] }} {{ $map['height'] }}"
                                aria-hidden="true"
                            >
                                <defs>
                                    <marker id="system-map-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse">
                                        <path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor" class="text-zinc-400 dark:text-zinc-600" />
                                    </marker>
                                </defs>

                                @foreach ($map['edges'] as $edge)
                                    @php
                                        $source = $nodesById->get($edge['source']);
                                        $target = $nodesById->get($edge['target']);
                                        $x1 = $source['x'] + 115;
                                        $y1 = $source['y'] + 55;
                                        $x2 = $target['x'] + 115;
                                        $y2 = $target['y'] + 55;
                                        $midX = ($x1 + $x2) / 2;
                                    @endphp
                                    <path
                                        d="M {{ $x1 }} {{ $y1 }} C {{ $midX }} {{ $y1 }}, {{ $midX }} {{ $y2 }}, {{ $x2 }} {{ $y2 }}"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="{{ $edge['kind'] === 'boundary' ? 2.5 : 2 }}"
                                        stroke-dasharray="{{ $edge['kind'] === 'boundary' || $edge['kind'] === 'direction' ? '8 7' : '0' }}"
                                        marker-end="url(#system-map-arrow)"
                                        class="system-map-edge text-zinc-300 transition-opacity dark:text-zinc-700"
                                        data-system-map-edge
                                        data-source="{{ $edge['source'] }}"
                                        data-target="{{ $edge['target'] }}"
                                        data-kind="{{ $edge['kind'] }}"
                                    />
                                @endforeach
                            </svg>

                            @foreach ($map['nodes'] as $node)
                                <button
                                    type="button"
                                    class="absolute z-20 flex h-[110px] w-[230px] flex-col overflow-hidden rounded-2xl border bg-white p-4 text-start shadow-md transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-zinc-900
                                        {{ $node['status'] === 'direction'
                                            ? 'border-dashed border-amber-400/80 dark:border-amber-500/70'
                                            : 'border-zinc-200 dark:border-zinc-700' }}"
                                    style="left: {{ $node['x'] }}px; top: {{ $node['y'] }}px;"
                                    data-system-map-node
                                    data-node-id="{{ $node['id'] }}"
                                >
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="font-semibold leading-5" dir="auto">{{ $node['label'] }}</span>
                                        <span
                                            class="mt-1 size-2 shrink-0 rounded-full {{ $node['status'] === 'direction' ? 'border border-dashed border-amber-500' : 'bg-emerald-500' }}"
                                            aria-hidden="true"
                                        ></span>
                                    </span>
                                    <span class="mt-2 line-clamp-3 text-xs leading-4 text-zinc-500" dir="auto">{{ $node['summary'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="pointer-events-none absolute bottom-3 start-3 rounded-lg bg-white/90 px-3 py-2 text-[11px] text-zinc-500 shadow-sm backdrop-blur dark:bg-zinc-900/90">
                    {{ __('system_map.canvas_help') }}
                </div>
            </div>
        </flux:card>

        <flux:card class="h-fit space-y-5 xl:sticky xl:top-4">
            <div data-system-map-inspector class="space-y-5"></div>
        </flux:card>
    </div>

    <script type="application/json" data-system-map-data>@json($clientMap)</script>
</section>
