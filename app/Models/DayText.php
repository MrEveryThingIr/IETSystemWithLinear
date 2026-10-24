<?php

namespace App\Models;

use App\Support\TimeBar\DayTexts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One entry of the "text of the day" line in the top time bar.
 *
 * @property array<string, mixed> $texts
 */
class DayText extends Model
{
    public const KINDS = ['quote', 'news', 'info'];

    protected $fillable = [
        'uuid', 'kind', 'texts', 'attribution', 'link_url', 'starts_on', 'ends_on',
        'active', 'sort_order', 'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'texts' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $text): void {
            $text->uuid ??= (string) Str::uuid();
        });
        static::saved(fn () => DayTexts::forget());
        static::deleted(fn () => DayTexts::forget());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /** Dated entries are announcements for their days; undated ones rotate daily. */
    public function isScheduled(): bool
    {
        return $this->starts_on !== null || $this->ends_on !== null;
    }

    /** The text in the locale asked for, else English, else the first one written. */
    public function textFor(string $locale): string
    {
        return DayTexts::pick($this->texts ?? [], $locale);
    }
}
