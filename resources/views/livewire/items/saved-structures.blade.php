<section class="space-y-5 rounded-2xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900" id="saved" aria-labelledby="saved-heading" data-saved-structures>
    <header class="space-y-1">
        <div class="flex items-center gap-2">
            <flux:icon name="document-duplicate" class="size-5 text-zinc-500" />
            <flux:heading size="lg" level="2" id="saved-heading">{{ __('items.saved.title') }}</flux:heading>
        </div>
        <flux:text size="sm">{{ __('items.saved.help') }}</flux:text>
    </header>

    @foreach (['templates' => $templates, 'groups' => $groups] as $section => $list)
        <div class="min-w-0 space-y-2" data-saved-{{ $section }}>
            <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ __('items.saved.'.$section) }}</h3>
            @forelse ($list as $saved)
                <article class="rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800" wire:key="saved-{{ $saved->uuid }}" data-saved-item="{{ $saved->uuid }}">
                    @if ($renaming === $saved->uuid)
                        <form wire:submit="saveRename" class="flex flex-wrap items-center gap-2">
                            <flux:input wire:model="renameTo" :aria-label="__('items.saved.rename')" dir="auto" class="min-w-0 flex-1" />
                            <flux:button size="sm" type="submit" variant="primary">{{ __('items.saved.save') }}</flux:button>
                            <flux:button size="sm" type="button" variant="ghost" wire:click="cancelRename">{{ __('items.saved.cancel') }}</flux:button>
                        </form>
                        @error('renameTo')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @else
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="min-w-0 flex-1 font-semibold [overflow-wrap:anywhere]" dir="auto">{{ $saved->name }}</span>
                            @if ($saved->kind === 'composition' && ! empty($saved->context['side']))
                                <flux:badge size="sm">{{ __('composer.kind.'.$saved->context['side']) }}</flux:badge>
                            @endif
                            @if ($saved->topic)<flux:badge size="sm">{{ __('composer.topics.'.$saved->topic) }}</flux:badge>@endif
                            <span class="text-xs text-zinc-500">{{ trans_choice('items.saved.fields', count($saved->fields), ['count' => count($saved->fields)]) }}</span>
                        </div>
                        <details class="mt-1 text-sm">
                            <summary class="cursor-pointer text-zinc-600 underline-offset-4 hover:underline dark:text-zinc-300">{{ __('items.saved.preview') }}</summary>
                            <ul class="mt-2 list-inside list-disc text-zinc-600 dark:text-zinc-400">
                                @foreach ($saved->fields as $field)
                                    <li dir="auto">{{ empty($field['canonical_key']) ? ($field['label'] ?? '') : \App\Support\Market\IntentDetailCatalog::label($field['canonical_key'], null, $saved->topic) }}</li>
                                @endforeach
                                @foreach ($saved->terms ?? [] as $block)<li>{{ __('composer.terms.'.$block.'.label') }}</li>@endforeach
                            </ul>
                        </details>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            <a href="{{ route('intents.compose', ['side' => $saved->context['side'] ?? 'need', 'template' => $saved->uuid]) }}" wire:navigate class="font-medium text-zinc-800 underline-offset-4 hover:underline dark:text-zinc-100" data-saved-use>{{ __('items.saved.use') }}</a>
                            <button type="button" wire:click="startRename('{{ $saved->uuid }}')" class="text-zinc-600 underline-offset-4 hover:underline dark:text-zinc-300">{{ __('items.saved.rename') }}</button>
                            <button type="button" wire:click="delete('{{ $saved->uuid }}')" wire:confirm="{{ __('items.saved.delete_confirm') }}" class="text-red-700 underline-offset-4 hover:underline dark:text-red-400" data-saved-delete>{{ __('items.saved.delete') }}</button>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-5 text-sm text-zinc-500 dark:border-zinc-700">{{ __('items.saved.empty_'.$section) }}</div>
            @endforelse
        </div>
    @endforeach
</section>
