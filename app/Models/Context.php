<?php

namespace App\Models;

use App\ContextKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['uuid', 'kind'])]
class Context extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => ContextKind::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $context): void {
            $context->uuid ??= (string) Str::uuid();

            if (! $context->kind instanceof ContextKind) {
                throw new LogicException('Context kind is invalid.');
            }
        });

        static::updating(function (self $context): void {
            if ($context->isDirty(['uuid', 'kind'])) {
                throw new LogicException('Context identity and kind are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Contexts are durable domain identity and cannot be deleted directly.');
        });
    }

    /** @return HasOne<PersonalContext, $this> */
    public function personalBinding(): HasOne
    {
        return $this->hasOne(PersonalContext::class);
    }

    /** @return HasOne<GroupSpaceContext, $this> */
    public function groupSpaceBinding(): HasOne
    {
        return $this->hasOne(GroupSpaceContext::class);
    }

    /** @return HasOne<AdmissionContext, $this> */
    public function admissionBinding(): HasOne
    {
        return $this->hasOne(AdmissionContext::class);
    }
}
