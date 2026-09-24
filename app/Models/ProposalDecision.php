<?php

namespace App\Models;

use App\ProposalDecisionKind;
use Database\Factories\ProposalDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'proposal_version_id',
    'proposal_party_id',
    'decision',
    'note',
    'decided_by_user_id',
    'decided_at',
])]
class ProposalDecision extends Model
{
    /** @use HasFactory<ProposalDecisionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $decision): void {
            $decision->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Proposal decisions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Proposal decisions preserve exact-version acceptance evidence.');
        });
    }

    /** @return BelongsTo<ProposalVersion, $this> */
    public function proposalVersion(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class);
    }

    /** @return BelongsTo<ProposalParty, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(ProposalParty::class, 'proposal_party_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'decision' => ProposalDecisionKind::class,
            'decided_at' => 'datetime',
        ];
    }
}
