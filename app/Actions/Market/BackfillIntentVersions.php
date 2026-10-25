<?php

namespace App\Actions\Market;

use App\Support\Market\IntentSnapshot;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Additive migration helper: does not instantiate current application models or convert old money. */
final class BackfillIntentVersions
{
    public function execute(): int
    {
        $count = 0;
        DB::table('actor_profile_intents')->whereNull('published_version_id')->whereIn('status', ['active', 'paused', 'closed'])
            ->orderBy('id')->chunkById(100, function ($records) use (&$count): void {
                foreach ($records as $record) {
                    $count += DB::transaction(function () use ($record): int {
                        $locked = DB::table('actor_profile_intents')->where('id', $record->id)->lockForUpdate()->first();
                        if ($locked === null || $locked->published_version_id !== null) {
                            return 0;
                        }
                        $snapshot = IntentSnapshot::fromRecord($locked);
                        $existing = DB::table('intent_versions')->where('actor_profile_intent_id', $locked->id)->where('version', 1)->value('id');
                        $id = $existing ?? DB::table('intent_versions')->insertGetId([
                            'uuid' => (string) Str::uuid(), 'actor_profile_intent_id' => $locked->id, 'version' => 1,
                            'published_by_actor_id' => $locked->created_by_actor_id, 'published_at' => $locked->created_at,
                            'snapshot' => SpaceContentSchema::canonicalJson($snapshot), 'content_hash' => SpaceContentSchema::hashArray($snapshot),
                            'search_text' => IntentSnapshot::searchText($snapshot),
                        ]);
                        DB::table('actor_profile_intents')->where('id', $locked->id)->update(['published_version_id' => $id]);
                        DB::table('relationships')->where('originating_intent_id', $locked->id)->whereNull('originating_intent_version_id')
                            ->update(['originating_intent_version_id' => $id]);

                        return 1;
                    }, 3);
                }
            });

        return $count;
    }
}
