<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Verify your email')]
class VerifyEmailNotice extends Component
{
    public function resend(): void
    {
        $user = request()->user();
        abort_unless($user !== null, 401);

        if ($user->hasVerifiedEmail()) {
            $this->redirectRoute('dashboard');

            return;
        }

        $key = 'verification-resend:'.$user->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages(['resend' => 'Please wait a minute before requesting another verification email.']);
        }
        RateLimiter::hit($key, 60);
        $user->sendEmailVerificationNotification();
        session()->flash('status', 'A new verification link has been sent.');
    }

    public function render(): View
    {
        return view('livewire.auth.verify-email-notice');
    }
}
