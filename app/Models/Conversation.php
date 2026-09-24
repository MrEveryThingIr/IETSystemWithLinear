<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'context_id',
    'key',
    'status',
    'created_by_actor_id',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $attributes = [
        'key' => 'main',
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            $conversation->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $conversation): void {
            if ($conversation->isDirty(['uuid', 'context_id', 'key', 'created_by_actor_id'])) {
                throw new LogicException('Conversation identity, Context and creator are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Conversations preserve collaboration history and cannot be deleted directly.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<ConversationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }
}
