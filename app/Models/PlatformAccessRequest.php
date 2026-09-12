<?php

namespace App\Models;

use App\PlatformRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'role',
    'status',
    'pending_key',
    'reason',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
    'correlation_id',
])]
class PlatformAccessRequest extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => PlatformRole::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
