<?php

namespace App\Models\Bookkeeping;

use App\Enums\Bookkeeping\CategoryDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'ledger_id', 'direction', 'name', 'system_key', 'archived_at'])]
class Category extends Model
{
    protected $table = 'bk_categories';

    protected function casts(): array
    {
        return [
            'direction' => CategoryDirection::class,
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            $category->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** Built-in categories show in the person's language; their own names as typed. */
    public function label(): string
    {
        return $this->system_key !== null
            ? (string) __('bookkeeping.categories.'.$this->system_key)
            : $this->name;
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
