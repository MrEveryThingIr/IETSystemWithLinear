<?php

namespace App\Support\Media;

use App\Models\Asset;
use App\Models\AssetMediaTrack;
use App\Models\AssetMediaVariant;
use App\Models\Context;
use App\Models\SpaceContent;
use Illuminate\Support\Collection;

/**
 * Player data for Content media: poster frame, caption tracks and duration,
 * all as authorized Content routes (never public storage URLs).
 */
final class MediaPresentation
{
    /**
     * @param  Collection<int, Asset>  $assets
     * @param  (callable(Asset, AssetMediaVariant): string)|null  $posterUrl  route builder for guests
     * @param  (callable(Asset, AssetMediaTrack): string)|null  $trackUrl  route builder for guests
     * @return array<int, array{poster: string|null, tracks: list<array{src: string, label: string, language: string, default: bool, uuid: string}>, duration_ms: int|null}>
     */
    public static function forAssets(Collection $assets, Context $context, SpaceContent $content, ?callable $posterUrl = null, ?callable $trackUrl = null): array
    {
        $media = $assets->filter(fn (Asset $asset): bool => in_array($asset->mediaKind(), ['video', 'audio'], true));
        if ($media->isEmpty()) {
            return [];
        }

        $ids = $media->pluck('id')->all();
        $variants = AssetMediaVariant::query()->whereIn('asset_id', $ids)->where('processing_status', 'ready')->get()->groupBy('asset_id');
        $tracks = AssetMediaTrack::query()->whereIn('asset_id', $ids)->orderByDesc('is_default')->orderBy('id')->get()->groupBy('asset_id');

        $extras = [];
        foreach ($media as $asset) {
            $assetVariants = $variants->get($asset->id, collect());
            $poster = $assetVariants->firstWhere('type', AssetMediaVariant::TYPE_POSTER);
            $original = $assetVariants->firstWhere('type', AssetMediaVariant::TYPE_ORIGINAL);

            $extras[$asset->id] = [
                'poster' => $poster instanceof AssetMediaVariant
                    ? ($posterUrl !== null ? $posterUrl($asset, $poster) : route('contexts.contents.assets.variants.show', [$context, $content, $asset, $poster]))
                    : null,
                'tracks' => $tracks->get($asset->id, collect())
                    ->map(fn (AssetMediaTrack $track): array => [
                        'src' => $trackUrl !== null ? $trackUrl($asset, $track) : route('contexts.contents.assets.tracks.show', [$context, $content, $asset, $track]),
                        'label' => $track->label,
                        'language' => $track->language,
                        'default' => $track->is_default,
                        'uuid' => $track->uuid,
                    ])
                    ->values()
                    ->all(),
                'duration_ms' => $original instanceof AssetMediaVariant ? $original->duration_ms : null,
            ];
        }

        return $extras;
    }
}
