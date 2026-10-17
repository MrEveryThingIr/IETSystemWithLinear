{{--
    A published edition read by a guest: through its public page, or through
    an invitation link. Read-only — no comments, drafts, history or places.
--}}
@php
    $guestTimezone = \App\Support\TemporalPreferences::timezoneFor(request()->user());
@endphp
<x-public-content.layout
    :title="$revision->title"
    :description="$excerpt !== '' ? $excerpt : null"
    :image="$canonicalUrl ? $coverUrl : null"
    :canonical="$canonicalUrl"
    :noindex="$invitation !== null"
>
    <section
        class="min-h-screen px-4 pb-16 sm:px-6"
        style="--content-bg: {{ $readerBackground }}; --content-surface: {{ $readerSurface }}; --content-text: {{ $readerText }}; --content-muted: {{ $readerMuted }}; --content-accent: {{ $readerAccent }}; --content-border: {{ $readerBorder }}; background: var(--content-bg); color: var(--content-text);"
        data-public-reader
    >
        @if ($invitation && $isInvitationRoot)
            <div class="mx-auto w-full max-w-3xl pt-6">
                <div class="rounded-2xl border p-4 sm:p-5" style="border-color: var(--content-border); background: var(--content-surface)" data-invitation-note>
                    <div class="flex items-start gap-3">
                        <flux:icon name="envelope-open" class="mt-0.5 size-5 shrink-0" style="color: var(--content-accent)" />
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="font-medium" dir="auto">{{ __('public_content.invitation.greeting', ['name' => $invitation->invitee_name]) }}</p>
                            <p class="text-sm" style="color: var(--content-muted)" dir="auto">
                                {{ __('public_content.invitation.invited_by', ['name' => $invitation->creator?->user?->username ?? __('public_content.invitation.someone')]) }}
                            </p>
                            @if ($invitation->message)
                                <p class="whitespace-pre-wrap pt-2 text-sm leading-6" dir="auto">{{ $invitation->message }}</p>
                            @endif
                            @if ($invitation->expires_at)
                                <p class="pt-1 text-xs" style="color: var(--content-muted)">
                                    {{ __('public_content.invitation.expires') }}
                                    <x-app.local-date :value="$invitation->expires_at->copy()->setTimezone($guestTimezone)" />
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <article class="mx-auto w-full max-w-7xl pt-8">
            <header class="mx-auto {{ $contentWidthClass }} border-b pb-6" style="border-color: var(--content-border)" data-reader-header>
                @if ($navigation)
                    <a href="{{ $navigation['parent']['url'] }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium hover:underline" style="color: var(--content-accent)" data-reader-course>
                        <span dir="auto">{{ $navigation['parent']['title'] }}</span>
                        <span style="color: var(--content-muted)">· {{ __('reader.position_of', ['position' => $navigation['position'], 'total' => $navigation['total']]) }}</span>
                    </a>
                @endif

                <h1 class="text-3xl sm:text-4xl {{ $headingClass }}" dir="auto">{{ $revision->title }}</h1>

                <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm" style="color: var(--content-muted)">
                    <x-app.actor-identity :actor="$content->author" size="xs" :linked="false" />
                    @if ($content->published_at)
                        <span aria-hidden="true">·</span>
                        <x-app.local-date :value="$content->published_at->copy()->setTimezone($guestTimezone)" />
                    @endif
                    <span aria-hidden="true">·</span>
                    <span>{{ $kindLabel }}</span>
                </div>
            </header>

            <div class="mx-auto mt-8 {{ $contentWidthClass }} space-y-7">
                @include('content.partials.reader-body')

                @if ($children !== [])
                    <section class="space-y-3 border-t pt-7" style="border-color: var(--content-border)" data-public-children>
                        <h2 class="text-xl {{ $headingClass }}">{{ __('public_content.reader.contents') }}</h2>
                        <ol class="space-y-2">
                            @foreach ($children as $index => $child)
                                <li>
                                    <a href="{{ $child['url'] }}" class="flex items-center gap-3 rounded-xl border p-3 hover:underline" style="border-color: var(--content-border); background: var(--content-surface)">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold" style="background: var(--content-bg)">{{ $index + 1 }}</span>
                                        <span class="min-w-0 flex-1 truncate font-medium" dir="auto">{{ $child['title'] !== '' ? $child['title'] : __('ui.content.untitled') }}</span>
                                        @if ($child['kind'])
                                            <span class="shrink-0 text-xs" style="color: var(--content-muted)">{{ $child['kind'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if ($navigation && ($navigation['previous'] || $navigation['next']))
                    <nav class="grid gap-3 border-t pt-6 sm:grid-cols-2" style="border-color: var(--content-border)" aria-label="{{ __('reader.course_navigation') }}" data-reader-course-nav>
                        <div>
                            @if ($navigation['previous'])
                                <a href="{{ $navigation['previous']['url'] }}" class="block rounded-xl border p-4 hover:underline" style="border-color: var(--content-border)">
                                    <div class="text-xs" style="color: var(--content-muted)">{{ __('reader.previous') }}</div>
                                    <div class="font-medium" dir="auto">{{ $navigation['previous']['title'] }}</div>
                                </a>
                            @endif
                        </div>
                        <div class="sm:text-end">
                            @if ($navigation['next'])
                                <a href="{{ $navigation['next']['url'] }}" class="block rounded-xl border p-4 hover:underline" style="border-color: var(--content-border)">
                                    <div class="text-xs" style="color: var(--content-muted)">{{ __('reader.next') }}</div>
                                    <div class="font-medium" dir="auto">{{ $navigation['next']['title'] }}</div>
                                </a>
                            @endif
                        </div>
                    </nav>
                @endif

                @guest
                    <p class="border-t pt-6 text-sm" style="border-color: var(--content-border); color: var(--content-muted)" data-public-signin-hint>
                        {{ __('public_content.reader.read_only') }}
                        <a href="{{ route('login') }}" class="font-medium underline underline-offset-4" style="color: var(--content-accent)">{{ __('ui.auth.login') }}</a>
                    </p>
                @endguest
            </div>
        </article>
    </section>
</x-public-content.layout>
