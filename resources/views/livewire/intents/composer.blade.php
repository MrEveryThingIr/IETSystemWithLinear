<section class="mx-auto w-full max-w-6xl space-y-5 pb-36" data-intention-composer x-data="{screen: 'write', wide: window.innerWidth >= 1024}" x-on:resize.window="wide = window.innerWidth >= 1024" x-on:composer-detail-added.window="$nextTick(() => $el.querySelectorAll('[data-composer-detail]')[$event.detail.index]?.querySelector('input,textarea,select')?.focus())" x-on:composer-detail-removed.window="$nextTick(() => $el.querySelector('[data-add-field]')?.focus())">
    <x-app.page-header :title="__('composer.title')" :description="__('composer.help')" />
    @if($notice)<p role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100">{{ $notice }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-400 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($intent?->working_copy && $intent?->published_version_id)<div class="flex flex-wrap items-center gap-3 rounded-xl border p-4"><p>{{ __('composer.pending') }}</p><flux:button wire:click="discard" size="sm">{{ __('composer.discard') }}</flux:button></div>@endif
    @if($catalog)
        <div class="space-y-2 rounded-2xl border p-4" role="region" aria-label="{{ __('composer.catalog.title') }}" data-catalog-card>
            <p class="font-medium">{{ __('composer.catalog.title') }}: {{ $catalog['title'] }} <span class="text-sm text-zinc-500">({{ __('composer.catalog.version', ['version' => $catalog['version']]) }})</span></p>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('composer.catalog.read_only') }}</p>
            <div class="flex flex-wrap gap-2"><flux:button size="sm" :href="$catalog['edit']">{{ __('composer.catalog.edit') }}</flux:button>@if($catalog['newer'])<flux:button size="sm" variant="primary" wire:click="useNewCatalogVersion" wire:confirm="{{ __('composer.catalog.use_new_confirm') }}">{{ __('composer.catalog.use_new') }}</flux:button>@endif</div>
        </div>
    @endif
    <div class="flex gap-2 lg:hidden"><flux:button x-on:click="screen='write'">{{ __('composer.write') }}</flux:button><flux:button x-on:click="screen='preview'">{{ __('composer.preview') }}</flux:button></div>
    @php
        $isOffer = in_array($intention, ['offer', 'service', 'sell'], true);
        $locked = $intent !== null && $intent->status !== \App\ProfileIntentStatus::Draft;
        $topicName = $topic === '' ? null : __('composer.topics.'.$topic);
    @endphp
    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div class="min-w-0 space-y-5" x-show="screen === 'write' || wide">
            <div class="divide-y rounded-2xl border bg-white dark:bg-zinc-900">
                {{-- 1. Describe: the whole initial form --}}
                <div class="space-y-4 p-4 sm:p-5">
                    <div class="inline-flex rounded-full border p-1" role="group" aria-label="{{ __('composer.choose') }}" data-composer-kind>
                        @foreach(['need' => false, 'offer' => true] as $choice => $offerSide)
                            <button type="button" wire:click="choose('{{ $choice }}')" @disabled($locked && $isOffer !== $offerSide) aria-pressed="{{ $isOffer === $offerSide ? 'true' : 'false' }}" class="min-h-10 rounded-full px-5 py-1.5 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:opacity-40 {{ $isOffer === $offerSide ? 'bg-indigo-600 text-white' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">{{ __('composer.kind.'.$choice) }}</button>
                        @endforeach
                    </div>
                    @if($intent === null && count($parties) > 1)<flux:select wire:model.live="ownerKey" :label="__('intents.fields.publisher')">@foreach($parties as $entry)<option value="{{ $entry['party']->key() }}">{{ $entry['label'] }}</option>@endforeach</flux:select>@endif
                    <flux:input wire:model.live.debounce.400ms="core.title" :disabled="$catalog !== null" :label="__('composer.heading')" dir="auto" />
                    <flux:textarea wire:model.live.debounce.400ms="core.description" :disabled="$catalog !== null" :label="__('composer.description')" rows="4" dir="auto" />
                    <div class="flex flex-wrap items-center gap-2" data-composer-topics x-data="{open: false}" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
                        <div class="relative">
                            <x-market.chip x-on:click="open = ! open" aria-haspopup="listbox" x-bind:aria-expanded="open.toString()" :dashed="$topic === ''"><span class="text-zinc-500">{{ __('composer.topic_label') }}:</span> {{ $topicName ?? __('composer.topic_choose') }} <flux:icon.chevron-down class="size-3.5" /></x-market.chip>
                            <div x-show="open" x-cloak role="listbox" class="absolute start-0 z-30 mt-1 w-64 max-w-[85vw] rounded-xl border bg-white p-1 shadow-lg dark:bg-zinc-900">
                                @foreach($profile['topics'] as $choice)
                                    <button type="button" role="option" aria-selected="{{ $topic === $choice ? 'true' : 'false' }}" wire:click="chooseTopic('{{ $choice }}')" x-on:click="open = false" class="flex min-h-10 w-full items-center rounded-lg px-3 py-2 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 {{ $topic === $choice ? 'font-semibold' : '' }}">{{ __('composer.topics.'.$choice) }}</button>
                                @endforeach
                                <button type="button" role="option" aria-selected="{{ $topic === '' ? 'true' : 'false' }}" wire:click="chooseTopic('')" x-on:click="open = false" class="flex min-h-10 w-full items-center rounded-lg px-3 py-2 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __('composer.topics.other') }}</button>
                            </div>
                        </div>
                        @if($profile['hint'])<x-market.chip dashed wire:click="chooseTopic('{{ $profile['hint'] }}')" data-topic-hint>{{ __('composer.topic_hint', ['topic' => __('composer.topics.'.$profile['hint'])]) }}</x-market.chip>@endif
                        @foreach($profile['arrangements'] as $choice)
                            <x-market.chip :active="($core['arrangement_kind'] ?? '') === $choice" wire:click="chooseArrangement('{{ $choice }}')" data-arrangement="{{ $choice }}">{{ __('composer.arrangements.'.$choice.'.'.($isOffer ? 'offer' : 'need')) }}</x-market.chip>
                        @endforeach
                    </div>
                </div>
                {{-- 2. Chosen details: only what the person added --}}
                <div class="space-y-4 p-4 sm:p-5" data-composer-selected>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold">{{ __('composer.details_title') }}</h2>
                        <x-market.chip wire:click="openPicker" data-add-field><flux:icon.plus class="size-4" /> {{ __('composer.add_field') }}</x-market.chip>
                    </div>
                    @include('livewire.intents.composer.terms')
                    @if(count($details))
                        <div class="grid gap-x-4 gap-y-5 sm:grid-cols-2">
                            @foreach($details as $index => $detail)
                                @php($custom = empty($detail['canonical_key']))
                                @php($misfit = ! $custom && in_array($detail['canonical_key'], $profile['inapplicable'], true))
                                <div class="min-w-0 space-y-1.5 {{ $custom ? 'sm:col-span-2 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/40' : '' }}" wire:key="detail-{{ ($detail['editor_key'] ?? $index) }}" data-composer-detail>
                                    @if($custom)@include('livewire.intents.composer.custom-row')@else<x-market.detail-field :index="$index" :detail="$detail" />@endif
                                    @if($misfit)<p class="text-xs text-amber-700 dark:text-amber-400" role="status">{{ __('composer.does_not_apply') }}</p>@endif
                                    @if(($detail['visibility'] ?? 'same') === 'private')<p class="text-xs text-zinc-500">{{ __('composer.only_me') }}</p>@endif
                                    <div class="flex flex-wrap items-center gap-0.5 text-zinc-500">
                                        <button type="button" wire:click="toggleDetailPrivacy({{ $index }})" aria-pressed="{{ ($detail['visibility'] ?? 'same') === 'private' ? 'true' : 'false' }}" title="{{ __('composer.only_me') }}" aria-label="{{ __('composer.only_me') }}" class="grid size-8 place-items-center rounded-full hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-800 {{ ($detail['visibility'] ?? 'same') === 'private' ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' : '' }}">@if(($detail['visibility'] ?? 'same') === 'private')<flux:icon.lock-closed class="size-4" />@else<flux:icon.lock-open class="size-4" />@endif</button>
                                        <button type="button" wire:click="moveDetail({{ $index }}, -1)" class="grid size-8 place-items-center rounded-full hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-800" aria-label="{{ __('composer.up') }}"><flux:icon.chevron-up class="size-4" /></button>
                                        <button type="button" wire:click="moveDetail({{ $index }}, 1)" class="grid size-8 place-items-center rounded-full hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-800" aria-label="{{ __('composer.down') }}"><flux:icon.chevron-down class="size-4" /></button>
                                        @if($custom)
                                            <button type="button" wire:click="saveField({{ $index }})" class="inline-flex min-h-8 items-center rounded-full px-2 text-xs hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __('composer.save_field') }}</button>
                                            <button type="button" wire:click="suggestStandard({{ $index }})" class="inline-flex min-h-8 items-center rounded-full px-2 text-xs hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __('composer.suggest_standard') }}</button>
                                        @endif
                                        <button type="button" wire:click="removeDetail({{ $index }})" class="ms-auto grid size-8 place-items-center rounded-full hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-indigo-500 dark:hover:bg-zinc-800" aria-label="{{ __('composer.remove') }}"><flux:icon.x-mark class="size-4" /></button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif(! count($terms))
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('composer.empty_hint') }}</p>
                    @endif
                    @php($chips = array_slice(array_merge(array_map(fn ($k) => ['field', $k], array_slice($profile['recommendedFields'], 0, 4)), array_map(fn ($k) => ['term', $k], $profile['recommendedTerms']), array_map(fn ($k) => ['field', $k], array_slice($profile['recommendedFields'], 4))), 0, 7))
                    @if(count($chips))
                        <div class="flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('composer.suggested') }}" data-composer-suggestions>
                            <span class="text-xs text-zinc-500">{{ __('composer.suggested') }}</span>
                            @foreach($chips as [$kind, $key])
                                @if($kind === 'field')<x-market.chip dashed wire:click="addField('{{ $key }}')" wire:key="suggest-field-{{ $key }}">+ {{ \App\Support\Market\IntentDetailCatalog::label($key) }}</x-market.chip>
                                @else<x-market.chip dashed wire:click="addTerm('{{ $key }}')" wire:key="suggest-term-{{ $key }}">+ {{ __('composer.terms.'.$key.'.'.(! $isOffer && in_array($key, ['price', 'place'], true) ? 'need' : 'label')) }}</x-market.chip>@endif
                            @endforeach
                        </div>
                    @endif
                </div>
                @if($versions->isNotEmpty())
                    <details class="p-4 sm:p-5" data-composer-history>
                        <summary class="cursor-pointer text-base font-semibold">{{ __('composer.history') }} ({{ $versions->count() }})</summary>
                        <ul class="mt-3 divide-y">
                            @foreach($versions as $version)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                    <span>{{ __('composer.version', ['version' => $version->version]) }} · <time datetime="{{ $version->published_at->toIso8601String() }}">{{ $version->published_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>@if($version->id === $intent->published_version_id) <span class="text-xs text-zinc-500">({{ __('composer.current') }})</span>@endif</span>
                                    <span class="flex gap-2"><flux:button size="sm" :href="route('intents.versions.show', [$intent, $version])" target="_blank">{{ __('composer.view_version') }}</flux:button>@if($version->id !== $intent->published_version_id)<flux:button size="sm" wire:click="restoreVersion('{{ $version->uuid }}')" wire:confirm="{{ __('composer.restore_confirm') }}">{{ __('composer.restore') }}</flux:button>@endif</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        </div>
@if($profile['picker'])
@include('livewire.intents.composer.picker')
@endif
        <aside x-show="screen === 'preview' || wide" class="min-w-0 rounded-2xl border p-5 lg:sticky lg:top-24" role="region" aria-label="{{ __('composer.preview') }}">
            <h2 class="mb-4 text-lg font-semibold">{{ __('composer.preview') }}</h2>
            <flux:select wire:model.live="previewAudience" :label="__('composer.audience')"><option value="public">{{ __('composer.everyone') }}</option><option value="authenticated">{{ __('composer.signed_in') }}</option><option value="owner">{{ __('composer.only_me') }}</option></flux:select>
            <div class="mt-4"><x-market.intention-presentation :presentation="$presentation" /></div>
        </aside>
    </div>
    <div class="sticky bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-20 flex flex-wrap items-end gap-2 rounded-2xl border bg-white/95 p-3 pe-20 shadow-lg backdrop-blur dark:bg-zinc-900/95 sm:gap-3 sm:p-4 lg:bottom-4 lg:pe-4 [&>[data-flux-field]]:w-full sm:[&>[data-flux-field]]:w-auto [&>button]:h-auto [&>button]:min-h-11 [&>button]:flex-1 sm:[&>button]:flex-none" aria-label="{{ __('composer.publish_controls') }}">
        <flux:select wire:model.live="core.visibility" :label="__('composer.audience')"><option value="private">{{ __('composer.only_me') }}</option><option value="authenticated">{{ __('composer.signed_in') }}</option><option value="public">{{ __('composer.everyone') }}</option>@if($publisher->kind !== \App\Support\Parties\Party::ACTOR)<option value="members">{{ __('intents.visibility.members') }}</option>@endif @if(($core['visibility'] ?? '') === 'inherited')<option value="inherited">{{ __('ui.profile.item_visibility_options.inherited') }}</option>@endif</flux:select>
        <flux:button wire:click="saveDraft" wire:loading.attr="disabled">{{ __('composer.save_draft') }}</flux:button>
        <flux:button wire:click="publish" variant="primary" wire:loading.attr="disabled"><span class="sm:hidden">{{ __('composer.publish') }}</span><span class="hidden sm:inline">{{ __('composer.publish_to') }} {{ __('composer.audiences.'.($core['visibility'] ?? 'authenticated')) }}</span></flux:button>
        <span wire:loading role="status">{{ __('composer.saving') }}</span>
    </div>
</section>
