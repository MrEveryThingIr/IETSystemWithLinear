<?php

namespace App\Actions\Categories;

use App\Actions\Concepts\AddConceptToScheme;
use App\Actions\Concepts\CreateConcept;
use App\Actions\Concepts\ManageConceptHierarchy;
use App\ConceptStatus;
use App\ConceptVocabularyScope;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptRelation;
use App\Models\ConceptRelationType;
use App\Models\ConceptScheme;
use App\Models\ConceptSuggestion;
use App\Models\ConceptVocabulary;
use App\Models\User;
use App\PlatformCapability;
use App\Support\Categories\CategoryCatalog;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Suggesting a private category for everyone, and the administrators'
 * review: approve (becomes shared), merge into an existing shared category,
 * or reject (stays private). Approving and merging move every use of the
 * private category to the shared one, so nothing is left pointing nowhere.
 */
class ReviewCategorySuggestions
{
    public function __construct(
        private readonly CategoryCatalog $catalog,
        private readonly PartyAuthority $authority,
    ) {}

    public function suggest(User $user, Concept $concept, ?string $note = null): ConceptSuggestion
    {
        $owner = $this->ownerOf($concept);
        abort_unless($owner instanceof Party && $this->authority->canActFor($user, $owner), 403);
        abort_unless($concept->status === ConceptStatus::Active, 422);

        $pending = ConceptSuggestion::query()->where('concept_id', $concept->id)->where('status', ConceptSuggestion::PENDING)->first();
        if ($pending instanceof ConceptSuggestion) {
            return $pending;
        }

        return ConceptSuggestion::query()->create([
            'concept_id' => $concept->id,
            'parent_concept_id' => $this->catalog->privateParentId((int) $concept->id),
            'suggested_by_actor_id' => $this->actor($user)->id,
            'note' => $note !== null && trim($note) !== '' ? Str::limit(Str::squish($note), 500, '') : null,
        ]);
    }

    public function canReview(User $user): bool
    {
        return $user->hasPlatformCapability(PlatformCapability::ManageConcepts);
    }

