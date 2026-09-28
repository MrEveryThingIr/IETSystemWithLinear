<section class="mx-auto max-w-2xl space-y-6">
    <x-app.page-header :title="__('planning_baseline.edit.title')" :description="__('planning_baseline.edit.help')" />

    <form wire:submit="save" class="space-y-5">
        <flux:card class="space-y-5">
            <flux:input wire:model="title" :label="__('planning_baseline.fields.title')" maxlength="180" />

            <div>
                <div class="mb-2 text-sm font-medium">{{ __('planning_baseline.fields.when') }}</div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="cursor-pointer rounded-xl border p-4 {{ $timingMode === 'fixed' ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-900' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <input class="sr-only" type="radio" wire:model.live="timingMode" value="fixed">
                        <div class="font-medium">{{ __('planning_baseline.timing.fixed') }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ __('planning_baseline.timing.fixed_help') }}</div>
                    </label>
                    <label class="cursor-pointer rounded-xl border p-4 {{ $timingMode === 'flexible_day' ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-900' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <input class="sr-only" type="radio" wire:model.live="timingMode" value="flexible_day">
                        <div class="font-medium">{{ __('planning_baseline.timing.flexible_day') }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ __('planning_baseline.timing.flexible_day_help') }}</div>
                    </label>
                </div>
            </div>

            <x-app.calendar-date-input model="date" :label="__('planning_baseline.fields.date')" />

            @if ($timingMode === 'fixed')
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="startTime" type="time" :label="__('planning_baseline.fields.starts')" />
                    <flux:input wire:model="endTime" type="time" :label="__('planning_baseline.fields.ends')" />
                </div>
            @endif

            <flux:input wire:model="category" :label="__('planning_baseline.fields.category')" maxlength="80" />
            <flux:textarea wire:model="description" :label="__('planning_baseline.fields.notes')" rows="3" maxlength="10000" />
        </flux:card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <flux:button :href="route('planner.show', $plan)" variant="ghost">{{ __('studio.cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                {{ __('planning_baseline.edit.submit') }}
            </flux:button>
        </div>
    </form>
</section>
