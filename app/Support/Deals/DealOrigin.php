<?php

namespace App\Support\Deals;

use App\Models\ActorProfileIntent;
use App\Models\IntentVersion;
use App\Models\User;
use App\Support\Market\IntentPresentation;
use App\Support\Parties\Party;
use Illuminate\Support\Facades\Gate;

/** The Need or Offer a Deal started from, as published in the pinned version (never the live, editable listing). */
final class DealOrigin
{
    /** @return array<string, mixed>|null */
    public static function card(ActorProfileIntent $intent, ?IntentVersion $version, ?User $viewer): ?array
    {
        $version ??= $intent->publishedVersion;
        if (! $version instanceof IntentVersion) {
            return null;
        }

        $card = app(IntentPresentation::class)->build($version->snapshot, $viewer, false, null, $intent->kind->value, $intent->metadata['composer_topic'] ?? null);
        $publisher = $intent->publisher();
        $owner = match ($publisher->kind) {
            Party::BUSINESS => $intent->publisherBusiness,
            Party::GROUP => $intent->publisherGroup,
            default => null,
        };

        return $card + [
            'version' => (int) $version->version,
            'publishedAt' => $version->published_at,
            'publisherKind' => $publisher->kind,
            'publisherName' => $owner !== null && $viewer instanceof User && Gate::forUser($viewer)->allows('view', $owner) ? $owner->name : null,
        ];
    }
}
