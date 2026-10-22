<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's view of a conversation: what they have read, and mute / archive / pin. */
#[Fillable(['conversation_id', 'actor_id', 'last_read_message_id', 'last_seen_at', 'muted_until', 'archived_at', 'pinned_at'])]
class ConversationParticipant extends Model
{
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'muted_until' => 'datetime',
            'archived_at' => 'datetime',
            'pinned_at' => 'datetime',
        ];
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
