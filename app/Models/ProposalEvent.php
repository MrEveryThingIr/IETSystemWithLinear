<?php

namespace App\Models;

use App\ProposalEventType;
use Database\Factories\ProposalEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['uuid', 'proposal_id', 'proposal_version_id', 'actor_id', 'event_type', 'payload'])]
class ProposalEvent extends Model
{
    /** @use HasFactory<ProposalEventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Proposal events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Proposal events preserve negotiation history.');
        });
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /** @return BelongsTo<ProposalVersion, $this> */
    public function proposalVersion(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    protected function casts(): array
    {
        return [
            'event_type' => ProposalEventType::class,
            'payload' => 'array',
        ];
    }
}
