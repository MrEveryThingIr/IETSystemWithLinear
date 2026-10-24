<section class="mx-auto max-w-2xl space-y-6" data-habit-create>
    <div>
        <flux:button :href="route('planner.index')" wire:navigate variant="ghost" size="sm" icon="arrow-left" class="rtl:[&_svg]:rotate-180">{{ __('planner.title') }}</flux:button>
    </div>
    <x-app.page-header :title="__('habits.create.title')" :description="__('habits.create.help')" />

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4">
            <flux:input wire:model="replaces" :label="__('habits.loop.replaces')" :placeholder="__('habits.loop.replaces_example')" maxlength="300" autofocus class="[&_input]:text-base sm:[&_input]:text-sm" data-habit-replaces />
            <flux:input wire:model="routine" :label="__('habits.create.routine')" :placeholder="__('habits.periods.focus_example_replace')" :description="__('habits.create.routine_help')" maxlength="300" class="[&_input]:text-base sm:[&_input]:text-sm" data-habit-routine />
            <details class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <summary class="cursor-pointer text-sm font-medium">{{ __('habits.create.loop_more') }} <span class="font-normal text-zinc-500">({{ __('habits.optional') }})</span></summary>
                <div class="mt-3 space-y-3">
                    <flux:input wire:model="cue" :label="__('habits.loop.cue_replace')" :placeholder="__('habits.loop.cue_example_replace')" maxlength="300" class="[&_input]:text-base sm:[&_input]:text-sm" />
                    <flux:input wire:model="reward" :label="__('habits.loop.reward')" :placeholder="__('habits.loop.reward_example_replace')" maxlength="300" class="[&_input]:text-base sm:[&_input]:text-sm" />
                    <flux:input wire:model="why" :label="__('habits.loop.why')" :placeholder="__('habits.loop.why_example')" maxlength="500" class="[&_input]:text-base sm:[&_input]:text-sm" />
                </div>
            </details>
            <div>
                <div class="mb-1.5 text-sm font-medium">{{ __('habits.periods.length') }}</div>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\Planner\PlanPeriod::LENGTHS as $length)
                        <button type="button" wire:click="$set('days', {{ $length }})" class="inline-flex min-h-10 items-center rounded-full border px-3 text-sm {{ (int) $days === $length ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 dark:border-zinc-600' }}">{{ trans_choice('habits.periods.days', $length, ['count' => $length]) }}</button>
                    @endforeach
                </div>
                <flux:text class="mt-1.5 text-xs">{{ __('habits.periods.length_help') }}</flux:text>
            </div>
            <flux:callout icon="light-bulb" color="teal">{{ __('habits.create.science') }}</flux:callout>
        </flux:card>
        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" data-habit-create-save>{{ __('habits.create.save') }}</flux:button>
        </div>
    </form>
</section>
