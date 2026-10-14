<section class="mx-auto max-w-4xl space-y-6">
    <x-app.page-header :title="__('ui.invitations.title', ['group' => $group->name])" :description="__('ui.invitations.workspace_help')">
        <x-slot:actions>
            <flux:button :href="route('groups.show', $group)" variant="ghost">{{ __('ui.common.back_to_group') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @if ($createdInvitationUrl)
        <flux:callout variant="success" class="space-y-2" x-data="{ copied: false }">
            <p class="font-medium">{{ __('ui.invitations.copy_now') }}</p>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input x-ref="invitationUrl" readonly value="{{ $createdInvitationUrl }}" aria-label="{{ __('ui.invitations.private_link') }}" class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 font-mono text-xs text-zinc-900 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100" />
                <button type="button" x-on:click="navigator.clipboard.writeText($refs.invitationUrl.value).then(() => copied = true)" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">{{ __('ui.invitations.copy_link') }}</button>
            </div>
            <span x-show="copied" role="status" class="text-sm">{{ __('ui.invitations.link_copied') }}</span>
        </flux:callout>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('classroom.invite.link_heading') }}</flux:heading>
                <flux:text>{{ __('classroom.invite.link_help') }}</flux:text>
            </div>
            <form wire:submit="createJoinLink" class="space-y-3">
                <flux:input wire:model="linkLabel" :label="__('classroom.invite.link_label')" :placeholder="__('classroom.invite.link_label_placeholder')" />
                @if ($canGrantRoles && $roles->isNotEmpty())
                    <flux:select wire:model="linkRoleId" :label="__('classroom.invite.role')">
                        <flux:select.option value="">{{ __('classroom.invite.role_member') }}</flux:select.option>
                        @foreach ($roles as $role)
                            <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="linkMaxUses" type="number" min="1" :label="__('classroom.invite.max_uses')" :placeholder="__('classroom.invite.unlimited')" />
                    <flux:input wire:model="linkDays" type="number" min="1" max="365" :label="__('classroom.invite.valid_days')" />
                </div>
                <flux:checkbox wire:model="linkDirect" :label="__('classroom.invite.direct')" :description="__('classroom.invite.direct_help')" />
                @if ($canOpenRegistration)
                    <flux:checkbox wire:model="linkAllowsRegistration" :label="__('classroom.invite.allow_registration')" :description="__('classroom.invite.allow_registration_help')" />
                @endif
                <flux:button type="submit" variant="primary" class="w-full">{{ __('classroom.invite.create_link') }}</flux:button>
            </form>
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('classroom.invite.person_heading') }}</flux:heading>
                <flux:text>{{ __('classroom.invite.person_help') }}</flux:text>
            </div>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('classroom.invite.search')" :placeholder="__('classroom.invite.search_placeholder')" autocomplete="off" />
            @if ($canGrantRoles && $roles->isNotEmpty())
                <flux:select wire:model="personRoleId" :label="__('classroom.invite.role')">
                    <flux:select.option value="">{{ __('classroom.invite.role_member') }}</flux:select.option>
                    @foreach ($roles as $role)
                        <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @if (mb_strlen(trim(ltrim($search, '@'))) >= 2)
                <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($candidates as $candidate)
                        <li wire:key="candidate-{{ $candidate->id }}" class="flex items-center justify-between gap-3 px-3 py-2">
                            <x-app.actor-identity :actor="$candidate->actor" size="sm" />
                            <flux:button size="sm" variant="primary" wire:click="invitePerson({{ $candidate->id }})">{{ __('classroom.invite.invite') }}</flux:button>
                        </li>
                    @empty
                        <li class="px-3 py-3 text-sm text-zinc-500">{{ __('classroom.invite.no_matches') }}</li>
                    @endforelse
                </ul>
            @else
                <flux:text class="text-sm">{{ __('classroom.invite.search_hint') }}</flux:text>
            @endif
        </flux:card>
    </div>

    <div class="space-y-4">
        @forelse ($invitations as $invitation)
            <flux:card class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 space-y-2">
                        @php($isExhausted = $invitation->max_uses !== null && $invitation->uses_count >= $invitation->max_uses)
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge :color="$invitation->revoked_at ? 'red' : ($invitation->expires_at?->isPast() || $isExhausted ? 'amber' : 'green')">
                                {{ $invitation->revoked_at ? __('ui.common.revoked') : ($invitation->expires_at?->isPast() ? __('ui.common.expired') : ($isExhausted ? __('ui.invitations.exhausted') : __('ui.common.active'))) }}
                            </flux:badge>
                            <flux:text>{{ __('ui.invitations.uses', ['used' => $invitation->uses_count, 'maximum' => $invitation->max_uses ?? '∞']) }}</flux:text>
                            @if ($invitation->label)<flux:badge color="indigo">{{ $invitation->label }}</flux:badge>@endif
                            @if ($invitation->email)<flux:badge>{{ $invitation->maskedEmail() }}</flux:badge>@endif
                            @if ($invitation->admitsDirectly())<flux:badge color="green">{{ __('classroom.invite.badge_direct') }}</flux:badge>@endif
                            @if ($invitation->grantedRole)<flux:badge>{{ $invitation->grantedRole->name }}</flux:badge>@endif
                            @if ($invitation->allows_registration)<flux:badge color="amber">{{ __('classroom.invite.badge_registration') }}</flux:badge>@endif
                        </div>
                        <flux:text class="text-sm">{{ __('ui.invitations.secret_not_recoverable') }}</flux:text>
                        <flux:text class="text-sm"><span>{{ __('ui.invitations.created') }}</span> <x-app.local-datetime :value="$invitation->created_at" /> · <span>{{ __('ui.invitations.expires') }}</span> @if ($invitation->expires_at)<x-app.local-datetime :value="$invitation->expires_at" />@else {{ __('ui.invitations.never') }} @endif</flux:text>
                    </div>
                    @if (! $invitation->revoked_at)
                        <flux:button wire:click="revoke({{ $invitation->id }})" variant="danger" size="sm">{{ __('ui.invitations.revoke') }}</flux:button>
                    @endif
                </div>

                @if ($invitation->admissions->isNotEmpty())
                    <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                        <p class="mb-2 text-sm font-medium">{{ __('ui.invitations.invitees') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($invitation->admissions as $admission)
                                <a href="{{ route('admissions.show', $admission) }}" class="rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <flux:badge>
                                        <span class="inline-flex flex-wrap items-center gap-2"><x-app.actor-identity :actor="$admission->candidate" size="xs" /><span>&middot; {{ __('ui.status.'.$admission->status) }}</span></span>
                                    </flux:badge>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </flux:card>
        @empty
            <x-app.empty-state :title="__('ui.invitations.none')" :description="__('ui.invitations.none_help')" />
        @endforelse
        {{ $invitations->links() }}
    </div>
</section>
