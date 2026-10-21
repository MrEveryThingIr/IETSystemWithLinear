<div class="space-y-3" data-labels-manager>
    @foreach ($edits as $uuid => $edit)
        <div wire:key="label-{{ $uuid }}" class="flex items-center gap-2">
            <input type="color" wire:model="edits.{{ $uuid }}.color" wire:change="saveLabel('{{ $uuid }}')" class="size-7 shrink-0 cursor-pointer rounded-full border-0 bg-transparent p-0" aria-label="{{ __('planner.labels.color') }}">
            <input type="text" wire:model="edits.{{ $uuid }}.name" wire:blur="saveLabel('{{ $uuid }}')" wire:keydown.enter="saveLabel('{{ $uuid }}')" maxlength="60" dir="auto" class="min-w-0 flex-1 rounded-lg border border-zinc-200 bg-transparent px-2 py-1 text-sm dark:border-zinc-700" aria-label="{{ __('planner.labels.title') }}">
            <flux:button wire:click="remove('{{ $uuid }}')" wire:confirm="{{ __('planner.labels.delete') }}?" size="xs" variant="ghost" icon="trash" :aria-label="__('planner.labels.delete')" />
        </div>
    @endforeach
    <form wire:submit="add" class="flex items-center gap-2 border-t border-zinc-100 pt-3 dark:border-zinc-800">
        <input type="color" wire:model="newColor" class="size-7 shrink-0 cursor-pointer rounded-full border-0 bg-transparent p-0" aria-label="{{ __('planner.labels.color') }}">
        <input type="text" wire:model="newName" maxlength="60" dir="auto" placeholder="{{ __('planner.labels.new') }}" class="min-w-0 flex-1 rounded-lg border border-dashed border-zinc-300 bg-transparent px-2 py-1 text-sm dark:border-zinc-700">
        <flux:button type="submit" size="xs" icon="plus" :aria-label="__('planner.labels.create')" />
    </form>
    @error('newName')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
</div>
