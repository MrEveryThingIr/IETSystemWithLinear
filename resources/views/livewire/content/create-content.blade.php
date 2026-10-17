<div>
    <x-app.dialog :show="$open" close="close" :title="$kind === '' ? __('content_workspace.create.title') : __('content_workspace.kinds.'.$kind.'.name')" size="lg">
        @if ($kind === '')
            <p class="mb-4 text-sm text-zinc-500">{{ __('content_workspace.create.help') }}</p>
            <div class="grid gap-2 sm:grid-cols-2" data-create-kinds>
                @foreach ($kinds as $key => $definition)
                    <button
                        type="button"
                        wire:click="choose('{{ $key }}')"
                        class="flex items-start gap-3 rounded-xl border border-zinc-200 p-3 text-start hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        data-create-kind="{{ $key }}"
                    >
                        <flux:icon :name="$definition['icon']" class="mt-0.5 size-5 shrink-0 text-zinc-500" />
                        <span>
                            <span class="block font-medium">{{ __('content_workspace.kinds.'.$key.'.name') }}</span>
                            <span class="mt-0.5 block text-xs leading-5 text-zinc-500">{{ __('content_workspace.kinds.'.$key.'.help') }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
            @if ($moreTemplatesUrl)
                <div class="mt-4 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                    <a href="{{ $moreTemplatesUrl }}" class="font-medium text-zinc-600 underline-offset-4 hover:underline dark:text-zinc-300">{{ __('content_workspace.create.more_templates') }} →</a>
                </div>
            @endif
        @else
            <form wire:submit="create" class="space-y-4">
                <flux:input wire:model="title" :label="__('content_workspace.create.title_label')" maxlength="255" autofocus />

                @if ($fixedContextUuid === null)
                    <div class="space-y-1">
                        <flux:select wire:model.live="contextUuid" :label="__('content_workspace.create.where')">
                            @foreach ($contexts as $context)
                                <option value="{{ $context->uuid }}">{{ $contextLabels[$context->uuid] }}</option>
                            @endforeach
                        </flux:select>
                        @if ($selectedAudience)
                            <p class="text-xs text-zinc-500">{{ __('content_workspace.create.audience_when_published', ['audience' => $selectedAudience]) }}</p>
                        @endif
                    </div>
                @endif

                @foreach ($requiredFields as $field)
                    @switch($field['type'])
                        @case('short_text')
                            <x-space-content.fields.short-text :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('long_text')
                            <x-space-content.fields.long-text :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('number')
                            <x-space-content.fields.number :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('date')
                            <x-space-content.fields.date :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('boolean')
                            <x-space-content.fields.boolean :field="$field" :model="'payload.'.$field['key']" />
                            @break
                        @case('select')
                            <x-space-content.fields.select :field="$field" :model="'payload.'.$field['key']" />
                            @break
                    @endswitch
                @endforeach

                @error('kind')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                @error('payload')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                <p class="text-xs text-zinc-500">{{ __('content_workspace.create.private_until_published') }}</p>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between">
                    <flux:button type="button" wire:click="back" variant="ghost">{{ __('content_workspace.create.back') }}</flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="create">{{ __('content_workspace.create.submit') }}</flux:button>
                </div>
            </form>
        @endif
    </x-app.dialog>
</div>
