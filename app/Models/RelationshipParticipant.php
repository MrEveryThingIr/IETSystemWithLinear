<?php

namespace App\Models;

use App\RelationshipParticipantStatus;
use Database\Factories\RelationshipParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'relationship_id',
    'actor_id',
    'role',
    'status',
    'can_manage',
    'invited_by_actor_id',
    'invited_at',
    'joined_at',
    'declined_at',
    'left_at',
])]
class RelationshipParticipant extends Model
{
    /** @use HasFactory<RelationshipParticipantFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => RelationshipParticipantStatus::Invited->value,
        'can_manage' => false,
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::updating(function (self $participant): void {
            if ($participant->isDirty(['relationship_id', 'actor_id', 'invited_by_actor_id'])) {
                throw new LogicException('Relationship participant provenance cannot be reassigned.');
            }

            if ($participant->isDirty(['status', 'joined_at', 'declined_at', 'left_at'])
                && ! $participant->applyingLifecycle) {
                throw new LogicException('Relationship participant lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Relationship participation history cannot be deleted directly.');
        });
    }

    /** @return BelongsTo<Relationship, $this> */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'invited_by_actor_id');
    }

    public function applyStatus(RelationshipParticipantStatus $status): void
    {
        $current = $this->status;

        $allowed = match ($current) {
            RelationshipParticipantStatus::Invited => [
                RelationshipParticipantStatus::Active,
                RelationshipParticipantStatus::Declined,
            ],
            RelationshipParticipantStatus::Active => [RelationshipParticipantStatus::Left],
            RelationshipParticipantStatus::Declined, RelationshipParticipantStatus::Left => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw new LogicException("Relationship participant cannot transition from {$current->value} to {$status->value}.");
        }

        $attributes = ['status' => $status];

        if ($status === RelationshipParticipantStatus::Active) {
            $attributes['joined_at'] = now();
        } elseif ($status === RelationshipParticipantStatus::Declined) {
            $attributes['declined_at'] = now();
        } elseif ($status === RelationshipParticipantStatus::Left) {
            $attributes['left_at'] = now();
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return [
            'status' => RelationshipParticipantStatus::class,
            'can_manage' => 'boolean',
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'declined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }
}
