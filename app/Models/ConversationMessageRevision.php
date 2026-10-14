<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['conversation_message_id', 'actor_id', 'kind', 'body', 'created_at'])]
class ConversationMessageRevision extends Model
{
    public const KIND_EDIT = 'edit';

    public const KIND_DELETE = 'delete';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $revision): void {
            if (! in_array($revision->kind, [self::KIND_EDIT, self::KIND_DELETE], true)) {
                throw new LogicException('Unknown message revision kind.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Message revisions are append-only history.');
        });

        static::deleting(function (): never {
            throw new LogicException('Message revisions are append-only history.');
        });
    }

    /** @return BelongsTo<ConversationMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
