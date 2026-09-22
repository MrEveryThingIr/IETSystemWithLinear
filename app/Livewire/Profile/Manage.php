<?php

namespace App\Livewire\Profile;

use App\Actions\Profile\EnsureActorProfile;
use App\Actions\Profile\RemoveActorProfileImage;
use App\Actions\Profile\SetDisplayedProfileImage;
use App\Actions\Profile\UpdateActorProfile;
use App\Actions\Profile\UploadActorProfileImage;
use App\Models\ActorProfile;
use App\Models\User;
use App\ProfileVisibility;
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
class Manage extends Component
{
    use WithFileUploads;

    #[Locked]
    public ActorProfile $profile;

    public ?string $displayName = null;

    public ?string $headline = null;

    public ?string $bio = null;

    public ?string $locationText = null;

    public ?string $websiteUrl = null;

    public string $visibility = ProfileVisibility::Private->value;

    public mixed $imageUpload = null;

    public bool $identityEditorOpen = false;

    public bool $mediaEditorOpen = false;

    public function mount(EnsureActorProfile $ensureProfile): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->profile = $ensureProfile->execute($user);
        $this->syncForm();
    }

    public function openIdentityEditor(): void
    {
        $this->syncForm();
        $this->resetValidation();
        $this->identityEditorOpen = true;
    }

    public function cancelIdentityEditor(): void
    {
        $this->syncForm();
        $this->resetValidation();
        $this->identityEditorOpen = false;
    }

    public function toggleMediaEditor(): void
    {
        $this->mediaEditorOpen = ! $this->mediaEditorOpen;

        if (! $this->mediaEditorOpen) {
            $this->reset('imageUpload');
            $this->resetValidation('imageUpload');
        }
    }

    public function save(UpdateActorProfile $updateProfile): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->validate([
            'displayName' => ['nullable', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:180'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'locationText' => ['nullable', 'string', 'max:180'],
            'websiteUrl' => ['nullable', 'url:http,https', 'max:500'],
            'visibility' => ['required', Rule::enum(ProfileVisibility::class)],
        ]);

        $this->profile = $updateProfile->execute($user, $this->profile, [
            'display_name' => $data['displayName'],
            'headline' => $data['headline'],
            'bio' => $data['bio'],
            'location_text' => $data['locationText'],
            'website_url' => $data['websiteUrl'],
            'visibility' => $data['visibility'],
        ]);

        $this->syncForm();
        $this->identityEditorOpen = false;
        session()->flash('status', __('ui.profile.saved'));
    }

    public function uploadImage(UploadActorProfileImage $uploadProfileImage): void
    {
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

        $uploadProfileImage->execute($user, $this->profile, $this->imageUpload);
        $this->reset('imageUpload');
        $this->refreshProfile();

        session()->flash('status', __('ui.profile.image_uploaded'));
    }

    public function setDisplayImage(int $imageId, SetDisplayedProfileImage $setDisplayedProfileImage): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $image = $this->profile->images()->findOrFail($imageId);
        $this->profile = $setDisplayedProfileImage->execute($user, $this->profile, $image);
        $this->refreshProfile();

        session()->flash('status', __('ui.profile.display_image_updated'));
    }

    public function clearDisplayImage(SetDisplayedProfileImage $setDisplayedProfileImage): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $this->profile = $setDisplayedProfileImage->execute($user, $this->profile, null);
        $this->refreshProfile();

        session()->flash('status', __('ui.profile.display_image_cleared'));
    }

    public function removeImage(int $imageId, RemoveActorProfileImage $removeProfileImage): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $image = $this->profile->images()->findOrFail($imageId);
        $removeProfileImage->execute($user, $this->profile, $image);
        $this->refreshProfile();

        session()->flash('status', __('ui.profile.image_removed'));
    }

    public function render(): View
    {
        Gate::authorize('update', $this->profile);

        return view('livewire.profile.manage', [
            'profile' => $this->profile->load([
                'actor.user',
                'images.asset',
                'displayImage.asset',
            ]),
            'visibilityOptions' => ProfileVisibility::cases(),
        ]);
    }

    private function syncForm(): void
    {
        $this->displayName = $this->profile->display_name;
        $this->headline = $this->profile->headline;
        $this->bio = $this->profile->bio;
        $this->locationText = $this->profile->location_text;
        $this->websiteUrl = $this->profile->website_url;
        $this->visibility = $this->profile->visibility->value;
    }

    private function refreshProfile(): void
    {
        $this->profile = $this->profile->fresh() ?? $this->profile;
        $this->profile->unsetRelations();
    }
}
