<?php

namespace App\Actions\Profile;

use App\Actions\Market\PublishIntent;
use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileIntent;
use App\Models\BusinessListing;
use App\Models\BusinessListingVersion;
use App\Models\Capability;
use App\Models\Concept;
use App\Models\User;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use App\Support\Categories\CategoryCatalog;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateActorProfileIntent
{
    /**
     * @param  array<string, mixed>  $input
     */
    /**
     * @param  array<string, mixed>  $input
     * @param  array{publisher?: Party, concept_id?: int|null, capability_id?: int|null, business_listing_id?: int|null, business_listing_version_id?: int|null}  $sources
     */
    public function execute(
        User $user,
        ActorProfile $profile,
        ProfileIntentKind $kind,
        string $conceptLabel,
        array $input,
        array $sources = [],
        bool $draft = false,
    ): ActorProfileIntent {
        Gate::forUser($user)->authorize('update', $profile);

        $publisher = $sources['publisher'] ?? Party::make(Party::ACTOR, (int) $profile->actor_id);
        abort_unless(app(PartyAuthority::class)->canActFor($user, $publisher), 403);

        $conceptId = $sources['concept_id'] ?? null;
        if ($conceptId !== null) {
            $catalog = app(CategoryCatalog::class);
            abort_unless($catalog->isUsableBy((int) $conceptId, $publisher) || $catalog->isUsableBy((int) $conceptId, Party::make(Party::ACTOR, (int) $profile->actor_id)), 422);
            $concept = Concept::query()->findOrFail($conceptId);
        } else {
            $concept = app(ResolveActorProfileConcept::class)->execute($user, $profile, $conceptLabel);
        }

        $data = app(NormalizeProfileIntentData::class)->execute($user, $input);
        if (($data['visibility'] ?? null) === ProfileItemVisibility::Members->value && $publisher->kind === Party::ACTOR) {
            throw ValidationException::withMessages(['visibility' => __('intents.errors.members_needs_organization')]);
        }
        $links = $this->sources($publisher, $sources);

        return DB::transaction(function () use ($user, $profile, $kind, $concept, $data, $publisher, $links, $draft): ActorProfileIntent {
            $lockedProfile = ActorProfile::query()->lockForUpdate()->findOrFail($profile->id);
            Gate::forUser($user)->authorize('update', $lockedProfile);

            $actor = Actor::query()->findOrFail($lockedProfile->actor_id);
            $intent = new ActorProfileIntent;
            $intent->fill([...$data, ...$links]);
            $intent->publisher_actor_id = $publisher->kind === Party::ACTOR ? $publisher->id : null;
            $intent->publisher_business_id = $publisher->kind === Party::BUSINESS ? $publisher->id : null;
            $intent->publisher_group_id = $publisher->kind === Party::GROUP ? $publisher->id : null;
            $intent->profile()->associate($lockedProfile);
            $intent->concept()->associate($concept);
            $intent->creator()->associate($actor);
            $intent->kind = $kind;
            $intent->metadata = [];
            if ($draft) {
                $intent->status = ProfileIntentStatus::Draft;
            }
            $intent->save();

            if (! $draft && $intent->publisher_business_id === null && $intent->publisher_group_id === null) {
                app(SyncProfileIntentConceptAssertion::class)->execute(
                    $user,
                    $lockedProfile,
                    $concept,
                    $kind,
                );
            }

            if (! $draft) {
                app(PublishIntent::class)->execute($user, $intent);
            }

            return $intent->refresh()->load('concept.labels');
        }, 3);
    }

    /**
     * A skill must be the publisher's own; a catalog item and version must be
     * the publishing business's own published version.
     *
     * @param  array<string, mixed>  $sources
     * @return array{capability_id: int|null, business_listing_id: int|null, business_listing_version_id: int|null}
     */
    private function sources(Party $publisher, array $sources): array
    {
        $capabilityId = $sources['capability_id'] ?? null;
        if ($capabilityId !== null) {
            abort_unless(Capability::query()->heldBy($publisher)->active()->whereKey($capabilityId)->exists(), 422);
        }

        $listingId = $sources['business_listing_id'] ?? null;
        $versionId = $sources['business_listing_version_id'] ?? null;
        if ($listingId !== null || $versionId !== null) {
            abort_unless($publisher->kind === Party::BUSINESS, 422);
            abort_unless(BusinessListing::query()->whereKey($listingId)->where('business_id', $publisher->id)->exists(), 422);
            abort_unless($versionId === null || BusinessListingVersion::query()->whereKey($versionId)->where('business_listing_id', $listingId)->whereNotNull('published_at')->exists(), 422);
        }

        return [
            'capability_id' => $capabilityId !== null ? (int) $capabilityId : null,
            'business_listing_id' => $listingId !== null ? (int) $listingId : null,
            'business_listing_version_id' => $versionId !== null ? (int) $versionId : null,
        ];
    }
}
