<div class="mx-auto max-w-5xl space-y-6" data-message-reports>
    <x-app.page-header :title="__('messages.reports_admin.title')" :description="__('messages.reports_admin.help')" />
    <div class="flex gap-2">
        @foreach (['open', 'reviewed'] as $tab)
            <button type="button" wire:click="$set('status', '{{ $tab }}')" @class(['min-h-9 rounded-full px-3 text-sm', 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $status === $tab, 'border border-zinc-300 dark:border-zinc-700' => $status !== $tab])>{{ __('messages.reports_admin.'.$tab) }}</button>
        @endforeach
    </div>
    <div class="space-y-3">
        @forelse ($reports as $report)
            <flux:card class="space-y-2" wire:key="report-{{ $report->uuid }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="text-sm">
                        <strong>{{ $report->reporter?->user?->username }}</strong>
                        → <strong>{{ $report->reported?->user?->username }}</strong>
                        · <flux:badge size="sm">{{ __('messages.report.reasons.'.$report->reason) }}</flux:badge>
                        · <x-app.local-datetime :value="$report->created_at" />
                    </div>
                    @if ($report->status === 'open')
                        <flux:button size="sm" wire:click="markReviewed('{{ $report->uuid }}')">{{ __('messages.reports_admin.mark_reviewed') }}</flux:button>
                    @endif
                </div>
                @if ($report->details)<p class="whitespace-pre-wrap text-sm [overflow-wrap:anywhere]" dir="auto">{{ $report->details }}</p>@endif
                @if ($report->message)
                    <blockquote class="rounded-lg bg-zinc-50 p-3 text-sm [overflow-wrap:anywhere] dark:bg-zinc-800" dir="auto">
                        {{ \Illuminate\Support\Str::limit($report->message->currentBody() ?: __('messages.kinds_short.'.$report->message->kind), 600) }}
                    </blockquote>
                    <flux:text class="text-xs">{{ __('messages.reports_admin.reported_message_note') }}</flux:text>
                @endif
            </flux:card>
        @empty
            <x-app.empty-state icon="flag" :title="__('messages.reports_admin.none')" />
        @endforelse
    </div>
    {{ $reports->links() }}
</div>
