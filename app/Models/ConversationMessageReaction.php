<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_message_id', 'actor_id', 'emoji', 'created_at'])]
class ConversationMessageReaction extends Model
{
    /** The small set offered in the interface (and accepted by the server). */
    public const EMOJI = ['👍', '❤️', '😂', '😮', '😢', '🙏', '✅'];

    public const UPDATED_AT = null;

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
