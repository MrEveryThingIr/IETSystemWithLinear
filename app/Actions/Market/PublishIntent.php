<?php

namespace App\Actions\Market;

use App\Actions\Profile\SyncProfileIntentConceptAssertion;
use App\Models\ActorProfileIntent;
use App\Models\IntentVersion;
use App\Models\User;
use App\ProfileIntentStatus;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentSnapshot;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class PublishIntent
{
    public function execute(User $user, ActorProfileIntent $intent): IntentVersion
    {
        Gate::forUser($user)->authorize('update', $intent);

        return DB::transaction(function () use ($user, $intent): IntentVersion {
            $locked = ActorProfileIntent::query()->lockForUpdate()->findOrFail($intent->id);
            Gate::forUser($user)->authorize('update', $locked);
            abort_if($locked->status === ProfileIntentStatus::Closed, 409);
            $working = $locked->working_copy;
            if (is_array($working)) {
                $locked->fill($working['core']);
                $locked->save();
                $locked->details()->delete();
                foreach ($working['details'] as $detail) {
                    $locked->details()->create(['uuid' => (string) Str::uuid(), 'canonical_key' => $detail['canonical_key'] ?? null,
                        'label' => $detail['label'], 'type' => $detail['type'], 'payload' => $detail['payload'],
                        'unit' => $detail['unit'] ?? null, 'group_label' => $detail['group_label'] ?? null,
                        'visibility' => $detail['visibility'], 'position' => $detail['position']]);
                }
            }
            $record = DB::table('actor_profile_intents')->where('id', $locked->id)->first();
            $snapshot = IntentSnapshot::fromRecord($record);
            $hash = SpaceContentSchema::hashArray($snapshot);
            $previous = $locked->publishedVersion;
            if ($previous !== null && hash_equals($previous->content_hash, $hash)) {
                $locked->forceFill(['working_copy' => null])->save();

                return $previous;
            }
            $version = $locked->versions()->create(['version' => (int) $locked->versions()->max('version') + 1,
                'published_by_actor_id' => $user->actor->id, 'published_at' => now(), 'snapshot' => $snapshot,
                'content_hash' => $hash, 'search_text' => IntentSnapshot::searchText($snapshot)]);
            foreach ($snapshot['details'] as $detail) {
                $key = $detail['canonical_key'] ?? null;
                if ($detail['visibility'] !== 'same' || $key === null || ! isset(IntentDetailCatalog::all()[$key])) {
                    continue;
                }
                $column = IntentDetailCatalog::all()[$key]['filter'] ?? null;
                if ($column === null) {
                    continue;
                }
                $value = $detail['payload']['value'];
                DB::table('intent_search_values')->insert(['intent_version_id' => $version->id, 'canonical_key' => $key, $column => $value]);
            }
            $locked->forceFill(['published_version_id' => $version->id, 'working_copy' => null])->save();
            if ($locked->status === ProfileIntentStatus::Draft) {
                $locked->applyStatus(ProfileIntentStatus::Active);
            }
            if ($locked->publisher_business_id === null && $locked->publisher_group_id === null) {
                app(SyncProfileIntentConceptAssertion::class)->execute($user, $locked->profile, $locked->concept, $locked->kind);
            }

            return $version;
        }, 3);
    }

    public function discard(User $user, ActorProfileIntent $intent): void
    {
        Gate::forUser($user)->authorize('update', $intent);
        DB::transaction(function () use ($user, $intent): void {
            $locked = ActorProfileIntent::query()->lockForUpdate()->findOrFail($intent->id);
            Gate::forUser($user)->authorize('update', $locked);
            $snapshot = $locked->publishedVersion?->snapshot;
            if (is_array($snapshot)) {
                $media = $snapshot['media'] ?? [];
                $uuids = array_column($media, 'uuid');
                $locked->media()->whereNotIn('uuid', $uuids)->delete();
                foreach ($media as $item) {
                    $existing = $locked->media()->withTrashed()->where('uuid', $item['uuid'])->first();
                    // Discard restores content/order, but never silently widens a file's privacy.
                    $visibility = $existing?->visibility === 'private' ? 'private' : ($item['visibility'] ?? 'same');
                    if ($existing !== null) {
                        $existing->restore();
                        $existing->update(['position' => $item['position'], 'caption' => $item['caption'], 'visibility' => $visibility]);
                    } else {
                        $restored = $locked->media()->make(['asset_id' => $item['asset_id'], 'position' => $item['position'],
                            'caption' => $item['caption'], 'visibility' => $visibility, 'created_by_actor_id' => $user->actor->id]);
                        $restored->uuid = $item['uuid'];
                        $restored->save();
                    }
                }
            }
            $locked->forceFill(['working_copy' => null])->save();
        });
    }
}
