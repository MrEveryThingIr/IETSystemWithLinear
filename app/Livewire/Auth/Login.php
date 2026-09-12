<?php

namespace App\Livewire\Auth;

use App\Actions\Groups\RedeemGroupInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Log in')]
class Login extends Component
{
    public ?string $invitationToken = null;

    public ?string $groupName = null;

    public ?string $targetEmailHint = null;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(RedeemGroupInvitation $redemption, ?string $token = null): void
    {
        if ($token === null) {
            return;
        }

        $invitation = $redemption->preview($token);
        $this->invitationToken = $token;
        $this->groupName = $invitation->group->name;
        $this->targetEmailHint = $invitation->maskedEmail();
    }

    public function login(RedeemGroupInvitation $redemption): void
    {
        $data = $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if ($this->invitationToken !== null) {
            $invitation = $redemption->preview($this->invitationToken);

            if ($invitation->email !== null && strcasecmp($invitation->email, $data['email']) !== 0) {
                throw ValidationException::withMessages([
                    'email' => __('ui.messages.invitation_email_mismatch'),
                ]);
            }
        }

        $key = 'login:'.Str::lower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('ui.messages.too_many_login_attempts')]);
        }

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'status' => 'active'], $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
        session()->regenerate();
        $this->reset('password');

        if ($this->invitationToken !== null) {
            $user = Auth::user();
            $admission = $redemption->execute($this->invitationToken, $user->actor, $user->email);
            session()->put('url.intended', route('admissions.show', $admission));

            if (! $user->hasVerifiedEmail()) {
                $this->redirectRoute('verification.notice');

                return;
            }

            $this->redirectRoute('admissions.show', ['admission' => $admission]);

            return;
        }

        $this->redirectIntended(route('dashboard'));
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
