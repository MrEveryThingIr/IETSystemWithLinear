<?php

namespace App\Actions\Market;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Contexts\EnsureBusinessContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\ActorProfileIntentMedia;
use App\Models\Business;
use App\Models\Context;
use App\Models\User;
use App\Support\Parties\Party;
use App\Support\RequestMemo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Photos and short videos of a Need/Offer. Files go through the shared Asset
 * pipeline (size and type limits, hashing, video processing) and are stored
 * in the publisher's context: the business's for business offers, otherwise
 * the acting person's own. They are served only to people who may see the
 * Need/Offer itself.
 */
class ManageIntentMedia
{
    public function __construct(
        private readonly CreateContextAsset $assets,
        private readonly EnsurePersonalContext $personal,
        private readonly EnsureBusinessContext $business,
    ) {}

    public function add(User $user, ActorProfileIntent $intent, UploadedFile $upload, ?string $caption = null): ActorProfileIntentMedia
    {
        Gate::forUser($user)->authorize('update', $intent);
        $mime = (string) $upload->getMimeType();
        if (! str_starts_with($mime, 'image/') && ! str_starts_with($mime, 'video/')) {
            throw ValidationException::withMessages(['upload' => __('intents.media.only_images_video')]);
        }
        if ($intent->media()->count() >= ActorProfileIntentMedia::MAX_PER_INTENT) {
            throw ValidationException::withMessages(['upload' => __('intents.media.too_many', ['max' => ActorProfileIntentMedia::MAX_PER_INTENT])]);
        }

        $caption = $caption !== null && trim($caption) !== '' ? Str::limit(Str::squish($caption), 500, '') : null;
        $asset = $this->assets->execute($this->contextFor($user, $intent), $user, $upload, 'owned', altText: $caption);
        $actor = $this->actor($user);

        return DB::transaction(function () use ($intent, $asset, $caption, $actor): ActorProfileIntentMedia {
            $locked = ActorProfileIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if ($locked->media()->count() >= ActorProfileIntentMedia::MAX_PER_INTENT) {
                throw ValidationException::withMessages(['upload' => __('intents.media.too_many', ['max' => ActorProfileIntentMedia::MAX_PER_INTENT])]);
            }

            return ActorProfileIntentMedia::query()->create([
                'actor_profile_intent_id' => $locked->id,
                'asset_id' => $asset->id,
                'position' => ((int) $locked->media()->max('position')) + 1,
                'caption' => $caption,
                'created_by_actor_id' => $actor->id,
            ]);
        }, 3);
    }

    /** Removes the photo from the Need/Offer; the stored file stays with its context (assets are never hard-deleted here). */
    public function remove(User $user, ActorProfileIntentMedia $media): void
    {
        Gate::forUser($user)->authorize('update', $media->intent);
        $media->delete();
    }

    /** Moves a photo one place earlier (first = cover). */
    public function moveUp(User $user, ActorProfileIntentMedia $media): void
    {
        Gate::forUser($user)->authorize('update', $media->intent);
        DB::transaction(function () use ($media): void {
            $items = ActorProfileIntentMedia::query()->where('actor_profile_intent_id', $media->actor_profile_intent_id)
                ->orderBy('position')->orderBy('id')->lockForUpdate()->get()->values();
            $index = $items->search(fn (ActorProfileIntentMedia $item): bool => $item->id === $media->id);
            if (! is_int($index) || $index === 0) {
                return;
            }
            $ordered = $items->all();
            [$ordered[$index - 1], $ordered[$index]] = [$ordered[$index], $ordered[$index - 1]];
            foreach (array_values($ordered) as $position => $item) {
                $item->update(['position' => $position + 1]);
            }
        }, 3);
    }

    private function contextFor(User $user, ActorProfileIntent $intent): Context
    {
        $publisher = $intent->publisher();
        if ($publisher->kind === Party::BUSINESS) {
            return $this->business->execute(Business::query()->findOrFail($publisher->id));
        }

        return $this->personal->execute($user);
    }

    private function actor(User $user): Actor
    {
        $actor = RequestMemo::user($user)?->actor;
        abort_unless($actor instanceof Actor, 403);

        return $actor;
    }
}
