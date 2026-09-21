<?php

namespace App\Actions\Concepts;

use App\Models\Concept;
use App\Models\ConceptVocabulary;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateConcept
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function execute(
        User $user,
        ConceptVocabulary $vocabulary,
        string $slug,
        ?string $summary = null,
        array $metadata = [],
    ): Concept {
        Gate::forUser($user)->authorize('manage', $vocabulary);

        $slug = Str::slug($slug);
        abort_if($slug === '', 422, 'Concept slug is required.');
        abort_if(
            $vocabulary->concepts()->where('slug', $slug)->exists(),
            422,
            'A Concept with this slug already exists in the Vocabulary.',
        );

        return $vocabulary->concepts()->create([
            'slug' => $slug,
            'summary' => $summary !== null ? Str::of($summary)->squish()->toString() : null,
            'metadata' => $metadata,
        ]);
    }
}
