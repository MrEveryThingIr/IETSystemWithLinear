<section class="mx-auto max-w-4xl space-y-6" data-category-review>
    <header class="space-y-2">
        <flux:heading size="xl">{{ __('categories.review.title') }}</flux:heading>
        <flux:text>{{ __('categories.review.help') }}</flux:text>
    </header>

    <x-app.flash-message />

    <nav class="flex flex-wrap gap-1" aria-label="{{ __('categories.review.filter') }}">
        @foreach (['pending', 'approved', 'merged', 'rejected'] as $tab)
            <button type="button" wire:click="$set('status', '{{ $tab }}')"
                class="rounded-full px-3 py-1.5 text-sm {{ $status === $tab ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200' }}">
                {{ __('categories.review.status.'.$tab) }} <span class="tabular-nums opacity-70">{{ $counts[$tab] ?? 0 }}</span>
            </button>
        @endforeach
    </nav>

    <div class="space-y-3">
        @forelse ($suggestions as $suggestion)
            <article wire:key="suggestion-{{ $suggestion->uuid }}" class="space-y-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" data-suggestion="{{ $suggestion->uuid }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1 space-y-1">
                        @if ($paths[$suggestion->uuid] !== '')
                            <div class="text-xs text-zinc-500 [overflow-wrap:anywhere]" dir="auto">{{ $paths[$suggestion->uuid] }} ›</div>
                        @endif
                        <div class="font-semibold [overflow-wrap:anywhere]" dir="auto">{{ $suggestion->concept->displayLabel() }}</div>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                            <x-app.actor-identity :actor="$suggestion->suggestedBy" size="xs" />
                            <span>·</span>
                            <x-app.local-datetime :value="$suggestion->created_at" />
                        </div>
                        @if ($suggestion->note)
                            <p class="text-sm text-zinc-600 [overflow-wrap:anywhere] dark:text-zinc-300" dir="auto">{{ $suggestion->note }}</p>
                        @endif
                        @if ($suggestion->review_note)
                            <p class="text-sm text-zinc-500 [overflow-wrap:anywhere]" dir="auto">{{ __('categories.review.review_note') }}: {{ $suggestion->review_note }}</p>
                        @endif
                    </div>
                    @if ($suggestion->status === 'pending' && $reviewing !== $suggestion->uuid)
                        <flux:button size="sm" variant="primary" wire:click="open('{{ $suggestion->uuid }}')" data-suggestion-review>{{ __('categories.review.review') }}</flux:button>
                    @endif
                </div>

                @if ($reviewing === $suggestion->uuid)
                    <div class="space-y-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/50" data-suggestion-decision>
                        <flux:radio.group wire:model.live="decision" :label="__('categories.review.decision')">
                            <flux:radio value="approve" :label="__('categories.review.approve')" :description="__('categories.review.approve_help')" />
                            <flux:radio value="merge" :label="__('categories.review.merge')" :description="__('categories.review.merge_help')" />
                            <flux:radio value="reject" :label="__('categories.review.reject')" :description="__('categories.review.reject_help')" />
                        </flux:radio.group>
                        @if ($decision !== 'reject')
                            <div class="space-y-1">
                                <flux:label>{{ $decision === 'merge' ? __('categories.review.merge_target') : __('categories.review.approve_parent') }}</flux:label>
                                <livewire:categories.picker wire:model="targetId" :allow-create="false" :key="'review-picker-'.$suggestion->uuid.'-'.$decision" />
                                <flux:error name="targetId" />
                                <flux:error name="target" />
                                <flux:error name="parent" />
                            </div>
                        @endif
                        @if ($decision !== 'approve')
                            <flux:input wire:model="reviewNote" maxlength="500" :label="__('categories.review.review_note')" />
                        @endif
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <flux:button variant="ghost" wire:click="close">{{ __('ui.common.cancel') }}</flux:button>
                            <flux:button variant="primary" wire:click="decide" wire:loading.attr="disabled" wire:target="decide" data-suggestion-confirm>{{ __('categories.review.confirm') }}</flux:button>
                        </div>
                    </div>
                @endif
            </article>
        @empty
            <x-app.empty-state :title="__('categories.review.empty')" />
        @endforelse
    </div>
</section>
