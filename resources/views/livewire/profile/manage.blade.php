<section class="space-y-8">
    <x-app.flash-message />

    <x-app.page-header :title="__('ui.profile.edit')" :description="__('ui.profile.edit_help')">
        <x-slot:actions>
            <flux:button
                :href="route('profiles.show', $profile)"
                variant="ghost"
                icon="arrow-top-right-on-square"
                class="w-full sm:w-auto"
            >
                {{ __('ui.profile.preview') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <div class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.8fr)]">
        <section class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold">{{ __('ui.profile.identity') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile.identity_help') }}</p>
                </div>

                @unless ($identityEditorOpen)
                    <flux:button
                        wire:click="openIdentityEditor"
                        size="sm"
                        variant="ghost"
                        icon="plus"
                        class="w-full shrink-0 sm:w-auto"
                    >
                        {{ __('ui.common.edit') }}
                    </flux:button>
                @endunless
            </div>

            @if ($identityEditorOpen)
                <form wire:submit="save" class="mt-6 space-y-5">
                    <flux:input wire:model="displayName" :label="__('ui.profile.display_name')" maxlength="120" />
                    <flux:input wire:model="headline" :label="__('ui.profile.headline')" maxlength="180" />
                    <flux:textarea wire:model="bio" :label="__('ui.profile.bio')" rows="7" maxlength="3000" />
                    <flux:input wire:model="locationText" :label="__('ui.profile.location')" maxlength="180" />
                    <flux:input wire:model="websiteUrl" type="url" :label="__('ui.profile.website_url')" maxlength="500" placeholder="https://" />

                    <div class="space-y-2">
                        <label for="profile-visibility" class="text-sm font-medium">{{ __('ui.profile.visibility') }}</label>
                        <select
                            id="profile-visibility"
                            wire:model="visibility"
                            class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-200 dark:border-zinc-700 dark:bg-zinc-900 dark:focus:ring-zinc-800"
                        >
                            @foreach ($visibilityOptions as $option)
                                <option value="{{ $option->value }}">{{ __('ui.profile.visibility_options.'.$option->value) }}</option>
                            @endforeach
                        </select>
                        <p class="text-sm text-zinc-500">{{ __('ui.profile.visibility_help') }}</p>
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <flux:button
                            type="button"
                            wire:click="cancelIdentityEditor"
                            variant="ghost"
                            class="w-full sm:w-auto"
                        >
                            {{ __('ui.common.cancel') }}
                        </flux:button>
                        <flux:button
                            type="submit"
                            variant="primary"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="w-full sm:w-auto"
                        >
                            {{ __('ui.common.save') }}
                        </flux:button>
                    </div>
                </form>
            @else
                <div class="mt-6 space-y-5">
                    <div class="min-w-0">
                        <h3 class="break-words text-xl font-semibold" dir="auto">
                            {{ $profile->display_name ?: $profile->actor->user?->username ?: __('ui.profile.unnamed') }}
                        </h3>
                        @if ($profile->headline)
                            <p class="mt-1 break-words text-sm text-zinc-600 dark:text-zinc-300" dir="auto">{{ $profile->headline }}</p>
                        @endif
                    </div>

                    @if ($profile->bio)
                        <p class="whitespace-pre-line break-words text-sm leading-7 text-zinc-700 dark:text-zinc-200" dir="auto">{{ $profile->bio }}</p>
                    @endif

                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        @if ($profile->location_text)
                            <div class="min-w-0">
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('ui.profile.location') }}</dt>
                                <dd class="mt-1 break-words" dir="auto">{{ $profile->location_text }}</dd>
                            </div>
                        @endif

                        @if ($profile->website_url)
                            <div class="min-w-0">
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('ui.profile.website') }}</dt>
                                <dd class="mt-1">
                                    <a
                                        href="{{ $profile->website_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="break-all underline decoration-zinc-300 underline-offset-2"
                                    >
                                        {{ $profile->website_url }}
                                    </a>
                                </dd>
                            </div>
                        @endif

                        <div class="min-w-0">
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('ui.profile.visibility') }}</dt>
                            <dd class="mt-1">{{ __('ui.profile.visibility_options.'.$profile->visibility->value) }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </section>

        <div class="min-w-0 space-y-6">
            <section class="rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">{{ __('ui.profile.display_image') }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile.display_image_help') }}</p>

                <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-zinc-200 dark:bg-zinc-800">
                        @if ($profile->displayImage && $profile->displayImage->asset?->isReadyForPublication())
                            <img
                                src="{{ route('profiles.images.show', [$profile, $profile->displayImage]) }}"
                                alt=""
                                class="h-full w-full object-cover"
                            >
                        @else
                            <div class="flex h-full w-full items-center justify-center text-2xl font-semibold text-zinc-500">
                                {{ mb_strtoupper(mb_substr($profile->display_name ?: $profile->actor->user?->username ?: '?', 0, 1)) }}
                            </div>
                        @endif
                    </div>

                    @if ($profile->display_profile_image_id)
                        <flux:button
                            wire:click="clearDisplayImage"
                            variant="ghost"
                            size="sm"
                            class="w-full sm:w-auto"
                        >
                            {{ __('ui.profile.clear_display_image') }}
                        </flux:button>
                    @endif
                </div>
            </section>

            <section class="min-w-0 rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold">{{ __('ui.profile.images') }}</h2>
                        <p class="mt-1 text-sm text-zinc-500">{{ __('ui.profile.images_help') }}</p>
                    </div>

                    <flux:button
                        wire:click="toggleMediaEditor"
                        size="sm"
                        variant="ghost"
                        icon="plus"
                        class="w-full shrink-0 sm:w-auto"
                    >
                        {{ __('ui.profile.upload_image') }}
                    </flux:button>
                </div>

                @if ($mediaEditorOpen)
                    <form wire:submit="uploadImage" class="mt-5 space-y-3 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-950/50">
                        <input
                            type="file"
                            wire:model="imageUpload"
                            accept="image/jpeg,image/png,image/webp,image/avif"
                            class="block w-full min-w-0 text-sm file:me-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:font-medium hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700"
                        >
                        @error('imageUpload')
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-zinc-500">{{ __('ui.profile.image_requirements') }}</p>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <flux:button
                                type="button"
                                wire:click="toggleMediaEditor"
                                variant="ghost"
                                class="w-full sm:w-auto"
                            >
                                {{ __('ui.common.cancel') }}
                            </flux:button>
                            <flux:button
                                type="submit"
                                size="sm"
                                wire:loading.attr="disabled"
                                wire:target="imageUpload,uploadImage"
                                class="w-full sm:w-auto"
                            >
                                {{ __('ui.profile.upload_image') }}
                            </flux:button>
                        </div>
                    </form>
                @endif

                @if ($profile->images->isNotEmpty())
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($profile->images as $image)
                            <article class="min-w-0 space-y-2" wire:key="profile-image-{{ $image->id }}">
                                <div class="aspect-square overflow-hidden rounded-xl bg-zinc-100 dark:bg-zinc-800">
                                    @if ($image->asset->isReadyForPublication())
                                        <img
                                            src="{{ route('profiles.images.show', [$profile, $image]) }}"
                                            alt=""
                                            class="h-full w-full object-cover"
                                        >
                                    @else
                                        <div class="flex h-full items-center justify-center px-3 text-center text-xs text-zinc-500">
                                            {{ __('ui.profile.processing') }}
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-col gap-1 sm:flex-row sm:flex-wrap">
                                    @if ((int) $profile->display_profile_image_id === (int) $image->id)
                                        <flux:badge color="green">{{ __('ui.profile.current') }}</flux:badge>
                                    @elseif ($image->asset->isReadyForPublication())
                                        <flux:button
                                            wire:click="setDisplayImage({{ $image->id }})"
                                            size="xs"
                                            variant="ghost"
                                            class="w-full sm:w-auto"
                                        >
                                            {{ __('ui.profile.use_as_display') }}
                                        </flux:button>
                                    @endif

                                    <flux:button
                                        wire:click="removeImage({{ $image->id }})"
                                        wire:confirm="{{ __('ui.profile.remove_image_confirm') }}"
                                        size="xs"
                                        variant="danger"
                                        class="w-full sm:w-auto"
                                    >
                                        {{ __('ui.profile.remove_image') }}
                                    </flux:button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>

    <livewire:profile.temporal-preferences />
    <livewire:profile.sharing :profile="$profile" />
    <livewire:profile.semantics :profile="$profile" />
    <livewire:profile.intents :profile="$profile" />
</section>
