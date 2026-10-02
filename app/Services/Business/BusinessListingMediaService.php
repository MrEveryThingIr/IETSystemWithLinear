<?php

namespace App\Services\Business;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Contexts\EnsureBusinessContext;
use App\Models\Actor;
use App\Models\Business;
use App\Models\BusinessListing;
use App\Models\BusinessListingMedia;
use App\Models\BusinessListingVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessListingMediaService
{
    public function __construct(
        private readonly EnsureBusinessContext $contexts,
        private readonly CreateContextAsset $assets,
    ) {}

    public function attach(
        Business $business,
        BusinessListing $listing,
        BusinessListingVersion $version,
        User $user,
        UploadedFile $upload,
        string $rightsStatus,
        ?string $caption,
        string $visibility = 'public',
        bool $cover = false,
    ): BusinessListingMedia {
        $this->assertEditable($business, $listing, $version);
        abort_unless(in_array($visibility, ['private', 'members', 'public'], true), 422);

        $caption = $this->optionalText($caption, 1000);
        $context = $this->contexts->execute($business);
        $asset = $this->assets->execute($context, $user, $upload, $rightsStatus, altText: $caption);
        $actor = $this->actor($user);

        return DB::transaction(function () use (
            $version,
            $asset,
            $actor,
            $caption,
            $visibility,
            $cover,
        ): BusinessListingMedia {
            $locked = BusinessListingVersion::query()->lockForUpdate()->findOrFail($version->id);
            abort_if($locked->published_at !== null, 422, 'Published Listing versions cannot receive media.');

            $next = ((int) $locked->media()->max('position')) + 1;
            $makeCover = $cover || ! $locked->media()->exists();

            if ($makeCover) {
                $locked->media()->where('role', 'cover')->update(['role' => 'gallery']);
            }

            return BusinessListingMedia::query()->create([
                'business_listing_version_id' => $locked->id,
                'asset_id' => $asset->id,
                'role' => $makeCover ? 'cover' : 'gallery',
                'position' => $next,
                'caption' => $caption,
                'visibility' => $visibility,
                'created_by_actor_id' => $actor->id,
            ]);
        }, attempts: 3);
    }

    public function update(
        BusinessListingMedia $media,
        User $user,
        int $position,
        ?string $caption,
        string $visibility,
        bool $cover,
    ): BusinessListingMedia {
        abort_unless(in_array($visibility, ['private', 'members', 'public'], true), 422);
        abort_if($position < 0 || $position > 10000, 422);
        $caption = $this->optionalText($caption, 1000);
        $this->actor($user);

        return DB::transaction(function () use ($media, $position, $caption, $visibility, $cover): BusinessListingMedia {
            $locked = BusinessListingMedia::query()
                ->with('listingVersion')
                ->lockForUpdate()
                ->findOrFail($media->id);

            abort_if($locked->listingVersion->published_at !== null, 422, 'Published Listing media cannot be changed.');

            if ($cover) {
                BusinessListingMedia::query()
                    ->where('business_listing_version_id', $locked->business_listing_version_id)
                    ->where('id', '!=', $locked->id)
                    ->where('role', 'cover')
                    ->update(['role' => 'gallery']);
            }

            $locked->update([
                'position' => $position,
                'caption' => $caption,
                'visibility' => $visibility,
                'role' => $cover ? 'cover' : ($locked->role === 'cover' ? 'gallery' : $locked->role),
            ]);

            return $locked->fresh(['asset']);
        }, attempts: 3);
    }

    public function remove(BusinessListingMedia $media, User $user): void
    {
        $this->actor($user);

        DB::transaction(function () use ($media): void {
            $locked = BusinessListingMedia::query()
                ->with('listingVersion')
                ->lockForUpdate()
                ->findOrFail($media->id);

            abort_if($locked->listingVersion->published_at !== null, 422, 'Published Listing media cannot be removed.');
            $wasCover = $locked->role === 'cover';
            $versionId = $locked->business_listing_version_id;
            $locked->delete();

            if ($wasCover) {
                $replacement = BusinessListingMedia::query()
                    ->where('business_listing_version_id', $versionId)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->first();

                $replacement?->update(['role' => 'cover']);
            }
        }, attempts: 3);
    }

    private function assertEditable(
        Business $business,
        BusinessListing $listing,
        BusinessListingVersion $version,
    ): void {
        abort_unless((int) $listing->business_id === (int) $business->id, 404);
        abort_unless((int) $version->business_listing_id === (int) $listing->id, 404);
        abort_if($version->published_at !== null, 422, 'Published Listing versions are immutable.');
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }

    private function optionalText(?string $value, int $max): ?string
    {
        $value = $value !== null ? trim($value) : null;
        $value = $value === '' ? null : $value;

        if ($value !== null && mb_strlen($value) > $max) {
            throw ValidationException::withMessages(['caption' => 'Caption is too long.']);
        }

        return $value;
    }
}