    /** Make it a shared category under a shared parent (default: its nearest shared ancestor). */
    public function approve(User $user, ConceptSuggestion $suggestion, ?int $parentId = null): Concept
    {
        $this->authorizeReview($user, $suggestion);
        $private = $suggestion->concept()->with('labels')->firstOrFail();
        $parentId ??= $this->nearestSharedAncestor((int) $private->id);
        if ($parentId === null || ! $this->catalog->isShared($parentId)) {
            throw ValidationException::withMessages(['parent' => __('categories.errors.choose_shared_parent')]);
        }

        return DB::transaction(function () use ($user, $suggestion, $private, $parentId): Concept {
            $vocabulary = ConceptVocabulary::query()->findOrFail($this->catalog->sharedVocabularyId());
            $scheme = ConceptScheme::query()->findOrFail($this->catalog->sharedSchemeId());
            $parentSlug = (string) Concept::query()->whereKey($parentId)->value('slug');
            $base = Str::slug($private->displayLabel('en')) ?: 'category';

            $shared = app(CreateConcept::class)->execute(
                $user,
                $vocabulary,
                $parentSlug.'.'.$base.'-'.substr(hash('sha256', (string) $private->uuid), 0, 6),
                null,
                ['source' => 'suggestion', 'suggestion' => $suggestion->uuid],
            );
            $now = now();
            DB::table('concept_labels')->insert($private->labels->map(fn ($label): array => [
                'concept_id' => $shared->id,
                'locale' => $label->locale,
                'label' => $label->label,
                'kind' => $label->kind->value,
                'normalized_label' => $label->normalized_label,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
            DB::table('concept_kinds')->insertOrIgnore(DB::table('concept_kinds')->where('concept_id', $private->id)->pluck('kind')
                ->map(fn ($kind): array => ['concept_id' => $shared->id, 'kind' => $kind, 'created_at' => $now, 'updated_at' => $now])->all());

            app(AddConceptToScheme::class)->execute($user, $scheme, $shared);
            app(ManageConceptHierarchy::class)->add($user, $scheme, Concept::query()->findOrFail($parentId), $shared, 999);

            $this->mergeInto($private, $shared, $user);
            $this->close($suggestion, $user, ConceptSuggestion::APPROVED, $shared, null);

            return $shared;
        }, 3);
    }

    /** It already exists as a shared category: use that one. */
    public function merge(User $user, ConceptSuggestion $suggestion, int $targetId, ?string $note = null): Concept
    {
        $this->authorizeReview($user, $suggestion);
        if (! $this->catalog->isShared($targetId)) {
            throw ValidationException::withMessages(['target' => __('categories.errors.choose_shared_parent')]);
        }
        $target = Concept::query()->where('status', ConceptStatus::Active->value)->findOrFail($targetId);
        $private = $suggestion->concept()->firstOrFail();

        return DB::transaction(function () use ($user, $suggestion, $private, $target, $note): Concept {
            $this->mergeInto($private, $target, $user);
            $this->close($suggestion, $user, ConceptSuggestion::MERGED, $target, $note);

            return $target;
        }, 3);
    }

    public function reject(User $user, ConceptSuggestion $suggestion, ?string $note = null): void
    {
        $this->authorizeReview($user, $suggestion);
        $this->close($suggestion, $user, ConceptSuggestion::REJECTED, null, $note);
    }

    private function mergeInto(Concept $private, Concept $target, User $user): void
    {
        $locked = Concept::query()->lockForUpdate()->findOrFail($private->id);
        $locked->applyLifecycle(['status' => ConceptStatus::Merged->value, 'merged_into_concept_id' => $target->id]);

        DB::table('capabilities')->where('concept_id', $private->id)->update(['concept_id' => $target->id, 'updated_at' => now()]);
        DB::table('actor_profile_intents')->where('concept_id', $private->id)->update(['concept_id' => $target->id, 'updated_at' => now()]);

        // The owner's own subcategories stay where they were, now under the shared category.
        $narrower = (int) ConceptRelationType::query()->where('key', 'narrower_than')->value('id');
        ConceptRelation::query()->where('relation_type_id', $narrower)->where('to_concept_id', $private->id)->get()
            ->each(function (ConceptRelation $relation) use ($private, $target, $narrower, $user): void {
                ConceptRelation::query()->firstOrCreate(
                    ['from_concept_id' => $relation->from_concept_id, 'relation_type_id' => $narrower, 'to_concept_id' => $target->id],
                    ['source' => $relation->source, 'created_by_actor_id' => $this->actor($user)->id, 'metadata' => ['moved_from' => $private->uuid]],
                );
                $relation->delete();
            });

        CategoryCatalog::bumpVersion();
    }

    private function close(ConceptSuggestion $suggestion, User $user, string $status, ?Concept $result, ?string $note): void
    {
        $suggestion->update([
            'status' => $status,
            'reviewed_by_actor_id' => $this->actor($user)->id,
            'reviewed_at' => now(),
            'review_note' => $note !== null && trim($note) !== '' ? Str::limit(Str::squish($note), 500, '') : null,
            'result_concept_id' => $result?->id,
        ]);
    }

    private function authorizeReview(User $user, ConceptSuggestion $suggestion): void
    {
        abort_unless($this->canReview($user), 403);
        abort_unless($suggestion->status === ConceptSuggestion::PENDING, 422);
    }

    private function nearestSharedAncestor(int $conceptId): ?int
    {
        $current = $conceptId;
        for ($i = 0; $i <= CategoryCatalog::MAX_PRIVATE_DEPTH + 1; $i++) {
            $parent = $this->catalog->privateParentId($current);
            if ($parent === null) {
                return null;
            }
            if ($this->catalog->isShared($parent)) {
                return $parent;
            }
            $current = $parent;
        }

        return null;
    }

    private function ownerOf(Concept $concept): ?Party
    {
        $vocabulary = ConceptVocabulary::query()->find($concept->vocabulary_id);
        if (! $vocabulary instanceof ConceptVocabulary) {
            return null;
        }

        return match ($vocabulary->scope_type) {
            ConceptVocabularyScope::Actor => Party::make(Party::ACTOR, (int) $vocabulary->scope_id),
            ConceptVocabularyScope::Business => Party::make(Party::BUSINESS, (int) $vocabulary->scope_id),
            ConceptVocabularyScope::Group => Party::make(Party::GROUP, (int) $vocabulary->scope_id),
            default => null,
        };
    }

    private function actor(User $user): Actor
    {
        $actor = RequestMemo::user($user)?->actor;
        abort_unless($actor instanceof Actor, 403);

        return $actor;
    }
}
