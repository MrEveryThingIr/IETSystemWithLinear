<section class="mx-auto max-w-5xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header
        :title="__('ai.title')"
        :description="__('ai.description')"
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('contexts.contents.studio', [$context, $content])" variant="ghost">
                    {{ __('ai.back_to_studio') }}
                </flux:button>
                <flux:button :href="route('contexts.contents.show', [$context, $content])" variant="ghost">
                    {{ __('ai.open_reader') }}
                </flux:button>
            </div>
        </x-slot:actions>
    </x-app.page-header>

    <flux:callout>
        {{ __('ai.safety_note') }}
    </flux:callout>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('ai.ask_heading') }}</flux:heading>
                    <flux:text>{{ __('ai.ask_help') }}</flux:text>
                </div>

                <flux:textarea
                    wire:model="prompt"
                    :label="__('ai.prompt')"
                    rows="7"
                    maxlength="8000"
                    :placeholder="__('ai.prompt_placeholder')"
                />

                @if ($assistantError)
                    <flux:callout variant="danger">{{ $assistantError }}</flux:callout>
                @endif

                <div class="flex justify-end">
                    <flux:button wire:click="plan" wire:loading.attr="disabled" variant="primary">
                        {{ __('ai.create_plan') }}
                    </flux:button>
                </div>
            </flux:card>

            @if ($selectedRun)
                @php($proposal = $selectedRun->proposal ?? [])
                <flux:card class="space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ __('ai.proposal') }}</flux:heading>
                            <flux:text dir="auto">{{ $proposal['summary'] ?? '' }}</flux:text>
                        </div>
                        <div class="flex gap-2">
                            <flux:badge>{{ $selectedRun->model }}</flux:badge>
                            <flux:badge>{{ $selectedRun->status }}</flux:badge>
                        </div>
                    </div>

                    @if (($proposal['apply_title'] ?? false) === true)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('ai.title_change') }}</div>
                            <div class="mt-1 font-medium" dir="auto">{{ $proposal['title'] ?? '' }}</div>
                        </div>
                    @endif

                    @if (! empty($proposal['field_updates']))
                        <div class="space-y-2">
                            <flux:heading>{{ __('ai.field_changes') }}</flux:heading>
                            @foreach ($proposal['field_updates'] as $update)
                                <div class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-800">
                                    <div class="font-mono text-xs text-zinc-500">{{ $update['key'] ?? '' }}</div>
                                    <div class="mt-1 whitespace-pre-wrap break-words" dir="auto">{{ is_scalar($update['value'] ?? null) ? var_export($update['value'], true) : 'null' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($proposal['block_operations']))
                        <div class="space-y-2">
                            <flux:heading>{{ __('ai.block_changes') }}</flux:heading>
                            @foreach ($proposal['block_operations'] as $operation)
                                <div class="rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-800">
                                    <div class="flex flex-wrap gap-2">
                                        <flux:badge>{{ $operation['operation'] ?? '' }}</flux:badge>
                                        <flux:badge>{{ $operation['block']['type'] ?? '' }}</flux:badge>
                                    </div>
                                    @if (! empty($operation['block']['text']))
                                        <div class="mt-2 whitespace-pre-wrap" dir="auto">{{ $operation['block']['text'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if (($proposal['apply_presentation'] ?? false) === true)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <flux:heading>{{ __('ai.presentation_change') }}</flux:heading>
                            <div class="mt-1 text-sm text-zinc-500">{{ $proposal['presentation']['base_key'] ?? '' }}</div>
                        </div>
                    @endif

                    @if (! empty($proposal['media_requests']))
                        <flux:callout variant="warning">
                            <div class="space-y-2">
                                <div class="font-medium">{{ __('ai.media_requests') }}</div>
                                <div>{{ __('ai.media_requests_help') }}</div>
                                @foreach ($proposal['media_requests'] as $request)
                                    <div class="rounded-lg border border-current/20 p-2 text-sm">
                                        <strong>{{ $request['kind'] ?? '' }}:</strong>
                                        <span dir="auto">{{ $request['prompt'] ?? '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </flux:callout>
                    @endif

                    <div class="flex justify-end">
                        <flux:button wire:click="apply" wire:loading.attr="disabled" variant="primary">
                            {{ __('ai.apply_plan') }}
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        </div>

        <div class="space-y-4">
            <flux:card class="space-y-3">
                <flux:heading>{{ __('ai.recent') }}</flux:heading>
                @forelse ($recentRuns as $run)
                    <button
                        type="button"
                        wire:click="$set('selectedRunUuid', '{{ $run->uuid }}')"
                        class="w-full rounded-xl border border-zinc-200 p-3 text-start text-sm transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                    >
                        <div class="line-clamp-2" dir="auto">{{ $run->prompt }}</div>
                        <div class="mt-2 flex items-center justify-between gap-2 text-xs text-zinc-500">
                            <span>{{ $run->created_at->format('Y-m-d H:i') }}</span>
                            <span>{{ $run->status }}</span>
                        </div>
                    </button>
                @empty
                    <x-app.empty-state :title="__('ai.none')" />
                @endforelse
            </flux:card>
        </div>
    </div>
</section>
