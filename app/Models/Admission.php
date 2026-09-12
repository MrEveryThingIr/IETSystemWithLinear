<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['group_id', 'candidate_actor_id', 'source_invitation_id', 'status', 'open_key', 'submitted_at', 'approved_at', 'finalized_at', 'rejected_at', 'cancelled_at', 'decision_note'])]
class Admission extends Model
{
    use HasFactory;

    public const TRANSITIONS = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['clarification_required', 'under_review', 'rejected', 'cancelled'], 'clarification_required' => ['submitted', 'cancelled'], 'under_review' => ['clarification_required', 'approved', 'rejected'], 'approved' => ['finalized'], 'finalized' => [], 'rejected' => [], 'cancelled' => []];

    private bool $applyingTransition = false;

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'approved_at' => 'datetime', 'finalized_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $admission): void {
            if (! in_array($admission->status, ['finalized', 'rejected', 'cancelled'], true)) {
                $admission->open_key = self::openKey((int) $admission->group_id, (int) $admission->candidate_actor_id);
            }
        });

        static::updating(function (self $admission): void {
            abort_if($admission->isDirty(['group_id', 'candidate_actor_id', 'source_invitation_id']), 422, 'Admission identity and provenance are immutable.');
            abort_if($admission->isDirty(['status', 'open_key']) && ! $admission->applyingTransition, 422, 'Admission status changes require the lifecycle action.');
        });
    }

    public static function openKey(int $groupId, int $candidateActorId): string
    {
        return $groupId.':'.$candidateActorId;
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'candidate_actor_id');
    }

    /** @return BelongsTo<GroupInvitation, $this> */
    public function sourceInvitation(): BelongsTo
    {
        return $this->belongsTo(GroupInvitation::class, 'source_invitation_id');
    }

    /** @return HasMany<AgreementAcceptance, $this> */
    public function acceptances(): HasMany
    {
        return $this->hasMany(AgreementAcceptance::class);
    }

    /** @return HasMany<AdmissionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AdmissionEvent::class);
    }

    /** @param array<string, mixed> $metadata */
    public function transitionTo(string $status, ?Actor $actor = null, ?string $note = null, array $metadata = []): bool
    {
        if ($this->status === $status) {
            return false;
        }

        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'admission' => __('ui.messages.admission_action_unavailable'),
            ]);
        }
        $timestamps = match ($status) {
            'submitted' => ['submitted_at' => now()], 'approved' => ['approved_at' => now()], 'finalized' => ['finalized_at' => now()], 'rejected' => ['rejected_at' => now()], 'cancelled' => ['cancelled_at' => now()], default => []
        };
        $this->applyingTransition = true;

        try {
            $this->update([
                'status' => $status,
                'open_key' => in_array($status, ['finalized', 'rejected', 'cancelled'], true) ? null : self::openKey($this->group_id, $this->candidate_actor_id),
                'decision_note' => $note,
                ...$timestamps,
            ]);
        } finally {
            $this->applyingTransition = false;
        }

        $this->events()->create(['actor_id' => $actor?->id, 'event' => "admission.{$status}", 'note' => $note, 'metadata' => $metadata ?: null]);

        return true;
    }
}
