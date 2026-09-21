<?php

namespace App\Models;

use App\ProfileDisclosureItemKind;
use Database\Factories\ActorProfileDisclosureItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['kind', 'item_key'])]
class ActorProfileDisclosureItem extends Model
{
    /** @use HasFactory<ActorProfileDisclosureItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Profile disclosure items are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Profile disclosure items are preserved with their grant.');
        });
    }

    /** @return BelongsTo<ActorProfileDisclosureGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(ActorProfileDisclosureGrant::class, 'grant_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => ProfileDisclosureItemKind::class,
        ];
    }
}
