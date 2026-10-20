{{-- Rename / delete-for-good dialogs; state lives in App\Livewire\Concerns\ManagesContentLifecycle. --}}
<x-app.dialog :show="$renameUuid !== null" close="closeRename" :title="__('studio.rename.title')" size="sm">
    <form wire:submit="rename" class="space-y-4" data-rename-dialog>
        <flux:input wire:model="renameTitle" :label="__('ui.content.title')" maxlength="255" dir="auto" autofocus />
        <p class="text-xs text-zinc-500">{{ __('studio.rename.help') }}</p>
        <div class="flex justify-end gap-2">
            <flux:button type="button" wire:click="closeRename" variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('studio.rename.save') }}</flux:button>
        </div>
    </form>
</x-app.dialog>

<x-app.dialog :show="$deleteUuid !== null" close="closeDelete" :title="__('studio.delete.title')" size="sm">
    <div class="space-y-4" data-delete-dialog>
        <p class="text-sm font-medium" dir="auto">«{{ $deleteAssessment['title'] }}»</p>

        @if ($deleteAssessment['blockers'] !== [])
            <flux:callout variant="warning" icon="exclamation-triangle" data-delete-blocked>
                <flux:callout.heading>{{ __('studio.delete.blocked_title') }}</flux:callout.heading>
                <flux:callout.text>
                    <ul class="mt-1 list-disc space-y-0.5 ps-5">
                        @foreach ($deleteAssessment['blockers'] as $key => $count)
                            <li>{{ trans_choice('studio.delete.blockers.'.$key, $count, ['count' => $count]) }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2">{{ __('studio.delete.blocked_help') }}</p>
                </flux:callout.text>
            </flux:callout>
            <div class="flex justify-end gap-2">
                <flux:button type="button" wire:click="closeDelete" variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
                @if ($deleteAssessment['can_archive'])
                    <flux:button type="button" wire:click="archiveInstead" icon="archive-box">{{ __('studio.delete.archive_instead') }}</flux:button>
                @endif
            </div>
        @else
            <flux:callout variant="danger" icon="trash">
                <flux:callout.text>
                    {{ __('studio.delete.warning') }}
                    @if ($deleteAssessment['removes'] !== [])
                        <ul class="mt-2 list-disc space-y-0.5 ps-5">
                            @foreach ($deleteAssessment['removes'] as $key => $count)
                                <li>{{ trans_choice('studio.delete.removes.'.$key, $count, ['count' => $count]) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </flux:callout.text>
            </flux:callout>
            <form wire:submit="deleteForGood" class="space-y-3">
                <flux:input wire:model="deleteConfirmation" :label="__('studio.delete.type_title')" dir="auto" autocomplete="off" data-delete-confirmation />
                <div class="flex justify-end gap-2">
                    <flux:button type="button" wire:click="closeDelete" variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
                    <flux:button type="submit" variant="danger" icon="trash" data-delete-confirm>{{ __('studio.delete.confirm') }}</flux:button>
                </div>
            </form>
        @endif
    </div>
</x-app.dialog>
