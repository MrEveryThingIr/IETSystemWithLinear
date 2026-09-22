<?php

namespace App\Models;

use App\ContextKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['context_id', 'admission_id'])]
class AdmissionContext extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $binding): void {
            $context = Context::query()->findOrFail($binding->context_id);

            if ($context->kind !== ContextKind::Admission) {
                throw new LogicException('Admission Context binding requires an Admission Context.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Admission Context bindings are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Admission Context bindings are preserved.');
        });
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Admission, $this> */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }
}
