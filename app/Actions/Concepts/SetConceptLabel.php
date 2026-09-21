<?php

namespace App\Actions\Concepts;

use App\ConceptLabelKind;
use App\Models\Concept;
use App\Models\ConceptLabel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SetConceptLabel
{
    public function execute(
        User $user,
        Concept $concept,
        string $locale,
        string $label,
        ConceptLabelKind $kind = ConceptLabelKind::Preferred,
    ): ConceptLabel {
        $concept->loadMissing('vocabulary');
        Gate::forUser($user)->authorize('manage', $concept->vocabulary);

        $locale = Str::lower(trim($locale));
        $label = Str::of($label)->squish()->toString();
        $normalized = Str::of($label)->lower()->toString();

        abort_if($locale === '' || $label === '', 422, 'Concept label locale and text are required.');

        return DB::transaction(function () use ($concept, $locale, $label, $normalized, $kind): ConceptLabel {
            if ($kind === ConceptLabelKind::Preferred) {
                $preferred = ConceptLabel::query()
                    ->where('concept_id', $concept->id)
                    ->where('locale', $locale)
                    ->where('kind', ConceptLabelKind::Preferred->value)
                    ->lockForUpdate()
                    ->first();

                if ($preferred instanceof ConceptLabel) {
                    $preferred->update([
                        'label' => $label,
                        'normalized_label' => $normalized,
                    ]);

                    return $preferred->refresh();
                }
            }

            return ConceptLabel::query()->firstOrCreate(
                [
                    'concept_id' => $concept->id,
                    'locale' => $locale,
                    'kind' => $kind->value,
                    'normalized_label' => $normalized,
                ],
                ['label' => $label],
            );
        }, 3);
    }
}
