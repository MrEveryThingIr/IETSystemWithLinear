<section class="mx-auto max-w-7xl space-y-6" data-living-planner>
    <x-app.page-header :title="__('living_planner.title')" :description="__('living_planner.help')">
        <x-slot:actions><flux:button :href="route('planner.create')" wire:navigate variant="primary" icon="plus">{{ __('planner.new') }}</flux:button></x-slot:actions>
    </x-app.page-header>
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('living_planner.title') }}">
        @foreach (['today', 'calendar', 'routines', 'purposes', 'insights', 'history'] as $mode)
            <flux:button :href="$mode === 'calendar' ? route('planner.index', ['view' => 'calendar', 'counts' => 1, 'titles' => 0]) : route('planner.lab', ['tab' => $mode])" wire:navigate :variant="$tab === $mode ? 'primary' : 'ghost'" size="sm">{{ __('living_planner.'.$mode) }}</flux:button>
        @endforeach
    </nav>
    @include('livewire.planner.partials.living-create')
    @if ($notice)<p role="status" class="rounded-lg bg-emerald-50 p-3 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ $notice }}</p>@endif
    @if ($errors->any())<div role="alert" class="rounded-lg border border-red-300 p-3">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    @if ($tab === 'today')
        <p class="text-sm text-zinc-500">{{ __('living_planner.today_help') }}</p>
        <flux:select wire:model="finishOutcome" :label="__('living_planner.finish_outcome')">@foreach(['completed', 'partial', 'interrupted'] as $outcome)<option value="{{ $outcome }}">{{ __('living_planner.'.$outcome) }}</option>@endforeach</flux:select>
        <div class="grid gap-4 lg:grid-cols-2">
        @forelse ($occurrences as $occurrence)
            @php($participation = $participations->get($occurrence->id))
            @php($phase = $participation?->phase ?? 'not_started')
            @php($closed = $occurrence->window_end_at->isPast())
            <article wire:key="living-{{ $occurrence->uuid }}" class="space-y-3 rounded-xl border border-zinc-200 p-5 dark:border-zinc-800" data-living-occurrence="{{ $occurrence->uuid }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <a href="{{ route('planner.show', $occurrence->plan) }}" wire:navigate class="text-lg font-semibold underline-offset-4 hover:underline">{{ $occurrence->plan->title }}</a>
                    <flux:badge>{{ __('living_planner.'.($participation?->outcome ?? ($closed ? 'unconfirmed' : $phase))) }}</flux:badge>
                </div>
                <p class="text-sm"><x-app.local-datetime :value="$occurrence->scheduled_start_at" /> · <x-app.local-time :value="$occurrence->scheduled_end_at" /></p>
                <div class="flex flex-wrap gap-2">
                    @if ($phase === 'not_started' && $occurrence->status !== \App\PlanOccurrenceStatus::Cancelled)
                        <flux:button wire:click="act('{{ $occurrence->uuid }}', 'acknowledge', '{{ $occurrence->uuid }}-acknowledge')" size="sm" wire:loading.attr="disabled">{{ __('living_planner.acknowledge') }}</flux:button>
                        <flux:button wire:click="act('{{ $occurrence->uuid }}', 'start', '{{ $occurrence->uuid }}-start')" :disabled="! $occurrence->canStart()" size="sm" wire:loading.attr="disabled">{{ __('living_planner.start') }}</flux:button>
                    @elseif ($phase === 'running')
                        <flux:button wire:click="act('{{ $occurrence->uuid }}', 'pause', '{{ (string) \Illuminate\Support\Str::uuid() }}')" size="sm" wire:loading.attr="disabled">{{ __('living_planner.pause') }}</flux:button>
                        <flux:button wire:click="act('{{ $occurrence->uuid }}', 'finish', '{{ $occurrence->uuid }}-finish')" size="sm" wire:loading.attr="disabled">{{ __('living_planner.finish') }}</flux:button>
                    @elseif ($phase === 'paused')
                        <flux:button wire:click="act('{{ $occurrence->uuid }}', 'resume', '{{ (string) \Illuminate\Support\Str::uuid() }}')" size="sm" wire:loading.attr="disabled">{{ __('living_planner.resume') }}</flux:button>
                        <flux:button wire:click="act('{{ $occurrence->uuid }}', 'finish', '{{ $occurrence->uuid }}-finish')" size="sm" wire:loading.attr="disabled">{{ __('living_planner.finish') }}</flux:button>
                    @endif
                    <flux:button wire:click="act('{{ $occurrence->uuid }}', 'attend', '{{ $occurrence->uuid }}-attend')" size="sm" variant="ghost">{{ __('living_planner.attend') }}</flux:button>
                </div>
                <details><summary>{{ __('living_planner.participation') }}</summary>
                    <p>{{ __('living_planner.outcome') }}: {{ __('living_planner.'.($occurrence->evaluated_outcome ?? 'unconfirmed')) }}</p>
                    @php($participantRows = $occurrence->participations()->get()->keyBy('actor_id'))
                    @foreach($occurrence->plan->participants()->with('actor.user')->where('status', 'active')->get() as $member)
                        @php($record = $participantRows->get($member->actor_id))
                        <p>{{ $member->actor->user?->username }} · {{ __('living_planner.'.($record->outcome ?? 'unconfirmed')) }} · {{ $record === null ? '—' : round($record->active_seconds / 60, 1) }} {{ __('living_planner.minutes') }}
                        @if($record?->acknowledged_at) · {{ __('living_planner.acknowledge') }}: <x-app.local-datetime :value="$record->acknowledged_at" />@endif
                        @if($record?->attended_at) · {{ __('living_planner.attend') }}: <x-app.local-datetime :value="$record->attended_at" />@endif</p>
                    @endforeach
                </details>
                @if ($closed && $participation?->outcome === null)<p class="text-sm text-zinc-500">{{ __('living_planner.unconfirmed_help') }}</p>@endif
                <details><summary class="cursor-pointer text-sm">{{ __('living_planner.report') }}</summary>
                    <flux:button wire:click="selectReport('{{ $occurrence->uuid }}')" class="mt-2" size="sm">{{ __('living_planner.select') }}</flux:button>
                </details>
            </article>
        @empty
            <p class="rounded-xl border border-dashed p-6 text-zinc-500">{{ __('living_planner.empty') }}</p>
        @endforelse
        </div>
        {{ $occurrences->links() }}
        <details @if($reportOccurrence !== '') open @endif class="rounded-xl border p-4">
            <summary class="cursor-pointer font-medium">{{ __('living_planner.report') }}</summary>
            <form wire:submit="report" class="mt-4 grid gap-4 sm:grid-cols-2">
                @if ($reportSelection)<p class="font-semibold">{{ $reportSelection->plan->title }} ? <x-app.local-datetime :value="$reportSelection->scheduled_start_at" /></p>@else<p>{{ __('living_planner.select_report') }}</p>@endif
                <flux:select wire:model="reportOutcome" :label="__('living_planner.outcome')">@foreach(['completed', 'partial', 'interrupted', 'missed'] as $outcome)<option value="{{ $outcome }}">{{ __('living_planner.'.$outcome) }}</option>@endforeach</flux:select>
                <x-app.calendar-datetime-input model="actualStart" :label="__('living_planner.actual_start')" />
                <x-app.calendar-datetime-input model="actualEnd" :label="__('living_planner.actual_end')" />
                <flux:input type="number" min="0" max="10080" step="0.001" wire:model="reportedMinutes" :label="__('living_planner.active_minutes')" />
                @if ($supersedes !== '')<p>{{ __('living_planner.correcting') }}</p>@endif
                <flux:input wire:model="correctionReason" :label="__('living_planner.reason')" />
                <p class="text-sm text-zinc-500">{{ __('living_planner.report_help') }} {{ $timezone }}</p>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('living_planner.save') }}</flux:button>
            </form>
        </details>
    @else
        <flux:select wire:model.live="planUuid" :label="__('living_planner.plan')">
            <option value="">{{ __('living_planner.all_plans') }}</option>
            @foreach($plans as $plan)<option value="{{ $plan->uuid }}">{{ $plan->title }}</option>@endforeach
        </flux:select>
    @endif

    @if (in_array($tab, ['routines', 'insights'], true))
        @include('livewire.planner.partials.living-revision')
    @endif
    @if ($tab === 'routines')
        <div class="grid gap-5 lg:grid-cols-2">
            <flux:card class="space-y-4">
                <h2 class="text-lg font-semibold">{{ __('living_planner.structure') }}</h2>
                @foreach($plans as $plan)<p class="text-sm">{{ $plan->parentPlan?->title ? $plan->parentPlan->title.' → ' : '' }}<a class="underline" wire:navigate href="{{ route('planner.show', $plan) }}">{{ $plan->title }}</a></p>@endforeach
                <form wire:submit="compose" class="space-y-3">
                    <flux:select wire:model="parentUuid" :label="__('living_planner.parent')"><option value="">{{ __('living_planner.no_parent') }}</option>@foreach($plans as $plan)<option value="{{ $plan->uuid }}">{{ $plan->title }}</option>@endforeach</flux:select>
                    <flux:button type="submit">{{ __('living_planner.save') }}</flux:button>
                </form>
                <form wire:submit="capture" class="space-y-3">
                    <flux:input wire:model="routineName" :label="__('living_planner.routine_name')" required />
                    <flux:button type="submit">{{ __('living_planner.capture') }}</flux:button>
                </form>
            </flux:card>
            <flux:card class="space-y-4">
                <h2 class="text-lg font-semibold">{{ __('living_planner.reuse') }}</h2>
                <x-app.calendar-date-input model="routineStart" :label="__('living_planner.starts_on')" />
                @forelse($templates as $template)<div class="flex flex-wrap items-center justify-between gap-2"><span>{{ $template->name }}</span><flux:button wire:click="reuse('{{ $template->uuid }}')" size="sm" wire:loading.attr="disabled">{{ __('living_planner.reuse') }}</flux:button></div>@empty<p>{{ __('living_planner.empty') }}</p>@endforelse
                <details><summary class="cursor-pointer">{{ __('living_planner.policy') }}</summary>
                    <form wire:submit="configurePolicy" class="mt-3 space-y-3">
                        <p class="text-sm text-zinc-500">{{ __('living_planner.policy_help') }}</p>
                        <flux:select wire:model="policyStyle" :label="__('living_planner.policy')">@foreach(['hard', 'flexible', 'deadline'] as $style)<option value="{{ $style }}">{{ __('living_planner.'.$style) }}</option>@endforeach</flux:select>
                        <flux:input type="number" min="0" max="365" wire:model="reportingDays" :label="__('living_planner.reporting_days')" />
                        <flux:button type="submit">{{ __('living_planner.save') }}</flux:button>
                    </form>
                </details>
            </flux:card>
        </div>
    @elseif ($tab === 'purposes')
        <div class="grid gap-5 lg:grid-cols-2">
            <form wire:submit="createPurpose" class="space-y-4 rounded-xl border p-5">
                <h2 class="text-lg font-semibold">{{ __('living_planner.new_purpose') }}</h2>
                <flux:input wire:model="purposeTitle" :label="__('living_planner.name')" required />
                <flux:textarea wire:model="purposeDescription" :label="__('living_planner.description')" /><x-app.calendar-date-input model="purposeTarget" :label="__('living_planner.target_date')" />
                <flux:button type="submit" variant="primary">{{ __('living_planner.save') }}</flux:button>
            </form>
            <div class="space-y-4">
                @foreach($purposes as $purpose)<article class="rounded-xl border p-4"><h2 class="font-semibold">{{ $purpose->title }}</h2><p>{{ $purpose->description }}</p>@if($purpose->target_on)<x-app.local-date :value="$purpose->target_on" />@endif
                <div class="my-2 flex gap-2"><flux:button size="sm" wire:click="editPurpose('{{ $purpose->uuid }}')">{{ __('living_planner.edit') }}</flux:button><flux:button size="sm" wire:click="archivePurpose('{{ $purpose->uuid }}')">{{ __('living_planner.archive') }}</flux:button></div>
                <flux:button size="sm" :href="route('planner.lab', ['tab' => 'insights', 'purposeFilter' => $purpose->uuid])" wire:navigate>{{ __('living_planner.progress') }}</flux:button><div class="mt-2 flex flex-wrap gap-2">@foreach($purpose->plans as $plan)@can('view', $plan)<flux:button size="sm" wire:click="unlinkPurpose('{{ $purpose->uuid }}', '{{ $plan->uuid }}')">{{ $plan->title }} ?</flux:button>@endcan @endforeach</div></article>@endforeach
                <form wire:submit="linkPurpose" class="space-y-3">
                    <flux:select wire:model="purposeUuid" :label="__('living_planner.purpose')"><option value="">{{ __('living_planner.select') }}</option>@foreach($purposes as $purpose)<option value="{{ $purpose->uuid }}">{{ $purpose->title }}</option>@endforeach</flux:select>
                    <flux:button type="submit">{{ __('living_planner.link') }}</flux:button>
                </form>
            </div>
        </div>
    @elseif ($tab === 'insights')
        <div class="flex flex-wrap gap-2">@foreach([1,7,30,90,365] as $days)<flux:button size="sm" wire:click="horizon({{ $days }})">{{ __('living_planner.horizon_'.$days) }}</flux:button>@endforeach</div>
        <flux:select wire:model.live="purposeFilter" :label="__('living_planner.purpose')"><option value="">{{ __('living_planner.all_plans') }}</option>@foreach($purposes as $purpose)<option value="{{ $purpose->uuid }}">{{ $purpose->title }}</option>@endforeach</flux:select>
        <p>{{ __('living_planner.previous_period') }}: {{ $previousFactors['counts']['completed'] ?? 0 }} / {{ $previousFactors['expected'] }} ? {{ __('living_planner.reported') }}: {{ $previousFactors['reported'] }}</p>
        <form wire:submit="$refresh" class="grid items-end gap-4 sm:grid-cols-3">
            <x-app.calendar-date-input model="from" :label="__('living_planner.from')" />
            <x-app.calendar-date-input model="through" :label="__('living_planner.through')" />
            <flux:button type="submit">{{ __('living_planner.compare') }}</flux:button>
        </form>
        <p class="rounded-xl bg-indigo-50 p-4 text-sm text-indigo-950 dark:bg-indigo-950 dark:text-indigo-100">{{ __('living_planner.factor_help') }}</p>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach(['expected' => $factors['expected'], 'reported' => $factors['reported'], 'unconfirmed' => $factors['counts']['unconfirmed'] ?? 0, 'completed' => $factors['counts']['completed'] ?? 0] as $key => $value)<div class="rounded-xl border p-4"><p class="text-sm text-zinc-500">{{ __('living_planner.'.$key) }}</p><p class="text-2xl font-semibold">{{ $value }}</p></div>@endforeach
        </div>
        <div class="overflow-x-auto rounded-xl border"><table class="w-full text-start text-sm"><thead><tr>@foreach(['commitment_to', 'target', 'outcome', 'deviation', 'duration', 'confidence'] as $label)<th class="p-3 text-start">{{ __('living_planner.'.$label) }}</th>@endforeach</tr></thead><tbody>
        @foreach($factors['rows'] as $row)<tr class="border-t"><td class="p-3">{{ __('living_planner.'.$row['commitment_to']) }}</td><td class="p-3"><x-app.local-datetime :value="$row['target_start']" /></td><td class="p-3">{{ __('living_planner.'.$row['outcome']) }}</td><td class="p-3">{{ $row['start_deviation_seconds'] === null ? '—' : round($row['start_deviation_seconds'] / 60, 1).' '.__('living_planner.minutes') }}</td><td class="p-3">{{ $row['active_seconds'] === null ? '—' : round($row['active_seconds'] / 60, 1).' / '.round($row['target_seconds'] / 60, 1) }}</td><td class="p-3">{{ __('living_planner.'.$row['confidence']) }}</td></tr>@endforeach
        </tbody></table></div>
        <details class="rounded-xl border p-5" @if($experimentUuid !== '') open @endif><summary class="cursor-pointer text-lg font-semibold">{{ __('living_planner.experiments') }}</summary>
            <p class="my-4 text-sm text-zinc-500">{{ __('living_planner.private_help') }}</p>
            <flux:select wire:model.live="experimentUuid" :label="__('living_planner.experiment')"><option value="">{{ __('living_planner.select') }}</option>@foreach($experiments as $item)<option value="{{ $item->uuid }}">{{ $item->title }}</option>@endforeach</flux:select>
            @if($experiment)
                <div class="my-5 space-y-2"><h3 class="font-semibold">{{ $experiment->title }}</h3><p>{{ $experiment->hypothesis }}</p><p>{{ $experiment->selected_change }}</p><p>{{ __('living_planner.baseline') }}: {{ $experimentInsights['baseline_mean'] ?? '—' }} (n={{ $experimentInsights['baseline_n'] }}) · {{ __('living_planner.observed') }}: {{ $experimentInsights['observed_mean'] ?? '—' }} (n={{ $experimentInsights['observed_n'] }})</p><p class="text-sm text-zinc-500">{{ __('living_planner.association_help') }}</p></div>
                @if($observationCorrection !== '')<p>{{ __('living_planner.correcting') }}</p>@endif
                <form wire:submit="observe" class="my-5 grid gap-3 sm:grid-cols-2">
                    <x-app.calendar-datetime-input model="observationForm.observed_at" :label="__('living_planner.observed_at')" />
                    <flux:input type="number" step="0.001" wire:model="observationForm.value" :label="__('living_planner.value')" />
                    <flux:input type="number" min="1" max="5" wire:model="observationForm.difficulty" :label="__('living_planner.difficulty')" />
                    @foreach(['cue', 'obstacle', 'response', 'reflection'] as $field)<flux:textarea wire:model="observationForm.{{ $field }}" :label="__('living_planner.'.$field)" rows="2" />@endforeach
                    <flux:button type="submit">{{ __('living_planner.record') }}</flux:button>
                </form>
                @foreach($observations as $observation)<p class="my-2 text-sm"><x-app.local-datetime :value="$observation->observed_at" /> · {{ $observation->value ?? '—' }} · {{ $observation->reflection }} <flux:button size="sm" wire:click="correctObservation('{{ $observation->uuid }}')">{{ __('living_planner.correct') }}</flux:button></p>@endforeach
                <form wire:submit="propose" class="my-5 space-y-3"><flux:textarea wire:model="proposal" :label="__('living_planner.proposal')" /><flux:button type="submit">{{ __('living_planner.propose') }}</flux:button></form>
                @foreach($adjustments as $adjustment)<div class="my-3 flex flex-wrap items-center gap-2"><span>{{ $adjustment->proposal }}</span>@if($adjustment->decision)<flux:badge>{{ __('living_planner.'.$adjustment->decision) }}</flux:badge>@if($adjustment->decision === 'approved')<flux:button size="sm" wire:click="chooseAdjustment('{{ $adjustment->uuid }}')">{{ __('living_planner.apply_future') }}</flux:button>@endif@else<flux:button size="sm" wire:click="decide('{{ $adjustment->uuid }}', true)">{{ __('living_planner.approve') }}</flux:button><flux:button size="sm" wire:click="decide('{{ $adjustment->uuid }}', false)">{{ __('living_planner.reject') }}</flux:button>@endif</div>@endforeach
            @endif
            <details class="mt-5"><summary class="cursor-pointer">{{ __('living_planner.new_experiment') }}</summary>
                <form wire:submit="createExperiment" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="experimentPurpose" :label="__('living_planner.purpose')"><option value="">{{ __('living_planner.use_selected_plan') }}</option>@foreach($purposes as $purpose)<option value="{{ $purpose->uuid }}">{{ $purpose->title }}</option>@endforeach</flux:select>
                    @foreach(['title', 'hypothesis', 'selected_change', 'cue', 'planned_response'] as $field)<flux:textarea wire:model="experimentForm.{{ $field }}" :label="__('living_planner.'.$field)" rows="2" />@endforeach
                    <flux:select wire:model="experimentForm.metric" :label="__('living_planner.metric')">@foreach(['active_minutes', 'completed_count', 'start_delay_minutes', 'difficulty', 'custom'] as $metric)<option value="{{ $metric }}">{{ __('living_planner.'.$metric) }}</option>@endforeach</flux:select>
                    <x-app.calendar-date-input model="experimentForm.starts_on" :label="__('living_planner.starts_on')" /><x-app.calendar-date-input model="experimentForm.ends_on" :label="__('living_planner.ends_on')" />
                    <flux:input type="number" step="0.001" wire:model="experimentForm.baseline" :label="__('living_planner.baseline')" /><flux:input type="number" step="0.001" wire:model="experimentForm.target" :label="__('living_planner.target')" />
                    <flux:button type="submit" variant="primary">{{ __('living_planner.save') }}</flux:button>
                </form>
            </details>
        </details>
    @elseif($tab === 'history')
        <p class="text-sm text-zinc-500">{{ __('living_planner.history_help') }}</p>
        <div class="space-y-3">@forelse($events as $event)<article class="rounded-xl border p-4"><div class="flex flex-wrap justify-between gap-2"><span class="font-medium">{{ $event->occurrence->plan->title }} · {{ __('living_planner.event_'.$event->event_type->value) }}</span>@if($event->actor_id === auth()->user()->actor->id)<flux:button :href="route('planner.lab', ['occurrence' => $event->occurrence->uuid])" size="sm" wire:navigate>{{ __('living_planner.report') }}</flux:button>@endif</div><p class="text-sm">{{ __('living_planner.effective') }}: <x-app.local-datetime :value="$event->effective_at ?? $event->created_at" /> · {{ __('living_planner.recorded') }}: <x-app.local-datetime :value="$event->created_at" /></p>@if(data_get($event->payload, 'reason'))<p>{{ data_get($event->payload, 'reason') }}</p>@endif</article>@empty<p>{{ __('living_planner.empty') }}</p>@endforelse</div>
        {{ $events->links() }}
    @endif
</section>
