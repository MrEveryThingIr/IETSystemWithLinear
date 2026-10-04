<flux:header class="border-b border-indigo-200/70 bg-gradient-to-r from-indigo-50 via-white to-cyan-50 py-2 dark:border-indigo-950 dark:from-indigo-950/50 dark:via-zinc-950 dark:to-cyan-950/30">
    <div class="flex min-w-0 flex-1 items-center gap-3">
        <span class="hidden shrink-0 items-center gap-2 rounded-full bg-indigo-100 px-3 py-1 text-[0.7rem] font-black uppercase tracking-[0.14em] text-indigo-700 sm:inline-flex dark:bg-indigo-950 dark:text-indigo-200">
            <span aria-hidden="true">◷</span>
            {{ __('ui.ambient.label') }}
        </span>
        <x-app.ambient-status />
    </div>
</flux:header>
