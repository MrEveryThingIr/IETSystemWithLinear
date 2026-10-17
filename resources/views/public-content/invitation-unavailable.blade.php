<x-public-content.layout :title="__('public_content.invitation.unavailable_title')" noindex>
    <main class="mx-auto flex w-full max-w-xl flex-col items-center gap-4 px-4 py-20 text-center sm:px-6" data-invitation-unavailable="{{ $reason }}">
        <flux:icon name="link-slash" class="size-10 text-zinc-400" />
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('public_content.invitation.unavailable_title') }}</h1>
        <p class="text-zinc-600 dark:text-zinc-300">{{ __('public_content.invitation.unavailable_'.$reason) }}</p>
        @if ($invitation->creator?->user)
            <p class="text-sm text-zinc-500">{{ __('public_content.invitation.ask_inviter', ['name' => $invitation->creator->user->username]) }}</p>
        @endif
    </main>
</x-public-content.layout>
