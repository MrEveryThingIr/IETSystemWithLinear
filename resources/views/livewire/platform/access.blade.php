<section class="space-y-8">
    <x-app.page-header
        :title="__('ui.platform_access.title')"
        :description="__('ui.platform_access.help')"
    />

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    <flux:card class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="lg">{{ __('ui.platform_access.create_groups') }}</flux:heading>
                <flux:text>{{ __('ui.platform_access.create_groups_help') }}</flux:text>
            </div>

            @if ($canCreateGroups)
                <flux:badge color="green">{{ __('ui.platform_access.granted') }}</flux:badge>
            @elseif ($myRequests->firstWhere('status', 'pending'))
                <flux:badge color="amber">{{ __('ui.platform_access.pending_review') }}</flux:badge>
            @else
                <flux:badge>{{ __('ui.platform_access.not_granted') }}</flux:badge>
            @endif
        </div>

        @unless ($canCreateGroups || $myRequests->firstWhere('status', 'pending'))
            <flux:textarea
                wire:model="requestReason"
                :label="__('ui.platform_access.reason_optional')"
                rows="3"
                :placeholder="__('ui.platform_access.reason_placeholder')"
            />
            <flux:button
                wire:click="requestGroupCreation"
                wire:loading.attr="disabled"
                wire:target="requestGroupCreation"
                variant="primary"
            >
                {{ __('ui.platform_access.request_group_creation') }}
            </flux:button>
        @endunless
    </flux:card>

    @if ($myRequests->isNotEmpty())
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('ui.platform_access.your_requests') }}</flux:heading>

            @foreach ($myRequests as $accessRequest)
                <flux:card class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <flux:text class="font-medium">
                            {{ __('ui.platform_access.roles.'.$accessRequest->role->value) }}
                        </flux:text>
                        <flux:badge>{{ __('ui.platform_access.statuses.'.$accessRequest->status) }}</flux:badge>
                    </div>

                    @if ($accessRequest->reason)
                        <flux:text>{{ $accessRequest->reason }}</flux:text>
                    @endif

                    @if ($accessRequest->review_note)
                        <flux:text>
                            {{ __('ui.platform_access.reviewer_note', ['note' => $accessRequest->review_note]) }}
                        </flux:text>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif

    @if ($canManagePlatformAccess)
        <div class="space-y-3">
            <flux:heading size="lg">{{ __('ui.platform_access.pending_requests') }}</flux:heading>

            @forelse ($pendingRequests as $accessRequest)
                <flux:card class="space-y-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            @if ($accessRequest->user->actor)
                                <x-app.actor-identity :actor="$accessRequest->user->actor" />
                            @else
                                <flux:heading>{{ $accessRequest->user->username }}</flux:heading>
                            @endif

                            <flux:text>
                                {{ $accessRequest->user->email }}
                                ·
                                {{ __('ui.platform_access.roles.'.$accessRequest->role->value) }}
                            </flux:text>
                        </div>

                        <flux:badge>{{ __('ui.platform_access.pending') }}</flux:badge>
                    </div>

                    @if ($accessRequest->reason)
                        <flux:text>{{ $accessRequest->reason }}</flux:text>
                    @endif

                    <flux:textarea
                        wire:model="reviewNotes.{{ $accessRequest->id }}"
                        :label="__('ui.platform_access.reviewer_note_optional')"
                        rows="2"
                    />

                    <div class="flex flex-wrap gap-2">
                        <flux:button wire:click="review({{ $accessRequest->id }}, true)" variant="primary">
                            {{ __('ui.platform_access.approve') }}
                        </flux:button>
                        <flux:button wire:click="review({{ $accessRequest->id }}, false)" variant="ghost">
                            {{ __('ui.platform_access.reject') }}
                        </flux:button>
                    </div>
                </flux:card>
            @empty
                <x-app.empty-state
                    :title="__('ui.platform_access.no_pending')"
                    :description="__('ui.platform_access.no_pending_help')"
                />
            @endforelse
        </div>
    @endif
</section>
