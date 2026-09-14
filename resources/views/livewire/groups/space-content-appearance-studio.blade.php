<section class="mx-auto w-full max-w-6xl space-y-6 pb-12">
    <x-app.page-header :title="__('presentation.title')" :description="$revision->title">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('groups.spaces.contents.studio', [$group, $space, $content])" variant="ghost">{{ __('presentation.back_to_studio') }}</flux:button>
                @if ($content->status === 'published')
                    <flux:button :href="route('groups.spaces.contents.show', [$group, $space, $content])" variant="ghost">{{ __('studio.back_to_reader') }}</flux:button>
                @endif
            </div>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <div class="grid gap-6 xl:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="space-y-4 self-start xl:sticky xl:top-6">
            <flux:card class="space-y-3">
                <div>
                    <flux:heading>{{ __('presentation.presets') }}</flux:heading>
                    <flux:text>{{ __('presentation.presets_help') }}</flux:text>
                </div>
                @foreach ($builtIns as $key => $preset)
                    <button type="button" wire:click="chooseTemplate('builtin:{{ $key }}')" class="w-full rounded-lg border p-3 text-start {{ $templateSource === 'builtin:'.$key ? 'border-[var(--color-accent)] ring-1 ring-[var(--color-accent)]' : 'border-zinc-200 dark:border-zinc-800' }}">
                        <div class="font-medium">{{ __('presentation.preset.'.$key) }}</div>
                    </button>
                @endforeach
            </flux:card>

            @if ($customTemplates->isNotEmpty())
                <flux:card class="space-y-3">
                    <div>
                        <flux:heading>{{ __('presentation.saved_templates') }}</flux:heading>
                        <flux:text>{{ __('presentation.saved_templates_help') }}</flux:text>
                    </div>
                    @foreach ($customTemplates as $template)
                        <div class="rounded-lg border border-zinc-200 p-2 dark:border-zinc-800">
                            <button type="button" wire:click="chooseTemplate('custom:{{ $template->uuid }}')" class="w-full p-1 text-start font-medium" dir="auto">
                                {{ $template->name }}
                            </button>
                            <button type="button" wire:click="toggleFavorite('{{ $template->uuid }}')" class="mt-1 text-xs text-zinc-500">
                                {{ $template->is_favorite ? '★ '.__('presentation.favorite') : '☆ '.__('presentation.favorite') }}
                            </button>
                        </div>
                    @endforeach
                </flux:card>
            @endif
        </aside>

        <div class="space-y-6">
            <flux:card class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('presentation.canvas') }}</flux:heading>
                    <flux:text>{{ __('presentation.canvas_help') }}</flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        'background' => __('presentation.background'),
                        'surface' => __('presentation.surface'),
                        'text' => __('presentation.text_color'),
                        'muted' => __('presentation.muted_color'),
                        'accent' => __('presentation.accent'),
                        'border' => __('presentation.border_color'),
                    ] as $token => $label)
                        <label class="space-y-2">
                            <span class="text-sm font-medium">{{ $label }}</span>
                            <div class="flex gap-2">
                                <input type="color" wire:model.live="presentation.{{ $token }}" class="h-10 w-12 rounded border border-zinc-300" />
                                <input type="text" wire:model.blur="presentation.{{ $token }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 text-sm dark:border-zinc-700 dark:bg-zinc-950" maxlength="7" />
                            </div>
                        </label>
                    @endforeach
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <flux:select wire:model="presentation.content_width" :label="__('presentation.width')">
                        <option value="narrow">{{ __('presentation.width_narrow') }}</option>
                        <option value="reading">{{ __('presentation.width_reading') }}</option>
                        <option value="wide">{{ __('presentation.width_wide') }}</option>
                        <option value="full">{{ __('presentation.width_full') }}</option>
                    </flux:select>
                    <flux:select wire:model="presentation.font_scale" :label="__('presentation.font_scale')">
                        <option value="compact">{{ __('presentation.scale_compact') }}</option>
                        <option value="comfortable">{{ __('presentation.scale_comfortable') }}</option>
                        <option value="large">{{ __('presentation.scale_large') }}</option>
                    </flux:select>
                    <flux:select wire:model="presentation.radius" :label="__('presentation.radius')">
                        <option value="none">{{ __('presentation.radius_none') }}</option>
                        <option value="soft">{{ __('presentation.radius_soft') }}</option>
                        <option value="rounded">{{ __('presentation.radius_rounded') }}</option>
                    </flux:select>
                    <flux:select wire:model="presentation.heading_style" :label="__('presentation.heading_style')">
                        <option value="plain">{{ __('presentation.heading_plain') }}</option>
                        <option value="serif">{{ __('presentation.heading_serif') }}</option>
                        <option value="display">{{ __('presentation.heading_display') }}</option>
                    </flux:select>
                    <flux:select wire:model="presentation.media_style" :label="__('presentation.media_style')">
                        <option value="contained">{{ __('presentation.media_contained') }}</option>
                        <option value="card">{{ __('presentation.media_card') }}</option>
                        <option value="edge">{{ __('presentation.media_edge') }}</option>
                    </flux:select>
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <details>
                    <summary class="cursor-pointer font-semibold">{{ __('presentation.field_styles') }}</summary>
                    <div class="mt-4 space-y-4">
                        <flux:text>{{ __('presentation.field_styles_help') }}</flux:text>
                        @foreach ($fields as $field)
                            <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 md:grid-cols-[1fr_10rem_10rem_12rem] dark:border-zinc-800">
                                <div class="font-medium" dir="auto">{{ $field['label'] }}</div>
                                <label class="space-y-1 text-sm">
                                    <span>{{ __('presentation.text_color') }}</span>
                                    <input type="color" wire:model="presentation.field_styles.{{ $field['key'] }}.text_color" class="h-10 w-full rounded border" />
                                </label>
                                <label class="space-y-1 text-sm">
                                    <span>{{ __('presentation.background') }}</span>
                                    <input type="color" wire:model="presentation.field_styles.{{ $field['key'] }}.background_color" class="h-10 w-full rounded border" />
                                </label>
                                <flux:select wire:model="presentation.field_styles.{{ $field['key'] }}.emphasis" :label="__('presentation.emphasis')">
                                    <option value="normal">{{ __('presentation.emphasis_normal') }}</option>
                                    <option value="muted">{{ __('presentation.emphasis_muted') }}</option>
                                    <option value="strong">{{ __('presentation.emphasis_strong') }}</option>
                                    <option value="callout">{{ __('presentation.emphasis_callout') }}</option>
                                </flux:select>
                            </div>
                        @endforeach
                    </div>
                </details>
            </flux:card>

            <flux:card class="space-y-4">
                <div>
                    <flux:heading>{{ __('presentation.preview') }}</flux:heading>
                    <flux:text>{{ __('presentation.preview_help') }}</flux:text>
                </div>
                <div
                    class="border p-6"
                    style="background: {{ $presentation['background'] ?? '#fafafa' }}; color: {{ $presentation['text'] ?? '#18181b' }}; border-color: {{ $presentation['border'] ?? '#e4e4e7' }}; border-radius: {{ ($presentation['radius'] ?? 'rounded') === 'none' ? '0' : (($presentation['radius'] ?? 'rounded') === 'soft' ? '.5rem' : '1rem') }}"
                >
                    <div class="mx-auto max-w-2xl space-y-4" style="background: {{ $presentation['surface'] ?? '#ffffff' }}; padding: 1.5rem; border-radius: inherit">
                        <h2 class="text-2xl font-semibold" dir="auto">{{ $revision->title }}</h2>
                        @foreach ($fields->take(2) as $field)
                            <div>
                                <div class="text-xs font-semibold uppercase" style="color: {{ $presentation['muted'] ?? '#71717a' }}" dir="auto">{{ $field['label'] }}</div>
                                <div class="mt-1" dir="auto">{{ \Illuminate\Support\Str::limit((string) ($revision->payload[$field['key']] ?? ''), 180) ?: '…' }}</div>
                            </div>
                        @endforeach
                        <div class="h-1 w-16 rounded" style="background: {{ $presentation['accent'] ?? '#2563eb' }}"></div>
                    </div>
                </div>
            </flux:card>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex-1">
                    <flux:input wire:model="templateName" :label="__('presentation.template_name')" :placeholder="__('presentation.template_name_placeholder')" maxlength="120" />
                    @error('templateName')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="saveAsTemplate" variant="ghost">{{ __('presentation.save_as_template') }}</flux:button>
                    <flux:button wire:click="save" wire:loading.attr="disabled" wire:target="save" variant="primary">{{ __('presentation.save_appearance') }}</flux:button>
                </div>
            </div>
        </div>
    </div>
</section>
