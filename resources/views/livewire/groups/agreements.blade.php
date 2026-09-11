<section class="mx-auto max-w-4xl space-y-6 px-4 sm:px-0">
    <x-app.page-header :title="__('ui.agreements.title', ['group' => $group->name])" :description="__('ui.agreements.manage_intro')">
        <x-slot:actions>
            <flux:button :href="route('groups.show', $group)" variant="ghost">{{ __('ui.common.back_to_group') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <flux:callout icon="information-circle">
        {{ __('ui.agreements.workflow') }}
    </flux:callout>

    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('ui.agreements.create') }}</flux:heading>
            <flux:text>{{ __('ui.agreements.initial_help') }}</flux:text>
        </div>
        <form wire:submit="create" class="space-y-3">
            <flux:input wire:model="name" :label="__('ui.agreements.name')" />
            <flux:textarea wire:model="content" :label="__('ui.agreements.terms')" rows="6" />
            <flux:checkbox wire:model="required" :label="__('ui.agreements.applicants_accept')" />
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto">{{ __('ui.agreements.create_button') }}</flux:button>
        </form>
    </flux:card>

    @forelse ($agreements as $agreement)
        <flux:card class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ $agreement->name }}</flux:heading>
                    <flux:text>{{ $agreement->required_for_admission ? __('ui.agreements.required_for_admission') : __('ui.agreements.optional') }}</flux:text>
                </div>
                <flux:button wire:click="startRevision({{ $agreement->id }})" size="sm" variant="ghost">{{ __('ui.agreements.new_version') }}</flux:button>
            </div>

            <div class="space-y-4">
                @foreach ($agreement->versions as $version)
                    <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading>{{ __('ui.common.version', ['version' => $version->version]) }}</flux:heading>
                                <flux:badge :color="match ($version->status) { 'active' => 'green', 'rejected' => 'red', 'scheduled' => 'blue', 'approved' => 'indigo', default => 'zinc' }">
                                    {{ __('ui.status.'.$version->status) }}
                                </flux:badge>
                            </div>
                            @if ($version->effective_from)
                                <flux:text class="text-sm">{{ __('ui.agreements.effective', ['date' => $version->effective_from->translatedFormat('M j, Y H:i')]) }}</flux:text>
                            @endif
                        </div>

                        <div class="whitespace-pre-wrap break-words rounded-lg bg-zinc-50 p-3 text-sm leading-6 dark:bg-zinc-800" dir="auto">{{ $version->content }}</div>

                        @if ($version->rationale)
                            <flux:text><span class="font-medium">{{ __('ui.agreements.revision_rationale') }}</span> {{ $version->rationale }}</flux:text>
                        @endif
                        @if ($version->decision_note)
                            <flux:text><span class="font-medium">{{ __('ui.agreements.decision_note') }}</span> {{ $version->decision_note }}</flux:text>
                        @endif

                        @if ($version->status === 'draft')
                            <flux:button wire:click="propose({{ $version->id }})" size="sm" variant="primary">{{ __('ui.agreements.submit_approval') }}</flux:button>
                        @elseif ($version->status === 'proposed')
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <flux:button wire:click="approve({{ $version->id }})" size="sm" variant="primary">{{ __('ui.agreements.approve') }}</flux:button>
                                <flux:button wire:click="requestClarification({{ $version->id }})" size="sm">{{ __('ui.agreements.request_clarification') }}</flux:button>
                                <flux:button wire:click="reject({{ $version->id }})" size="sm" variant="danger">{{ __('ui.agreements.reject') }}</flux:button>
                            </div>
                        @elseif (in_array($version->status, ['approved', 'scheduled'], true))
                            <div class="space-y-3">
                                <flux:button wire:click="activate({{ $version->id }})" size="sm" variant="primary">{{ __('ui.agreements.activate_now') }}</flux:button>
                                @if ($version->status === 'approved')
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                        <flux:input wire:model="effectiveFrom" type="datetime-local" :label="__('ui.agreements.activation_time')" />
                                        <flux:input wire:model="effectiveUntil" type="datetime-local" :label="__('ui.agreements.end_time')" />
                                        <flux:button wire:click="schedule({{ $version->id }})" size="sm">{{ __('ui.agreements.schedule') }}</flux:button>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($revisingAgreementId === $agreement->id)
                <form wire:submit="revise({{ $agreement->id }})" class="space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800">
                    <flux:heading>{{ __('ui.agreements.create_next') }}</flux:heading>
                    <flux:textarea wire:model="revisionContent" :label="__('ui.agreements.revised_terms')" rows="6" />
                    <flux:textarea wire:model="revisionRationale" :label="__('ui.agreements.rationale')" rows="3" />
                    <flux:checkbox wire:model="reacceptanceRequired" :label="__('ui.agreements.members_reaccept')" />
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <flux:button type="submit" variant="primary">{{ __('ui.agreements.save_draft') }}</flux:button>
                        <flux:button wire:click="cancelRevision" variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
                    </div>
                </form>
            @endif
        </flux:card>
    @empty
        <x-app.empty-state :title="__('ui.agreements.no_agreements')" :description="__('ui.agreements.no_agreements_help')" />
    @endforelse

    @if ($agreements->isNotEmpty())
        <flux:card class="space-y-2">
            <flux:heading>{{ __('ui.agreements.review_notes') }}</flux:heading>
            <flux:text>{{ __('ui.agreements.review_notes_help') }}</flux:text>
            <flux:textarea wire:model="decisionNote" :label="__('ui.agreements.review_note_label')" rows="3" />
        </flux:card>
    @endif
</section>
