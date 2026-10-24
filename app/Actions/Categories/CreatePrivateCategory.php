<?php

namespace App\Actions\Categories;

use App\Actions\Concepts\CreateConcept;
use App\Actions\Concepts\CreateConceptVocabulary;
use App\Actions\Concepts\SetConceptLabel;
use App\ConceptAssertionSource;
use App\ConceptLabelKind;
use App\ConceptStatus;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptRelation;
use App\Models\ConceptRelationType;
use App\Models\ConceptVocabulary;
use App\Models\User;
use App\Support\Categories\CategoryCatalog;
use App\Support\Categories\CategoryKind;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Adds a category of one's own (or of a business or group one manages) under
 * any category one can see. It is usable immediately and visible only to that
 * owner; it can be suggested for everyone later.
 */
class CreatePrivateCategory
{
    public function __construct(
        private readonly CategoryCatalog $catalog,
        private readonly PartyAuthority $authority,
    ) {}

    /** @param list<string> $kinds empty = same kinds as the parent */
    public function execute(User $user, Party $owner, int $parentId, string $label, array $kinds = []): Concept
    {
        abort_unless($this->authority->canActFor($user, $owner), 403);

        $label = Str::of($label)->squish()->toString();
        if ($label === '' || mb_strlen($label) > 120) {
            throw ValidationException::withMessages(['newCategory' => __('categories.errors.label_length')]);
        }
        abort_unless($this->catalog->isUsableBy($parentId, $owner), 404);

        $privateDepth = count(array_filter($this->catalog->path($parentId, $owner), fn (array $step): bool => ! $this->catalog->isShared($step['id'])));
        if ($privateDepth >= CategoryCatalog::MAX_PRIVATE_DEPTH) {
            throw ValidationException::withMessages(['newCategory' => __('categories.errors.too_deep')]);
        }

        $normalized = Str::lower($label);
        // The same name already under this parent (shared or one's own): reuse it.
        foreach ($this->catalog->children($parentId, $owner) as $sibling) {
            if (Str::lower($sibling['label']) === $normalized) {
                return Concept::query()->findOrFail($sibling['id']);
            }
        }

        $kinds = array_values(array_intersect($kinds, CategoryKind::values()));
        if ($kinds === []) {
            $kinds = DB::table('concept_kinds')->where('concept_id', $parentId)->pluck('kind')->all() ?: [CategoryKind::Skill->value, CategoryKind::Service->value];
        }

        return DB::transaction(function () use ($user, $owner, $parentId, $label, $normalized, $kinds): Concept {
            $vocabulary = $this->vocabularyFor($user, $owner);
            $concept = app(CreateConcept::class)->execute(
                $user,
                $vocabulary,
                (Str::slug($label) ?: 'category').'-'.substr(hash('sha256', $normalized.'|'.$parentId.'|'.Str::uuid()), 0, 10),
                null,
                ['source' => 'private_category'],
            );
            app(SetConceptLabel::class)->execute($user, $concept, $user->locale ?: app()->getLocale(), $label, ConceptLabelKind::Preferred);

            ConceptRelation::query()->create([
                'from_concept_id' => $concept->id,
                'relation_type_id' => ConceptRelationType::query()->where('key', 'narrower_than')->value('id'),
                'to_concept_id' => $parentId,
                'source' => ConceptAssertionSource::Manual,
                'created_by_actor_id' => $this->actor($user)->id,
                'metadata' => ['purpose' => 'category_placement'],
            ]);

            $now = now();
            DB::table('concept_kinds')->insert(array_map(fn (string $kind): array => [
                'concept_id' => $concept->id, 'kind' => $kind, 'created_at' => $now, 'updated_at' => $now,
            ], $kinds));

            return $concept->load('labels');
        }, 3);
    }

    public function rename(User $user, Party $owner, Concept $concept, string $label): Concept
    {
        abort_unless($this->authority->canActFor($user, $owner), 403);
        abort_unless($concept->status === ConceptStatus::Active && in_array((int) $concept->vocabulary_id, $this->catalog->ownerVocabularyIds($owner), true), 404);
        $label = Str::of($label)->squish()->toString();
        if ($label === '' || mb_strlen($label) > 120) {
            throw ValidationException::withMessages(['newCategory' => __('categories.errors.label_length')]);
        }
        app(SetConceptLabel::class)->execute($user, $concept, $user->locale ?: app()->getLocale(), $label, ConceptLabelKind::Preferred);

        return $concept->load('labels');
    }

    private function vocabularyFor(User $user, Party $owner): ConceptVocabulary
    {
        $scope = $this->catalog->scopeOf($owner);
        // People keep using the vocabulary their Needs and Offers already use.
        $slug = $owner->kind === Party::ACTOR ? 'profile-concepts' : 'categories';
        $existing = ConceptVocabulary::query()->where('scope_type', $scope->value)->where('scope_id', $owner->id)->where('slug', $slug)->first();
        if ($existing instanceof ConceptVocabulary) {
            return $existing;
        }

        $vocabulary = app(CreateConceptVocabulary::class)->execute($user, $scope, $owner->kind === Party::ACTOR ? 'Profile Concepts' : 'Categories', $slug, $owner->model(), ['purpose' => 'private_categories']);
        RequestMemo::flush();

        return $vocabulary;
    }

    private function actor(User $user): Actor
    {
        $actor = RequestMemo::user($user)?->actor;
        abort_unless($actor instanceof Actor, 403);

        return $actor;
    }
}
