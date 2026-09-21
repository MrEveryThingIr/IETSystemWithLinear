<?php

namespace App\Actions\Concepts;

use App\ConceptVocabularyScope;
use App\Models\Actor;
use App\Models\ConceptVocabulary;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateConceptVocabulary
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        User $user,
        ConceptVocabularyScope $scope,
        string $name,
        string $slug,
        Actor|Group|null $owner = null,
        array $metadata = [],
    ): ConceptVocabulary {
        $scopeId = $this->authorizeScope($user, $scope, $owner);
        $creator = $this->creator($user);
        $name = Str::of($name)->squish()->toString();
        $slug = Str::slug($slug);

        abort_if($name === '' || $slug === '', 422, 'Concept Vocabulary name and slug are required.');
        abort_if(
            ConceptVocabulary::query()
                ->where('scope_type', $scope->value)
                ->where('scope_id', $scopeId)
                ->where('slug', $slug)
                ->exists(),
            422,
            'A Concept Vocabulary with this slug already exists in the selected scope.',
        );

        return ConceptVocabulary::query()->create([
            'scope_type' => $scope,
            'scope_id' => $scopeId,
            'name' => $name,
            'slug' => $slug,
            'created_by_actor_id' => $creator->id,
            'metadata' => $metadata,
        ]);
    }

    private function authorizeScope(User $user, ConceptVocabularyScope $scope, Actor|Group|null $owner): int
    {
        return match ($scope) {
            ConceptVocabularyScope::Platform => $this->authorizePlatform($user, $owner),
            ConceptVocabularyScope::Actor => $this->authorizeActor($user, $owner),
            ConceptVocabularyScope::Group => $this->authorizeGroup($user, $owner),
        };
    }

    private function authorizePlatform(User $user, Actor|Group|null $owner): int
    {
        abort_unless($owner === null, 422, 'Platform Concept Vocabularies do not have a scope owner.');
        Gate::forUser($user)->authorize('createPlatform', ConceptVocabulary::class);

        return 0;
    }

    private function authorizeActor(User $user, Actor|Group|null $owner): int
    {
        abort_unless($owner instanceof Actor, 422, 'Actor-scoped Concept Vocabulary requires an Actor owner.');
        Gate::forUser($user)->authorize('createForActor', [ConceptVocabulary::class, $owner]);

        return (int) $owner->id;
    }

    private function authorizeGroup(User $user, Actor|Group|null $owner): int
    {
        abort_unless($owner instanceof Group, 422, 'Group-scoped Concept Vocabulary requires a Group owner.');
        Gate::forUser($user)->authorize('createForGroup', [ConceptVocabulary::class, $owner]);

        return (int) $owner->id;
    }

    private function creator(User $user): Actor
    {
        $current = User::query()->with('actor')->findOrFail($user->id);
        abort_unless($current->actor instanceof Actor, 422, 'Concept governance requires an Actor identity.');

        return $current->actor;
    }
}
