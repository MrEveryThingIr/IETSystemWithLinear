<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['reporter_actor_id', 'reported_actor_id', 'conversation_message_id', 'reason', 'details', 'status', 'reviewed_by_actor_id', 'reviewed_at'])]
class MessageReport extends Model
{
    public const REASONS = ['spam', 'harassment', 'scam', 'inappropriate', 'other'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $report): void {
            $report->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Actor, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'reporter_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function reported(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'reported_actor_id');
    }

    /** @return BelongsTo<ConversationMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
