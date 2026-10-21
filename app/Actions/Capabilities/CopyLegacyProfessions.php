<?php

namespace App\Actions\Capabilities;

use App\Actions\Categories\EnsureSharedCategories;
use App\Actions\Concepts\RebuildConceptClosure;
use App\ConceptLabelKind;
use App\ConceptStatus;
use App\Models\Profession;
use App\Support\Categories\LifeDomainCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies the old skill records (people's professions and business members'
 * professions) into capabilities. The old rows are never changed or removed;
 * each copy remembers the old row's id, so running this again copies nothing
 * twice. A profession that is not in the shared tree gets its own shared
 * category, so no skill is lost.
 *
 * @phpstan-type Report array{people: int, business_members: int, new_categories: int, skipped: int}
 */
class CopyLegacyProfessions
{
    public function __construct(private readonly EnsureSharedCategories $shared) {}

    /** Copies once; afterwards a single cache read (the old tables no longer receive new rows). */
    public function ensureCopied(): void
    {
        if (Cache::get('capabilities:legacy-copied') === true) {
            return;
        }
        $report = $this->execute();
        if ($report['skipped'] === 0 && $this->shared->vocabulary() !== null) {
            Cache::forever('capabilities:legacy-copied', true);
        }
    }

    /** @return array{people: int, business_members: int, new_categories: int, skipped: int} */
    public function execute(bool $dryRun = false): array
    {
        $report = ['people' => 0, 'business_members' => 0, 'new_categories' => 0, 'skipped' => 0];
        if (! $this->shared->ensureCurrent()) {
            return $report;
        }

        $people = DB::table('actor_professions')
            ->whereNotIn('id', DB::table('capabilities')->whereNotNull('legacy_actor_profession_id')->select('legacy_actor_profession_id'))
            ->orderBy('id')
            ->get();
        $members = DB::table('business_membership_profession as p')
            ->join('business_memberships as m', 'm.id', '=', 'p.business_membership_id')
            ->whereNotIn('p.id', DB::table('capabilities')->whereNotNull('legacy_membership_profession_id')->select('legacy_membership_profession_id'))
            ->orderBy('p.id')
            ->get(['p.id', 'p.profession_id', 'p.is_primary', 'p.business_membership_id', 'm.actor_id', 'm.status as membership_status']);

        if ($dryRun) {
            return [...$report, 'people' => $people->count(), 'business_members' => $members->count()];
        }

        DB::transaction(function () use ($people, $members, &$report): void {
            $now = now();
            $concepts = [];
            foreach ($people->pluck('profession_id')->merge($members->pluck('profession_id'))->unique() as $professionId) {
                $concepts[(int) $professionId] = $this->conceptFor((int) $professionId, $report);
            }

            foreach ($people as $row) {
                $conceptId = $concepts[(int) $row->profession_id] ?? null;
                if ($conceptId === null) {
                    $report['skipped']++;

                    continue;
                }
                DB::table('capabilities')->insert([
                    'uuid' => (string) Str::uuid(),
                    'holder_actor_id' => $row->actor_id,
                    'concept_id' => $conceptId,
                    'level' => in_array($row->level, ['beginner', 'intermediate', 'advanced', 'expert'], true) ? $row->level : null,
                    'years_experience' => $row->years_experience,
                    'is_primary' => (bool) $row->is_primary,
                    'visibility' => in_array($row->visibility, ['private', 'contacts', 'members', 'public'], true) ? $row->visibility : 'private',
                    'status' => 'active',
                    'sort_order' => (int) $row->id,
                    'created_by_actor_id' => $row->actor_id,
                    'legacy_actor_profession_id' => $row->id,
                    'created_at' => $row->created_at ?? $now,
                    'updated_at' => $now,
                ]);
                $report['people']++;
            }

            foreach ($members as $row) {
                $conceptId = $concepts[(int) $row->profession_id] ?? null;
                if ($conceptId === null) {
                    $report['skipped']++;

                    continue;
                }
                DB::table('capabilities')->insert([
                    'uuid' => (string) Str::uuid(),
                    'holder_actor_id' => $row->actor_id,
                    'business_membership_id' => $row->business_membership_id,
                    'concept_id' => $conceptId,
                    'is_primary' => false,
                    'visibility' => 'members',
                    'status' => $row->membership_status === 'active' ? 'active' : 'archived',
                    'sort_order' => 1000 + (int) $row->id,
                    'created_by_actor_id' => $row->actor_id,
                    'legacy_membership_profession_id' => $row->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $report['business_members']++;
            }
        }, 3);

        return $report;
    }

    /** @param array{people: int, business_members: int, new_categories: int, skipped: int} $report */
    private function conceptFor(int $professionId, array &$report): ?int
    {
        $profession = Profession::query()->find($professionId);
        if (! $profession instanceof Profession) {
            return null;
        }
        if ($profession->concept_id !== null && DB::table('concepts')->where('id', $profession->concept_id)->exists()) {
            return $this->canonical((int) $profession->concept_id);
        }

        // Not in the shared tree: give it a shared category so nothing is lost.
        $vocabularyId = (int) $this->shared->vocabulary()?->id;
        $scheme = $this->shared->scheme();
        $slug = 'legacy.profession.'.Str::slug((string) $profession->code ?: (string) $profession->id);
        $existing = DB::table('concepts')->where('vocabulary_id', $vocabularyId)->where('slug', $slug)->value('id');
        $now = now();
        $conceptId = $existing !== null ? (int) $existing : (int) DB::table('concepts')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'vocabulary_id' => $vocabularyId,
            'slug' => $slug,
            'status' => ConceptStatus::Active->value,
            'metadata' => json_encode(['source' => 'legacy_profession', 'profession_id' => $profession->id]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($existing === null) {
            foreach (['en' => $profession->name, 'fa' => $profession->name_fa] as $locale => $label) {
                if (filled($label)) {
                    DB::table('concept_labels')->insert([
                        'concept_id' => $conceptId, 'locale' => $locale, 'label' => $label,
                        'kind' => ConceptLabelKind::Preferred->value, 'normalized_label' => Str::lower((string) $label),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            DB::table('concept_kinds')->insert([
                ['concept_id' => $conceptId, 'kind' => 'skill', 'created_at' => $now, 'updated_at' => $now],
                ['concept_id' => $conceptId, 'kind' => 'service', 'created_at' => $now, 'updated_at' => $now],
            ]);
            if ($scheme !== null) {
                $parentSlug = LifeDomainCatalog::LEGACY_PROFESSIONS[(string) $profession->parent?->code] ?? 'business';
                $parentId = DB::table('concepts')->where('vocabulary_id', $vocabularyId)->where('slug', $parentSlug)->value('id');
                DB::table('concept_scheme_memberships')->insert(['scheme_id' => $scheme->id, 'concept_id' => $conceptId, 'created_at' => $now, 'updated_at' => $now]);
                if ($parentId !== null) {
                    DB::table('concept_hierarchy_edges')->insert([
                        'uuid' => (string) Str::uuid(), 'scheme_id' => $scheme->id, 'parent_concept_id' => $parentId,
                        'child_concept_id' => $conceptId, 'sort_order' => 900, 'created_by_actor_id' => (int) $scheme->created_by_actor_id,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                app(RebuildConceptClosure::class)->execute($scheme);
            }
            $report['new_categories']++;
        }
        DB::table('professions')->where('id', $profession->getKey())->update(['concept_id' => $conceptId, 'updated_at' => $now]);

        return $conceptId;
    }

    private function canonical(int $conceptId): int
    {
        for ($i = 0; $i < 10; $i++) {
            $row = DB::table('concepts')->where('id', $conceptId)->first(['status', 'merged_into_concept_id']);
            if ($row === null || $row->status !== ConceptStatus::Merged->value || $row->merged_into_concept_id === null) {
                return $conceptId;
            }
            $conceptId = (int) $row->merged_into_concept_id;
        }

        return $conceptId;
    }
}
