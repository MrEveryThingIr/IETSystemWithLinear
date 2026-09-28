<?php

namespace App\Livewire\Profile;

use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Profile\UpdateActorProfile;
use App\Models\ActorProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Identity')]
class BasicManage extends Component
{
    #[Locked]
    public ActorProfile $profile;

    public string $displayName = '';

    public function mount(EnsureActorProfile $ensureProfile): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->profile = $ensureProfile->execute($user);
        $this->displayName = (string) $this->profile->display_name;
    }

    public function save(UpdateActorProfile $updateProfile): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->validate([
            'displayName' => ['nullable', 'string', 'max:120'],
        ]);

        $this->profile = $updateProfile->execute($user, $this->profile, [
            'display_name' => $this->displayName,
            'headline' => $this->profile->headline,
            'bio' => $this->profile->bio,
            'location_text' => $this->profile->location_text,
            'website_url' => $this->profile->website_url,
            'visibility' => $this->profile->visibility->value,
        ]);

        $this->displayName = (string) $this->profile->display_name;
        session()->flash('status', __('ui.profile.saved'));
    }

    public function render(): View
    {
        Gate::authorize('update', $this->profile);

        return view('livewire.profile.basic-manage', [
            'user' => request()->user(),
        ]);
    }
}
