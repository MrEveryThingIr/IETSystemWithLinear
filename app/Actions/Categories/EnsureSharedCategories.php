<?php

namespace App\Actions\Categories;

use App\Actions\Concepts\RebuildConceptClosure;
use App\ConceptLabelKind;
use App\ConceptStatus;
use App\ConceptVocabularyScope;
use App\Models\Actor;
use App\Models\ConceptScheme;
use App\Models\ConceptVocabulary;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use App\Support\Categories\LifeDomainCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Installs and updates the shared category tree from LifeDomainCatalog.
 *
 * Runs only when the catalog changes (fingerprint), works in bulk, never
 * removes anything, and never reactivates a category an administrator has
 * deprecated or merged. Also links the old skill list to its categories.
 */
class EnsureSharedCategories
{
    private const CACHE_KEY = 'categories:shared:fingerprint';

    public function __construct(private readonly RebuildConceptClosure $closure) {}

    public static function fingerprint(): string
    {
        return hash('sha256', serialize([LifeDomainCatalog::tree(), LifeDomainCatalog::LEGACY_PROFESSIONS, 'v1']));
    }

    /** @return bool whether the shared tree is installed */
    public function ensureCurrent(): bool
    {
        if (Cache::get(self::CACHE_KEY) === self::fingerprint() && $this->vocabulary() instanceof ConceptVocabulary) {
            return true;
        }

        if (! $this->execute()) {
            return false;
        }

        Cache::forever(self::CACHE_KEY, self::fingerprint());

        return true;
    }

    public function vocabulary(): ?ConceptVocabulary
    {
        return ConceptVocabulary::query()
            ->where('scope_type', ConceptVocabularyScope::Platform->value)
            ->where('scope_id', 0)
            ->where('slug', LifeDomainCatalog::VOCABULARY_SLUG)
            ->first();
    }

    public function scheme(): ?ConceptScheme
    {
        $vocabulary = $this->vocabulary();

        return $vocabulary instanceof ConceptVocabulary
            ? ConceptScheme::query()->where('vocabulary_id', $vocabulary->id)->where('slug', LifeDomainCatalog::SCHEME_SLUG)->first()
            : null;
    }

