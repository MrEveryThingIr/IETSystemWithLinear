<flux:card class="space-y-3" data-plan-content>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <flux:heading size="lg">{{ __('plan_content.title') }}</flux:heading>
    </div>

    @forelse ($entries as $e)
        @php($link = $e['link'])
        <div wire:key="plan-content-{{ $link->uuid }}" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700" data-plan-content-entry>
            <div class="min-w-0">
                <a href="{{ $e['url'] }}" class="block truncate font-medium hover:underline" dir="auto">{{ $e['title'] }}</a>
                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                    @if ($link->occurrence)
                        <a class="underline-offset-2 hover:underline" href="{{ route('planner.show', $plan) }}#occurrence-{{ $link->occurrence->uuid }}"><x-app.local-date :value="$link->occurrence->scheduled_start_at->setTimezone($displayTimezone)" /></a>
                    @else
                        <span>{{ __('plan_content.whole_plan') }}</span>
                    @endif
                    <flux:badge size="sm" :color="$e['state'] === 'published' ? 'green' : 'blue'">{{ __('plan_content.state.'.$e['state']) }}</flux:badge>
                    @if ($e['state'] === 'published')<span>{{ __('plan_content.audience.'.$e['audience']) }}</span>@endif
                </div>
            </div>
            @if ($e['mine'] || $canWrite)
                <flux:button size="sm" variant="ghost" wire:click="unlink('{{ $link->uuid }}')" wire:confirm="{{ __('plan_content.unlink_confirm') }}">{{ __('plan_content.unlink') }}</flux:button>
            @endif
        </div>
    @empty
        <p class="text-sm text-zinc-500" data-plan-content-empty>{{ __('plan_content.empty') }}</p>
    @endforelse

    @if ($canWrite)
        <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
            <flux:select wire:model.live="occurrence" :label="__('plan_content.belongs_to')" data-plan-content-day>
                <option value="">{{ __('plan_content.whole_plan') }}</option>
                @foreach ($occurrences as $o)<option value="{{ $o->uuid }}">{{ \App\Support\TemporalCalendar::dateLabelWithEquivalent($o->scheduled_start_at, $viewer, $displayTimezone) }}</option>@endforeach
            </flux:select>
            <div class="flex flex-wrap items-end gap-2">
                <flux:button size="sm" variant="primary" icon="plus" x-on:click="$dispatch('open-content-create', { kind: 'note', plan: '{{ $plan->uuid }}', occurrence: $wire.occurrence })" data-plan-content-add>{{ __('plan_content.add') }}</flux:button>
                @if ($attachable->isNotEmpty())
                    <flux:select size="sm" wire:model="attach" class="min-w-48 max-w-xs" aria-label="{{ __('plan_content.attach_existing') }}">
                        <option value="">{{ __('plan_content.attach_existing') }}</option>
                        @foreach ($attachable as $c)<option value="{{ $c['uuid'] }}">{{ $c['title'] }}</option>@endforeach
                    </flux:select>
                    <flux:button size="sm" wire:click="attachExisting" wire:loading.attr="disabled" wire:target="attachExisting">{{ __('plan_content.attach') }}</flux:button>
                @endif
            </div>
            <p class="text-xs text-zinc-500">{{ __('plan_content.hint') }}</p>
        </div>
    @endif
</flux:card>
