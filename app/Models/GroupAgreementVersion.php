<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_agreement_id', 'version', 'content', 'rationale', 'status', 'effective_from', 'effective_until', 'reacceptance_required', 'created_by_actor_id', 'approved_by_actor_id', 'approved_at', 'published_at', 'activated_at', 'superseded_by_version_id', 'decision_note'])]
class GroupAgreementVersion extends Model
{
    public const STATUSES = ['draft', 'proposed', 'clarification_requested', 'approved', 'scheduled', 'active', 'superseded', 'rejected'];

    protected function casts(): array
    {
        return ['effective_from' => 'datetime', 'effective_until' => 'datetime', 'approved_at' => 'datetime', 'published_at' => 'datetime', 'activated_at' => 'datetime', 'reacceptance_required' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if (in_array($version->getOriginal('status'), ['approved', 'scheduled', 'active', 'superseded'], true)
                && $version->isDirty(['content', 'rationale', 'version', 'group_agreement_id', 'reacceptance_required', 'created_by_actor_id'])) {
                abort(422, 'Published agreement version data is immutable.');
            }
        });

        static::deleting(function (): void {
            abort(422, 'Agreement versions are immutable history.');
        });
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(GroupAgreement::class, 'group_agreement_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    public function isActiveAt(?\DateTimeInterface $at = null): bool
    {
        $at ??= now();

        return $this->status === 'active' && $this->effective_from <= $at && ($this->effective_until === null || $this->effective_until > $at);
    }
}
