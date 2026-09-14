<section class="space-y-6">
    <x-app.page-header :title="__('ui.spaces.manage_title', ['group' => $group->name])" :description="__('ui.spaces.manage_help')">
        <x-slot:actions>
            <flux:button :href="route('groups.index')" variant="ghost">{{ __('ui.common.all_groups') }}</flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <x-app.group-space-tabs :group="$group" :managing="true" />

    @if (session('status'))
        <flux:callout variant="success">{{ session('status') }}</flux:callout>
    @endif

    @if ($canCreateSpaces)
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('ui.spaces.create') }}</flux:heading>
                <flux:text>{{ __('ui.spaces.create_help') }}</flux:text>
            </div>
            <form wire:submit="createSpace" class="grid gap-4 lg:grid-cols-[1fr_16rem_auto] lg:items-end">
                <flux:input wire:model="newSpaceName" :label="__('ui.spaces.name')" />
                <flux:select wire:model="newSpaceAccessMode" :label="__('ui.spaces.access_mode')">
                    <option value="group">{{ __('ui.spaces.access_group') }}</option>
                    <option value="restricted">{{ __('ui.spaces.access_restricted') }}</option>
                </flux:select>
                <flux:button type="submit" variant="primary">{{ __('ui.spaces.create_button') }}</flux:button>
            </form>
        </flux:card>
    @endif

    <div class="grid gap-6 xl:grid-cols-[18rem_1fr]">
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('ui.spaces.manageable') }}</flux:heading>
            @forelse ($manageableSpaces as $candidate)
                <flux:button
                    wire:click="selectSpace({{ $candidate->id }})"
                    class="w-full justify-start"
                    :variant="$selectedSpace?->is($candidate) ? 'primary' : 'ghost'"
                >
                    # {{ $candidate->name }}
                </flux:button>
            @empty
                <flux:text>{{ __('ui.spaces.none_manageable') }}</flux:text>
            @endforelse
        </flux:card>

        @if ($selectedSpace)
            <div class="space-y-6">
                <flux:card class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <flux:heading size="lg"># {{ $selectedSpace->name }}</flux:heading>
                            <flux:text>
                                {{ $selectedSpace->access_mode === 'group' ? __('ui.spaces.access_group') : __('ui.spaces.access_restricted') }}
                            </flux:text>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @can('view', $selectedSpace)
                                <flux:button :href="route('groups.spaces.show', [$group, $selectedSpace])" size="sm" variant="ghost">
                                    {{ __('ui.spaces.open') }}
                                </flux:button>
                            @endcan
                            @unless ($selectedSpace->is_default)
                                <flux:button wire:click="beginEdit({{ $selectedSpace->id }})" size="sm" variant="ghost">
                                    {{ __('ui.groups.edit') }}
                                </flux:button>
                                <flux:button wire:click="archiveSpace({{ $selectedSpace->id }})" size="sm" variant="danger">
                                    {{ __('ui.spaces.archive') }}
                                </flux:button>
                            @endunless
                        </div>
                    </div>

                    @if ($editingSpaceId === $selectedSpace->id)
                        <form wire:submit="updateSpace" class="grid gap-4 lg:grid-cols-[1fr_16rem_auto] lg:items-end">
                            <flux:input wire:model="editingSpaceName" :label="__('ui.spaces.name')" />
                            <flux:select wire:model="editingSpaceAccessMode" :label="__('ui.spaces.access_mode')">
                                <option value="group">{{ __('ui.spaces.access_group') }}</option>
                                <option value="restricted">{{ __('ui.spaces.access_restricted') }}</option>
                            </flux:select>
                            <div class="flex gap-2">
                                <flux:button type="submit" variant="primary">{{ __('ui.common.save') }}</flux:button>
                                <flux:button wire:click="cancelEdit" variant="ghost">{{ __('ui.common.cancel') }}</flux:button>
                            </div>
                        </form>
                    @elseif ($selectedSpace->is_default)
                        <flux:callout>{{ __('ui.spaces.general_locked') }}</flux:callout>
                    @endif
                </flux:card>

                <flux:card class="space-y-5">
                    <div>
                        <flux:heading size="lg">{{ __('ui.spaces.participants') }}</flux:heading>
                        <flux:text>{{ __('ui.spaces.participants_help') }}</flux:text>
                    </div>

                    <div class="space-y-3">
                        <flux:input
                            wire:model.live.debounce.300ms="actorSearch"
                            :label="__('ui.spaces.find_actor')"
                            :placeholder="__('ui.spaces.find_actor_placeholder')"
                        />
                        @if ($actorResults->isNotEmpty())
                            <div class="space-y-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                @foreach ($actorResults as $actor)
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <flux:text class="font-medium">
                                                {{ $actor->user?->username ?? __('ui.spaces.accountless_actor', ['id' => $actor->id]) }}
                                            </flux:text>
                                            <flux:text class="text-xs">{{ __('ui.spaces.actor_status', ['id' => $actor->id, 'status' => $actor->status]) }}</flux:text>
                                        </div>
                                        <flux:button wire:click="chooseActor({{ $actor->id }})" size="sm" variant="ghost">
                                            {{ __('ui.spaces.choose') }}
                                        </flux:button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <form wire:submit="setParticipant" class="grid gap-4 lg:grid-cols-[10rem_12rem_12rem_auto] lg:items-end">
                        <flux:input wire:model="participantActorId" :label="__('ui.spaces.actor_id')" inputmode="numeric" />
                        <flux:select wire:model="participantAccess" :label="__('ui.spaces.access')">
                            <option value="allow">{{ __('ui.spaces.allow') }}</option>
                            <option value="deny">{{ __('ui.spaces.deny') }}</option>
                        </flux:select>
                        <flux:select wire:model="participantRole" :label="__('ui.spaces.role')">
                            <option value="participant">{{ __('ui.spaces.participant') }}</option>
                            <option value="manager">{{ __('ui.spaces.manager') }}</option>
                        </flux:select>
                        <flux:button type="submit" variant="primary">{{ __('ui.spaces.set_rule') }}</flux:button>
                    </form>

                    <div class="space-y-3">
                        @forelse ($participants as $participant)
                            <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                                <div class="space-y-1">
                                    <flux:heading>
                                        {{ $participant->actor->user?->username ?? __('ui.spaces.accountless_actor', ['id' => $participant->actor_id]) }}
                                    </flux:heading>
                                    <div class="flex flex-wrap gap-2">
                                        <flux:badge>{{ __('ui.spaces.'.$participant->access) }}</flux:badge>
                                        <flux:badge>{{ __('ui.spaces.'.$participant->role) }}</flux:badge>
                                        <flux:text class="text-xs">
                                            {{ __('ui.spaces.granted_by', ['username' => $participant->grantedBy->user?->username ?? __('ui.spaces.accountless_actor', ['id' => $participant->granted_by_actor_id])]) }}
                                        </flux:text>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if ($participant->access === 'allow' && $participant->role !== 'manager')
                                        <flux:button wire:click="promoteParticipant({{ $participant->actor_id }})" size="sm" variant="ghost">
                                            {{ __('ui.spaces.promote_manager') }}
                                        </flux:button>
                                    @endif
                                    @if ($participant->access !== 'deny')
                                        <flux:button wire:click="denyParticipant({{ $participant->actor_id }})" size="sm" variant="ghost">
                                            {{ __('ui.spaces.deny') }}
                                        </flux:button>
                                    @endif
                                    <flux:button wire:click="removeParticipant({{ $participant->actor_id }})" size="sm" variant="danger">
                                        {{ __('ui.spaces.remove_rule') }}
                                    </flux:button>
                                </div>
                            </div>
                        @empty
                            <x-app.empty-state :title="__('ui.spaces.no_rules')" :description="__('ui.spaces.no_rules_help')" />
                        @endforelse
                    </div>
                </flux:card>

                <flux:card class="space-y-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('ui.content.design_title') }}</flux:heading>
                            <flux:text>{{ __('ui.content.design_help') }}</flux:text>
                        </div>
                        <flux:button wire:click="newDefinition" size="sm" variant="ghost">
                            {{ __('ui.content.new_definition') }}
                        </flux:button>
                    </div>

                    <div class="space-y-3">
                        @forelse ($contentDefinitions as $definition)
                            @php($currentDefinitionVersion = $definition->versions->firstWhere('version', $definition->current_version))
                            <div wire:key="content-definition-{{ $definition->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <flux:heading>{{ $definition->name }}</flux:heading>
                                            <flux:badge>{{ __('ui.content.definition_status_'.$definition->status) }}</flux:badge>
                                            <flux:badge>{{ __('ui.content.definition_version', ['version' => $definition->current_version]) }}</flux:badge>
                                        </div>
                                        @if ($definition->description)
                                            <flux:text>{{ $definition->description }}</flux:text>
                                        @endif
                                        @if ($currentDefinitionVersion)
                                            <flux:text class="font-mono text-[11px] text-zinc-500">sha256:{{ $currentDefinitionVersion->content_hash }}</flux:text>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($definition->status !== 'archived')
                                            <flux:button wire:click="editDefinition({{ $definition->id }})" size="sm" variant="ghost">
                                                {{ __('ui.groups.edit') }}
                                            </flux:button>
                                        @endif
                                        @if ($definition->status === 'draft')
                                            <flux:button wire:click="activateDefinition({{ $definition->id }})" size="sm" variant="primary">
                                                {{ __('ui.content.activate_definition') }}
                                            </flux:button>
                                        @endif
                                        @if ($definition->status !== 'archived')
                                            <flux:button wire:click="archiveDefinition({{ $definition->id }})" size="sm" variant="danger">
                                                {{ __('ui.content.archive_definition') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <x-app.empty-state :title="__('ui.content.no_definitions')" :description="__('ui.content.no_definitions_help')" />
                        @endforelse
                    </div>

                    <form wire:submit="saveDefinition" class="space-y-5 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                        <div>
                            <flux:heading>{{ $editingDefinitionId ? __('ui.content.edit_definition') : __('ui.content.create_definition') }}</flux:heading>
                            <flux:text>{{ __('ui.content.builder_help') }}</flux:text>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <flux:input wire:model="definitionName" :label="__('ui.content.definition_name')" />
                            <flux:input wire:model="definitionDescription" :label="__('ui.content.definition_description')" />
                        </div>

                        <div class="space-y-4">
                            @foreach ($definitionFields as $index => $field)
                                <div wire:key="definition-field-{{ $index }}-{{ $field['key'] ?? 'new' }}" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                    <div class="grid gap-4 lg:grid-cols-3">
                                        <flux:input wire:model="definitionFields.{{ $index }}.key" :label="__('ui.content.field_key')" placeholder="report_date" />
                                        <flux:input wire:model="definitionFields.{{ $index }}.label" :label="__('ui.content.field_label')" />
                                        <flux:select wire:model.live="definitionFields.{{ $index }}.type" :label="__('ui.content.field_type')">
                                            <option value="short_text">{{ __('ui.content.type_short_text') }}</option>
                                            <option value="long_text">{{ __('ui.content.type_long_text') }}</option>
                                            <option value="number">{{ __('ui.content.type_number') }}</option>
                                            <option value="date">{{ __('ui.content.type_date') }}</option>
                                            <option value="boolean">{{ __('ui.content.type_boolean') }}</option>
                                            <option value="select">{{ __('ui.content.type_select') }}</option>
                                        </flux:select>
                                    </div>

                                    <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                                        <flux:input wire:model="definitionFields.{{ $index }}.help" :label="__('ui.content.field_help')" />
                                        <flux:checkbox wire:model="definitionFields.{{ $index }}.required" :label="__('ui.content.field_required')" />
                                    </div>

                                    @if (($definitionFields[$index]['type'] ?? null) === 'select')
                                        <div class="space-y-1">
                                            <flux:textarea
                                                wire:model="definitionFields.{{ $index }}.options_text"
                                                :label="__('ui.content.select_options')"
                                                rows="3"
                                                placeholder="on_track|On track&#10;delayed|Delayed&#10;blocked|Blocked"
                                            />
                                            <flux:text class="text-xs text-zinc-500">{{ __('ui.content.select_options_help') }}</flux:text>
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap justify-end gap-2">
                                        <flux:button type="button" wire:click="moveDefinitionField({{ $index }}, -1)" size="sm" variant="ghost">↑</flux:button>
                                        <flux:button type="button" wire:click="moveDefinitionField({{ $index }}, 1)" size="sm" variant="ghost">↓</flux:button>
                                        <flux:button type="button" wire:click="removeDefinitionField({{ $index }})" size="sm" variant="danger">
                                            {{ __('ui.content.remove_field') }}
                                        </flux:button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap justify-between gap-3">
                            <flux:button type="button" wire:click="addDefinitionField" variant="ghost">
                                {{ __('ui.content.add_field') }}
                            </flux:button>
                            <flux:button type="submit" variant="primary">
                                {{ $editingDefinitionId ? __('ui.content.update_definition') : __('ui.content.create_definition') }}
                            </flux:button>
                        </div>
                    </form>
                </flux:card>
            </div>
        @endif
    </div>
</section>
