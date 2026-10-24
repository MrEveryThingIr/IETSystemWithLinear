<?php

namespace App\Livewire\Intents;

use App\Actions\Market\ManageIntentMedia;
use App\Models\ActorProfileIntent;
use App\Models\ActorProfileIntentMedia;
use App\Models\User;
use App\Support\MediaLimits;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Photos extends Component
{
    use WithFileUploads;

    #[Locked]
    public ActorProfileIntent $intent;

    /** @var TemporaryUploadedFile|null */
    public $upload = null;

    public string $caption = '';

    public bool $rights = false;

    public function mount(ActorProfileIntent $intent): void
    {
        $this->authorize('update', $intent);
        $this->intent = $intent;
    }

    public function save(ManageIntentMedia $media): void
    {
        $this->authorize('update', $this->intent);
        $this->validate([
            'upload' => ['required', 'file', 'max:'.MediaLimits::kilobytes('asset'), 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime'],
            'caption' => ['nullable', 'string', 'max:500'],
            'rights' => ['accepted'],
        ], [], [
            'upload' => __('intents.media.file'),
            'rights' => __('intents.media.rights'),
        ]);
        $media->add($this->user(), $this->intent, $this->upload, $this->caption);
        $this->reset('upload', 'caption', 'rights');
        $this->dispatch('intent-media-added');
        session()->flash('status', __('intents.media.added'));
    }

    public function remove(string $uuid, ManageIntentMedia $media): void
    {
        $media->remove($this->user(), $this->item($uuid));
    }

    public function moveUp(string $uuid, ManageIntentMedia $media): void
    {
        $media->moveUp($this->user(), $this->item($uuid));
    }

    public function render(): View
    {
        $this->intent->loadMissing('concept.labels');

        return view('livewire.intents.photos', [
            'items' => $this->intent->media()->with('asset')->get(),
            'max' => ActorProfileIntentMedia::MAX_PER_INTENT,
        ])->title(__('intents.media.title'));
    }

    private function item(string $uuid): ActorProfileIntentMedia
    {
        return ActorProfileIntentMedia::query()->where('actor_profile_intent_id', $this->intent->id)->where('uuid', $uuid)->firstOrFail();
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
