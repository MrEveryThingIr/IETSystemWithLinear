<?php

namespace App\Support\Categories;

use App\Actions\Categories\EnsureSharedCategories;
use App\ConceptLabelKind;
use App\ConceptStatus;
use App\ConceptVocabularyScope;
use App\Models\Concept;
use App\Models\ConceptVocabulary;
use App\Support\Parties\Party;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads categories for pickers and pages: the shared tree (cached) plus the
 * private categories of one owner. Never shows another owner's private
 * categories.
 *
 * Items are arrays: id, label, kinds, private, has_children.
 */
final class CategoryCatalog
{
    public const MAX_PRIVATE_DEPTH = 6;

    /** Heroicon per shared area, for the picker. */
    public const ICONS = [
        'housing' => 'home', 'food' => 'cake', 'clothing' => 'shopping-bag', 'health' => 'heart',
        'education' => 'academic-cap', 'transport' => 'truck', 'industry' => 'wrench-screwdriver',
        'technology' => 'computer-desktop', 'business' => 'briefcase', 'finance' => 'banknotes',
        'arts' => 'musical-note', 'environment' => 'sun', 'community' => 'user-group',
        'civic' => 'building-library', 'travel' => 'globe-alt', 'sports' => 'trophy',
    ];

    public function __construct(private readonly EnsureSharedCategories $shared) {}

    public static function version(): int
    {
        return (int) Cache::get('categories:shared:version', 1);
    }

    /** Call after the shared tree changes (an approved suggestion, an edit). */
    public static function bumpVersion(): void
    {
        Cache::forever('categories:shared:version', self::version() + 1);
        RequestMemo::flush();
    }

    public function sharedVocabularyId(): ?int
    {
        return RequestMemo::remember('categories:shared-vocabulary', function (): ?int {
            $this->shared->ensureCurrent();
            $vocabulary = $this->shared->vocabulary();

            return $vocabulary instanceof ConceptVocabulary ? (int) $vocabulary->id : null;
        });
    }

    public function sharedSchemeId(): ?int
    {
        return RequestMemo::remember('categories:shared-scheme', function (): ?int {
            $this->sharedVocabularyId();

            return $this->shared->scheme()?->id;
        });
    }

