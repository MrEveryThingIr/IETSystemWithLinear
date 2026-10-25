<?php

namespace App\Actions\Items;

use App\ConceptStatus;
use App\Models\Interest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies the old profile tags into the one home for each fact:
 * "has skill" becomes an Ability (capability), "interested in" and "wants to
 * learn" become one Interest per category with the purposes follow and learn.
 *
 * Safe to run any number of times: each copied tag is remembered in
 * legacy_item_links, the old rows are never changed, and a category the
 * person already has is reused rather than duplicated. When two tags merge
 * into one interest, the more private visibility wins.
 *
 * Shares that named an old tag also get the new item, so nothing someone was
 * shown disappears.
 *
 * @phpstan-type Report array{abilities: int, interests: int, merged: int, shares: int, skipped: int}
 */
class ConsolidateLegacyProfileItems
{
    public const SOURCE = 'concept_assertion';

    private const PREDICATES = ['has_skill', 'interested_in', 'wants_to_learn'];

    /** @return array{abilities: int, interests: int, merged: int, shares: int, skipped: int} */
    public function execute(bool $dryRun = false): array
    {
        $report = ['abilities' => 0, 'interests' => 0, 'merged' => 0, 'shares' => 0, 'skipped' => 0];

        if (! $this->tablesReady()) {
            return $report;
        }

        $pending = DB::table('concept_assertions as a')
            ->where('a.subject_type', 'actor')
            ->whereIn('a.predicate', self::PREDICATES)
            ->whereNotExists(fn ($query) => $query->from('legacy_item_links as l')
                ->where('l.source_type', self::SOURCE)
                ->whereColumn('l.source_id', 'a.id'))
            ->orderBy('a.id')
            ->get(['a.id', 'a.subject_id', 'a.concept_id', 'a.predicate', 'a.weight', 'a.visibility', 'a.valid_until', 'a.created_by_actor_id', 'a.created_at']);

        if ($dryRun) {
            foreach ($pending as $row) {
                $row->predicate === 'has_skill' ? $report['abilities']++ : $report['interests']++;
            }

            return $report;
        }

        foreach ($pending->groupBy('subject_id') as $actorId => $rows) {
            DB::transaction(function () use ($rows, $actorId, &$report): void {
                foreach ($rows as $row) {
                    if ($row->valid_until !== null && strtotime((string) $row->valid_until) <= time()) {
                        $report['skipped']++;

                        continue;
                    }
                    if (! DB::table('actors')->where('id', $actorId)->exists()) {
                        $report['skipped']++;

                        continue;
                    }
                    $conceptId = $this->canonical((int) $row->concept_id);
                    if ($conceptId === null) {
                        $report['skipped']++;

                        continue;
                    }

                    [$type, $targetId] = $row->predicate === 'has_skill'
                        ? ['capability', $this->ability((int) $actorId, $conceptId, $row, $report)]
                        : ['interest', $this->interest((int) $actorId, $conceptId, $row, $report)];

                    DB::table('legacy_item_links')->insert([
                        'source_type' => self::SOURCE,
                        'source_id' => $row->id,
                        'target_type' => $type,
                        'target_id' => $targetId,
                        'created_at' => now(),
                    ]);
                }
            }, 3);
        }

        $report['shares'] = $this->copyShares();

        return $report;
    }

