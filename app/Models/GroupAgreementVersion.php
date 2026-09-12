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

    private const LIFECYCLE_ATTRIBUTES = ['status', 'decision_note', 'approved_by_actor_id', 'approved_at', 'published_at', 'activated_at', 'effective_from', 'effective_until', 'superseded_by_version_id'];

    private bool $applyingLifecycleTransition = false;

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
            if ($version->isDirty('content')) {
                $version->content_hash = self::hashContent($version->content);
            } elseif ($version->isDirty('content_hash')) {
                abort(422, 'The canonical content hash is managed by the agreement version.');
            }

            if (in_array($version->getOriginal('status'), ['approved', 'scheduled', 'active', 'superseded'], true)
                && $version->isDirty(['content', 'content_hash', 'rationale', 'version', 'group_agreement_id', 'reacceptance_required', 'created_by_actor_id'])) {
                abort(422, 'Published agreement version data is immutable.');
            }

            abort_if($version->isDirty(self::LIFECYCLE_ATTRIBUTES) && ! $version->applyingLifecycleTransition, 422, 'Agreement lifecycle changes require the lifecycle action.');
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

    /** @param array<string, mixed> $attributes */
    public function applyLifecycleTransition(array $attributes): void
    {
        abort_if(array_diff(array_keys($attributes), self::LIFECYCLE_ATTRIBUTES) !== [], 422, 'Only lifecycle attributes may change through this operation.');
        $this->applyingLifecycleTransition = true;

        try {
            $this->update($attributes);
        } finally {
            $this->applyingLifecycleTransition = false;
        }
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
