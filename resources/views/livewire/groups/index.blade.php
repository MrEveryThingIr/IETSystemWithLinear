<section class="space-y-8">
    <x-app.page-header :title="__('ui.groups.title')" :description="__('ui.groups.overview')">
        @can('create', App\Models\Group::class)
            <x-slot:actions><flux:button :href="route('groups.create')" variant="primary" icon="plus">{{ __('ui.groups.create') }}</flux:button></x-slot:actions>
        @endcan
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success" class="break-all">{{ session('status') }}</flux:callout>
    @endif

    <div class="space-y-4">
        <flux:heading size="lg">{{ __('ui.groups.your_groups') }}</flux:heading>
        @if ($memberships->isEmpty())
            <x-app.empty-state :title="__('ui.groups.no_memberships')" :description="__('ui.groups.no_memberships_help')" />
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($memberships as $membership)
                    <flux:card class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <flux:heading size="lg">{{ $membership->group->name }}</flux:heading>
                            <flux:badge>{{ $roles[$membership->group_id] }}</flux:badge>
                        </div>
                        <flux:text>{{ $membership->group->description ?: __('ui.groups.no_description') }}</flux:text>
                        @if ($requiresAgreementAcceptance[$membership->group_id])
                            <flux:callout variant="warning">{{ __('ui.groups.new_agreement_pending') }}</flux:callout>
                            <flux:button :href="route('groups.accept-agreements', $membership->group)" class="w-full sm:w-auto" size="sm" variant="primary">{{ __('ui.groups.review_agreements') }}</flux:button>
                        @else
                            <flux:button :href="route('groups.show', $membership->group)" class="w-full sm:w-auto" size="sm" variant="primary">{{ __('ui.groups.open') }}</flux:button>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        @endif
    </div>

    @if ($receivedInvitations->isNotEmpty())
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('ui.groups.received') }}</flux:heading>
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($receivedInvitations as $admission)
                    <flux:card class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1">
                            <flux:heading>{{ $admission->group->name }}</flux:heading>
                            <flux:text>
                                {{ __('ui.groups.invited_by', ['username' => $admission->sourceInvitation->inviter->user?->username ?? __('ui.invitation.group_member')]) }}
                            </flux:text>
                            <flux:badge>{{ __('ui.status.'.$admission->status) }}</flux:badge>
                        </div>
                        <flux:button :href="route('admissions.show', $admission)" size="sm">{{ __('ui.groups.view_admission') }}</flux:button>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif

    @if ($sentInvitations->isNotEmpty())
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('ui.groups.people_invited') }}</flux:heading>
            <div class="space-y-3">
                @foreach ($sentInvitations as $invitation)
                    <flux:card class="space-y-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <flux:heading>{{ $invitation->group->name }}</flux:heading>
                                <flux:text>{{ $invitation->email ?: __('ui.groups.reusable_link') }} · {{ __('ui.groups.accepted_count', ['count' => $invitation->acceptances_count]) }}</flux:text>
                            </div>
                            <flux:button :href="route('groups.invitations', $invitation->group)" size="sm" variant="ghost">{{ __('ui.groups.manage') }}</flux:button>
                        </div>
                        @if ($invitation->admissions->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach ($invitation->admissions as $admission)
                                    <a href="{{ route('admissions.show', $admission) }}" class="rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                        <flux:badge>{{ $admission->candidate->user?->username ?? __('ui.common.unknown_account') }} · {{ __('ui.status.'.$admission->status) }}</flux:badge>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif
</section>