    /** @param array{abilities: int, interests: int, merged: int, shares: int, skipped: int} $report */
    private function ability(int $actorId, int $conceptId, object $row, array &$report): int
    {
        $existing = DB::table('capabilities')
            ->where('holder_actor_id', $actorId)
            ->whereNull('business_membership_id')
            ->where('concept_id', $conceptId)
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->value('id');
        if ($existing !== null) {
            $report['merged']++;

            return (int) $existing;
        }

        $report['abilities']++;
        $now = now();

        return (int) DB::table('capabilities')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'holder_actor_id' => $actorId,
            'concept_id' => $conceptId,
            'level' => $this->level($row->weight),
            // A tag shown with the profile becomes visible to signed-in members;
            // a private tag stays private. Never wider than before.
            'visibility' => $row->visibility === 'private' ? 'private' : 'members',
            'status' => 'active',
            'is_primary' => false,
            'sort_order' => 2000 + (int) $row->id,
            'created_by_actor_id' => (int) ($row->created_by_actor_id ?? $actorId),
            'created_at' => $row->created_at ?? $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array{abilities: int, interests: int, merged: int, shares: int, skipped: int} $report */
    private function interest(int $actorId, int $conceptId, object $row, array &$report): int
    {
        $purpose = $row->predicate === 'wants_to_learn' ? 'learn' : 'follow';
        $visibility = $row->visibility === 'private' ? Interest::VISIBILITY_PRIVATE : Interest::VISIBILITY_PROFILE;
        $now = now();

        $existing = DB::table('interests')->where('holder_actor_id', $actorId)->where('concept_id', $conceptId)->first(['id', 'purposes', 'visibility']);
        if ($existing !== null) {
            $purposes = Interest::normalizePurposes([...json_decode((string) $existing->purposes, true) ?: [], $purpose]);
            DB::table('interests')->where('id', $existing->id)->update([
                'purposes' => json_encode($purposes),
                'visibility' => $existing->visibility === Interest::VISIBILITY_PRIVATE ? Interest::VISIBILITY_PRIVATE : $visibility,
                'updated_at' => $now,
            ]);
            $report['merged']++;

            return (int) $existing->id;
        }

        $report['interests']++;

        return (int) DB::table('interests')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'holder_actor_id' => $actorId,
            'concept_id' => $conceptId,
            'purposes' => json_encode([$purpose]),
            'visibility' => $visibility,
            'created_by_actor_id' => (int) ($row->created_by_actor_id ?? $actorId),
            'created_at' => $row->created_at ?? $now,
            'updated_at' => $now,
        ]);
    }

    /** Adds the new item to every share that listed a copied tag. */
    private function copyShares(): int
    {
        $rows = DB::table('actor_profile_disclosure_items as i')
            ->join('concept_assertions as a', 'a.uuid', '=', DB::raw('substr(i.item_key, 11)'))
            ->join('legacy_item_links as l', fn ($join) => $join->on('l.source_id', '=', 'a.id')->where('l.source_type', '=', self::SOURCE))
            ->where('i.kind', 'concept_assertion')
            ->where('i.item_key', 'like', 'assertion:%')
            ->get(['i.grant_id', 'l.target_type', 'l.target_id']);

        $added = 0;
        foreach ($rows as $row) {
            $isAbility = $row->target_type === 'capability';
            $uuid = DB::table($isAbility ? 'capabilities' : 'interests')->where('id', $row->target_id)->value('uuid');
            if ($uuid === null) {
                continue;
            }
            $key = ($isAbility ? 'ability:' : 'interest:').$uuid;
            if (DB::table('actor_profile_disclosure_items')->where('grant_id', $row->grant_id)->where('item_key', $key)->exists()) {
                continue;
            }
            DB::table('actor_profile_disclosure_items')->insert([
                'grant_id' => $row->grant_id,
                'kind' => $isAbility ? 'ability' : 'interest',
                'item_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $added++;
        }

        return $added;
    }

    private function level(mixed $weight): ?string
    {
        if ($weight === null) {
            return null;
        }
        $percent = (int) round((float) $weight * 100);

        return match (true) {
            $percent <= 40 => 'beginner',
            $percent <= 60 => 'intermediate',
            $percent <= 80 => 'advanced',
            default => 'expert',
        };
    }

    /** Follows merged categories to the one that is still in use. */
    private function canonical(int $conceptId): ?int
    {
        for ($i = 0; $i < 10; $i++) {
            $row = DB::table('concepts')->where('id', $conceptId)->first(['status', 'merged_into_concept_id']);
            if ($row === null) {
                return null;
            }
            if ($row->status !== ConceptStatus::Merged->value || $row->merged_into_concept_id === null) {
                return $conceptId;
            }
            $conceptId = (int) $row->merged_into_concept_id;
        }

        return $conceptId;
    }

    private function tablesReady(): bool
    {
        $schema = DB::getSchemaBuilder();

        return $schema->hasTable('concept_assertions')
            && $schema->hasTable('capabilities')
            && $schema->hasTable('interests')
            && $schema->hasTable('legacy_item_links');
    }
}
