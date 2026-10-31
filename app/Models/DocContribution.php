<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A proposal sent from the documentation by a guest or a member. It deliberately keeps its own small set of facts about
 * the author (field, expertise, education): a reader of public documentation may not be a user, and what matters for one
 * proposal is not the same as a profile.
 */
class DocContribution extends Model
{
    public const STATUSES = ['new', 'reviewed', 'accepted', 'declined'];

    protected $fillable = [
        'user_id', 'locale', 'topic', 'name', 'email', 'field', 'expertise', 'education', 'experience', 'links',
        'proposal', 'may_credit', 'status',
    ];

    protected function casts(): array
    {
        return ['may_credit' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contribution): void {
            $contribution->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
