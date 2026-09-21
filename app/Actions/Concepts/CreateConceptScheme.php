<?php

namespace App\Actions\Concepts;

use App\Models\Actor;
use App\Models\ConceptScheme;
use App\Models\ConceptVocabulary;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateConceptScheme
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function execute(
        User $user,
        ConceptVocabulary $vocabulary,
        string $name,
        string $slug,
        ?string $description = null,
        array $metadata = [],
    ): ConceptScheme {
        Gate::forUser($user)->authorize('manage', $vocabulary);
        $creator = $this->creator($user);
        $name = Str::of($name)->squish()->toString();
        $slug = Str::slug($slug);

        abort_if($name === '' || $slug === '', 422, 'Concept Scheme name and slug are required.');
        abort_if(
            $vocabulary->schemes()->where('slug', $slug)->exists(),
            422,
            'A Concept Scheme with this slug already exists in the Vocabulary.',
        );

        return $vocabulary->schemes()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description !== null ? Str::of($description)->squish()->toString() : null,
            'created_by_actor_id' => $creator->id,
            'metadata' => $metadata,
        ]);
    }

    private function creator(User $user): Actor
    {
        $current = User::query()->with('actor')->findOrFail($user->id);
        abort_unless($current->actor instanceof Actor, 422, 'Concept governance requires an Actor identity.');

        return $current->actor;
    }
}
