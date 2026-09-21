<?php

namespace App\Models;

use App\ConceptLabelKind;
use Database\Factories\ConceptLabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'concept_id',
    'locale',
    'label',
    'kind',
    'normalized_label',
])]
class ConceptLabel extends Model
{
    /** @use HasFactory<ConceptLabelFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        $normalize = static function (self $label): void {
            $label->locale = Str::lower(trim($label->locale));
            $label->label = Str::of($label->label)->squish()->toString();
            $label->normalized_label = Str::of($label->label)->lower()->toString();
        };

        static::creating($normalize);
        static::updating($normalize);
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => ConceptLabelKind::class,
        ];
    }
}
