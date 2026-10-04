<?php

namespace App\Livewire\Profile;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AccountEmail extends Component
{
    public string $email = '';

    public string $currentPassword = '';

    public bool $editorOpen = false;

    public function mount(): void
    {
        $this->syncEmail();
    }

    public function openEditor(): void
    {
        $this->syncEmail();
        $this->resetValidation();
        $this->currentPassword = '';
        $this->editorOpen = true;
    }

    public function cancelEditor(): void
    {
        $this->syncEmail();
        $this->resetValidation();
        $this->currentPassword = '';
        $this->editorOpen = false;
    }

    public function save(): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->email = Str::lower(trim($this->email));

        if ($this->email === Str::lower((string) $user->email)) {
            $this->addError('email', __('ui.profile.account_email.same_email'));

            return;
        }

        $data = $this->validate([
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
            'currentPassword' => ['required', 'current_password'],
        ]);

        $user->forceFill([
            'email' => $data['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        $this->currentPassword = '';
        $this->editorOpen = false;

        session()->flash('status', __('ui.profile.account_email.verification_sent'));
        $this->redirectRoute('verification.notice', navigate: true);
    }

    public function render(): View
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return view('livewire.profile.account-email', [
            'user' => $user,
        ]);
    }

    private function syncEmail(): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->email = (string) $user->email;
    }
}
