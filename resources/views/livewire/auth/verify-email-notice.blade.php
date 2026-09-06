<section class="space-y-6">
    <h1 class="text-2xl font-semibold">Verify your email</h1>
    <p>Use the link in your verification email to access your dashboard.</p>
    @if (session('status')) <p role="status">{{ session('status') }}</p> @endif
    <form wire:submit="resend">
        <button type="submit" wire:loading.attr="disabled" class="rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50">Resend verification email</button>
        @error('resend') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
    </form>
    <a href="{{ route('dashboard') }}" class="underline">Continue to dashboard</a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="underline">Log out</button>
    </form>
</section>
