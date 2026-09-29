<section class="mx-auto max-w-6xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header
        :title="__('development.title')"
        :description="__('development.description')"
    />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-4">
            @forelse ($origins as $origin)
                <flux:card class="space-y-4" wire:key="development-origin-{{ $origin->uuid }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg" dir="auto">{{ $origin->title }}</flux:heading>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <flux:badge>{{ $origin->source_type }}</flux:badge>
                                @if ($origin->phase_key)<flux:badge>{{ $origin->phase_key }}</flux:badge>@endif
                                @if ($origin->system_version)<flux:badge>{{ $origin->system_version }}</flux:badge>@endif
                            </div>
                        </div>
                        <div class="text-xs text-zinc-500">{{ $origin->created_at->format('Y-m-d H:i') }}</div>
                    </div>

                    <div class="whitespace-pre-wrap text-sm" dir="auto">{{ $origin->summary }}</div>

                    @if ($origin->source_url)
                        <a href="{{ $origin->source_url }}" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-medium underline">
                            {{ __('development.open_source') }} ↗
                        </a>
                    @endif

                    <div class="grid gap-3 text-xs sm:grid-cols-2">
                        @if ($origin->branch)
                            <div><span class="text-zinc-500">{{ __('development.branch') }}:</span> <code>{{ $origin->branch }}</code></div>
                        @endif
                        @if ($origin->occurred_at)
                            <div><span class="text-zinc-500">{{ __('development.occurred_at') }}:</span> {{ $origin->occurred_at->format('Y-m-d H:i') }}</div>
                        @endif
                        @if ($origin->baseline_commit_sha)
                            <div class="break-all"><span class="text-zinc-500">{{ __('development.baseline') }}:</span> <code>{{ $origin->baseline_commit_sha }}</code></div>
                        @endif
                        @if ($origin->result_commit_sha)
                            <div class="break-all"><span class="text-zinc-500">{{ __('development.result') }}:</span> <code>{{ $origin->result_commit_sha }}</code></div>
                        @endif
                    </div>

                    @if (! empty($origin->repository_paths))
                        <div class="space-y-2">
                            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('development.repository_paths') }}</div>
                            @foreach ($origin->repository_paths as $path)
                                <div class="rounded-lg bg-zinc-50 px-3 py-2 font-mono text-xs dark:bg-zinc-900">{{ $path }}</div>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            @empty
                <x-app.empty-state :title="__('development.none')" />
            @endforelse

            {{ $origins->links() }}
        </div>

        <div class="self-start xl:sticky xl:top-6">
            <flux:card class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('development.capture_heading') }}</flux:heading>
                    <flux:text>{{ __('development.capture_help') }}</flux:text>
                </div>

                <form wire:submit="capture" class="space-y-4">
                    <flux:select wire:model="sourceType" :label="__('development.source_type')">
                        <option value="chatgpt">ChatGPT</option>
                        <option value="design_session">Design session</option>
                        <option value="external_discussion">External discussion</option>
                        <option value="manual_note">Manual note</option>
                    </flux:select>

                    <flux:input wire:model="sourceUrl" :label="__('development.source_url')" maxlength="2000" />
                    <flux:input wire:model="title" :label="__('development.origin_title')" maxlength="255" />
                    <flux:textarea wire:model="summary" :label="__('development.summary')" rows="7" maxlength="20000" />
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                        <flux:input wire:model="phaseKey" :label="__('development.phase')" maxlength="80" />
                        <flux:input wire:model="systemVersion" :label="__('development.system_version')" maxlength="80" />
                    </div>
                    <flux:input wire:model="branch" :label="__('development.branch')" maxlength="160" />
                    <flux:input wire:model="baselineCommitSha" :label="__('development.baseline')" maxlength="40" />
                    <flux:input wire:model="resultCommitSha" :label="__('development.result')" maxlength="40" />
                    <flux:textarea wire:model="repositoryPaths" :label="__('development.repository_paths')" :description="__('development.repository_paths_help')" rows="5" maxlength="8000" />
                    <flux:input wire:model="occurredAt" type="datetime-local" :label="__('development.occurred_at')" />

                    <flux:button type="submit" variant="primary" class="w-full">{{ __('development.capture') }}</flux:button>
                </form>
            </flux:card>
        </div>
    </div>
</section>
