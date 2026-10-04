<section class="mx-auto max-w-4xl space-y-6">
    <x-app.page-header :title="__('planning_baseline.identity.title')" :description="__('planning_baseline.identity.help')" />

    <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 bg-zinc-50/80 p-5 sm:p-6 dark:border-zinc-800 dark:bg-zinc-950/50">
            <h2 class="text-lg font-semibold">{{ __('planning_baseline.identity.account') }}</h2>
            <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ __('planning_baseline.identity.account_help') }}</p>
        </div>

        <div class="grid gap-6 p-5 sm:p-6 md:grid-cols-[9rem_minmax(0,1fr)]">
            <div class="space-y-3">
                <div class="mx-auto flex size-28 items-center justify-center overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    @if ($profile->displayImage?->asset?->isReadyForPublication())
                        <img
                            src="{{ route('profiles.images.show', [$profile, $profile->displayImage]) }}"
                            alt="{{ __('ui.profile.avatar_alt', ['name' => $displayName ?: $user->username]) }}"
                            class="h-full w-full object-cover"
                        >
                    @else
                        <span class="text-3xl font-semibold text-zinc-500">
                            {{ mb_strtoupper(mb_substr($displayName ?: $user->username, 0, 1)) }}
                        </span>
                    @endif
                </div>

                <form wire:submit="uploadImage" class="space-y-2">
                    <label class="block">
                        <span class="sr-only">{{ __('ui.profile.upload_image') }}</span>
                        <input
                            type="file"
                            wire:model="imageUpload"
                            accept="image/jpeg,image/png,image/webp,image/avif"
                            class="block w-full text-xs file:me-2 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-xs file:font-semibold hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:hover:file:bg-zinc-700"
                        >
                    </label>
                    @error('imageUpload')
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    <flux:button type="submit" size="sm" class="w-full" wire:loading.attr="disabled" wire:target="imageUpload,uploadImage">
                        {{ __('ui.profile.upload_image') }}
                    </flux:button>
                </form>

                @if ($profile->displayImage)
                    <flux:button wire:click="removeImage" size="sm" variant="ghost" class="w-full">
                        {{ __('ui.profile.remove_image') }}
                    </flux:button>
                @endif
            </div>

            <form wire:submit="save" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('planning_baseline.identity.username') }}</div>
                        <div class="mt-1 font-semibold">{{ '@'.$user->username }}</div>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('planning_baseline.identity.email') }}</div>
                        <div class="mt-1 break-all font-semibold">{{ $user->email }}</div>
                    </div>
                </div>

                <flux:input wire:model="displayName" :label="__('planning_baseline.identity.display_name')" maxlength="120" />

                <div class="space-y-2">
                    <label for="baseline-profile-locale" class="text-sm font-medium">{{ __('planning_baseline.identity.language') }}</label>
                    <select
                        id="baseline-profile-locale"
                        wire:model="locale"
                        class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950"
                    >
                        @foreach ($locales as $code => $details)
                            <option value="{{ $code }}">{{ $details['native_name'] }} · {{ $details['name'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs leading-5 text-zinc-500">{{ __('planning_baseline.identity.language_help') }}</p>
                </div>

                <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                        {{ __('ui.common.save') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </section>

    <livewire:profile.account-email />
    <livewire:profile.temporal-preferences />
</section>