    /** @return list<int> vocabularies holding this owner's private categories */
    public function ownerVocabularyIds(?Party $owner): array
    {
        if (! $owner instanceof Party) {
            return [];
        }

        return RequestMemo::remember('categories:owner-vocabularies:'.$owner->key(), fn (): array => ConceptVocabulary::query()
            ->where('scope_type', $this->scopeOf($owner)->value)
            ->where('scope_id', $owner->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }

    public function scopeOf(Party $owner): ConceptVocabularyScope
    {
        return match ($owner->kind) {
            Party::ACTOR => ConceptVocabularyScope::Actor,
            Party::BUSINESS => ConceptVocabularyScope::Business,
            default => ConceptVocabularyScope::Group,
        };
    }

    /** @return list<array<string, mixed>> the 16 shared areas */
    public function areas(?string $locale = null): array
    {
        $locale = $this->locale($locale);
        $schemeId = $this->sharedSchemeId();
        if ($schemeId === null) {
            return [];
        }

        return Cache::remember('categories:areas:'.$locale.':'.self::version(), 86400, function () use ($schemeId, $locale): array {
            $childIds = DB::table('concept_hierarchy_edges')->where('scheme_id', $schemeId)->pluck('child_concept_id')->all();
            $order = array_flip(array_map(fn (array $domain): string => $domain['slug'], LifeDomainCatalog::tree()));
            $rows = DB::table('concepts')
                ->join('concept_scheme_memberships as m', 'm.concept_id', '=', 'concepts.id')
                ->where('m.scheme_id', $schemeId)
                ->where('concepts.status', ConceptStatus::Active->value)
                ->whereNotIn('concepts.id', $childIds)
                ->get(['concepts.id', 'concepts.slug']);

            $items = $this->decorate($rows->pluck('id')->all(), $locale, false);
            foreach ($items as &$item) {
                $slug = (string) $rows->firstWhere('id', $item['id'])->slug;
                $item['icon'] = self::ICONS[$slug] ?? 'tag';
                $item['has_children'] = true;
                $item['order'] = $order[$slug] ?? 999;
            }
            unset($item);
            usort($items, fn (array $a, array $b): int => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

            return $items;
        });
    }

    /**
     * Shared and the owner's private categories directly under a category.
     *
     * @param  list<string>  $kinds  only shared subcategories of these kinds (areas and private ones always show)
     * @return list<array<string, mixed>>
     */
    public function children(int $parentId, ?Party $owner, array $kinds = [], ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        $schemeId = $this->sharedSchemeId();

        $shared = $schemeId === null ? [] : Cache::remember(
            'categories:children:'.$parentId.':'.$locale.':'.self::version(),
            86400,
            function () use ($schemeId, $parentId, $locale): array {
                $ids = DB::table('concept_hierarchy_edges')
                    ->join('concepts', 'concepts.id', '=', 'concept_hierarchy_edges.child_concept_id')
                    ->where('concept_hierarchy_edges.scheme_id', $schemeId)
                    ->where('concept_hierarchy_edges.parent_concept_id', $parentId)
                    ->where('concepts.status', ConceptStatus::Active->value)
                    ->orderBy('concept_hierarchy_edges.sort_order')
                    ->pluck('concepts.id')
                    ->all();

                return $this->decorate($ids, $locale, false);
            },
        );

        if ($kinds !== []) {
            $shared = array_values(array_filter($shared, fn (array $item): bool => array_intersect($kinds, $item['kinds']) !== [] || $item['kinds'] === []));
        }

        $privateIds = $this->privateChildIds($parentId, $owner);
        $private = $this->decorate($privateIds, $locale, true);
        usort($private, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $this->markOwnerChildren([...$shared, ...$private], $owner);
    }

    /** @return list<array<string, mixed>> */
    public function search(string $term, ?Party $owner, array $kinds = [], int $limit = 20, ?string $locale = null): array
    {
        $term = Str::lower(Str::squish($term));
        if (mb_strlen($term) < 2) {
            return [];
        }

        $vocabularies = array_values(array_filter([$this->sharedVocabularyId(), ...$this->ownerVocabularyIds($owner)]));
        if ($vocabularies === []) {
            return [];
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        $ids = DB::table('concept_labels')
            ->join('concepts', 'concepts.id', '=', 'concept_labels.concept_id')
            ->whereIn('concepts.vocabulary_id', $vocabularies)
            ->where('concepts.status', ConceptStatus::Active->value)
            ->where('concept_labels.normalized_label', 'like', '%'.$escaped.'%')
            ->select('concepts.id')
            ->distinct()
            ->limit($limit * 3)
            ->pluck('concepts.id')
            ->all();

        $sharedVocabulary = $this->sharedVocabularyId();
        $privateIds = DB::table('concepts')->whereIn('id', $ids)->where('vocabulary_id', '!=', (int) $sharedVocabulary)->pluck('id')->all();
        $items = $this->decorate($ids, $this->locale($locale), false);
        foreach ($items as &$item) {
            $item['private'] = in_array($item['id'], $privateIds, true);
            $item['path'] = $this->pathLabels($item['id'], $owner, $locale);
            $item['starts'] = str_starts_with(Str::lower($item['label']), $term);
        }
        unset($item);

        if ($kinds !== []) {
            $items = array_values(array_filter($items, fn (array $item): bool => $item['private'] || array_intersect($kinds, $item['kinds']) !== []));
        }
        $items = $this->markOwnerChildren($items, $owner);
        usort($items, fn (array $a, array $b): int => [! $a['starts'], mb_strlen($a['label'])] <=> [! $b['starts'], mb_strlen($b['label'])]);

        return array_slice($items, 0, $limit);
    }

    /**
     * From the area down to the category, as [id, label] pairs.
     *
     * @return list<array{id: int, label: string}>
     */
    public function path(int $conceptId, ?Party $owner, ?string $locale = null): array
    {
        $chain = array_reverse([$conceptId, ...$this->ancestorIds($conceptId)]);
        $labels = $this->labels($chain, $locale);

        return array_map(fn (int $id): array => ['id' => $id, 'label' => $labels[$id] ?? ''], $chain);
    }

    /**
     * Parents of a category, nearest first, up to its area (shared tree edges,
     * then private "narrower than" links). Remembered for the request.
     *
     * @return list<int>
     */
    public function ancestorIds(int $conceptId): array
    {
        return RequestMemo::remember('category-ancestors:'.$conceptId, function () use ($conceptId): array {
            $chain = [$conceptId];
            $current = $conceptId;
            $sharedVocabulary = (int) $this->sharedVocabularyId();
            $schemeId = $this->sharedSchemeId();

            for ($depth = 0; $depth < self::MAX_PRIVATE_DEPTH + 2; $depth++) {
                $isShared = (int) DB::table('concepts')->where('id', $current)->value('vocabulary_id') === $sharedVocabulary;
                $parent = $isShared
                    ? DB::table('concept_hierarchy_edges')->where('scheme_id', $schemeId)->where('child_concept_id', $current)->value('parent_concept_id')
                    : $this->privateParentId($current);
                if ($parent === null || in_array((int) $parent, $chain, true)) {
                    break;
                }
                $chain[] = (int) $parent;
                $current = (int) $parent;
            }
            array_shift($chain);

            return $chain;
        });
    }

    /** The one shared category whose name (any language, synonyms too) is exactly this text, if there is exactly one. */
    public function exactSharedMatch(string $label): ?int
    {
        $normalized = Str::of($label)->squish()->lower()->toString();
        $vocabulary = $this->sharedVocabularyId();
        if ($normalized === '' || $vocabulary === null) {
            return null;
        }
        $ids = DB::table('concept_labels')
            ->join('concepts', 'concepts.id', '=', 'concept_labels.concept_id')
            ->where('concepts.vocabulary_id', $vocabulary)
            ->where('concepts.status', ConceptStatus::Active->value)
            ->where('concept_labels.normalized_label', $normalized)
            ->distinct()
            ->limit(2)
            ->pluck('concepts.id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /** Whether this owner (or anyone, for shared ones) may use the category. */
    public function isUsableBy(int $conceptId, ?Party $owner): bool
    {
        $vocabulary = (int) DB::table('concepts')->where('id', $conceptId)->where('status', ConceptStatus::Active->value)->value('vocabulary_id');

        return $vocabulary !== 0
            && ($vocabulary === (int) $this->sharedVocabularyId() || in_array($vocabulary, $this->ownerVocabularyIds($owner), true));
    }

    public function isShared(int $conceptId): bool
    {
        return (int) DB::table('concepts')->where('id', $conceptId)->value('vocabulary_id') === (int) $this->sharedVocabularyId();
    }

    /** @return array<int, string> concept id => label in the locale (with fallbacks) */
    public function labels(array $conceptIds, ?string $locale = null): array
    {
        $locale = Str::lower($this->locale($locale));
        $rows = DB::table('concept_labels')
            ->whereIn('concept_id', $conceptIds)
            ->where('kind', ConceptLabelKind::Preferred->value)
            ->get(['concept_id', 'locale', 'label']);

        $labels = [];
        foreach ($conceptIds as $id) {
            $mine = $rows->where('concept_id', $id);
            $labels[$id] = (string) ($mine->firstWhere('locale', $locale)->label
                ?? $mine->firstWhere('locale', 'en')->label
                ?? $mine->first()->label
                ?? '');
        }

        return $labels;
    }

    private function pathLabels(int $conceptId, ?Party $owner, ?string $locale): string
    {
        $path = $this->path($conceptId, $owner, $locale);
        array_pop($path);

        return implode(' › ', array_column($path, 'label'));
    }

    /** @return list<int> */
    private function privateChildIds(int $parentId, ?Party $owner): array
    {
        $vocabularies = $this->ownerVocabularyIds($owner);
        if ($vocabularies === []) {
            return [];
        }

        return DB::table('concept_relations')
            ->join('concept_relation_types as t', 't.id', '=', 'concept_relations.relation_type_id')
            ->join('concepts', 'concepts.id', '=', 'concept_relations.from_concept_id')
            ->where('t.key', 'narrower_than')
            ->where('concept_relations.to_concept_id', $parentId)
            ->whereIn('concepts.vocabulary_id', $vocabularies)
            ->where('concepts.status', ConceptStatus::Active->value)
            ->pluck('concepts.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Marks items that have private subcategories of this owner (and only this owner).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function markOwnerChildren(array $items, ?Party $owner): array
    {
        $vocabularies = $this->ownerVocabularyIds($owner);
        if ($items === [] || $vocabularies === []) {
            return $items;
        }

        $withChildren = DB::table('concept_relations')
            ->join('concept_relation_types as t', 't.id', '=', 'concept_relations.relation_type_id')
            ->join('concepts', 'concepts.id', '=', 'concept_relations.from_concept_id')
            ->where('t.key', 'narrower_than')
            ->whereIn('concept_relations.to_concept_id', array_column($items, 'id'))
            ->whereIn('concepts.vocabulary_id', $vocabularies)
            ->where('concepts.status', ConceptStatus::Active->value)
            ->distinct()
            ->pluck('concept_relations.to_concept_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($items as &$item) {
            $item['has_children'] = $item['has_children'] || in_array($item['id'], $withChildren, true);
        }
        unset($item);

        return $items;
    }

    public function privateParentId(int $conceptId): ?int
    {
        $parent = DB::table('concept_relations')
            ->join('concept_relation_types as t', 't.id', '=', 'concept_relations.relation_type_id')
            ->where('t.key', 'narrower_than')
            ->where('concept_relations.from_concept_id', $conceptId)
            ->value('concept_relations.to_concept_id');

        return $parent === null ? null : (int) $parent;
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<array<string, mixed>>
     */
    private function decorate(array $ids, string $locale, bool $private): array
    {
        $ids = array_map('intval', $ids);
        if ($ids === []) {
            return [];
        }

        $labels = $this->labels($ids, $locale);
        $kinds = DB::table('concept_kinds')->whereIn('concept_id', $ids)->get(['concept_id', 'kind'])->groupBy('concept_id');
        // Shared children only: whether an owner has private ones is added per owner.
        $parentsWithChildren = DB::table('concept_hierarchy_edges')->whereIn('parent_concept_id', $ids)->distinct()->pluck('parent_concept_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_map(fn (int $id): array => [
            'id' => $id,
            'label' => $labels[$id] ?? '',
            'kinds' => collect($kinds->get($id, []))->pluck('kind')->values()->all(),
            'private' => $private,
            'has_children' => in_array($id, $parentsWithChildren, true),
        ], $ids);
    }

    private function locale(?string $locale): string
    {
        return $locale ?? app()->getLocale();
    }

    /** The concept or null if it is not active. */
    public function find(int $conceptId): ?Concept
    {
        return Concept::query()->where('status', ConceptStatus::Active->value)->find($conceptId);
    }
}
