{{-- "Notify me on this device" (Web Push), see resources/js/device-push.js. --}}
@php
    $pushConfig = [
        'publicKey' => \App\Support\Push\VapidKeys::publicKey(),
        'storeUrl' => route('push.subscriptions.store'),
        'testUrl' => route('push.test'),
        'labels' => [
            'enabled' => __('notifications.device.enabled'),
            'failed' => __('notifications.device.failed'),
            'sending' => __('notifications.device.sending'),
            'test_sent' => __('notifications.device.test_sent'),
            'test_failed' => __('notifications.device.test_failed'),
        ],
    ];
@endphp
<div x-data="devicePush(@js($pushConfig))" wire:ignore {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 p-3 text-sm dark:border-zinc-700']) }} data-device-notifications>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-start gap-2">
            <flux:icon name="device-phone-mobile" class="mt-0.5 size-5 shrink-0 text-zinc-500" />
            <div>
                <div class="font-medium">{{ __('notifications.device.title') }}</div>
                <div class="text-xs text-zinc-500">
                    <span x-show="state === 'on'" x-cloak>{{ __('notifications.device.state_on') }}</span>
                    <span x-show="state === 'off' || state === 'working' || state === 'checking'">{{ __('notifications.device.state_off') }}</span>
                    <span x-show="state === 'blocked'" x-cloak class="text-amber-700 dark:text-amber-400">{{ __('notifications.device.state_blocked') }}</span>
                    <span x-show="state === 'unsupported'" x-cloak>{{ __('notifications.device.state_unsupported') }}</span>
                    <span x-show="state === 'ios'" x-cloak>{{ __('notifications.device.state_ios') }}</span>
                </div>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="button" x-show="state === 'off' || state === 'working'" x-on:click="enable()" x-bind:disabled="state === 'working'" class="rounded-lg bg-zinc-900 px-3 py-1.5 text-xs font-medium text-white disabled:opacity-60 dark:bg-white dark:text-zinc-900" data-device-enable>{{ __('notifications.device.enable') }}</button>
            <button type="button" x-show="state === 'on'" x-cloak x-on:click="test()" class="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs dark:border-zinc-700">{{ __('notifications.device.test') }}</button>
            <button type="button" x-show="state === 'on'" x-cloak x-on:click="disable()" class="rounded-lg px-3 py-1.5 text-xs text-zinc-500 hover:underline">{{ __('notifications.device.disable') }}</button>
        </div>
    </div>
    <p x-show="message" x-cloak x-text="message" class="mt-2 text-xs text-zinc-600 dark:text-zinc-300"></p>
</div>
