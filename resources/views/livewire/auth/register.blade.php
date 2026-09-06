<section class="space-y-6">
    <h1 class="text-2xl font-semibold">Create your account</h1>
    @if (session('status')) <p role="status">{{ session('status') }}</p> @endif
    <form wire:submit="register" class="space-y-4">
    <div>
        <label for="username" class="block font-medium">Username</label>
        <input id="username" type="text" wire:model="username" autocomplete="username" required class="mt-1 w-full rounded border px-3 py-2">
        @error('username') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="email" class="block font-medium">Email</label>
        <input id="email" type="email" wire:model="email" autocomplete="email" required class="mt-1 w-full rounded border px-3 py-2">
        @error('email') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password" class="block font-medium">Password</label>
        <input id="password" type="password" wire:model="password" autocomplete="new-password" required class="mt-1 w-full rounded border px-3 py-2">
        @error('password') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password_confirmation" class="block font-medium">Confirm password</label>
        <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required class="mt-1 w-full rounded border px-3 py-2">
        @error('password_confirmation') <p role="alert" class="text-red-700">{{ $message }}</p> @enderror
    </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50">Create account</button>
    </form>
    <a href="{{ route('login') }}" class="underline">Already registered? Log in</a>
</section>
