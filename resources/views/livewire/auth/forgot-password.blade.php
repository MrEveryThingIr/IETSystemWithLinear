<section class="space-y-6">
    <h1 class="text-2xl font-semibold">Forgot password</h1>
    @if (session('status')) <p role="status">{{ session('status') }}</p> @endif
    <form wire:submit="sendResetLink" class="space-y-4">
    <div>
        <label for="email" class="block font-medium">Email</label>
        <input id="email" type="email" wire:model="email" autocomplete="email" required class="mt-1 w-full rounded border px-3 py-2">
        @error('email') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
    </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50">Send reset link</button>
    </form>
    <a href="{{ route('login') }}" class="underline">Back to login</a>
</section>
