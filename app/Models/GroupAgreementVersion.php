<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_agreement_id', 'version', 'content', 'content_hash', 'rationale', 'status', 'effective_from', 'effective_until', 'reacceptance_required', 'created_by_actor_id', 'approved_by_actor_id', 'approved_at', 'published_at', 'activated_at', 'superseded_by_version_id', 'decision_note'])]
class GroupAgreementVersion extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'proposed', 'clarification_requested', 'approved', 'scheduled', 'active', 'superseded', 'rejected'];

    protected function casts(): array
    {
        return ['effective_from' => 'datetime', 'effective_until' => 'datetime', 'approved_at' => 'datetime', 'published_at' => 'datetime', 'activated_at' => 'datetime', 'reacceptance_required' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->content_hash = self::hashContent($version->content);
        });

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

    public static function hashContent(string $content): string
    {
        $canonical = preg_replace('/[ \t]+$/m', '', str_replace(["\r\n", "\r"], "\n", trim($content))) ?? '';

        return hash('sha256', $canonical);
    }

    /** @return BelongsTo<GroupAgreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(GroupAgreement::class, 'group_agreement_id');
    }

    /** @return BelongsTo<Actor, $this> */
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
