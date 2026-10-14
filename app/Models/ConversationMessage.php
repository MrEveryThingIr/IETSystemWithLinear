<?php

namespace App\Models;

use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'conversation_id',
    'author_actor_id',
    'reply_to_message_id',
    'body',
])]
class ConversationMessage extends Model
{
    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    protected $table = 'conversation_messages';

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Conversation messages are immutable collaboration evidence.');
        });

        static::deleting(function (): never {
            throw new LogicException('Conversation messages are immutable collaboration evidence.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return HasMany<ConversationMessageRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ConversationMessageRevision::class);
    }

    /** @return HasOne<ConversationMessageRevision, $this> */
    public function latestRevision(): HasOne
    {
        return $this->hasOne(ConversationMessageRevision::class)->latestOfMany();
    }

    /** The text readers see: the latest edit, or the original body. */
    public function currentBody(): string
    {
        $revision = $this->latestRevision;

        if ($revision instanceof ConversationMessageRevision && $revision->kind === ConversationMessageRevision::KIND_EDIT) {
            return (string) $revision->body;
        }

        return (string) $this->body;
    }

    public function isDeleted(): bool
    {
        return $this->latestRevision?->kind === ConversationMessageRevision::KIND_DELETE;
    }

    public function isEdited(): bool
    {
        return $this->latestRevision?->kind === ConversationMessageRevision::KIND_EDIT;
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'author_actor_id');
    }

    /** @return BelongsTo<ConversationMessage, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'conversation_message_assets')
            ->withPivot('uuid')
            ->withTimestamps();
    }

    /** @return BelongsToMany<ContentEvidenceReference, $this> */
    public function evidenceReferences(): BelongsToMany
    {
        return $this->belongsToMany(
            ContentEvidenceReference::class,
            'conversation_message_evidence_references',
        )
            ->withPivot('uuid')
            ->withTimestamps();
    }
}
