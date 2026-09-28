<?php

namespace App\Livewire\Profile;

use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Profile\RemoveActorProfileImage;
use App\Actions\Profile\SetDisplayedProfileImage;
use App\Actions\Profile\UpdateActorProfile;
use App\Actions\Profile\UploadActorProfileImage;
use App\Models\ActorProfile;
use App\Models\User;
use App\Support\Localization;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Profile')]
class BasicManage extends Component
{
    use WithFileUploads;

    #[Locked]
    public ActorProfile $profile;

    public string $displayName = '';

    public string $locale = 'en';

    public mixed $imageUpload = null;

    public function mount(EnsureActorProfile $ensureProfile): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->profile = $ensureProfile->execute($user);
        $this->syncForm($user);
    }

    public function save(UpdateActorProfile $updateProfile): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->validate([
            'displayName' => ['nullable', 'string', 'max:120'],
            'locale' => ['required', 'string', Rule::in(Localization::codes())],
        ]);

        $this->profile = $updateProfile->execute($user, $this->profile, [
            'display_name' => $data['displayName'],
            'headline' => $this->profile->headline,
            'bio' => $this->profile->bio,
            'location_text' => $this->profile->location_text,
            'website_url' => $this->profile->website_url,
            'visibility' => $this->profile->visibility->value,
        ]);

        $user->locale = $data['locale'];
        $user->save();

        $this->syncForm($user);
        session()->flash('status', __('ui.profile.saved'));

        $this->redirectRoute('profile.edit', navigate: true);
    }

    public function uploadImage(
        UploadActorProfileImage $upload,
        SetDisplayedProfileImage $setDisplayed,
    ): void {
        $this->validate([
            'imageUpload' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/avif',
                'max:8192',
            ],
        ]);

        abort_unless($this->imageUpload instanceof UploadedFile, 422);

        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $image = $upload->execute($user, $this->profile, $this->imageUpload);
        $this->profile = $setDisplayed->execute($user, $this->profile, $image);
        $this->reset('imageUpload');
        $this->refreshProfile();

        session()->flash('status', __('ui.profile.image_uploaded'));
    }

    public function removeImage(
        SetDisplayedProfileImage $setDisplayed,
        RemoveActorProfileImage $remove,
    ): void {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->refreshProfile();
        $image = $this->profile->displayImage;

        if ($image === null) {
            return;
        }

        $this->profile = $setDisplayed->execute($user, $this->profile, null);
        $remove->execute($user, $this->profile, $image);
        $this->refreshProfile();

        session()->flash('status', __('ui.profile.image_removed'));
    }

    public function render(): View
    {
        Gate::authorize('update', $this->profile);
        $this->refreshProfile();

        return view('livewire.profile.basic-manage', [
            'user' => request()->user(),
            'locales' => Localization::supported(),
        ]);
    }

    private function syncForm(User $user): void
    {
        $this->displayName = (string) $this->profile->display_name;
        $this->locale = Localization::supports($user->locale)
            ? (string) $user->locale
            : (string) config('app.locale', 'en');
    }

    private function refreshProfile(): void
    {
        $this->profile = $this->profile->fresh(['displayImage.asset']) ?? $this->profile;
    }
}
