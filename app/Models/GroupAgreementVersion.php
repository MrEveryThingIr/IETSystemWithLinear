<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_agreement_id', 'version', 'content', 'status', 'effective_from', 'effective_until', 'created_by_actor_id'])]
class GroupAgreementVersion extends Model
{
    public const STATUSES = ['draft', 'proposed', 'approved', 'scheduled', 'active', 'superseded', 'rejected'];
    protected function casts(): array { return ['effective_from' => 'datetime', 'effective_until' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            abort_if($version->isDirty(['content', 'version', 'group_agreement_id']), 422, 'Agreement versions are immutable; create a new version instead.');
        });
    }
    public function agreement(): BelongsTo { return $this->belongsTo(GroupAgreement::class, 'group_agreement_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(Actor::class, 'created_by_actor_id'); }
    public function isActiveAt(?\DateTimeInterface $at = null): bool { $at ??= now(); return $this->status === 'active' && ($this->effective_from === null || $this->effective_from <= $at) && ($this->effective_until === null || $this->effective_until > $at); }
}
