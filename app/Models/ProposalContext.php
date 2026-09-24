<?php

namespace App\Models;

use App\ContextKind;
use Database\Factories\ProposalContextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['context_id', 'proposal_id'])]
class ProposalContext extends Model
{
    /** @use HasFactory<ProposalContextFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $binding): void {
            $context = Context::query()->findOrFail($binding->context_id);

            if ($context->kind !== ContextKind::Negotiation) {
                throw new LogicException('Proposal Context binding requires a negotiation Context.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Proposal Context bindings are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Proposal Context bindings are preserved.');
        });
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
