<section class="mx-auto max-w-7xl space-y-6 pb-32" data-intention-composer x-data="{screen: 'write', wide: window.innerWidth >= 1024}" x-on:resize.window="wide = window.innerWidth >= 1024" x-on:composer-detail-added.window="$nextTick(() => $el.querySelectorAll('[data-composer-detail]')[$event.detail.index]?.querySelector('input,textarea,select')?.focus())" x-on:composer-detail-removed.window="$nextTick(() => $el.querySelector('[data-add-detail]')?.focus())">
    <x-app.page-header :title="__('composer.title')" :description="__('composer.help')" />
    @if($notice)<p role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ $notice }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-400 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($intent?->working_copy && $intent?->published_version_id)<div class="flex flex-wrap items-center gap-3 rounded-xl border p-4"><p>{{ __('composer.pending') }}</p><flux:button wire:click="discard" size="sm">{{ __('composer.discard') }}</flux:button></div>@endif
    <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('composer.choose') }}">
        @foreach(['need','offer','service','person','sell','rent','exchange','activity'] as $choice)
            <button type="button" wire:click="choose('{{ $choice }}')" aria-pressed="{{ $intention === $choice ? 'true' : 'false' }}" class="min-h-11 rounded-full border px-4 py-2 text-sm {{ $intention === $choice ? 'bg-indigo-600 text-white' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">{{ __('composer.intentions.'.$choice) }}</button>
        @endforeach
    </div>
    <div class="flex gap-2 lg:hidden"><flux:button x-on:click="screen='write'">{{ __('composer.write') }}</flux:button><flux:button x-on:click="screen='preview'">{{ __('composer.preview') }}</flux:button></div>
    <div class="grid items-start gap-6 lg:grid-cols-2">
        <div class="space-y-5" x-show="screen === 'write' || wide">
            @if($intent === null)<flux:select wire:model.live="ownerKey" :label="__('intents.fields.publisher')">@foreach($parties as $entry)<option value="{{ $entry['party']->key() }}">{{ $entry['label'] }}</option>@endforeach</flux:select>@endif
            <div class="space-y-4 rounded-2xl border p-5">
                <h2 class="text-lg font-semibold">{{ __('composer.describe') }}</h2>
                <flux:input wire:model.live.debounce.400ms="core.title" :label="__('composer.heading')" dir="auto" />
                <flux:textarea wire:model.live.debounce.400ms="core.description" :label="__('composer.description')" rows="5" dir="auto" />
                @if($intent === null)
                    <details><summary class="cursor-pointer py-2 text-sm">{{ __('composer.topic_optional') }}</summary>
                        <livewire:categories.picker wire:model.live="conceptId" :owner="$publisher->key()" :kinds="\App\Support\Categories\CategoryKind::values()" :allow-create="true" :key="'composer-picker-'.$publisher->key()" />
                        <flux:input wire:model="conceptLabel" :label="__('intents.create.type_instead')" />
                    </details>
                    @if($capabilities->isNotEmpty())<details><summary class="cursor-pointer py-2">{{ __('intents.create.from_capability') }}</summary><div class="flex flex-wrap gap-2">@foreach($capabilities as $capability)<flux:button wire:click="useCapability('{{ $capability->uuid }}')" size="sm">{{ $capability->title ?: $capability->concept?->displayLabel(app()->getLocale()) }}</flux:button>@endforeach</div></details>@endif
                @endif
            </div>
            <details class="rounded-2xl border p-5" @if(count($details)) open @endif>
                <summary class="cursor-pointer text-lg font-semibold">{{ __('composer.details_title') }} ({{ count($details) }})</summary>
                <div class="mt-4 space-y-4">
                    <p class="text-sm text-zinc-500">{{ __('composer.suggested') }}</p>
                    <div class="flex flex-wrap gap-2">@foreach($suggestions as $key)<flux:button wire:click="addDetail('{{ $key }}')" size="sm">+ {{ __('composer.details.'.$key) }}</flux:button>@endforeach</div>
                    @foreach($details as $index => $detail)
                        <div class="space-y-3 rounded-xl border p-4" wire:key="detail-{{ $detail['editor_key'] ?? $index }}" data-composer-detail>
                            @if(empty($detail['canonical_key']))<flux:input wire:model="details.{{ $index }}.label" :label="__('composer.detail_name')" dir="auto" />@else<p class="font-medium">{{ __('composer.details.'.$detail['canonical_key']) }}</p>@endif
                            @if(in_array($detail['type'], ['long_text','place'], true))
                                <flux:textarea wire:model="details.{{ $index }}.value" :label="__('composer.value')" dir="auto" />
                            @elseif($detail['type'] === 'boolean')
                                <flux:select wire:model="details.{{ $index }}.value" :label="__('composer.value')"><option value="">{{ __('composer.select') }}</option><option value="1">{{ __('composer.yes') }}</option><option value="0">{{ __('composer.no') }}</option></flux:select>
                            @elseif($detail['type'] === 'date')
                                <x-app.calendar-date-input :model="'details.'.$index.'.value'" :label="__('composer.value')" />
                            @elseif($detail['type'] === 'date_range')
                                <x-app.calendar-date-input :model="'details.'.$index.'.value.start'" :label="__('composer.starts_on')" /><x-app.calendar-date-input :model="'details.'.$index.'.value.end'" :label="__('composer.ends_on')" />
                            @elseif($detail['type'] === 'multiple_choice')
                                @foreach($detail['options'] ?? [] as $option)<flux:checkbox wire:model="details.{{ $index }}.value" :value="$option" :label="$option" />@endforeach
                            @elseif($detail['type'] === 'choice' && !empty($detail['options']))
                                <flux:select wire:model="details.{{ $index }}.value" :label="__('composer.value')"><option value="">{{ __('composer.select') }}</option>@foreach($detail['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</flux:select>
                            @elseif($detail['type'] === 'reference')
                                <p>{{ __('composer.reference_help') }}</p>
                                <flux:select wire:model.live="details.{{ $index }}.value.type" :label="__('composer.reference_type')"><option value="">{{ __('composer.select') }}</option><option value="content">{{ __('ui.content.tab') }}</option><option value="group">{{ __('ui.groups.title') }}</option><option value="catalog">{{ __('business.catalog.title') }}</option></flux:select>
                                <flux:input wire:model.live.debounce.400ms="referenceSearch" :label="__('business.clients.search')" />
                                <flux:select wire:model="details.{{ $index }}.value.id" :label="__('composer.reference_item')"><option value="">{{ __('composer.select') }}</option>@foreach($referenceChoices[$detail['value']['type'] ?? 'content'] ?? [] as $reference)<option value="{{ $reference['id'] }}">{{ $reference['label'] }}</option>@endforeach</flux:select>
                            @else
                                <flux:input wire:model="details.{{ $index }}.value" :label="__('composer.value')" :type="$detail['type'] === 'time' ? 'time' : 'text'" dir="auto" />
                            @endif
                            @if(in_array($detail['type'], ['choice','multiple_choice'], true) && empty($detail['canonical_key']))<flux:input wire:model.live.debounce.300ms="details.{{ $index }}.optionsText" :label="__('composer.choices_help')" />@endif
                            @if($detail['type'] === 'quantity')<flux:input wire:model="details.{{ $index }}.unit" :label="__('composer.unit')" />@endif
                            @if($detail['type'] === 'amount')<p class="text-sm text-zinc-500">{{ __('composer.information_money') }}</p>@endif
                            <flux:input wire:model="details.{{ $index }}.group_label" :label="__('composer.group_optional')" />
                            <flux:select wire:model="details.{{ $index }}.visibility" :label="__('composer.detail_audience')"><option value="same">{{ __('composer.same_audience') }}</option><option value="private">{{ __('composer.only_me') }}</option></flux:select>
                            <div class="flex flex-wrap gap-2"><flux:button size="sm" wire:click="moveDetail({{ $index }}, -1)">{{ __('composer.up') }}</flux:button><flux:button size="sm" wire:click="moveDetail({{ $index }}, 1)">{{ __('composer.down') }}</flux:button><flux:button size="sm" wire:click="removeDetail({{ $index }})">{{ __('composer.remove') }}</flux:button></div>
                        </div>
                    @endforeach
                    <div class="flex flex-wrap items-end gap-3"><flux:select wire:model="detailType" :label="__('composer.detail_type')">@foreach($detailTypes as $type)<option value="{{ $type }}">{{ __('composer.types.'.$type) }}</option>@endforeach</flux:select><flux:button wire:click="addDetail" data-add-detail>+ {{ __('composer.add_detail') }}</flux:button></div>
                    <details><summary class="cursor-pointer py-2">{{ __('composer.templates') }}</summary><div class="space-y-3"><flux:input wire:model="templateName" :label="__('composer.template_name')" /><flux:button wire:click="saveTemplate">{{ __('composer.save_template') }}</flux:button>@foreach($templates as $template)<flux:button wire:click="applyTemplate('{{ $template->uuid }}')">{{ $template->name }}</flux:button>@endforeach</div></details>
                </div>
            </details>
            <details class="rounded-2xl border p-5">
                <summary class="cursor-pointer text-lg font-semibold">{{ __('composer.advanced_terms') }}</summary>
                <div class="mt-4 space-y-4">
                    <flux:select wire:model="core.subject_kind" :label="__('intents.fields.subject_kind')">@foreach(\App\ProfileIntentSubjectKind::cases() as $option)<option value="{{ $option->value }}">{{ __('intents.subjects.'.$option->value) }}</option>@endforeach</flux:select>
                    <flux:select wire:model="core.arrangement_kind" :label="__('intents.fields.arrangement')">@foreach(\App\ProfileIntentArrangementKind::cases() as $option)<option value="{{ $option->value }}">{{ __('intents.arrangements.'.($intent?->kind->value ?? (in_array($intention,['offer','service','sell']) ? 'offer' : 'need')).'.'.$option->value) }}</option>@endforeach</flux:select>
                    <flux:textarea wire:model="core.exchange_notes" :label="__('intents.fields.exchange_notes')" />
                    <flux:checkbox wire:model="core.negotiable" :label="__('intents.fields.negotiable')" />
                    <flux:input wire:model="core.importance_percent" :label="__('ui.profile.intents.importance')" inputmode="numeric" />
                    <flux:input wire:model="core.origin_text" :label="__('ui.profile.intents.origin')" dir="auto" />
                    <flux:input wire:model="core.destination_text" :label="__('ui.profile.intents.destination')" dir="auto" />
                    <flux:checkbox wire:model.live="core.round_trip" :label="__('ui.profile.intents.round_trip')" />
                    @if($core['round_trip'] ?? false)<flux:input wire:model="core.return_after_days" :label="__('ui.profile.intents.return_after_days')" inputmode="numeric" />@endif
                    <flux:select wire:model.live="core.schedule_kind" :label="__('ui.profile.intents.schedule_kind')">@foreach(\App\ProfileIntentScheduleKind::cases() as $schedule)<option value="{{ $schedule->value }}">{{ __('ui.profile.intents.schedules.'.$schedule->value) }}</option>@endforeach</flux:select>
                    <flux:input wire:model="core.timezone" :label="__('ui.profile.intents.timezone')" />
                    <flux:input wire:model="core.time_window_start" type="time" :label="__('ui.profile.intents.time_from')" />
                    <flux:input wire:model="core.time_window_end" type="time" :label="__('ui.profile.intents.time_to')" />
                    @if(in_array($core['schedule_kind'] ?? '', ['daily','weekly','monthly'], true))<flux:input wire:model="core.recurrence_interval" :label="__('ui.profile.intents.repeat_every')" inputmode="numeric" />@endif
                    @if(($core['schedule_kind'] ?? '') === 'weekly')<fieldset><legend>{{ __('ui.profile.intents.weekdays') }}</legend><div class="flex flex-wrap gap-3">@foreach(range(1,7) as $day)<flux:checkbox wire:model="core.recurrence_weekdays" value="{{ $day }}" :label="__('ui.profile.intents.weekday_names.'.$day)" />@endforeach</div></fieldset>@endif
                    @if(($core['schedule_kind'] ?? '') === 'monthly')<flux:input wire:model="core.recurrence_day_of_month" :label="__('ui.profile.intents.day_of_month')" inputmode="numeric" />@endif
                </div>
            </details>
            <details class="rounded-2xl border p-5" @if(in_array($intention, ['service','sell','rent'], true)) open @endif>
                <summary class="cursor-pointer text-lg font-semibold">{{ __('composer.price_terms') }}</summary>
                <div class="mt-4 space-y-4">
                    @if($legacyPrice)<p class="rounded-xl border p-3">{{ __('composer.legacy_price') }}</p><flux:checkbox wire:model.live="replaceLegacyPrice" :label="__('composer.replace_price')" />@endif
                    <div @if($legacyPrice && !$replaceLegacyPrice) inert class="space-y-4 opacity-50" @else class="space-y-4" @endif>
                    <flux:select wire:model.live="priceMode" :label="__('composer.price_type')">@foreach(['free','fixed','per_unit','range','ask','exchange'] as $mode)<option value="{{ $mode }}">{{ __('composer.prices.'.$mode) }}</option>@endforeach</flux:select>
                    @if(in_array($priceMode, ['fixed','per_unit','range'], true))<flux:input wire:model="price" :label="__('composer.amount_toman')" inputmode="decimal" />@endif
                    @if($priceMode === 'range')<flux:input wire:model="maximumPrice" :label="__('composer.maximum_toman')" inputmode="decimal" />@endif
                    @if($priceMode === 'per_unit')<flux:select wire:model="core.cash_basis" :label="__('composer.per')">@foreach(\App\Support\Market\RateBasis::cases() as $basis)<option value="{{ $basis->value }}">{{ __('intents.rate_basis.'.$basis->value) }}</option>@endforeach</flux:select>@endif
                    </div>
                    <flux:input wire:model="core.quantity" :label="__('composer.quantity')" inputmode="decimal" /><flux:input wire:model="core.unit" :label="__('composer.unit')" />
                    <flux:input wire:model="core.location_text" :label="__('composer.where')" dir="auto" />
                    <x-app.calendar-date-input model="core.starts_on" :label="__('composer.starts_on')" /><x-app.calendar-date-input model="core.ends_on" :label="__('composer.ends_on')" />
                </div>
            </details>
            <details class="rounded-2xl border p-5">
                <summary class="cursor-pointer text-lg font-semibold">{{ __('composer.media_title') }} ({{ $mediaItems->count() }})</summary>
                <div class="mt-4 space-y-4">
                    @foreach($mediaItems as $item)
                        <div class="space-y-3 rounded-xl border p-3" wire:key="composer-media-{{ $item->uuid }}">
                            <a class="block break-words underline" href="{{ route('intents.media.show', [$intent, $item]) }}" dir="auto">{{ $item->caption ?: $item->asset?->original_filename }}</a>
                            <flux:select wire:change="setMediaVisibility('{{ $item->uuid }}', $event.target.value)" :label="__('composer.detail_audience')"><option value="same" @selected($item->visibility !== 'private')>{{ __('composer.same_audience') }}</option><option value="private" @selected($item->visibility === 'private')>{{ __('composer.only_me') }}</option></flux:select>
                            <div class="flex gap-2"><flux:button wire:click="moveMediaUp('{{ $item->uuid }}')" size="sm">{{ __('composer.up') }}</flux:button><flux:button wire:click="removeMedia('{{ $item->uuid }}')" size="sm">{{ __('composer.remove') }}</flux:button></div>
                        </div>
                    @endforeach
                    @if($mediaItems->count() < \App\Models\ActorProfileIntentMedia::MAX_PER_INTENT)
                        <div class="space-y-3" x-data="{progress: 0, uploading: false, failed: false}" x-on:livewire-upload-start="uploading=true; failed=false" x-on:livewire-upload-finish="uploading=false" x-on:livewire-upload-error="uploading=false; failed=true" x-on:livewire-upload-progress="progress=$event.detail.progress">
                            <label class="block space-y-2"><span class="text-sm font-medium">{{ __('composer.media_title') }}</span><input type="file" wire:model="upload" accept="image/*,video/*,application/pdf" class="block w-full min-w-0 text-sm" /></label>
                            <div x-show="uploading" role="status"><progress max="100" x-bind:value="progress" :aria-label="__('intents.media.uploading')"></progress> <span x-text="progress+'%'"></span></div>
                            <p x-show="failed" role="alert">{{ __('composer.retry_upload') }}</p>
                            <flux:input wire:model="uploadCaption" :label="__('intents.media.caption')" />
                            <flux:select wire:model="uploadVisibility" :label="__('composer.detail_audience')"><option value="same">{{ __('composer.same_audience') }}</option><option value="private">{{ __('composer.only_me') }}</option></flux:select>
                            <flux:checkbox wire:model="rights" :label="__('intents.media.rights')" />
                            <flux:button wire:click="uploadMedia" wire:loading.attr="disabled" wire:target="upload,uploadMedia">{{ __('intents.media.save') }}</flux:button>
                        </div>
                    @endif
                </div>
            </details>
        </div>
        <aside x-show="screen === 'preview' || wide" class="min-w-0 rounded-2xl border p-5 lg:sticky lg:top-24" role="region" aria-label="{{ __('composer.preview') }}">
            <h2 class="mb-4 text-lg font-semibold">{{ __('composer.preview') }}</h2>
            <flux:select wire:model.live="previewAudience" :label="__('composer.audience')"><option value="public">{{ __('composer.everyone') }}</option><option value="authenticated">{{ __('composer.signed_in') }}</option><option value="owner">{{ __('composer.only_me') }}</option></flux:select>
            <div class="mt-4"><x-market.intention-presentation :presentation="$presentation" /></div>
        </aside>
    </div>
    <div class="sticky bottom-20 z-20 flex flex-wrap items-end gap-3 rounded-2xl border bg-white/95 p-4 shadow-lg backdrop-blur dark:bg-zinc-900/95 lg:bottom-4" aria-label="{{ __('composer.publish_controls') }}">
        <flux:select wire:model.live="core.visibility" :label="__('composer.audience')"><option value="private">{{ __('composer.only_me') }}</option><option value="authenticated">{{ __('composer.signed_in') }}</option><option value="public">{{ __('composer.everyone') }}</option>@if($publisher->kind !== \App\Support\Parties\Party::ACTOR)<option value="members">{{ __('intents.visibility.members') }}</option>@endif @if(($core['visibility'] ?? '') === 'inherited')<option value="inherited">{{ __('ui.profile.item_visibility_options.inherited') }}</option>@endif</flux:select>
        <flux:button wire:click="saveDraft" wire:loading.attr="disabled">{{ __('composer.save_draft') }}</flux:button>
        <flux:button wire:click="publish" variant="primary" wire:loading.attr="disabled">{{ __('composer.publish_to') }} {{ __('composer.audiences.'.($core['visibility'] ?? 'authenticated')) }}</flux:button>
        <span wire:loading role="status">{{ __('composer.saving') }}</span>
    </div>
</section>