    /** @return bool false when there is no administrator to record as creator yet */
    public function execute(): bool
    {
        $creator = $this->creator();
        if (! $creator instanceof Actor) {
            return false;
        }

        DB::transaction(function () use ($creator): void {
            $now = now();
            $vocabulary = $this->vocabulary() ?? ConceptVocabulary::query()->create([
                'scope_type' => ConceptVocabularyScope::Platform,
                'scope_id' => 0,
                'name' => 'IET categories',
                'slug' => LifeDomainCatalog::VOCABULARY_SLUG,
                'created_by_actor_id' => $creator->id,
                'metadata' => ['purpose' => 'shared_categories'],
            ]);
            $scheme = ConceptScheme::query()->firstOrCreate(
                ['vocabulary_id' => $vocabulary->id, 'slug' => LifeDomainCatalog::SCHEME_SLUG],
                ['name' => 'Life domains', 'created_by_actor_id' => $creator->id, 'metadata' => ['purpose' => 'category_browser']],
            );

            // Concepts
            $nodes = [];
            foreach (LifeDomainCatalog::tree() as $domainOrder => $domain) {
                $nodes[] = ['slug' => $domain['slug'], 'labels' => $domain['labels'], 'kinds' => $domain['kinds'], 'parent' => null, 'order' => $domainOrder];
                foreach ($domain['children'] as $childOrder => $child) {
                    $nodes[] = ['slug' => $child['slug'], 'labels' => $child['labels'], 'kinds' => $child['kinds'], 'parent' => $domain['slug'], 'order' => $childOrder];
                }
            }

            $existing = DB::table('concepts')->where('vocabulary_id', $vocabulary->id)->pluck('id', 'slug');
            $missing = array_values(array_filter($nodes, fn (array $node): bool => ! $existing->has($node['slug'])));
            foreach (array_chunk($missing, 200) as $chunk) {
                DB::table('concepts')->insert(array_map(fn (array $node): array => [
                    'uuid' => (string) Str::uuid(),
                    'vocabulary_id' => $vocabulary->id,
                    'slug' => $node['slug'],
                    'status' => ConceptStatus::Active->value,
                    'metadata' => json_encode(['source' => 'life_domain_catalog']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
            $ids = DB::table('concepts')->where('vocabulary_id', $vocabulary->id)->pluck('id', 'slug');
            $active = DB::table('concepts')->where('vocabulary_id', $vocabulary->id)->where('status', ConceptStatus::Active->value)->pluck('id', 'slug');

            // Preferred labels in every language (update text if the catalog changed it).
            $labels = DB::table('concept_labels')
                ->whereIn('concept_id', $ids->values())
                ->where('kind', ConceptLabelKind::Preferred->value)
                ->get(['id', 'concept_id', 'locale', 'label'])
                ->keyBy(fn ($row): string => $row->concept_id.'|'.$row->locale);
            $newLabels = [];
            foreach ($nodes as $node) {
                $conceptId = (int) $ids[$node['slug']];
                foreach ($node['labels'] as $locale => $text) {
                    $locale = Str::lower($locale);
                    $current = $labels->get($conceptId.'|'.$locale);
                    if ($current === null) {
                        $newLabels[] = [
                            'concept_id' => $conceptId,
                            'locale' => $locale,
                            'label' => $text,
                            'kind' => ConceptLabelKind::Preferred->value,
                            'normalized_label' => Str::lower($text),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    } elseif ($current->label !== $text) {
                        DB::table('concept_labels')->where('id', $current->id)->update(['label' => $text, 'normalized_label' => Str::lower($text), 'updated_at' => $now]);
                    }
                }
            }
            foreach (array_chunk($newLabels, 300) as $chunk) {
                DB::table('concept_labels')->insert($chunk);
            }

            // Scheme membership, tree edges and kinds for active concepts.
            $members = DB::table('concept_scheme_memberships')->where('scheme_id', $scheme->id)->pluck('concept_id')->flip();
            $edges = DB::table('concept_hierarchy_edges')->where('scheme_id', $scheme->id)
                ->get(['parent_concept_id', 'child_concept_id'])
                ->mapWithKeys(fn ($row): array => [$row->parent_concept_id.'>'.$row->child_concept_id => true]);
            $kinds = DB::table('concept_kinds')->whereIn('concept_id', $ids->values())
                ->get(['concept_id', 'kind'])
                ->mapWithKeys(fn ($row): array => [$row->concept_id.'|'.$row->kind => true]);

            $newMembers = [];
            $newEdges = [];
            $newKinds = [];
            foreach ($nodes as $node) {
                if (! $active->has($node['slug'])) {
                    continue;
                }
                $conceptId = (int) $active[$node['slug']];
                if (! $members->has($conceptId)) {
                    $newMembers[] = ['scheme_id' => $scheme->id, 'concept_id' => $conceptId, 'created_at' => $now, 'updated_at' => $now];
                }
                if ($node['parent'] !== null && $active->has($node['parent'])) {
                    $parentId = (int) $active[$node['parent']];
                    if (! $edges->has($parentId.'>'.$conceptId)) {
                        $newEdges[] = [
                            'uuid' => (string) Str::uuid(),
                            'scheme_id' => $scheme->id,
                            'parent_concept_id' => $parentId,
                            'child_concept_id' => $conceptId,
                            'sort_order' => $node['order'],
                            'created_by_actor_id' => $creator->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                foreach ($node['kinds'] as $kind) {
                    if (! $kinds->has($conceptId.'|'.$kind)) {
                        $newKinds[] = ['concept_id' => $conceptId, 'kind' => $kind, 'created_at' => $now, 'updated_at' => $now];
                    }
                }
            }
            foreach ([['concept_scheme_memberships', $newMembers], ['concept_hierarchy_edges', $newEdges], ['concept_kinds', $newKinds]] as [$table, $rows]) {
                foreach (array_chunk($rows, 300) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }

            $this->closure->execute($scheme);

            // Old skill list → categories (only where not linked yet).
            foreach (LifeDomainCatalog::LEGACY_PROFESSIONS as $code => $slug) {
                if ($ids->has($slug)) {
                    DB::table('professions')->where('code', $code)->whereNull('concept_id')->update(['concept_id' => $ids[$slug], 'updated_at' => $now]);
                }
            }
        }, 3);

        return true;
    }

    private function creator(): ?Actor
    {
        $adminIds = PlatformAccessGrant::query()->active()->where('role', PlatformRole::Superadmin->value)->pluck('user_id');
        $admin = User::query()
            ->where('status', 'active')
            ->whereIn('id', $adminIds)
            ->whereHas('actor')
            ->with('actor')
            ->orderBy('id')
            ->first();

        return $admin?->actor;
    }
}
