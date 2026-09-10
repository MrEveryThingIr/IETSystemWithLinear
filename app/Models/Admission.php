<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['group_id', 'candidate_actor_id', 'source_invitation_id', 'status', 'submitted_at', 'approved_at', 'finalized_at', 'rejected_at', 'cancelled_at', 'decision_note'])]
class Admission extends Model
{
    use HasFactory;

    public const TRANSITIONS = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['clarification_required', 'under_review', 'rejected', 'cancelled'], 'clarification_required' => ['submitted', 'cancelled'], 'under_review' => ['clarification_required', 'approved', 'rejected'], 'approved' => ['finalized'], 'finalized' => [], 'rejected' => [], 'cancelled' => []];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'approved_at' => 'datetime', 'finalized_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $admission): void {
            abort_if($admission->isDirty('source_invitation_id'), 422, 'Admission provenance is immutable.');
        });
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
    public function transitionTo(string $status, ?Actor $actor = null, ?string $note = null, array $metadata = []): void
    {
        if (! in_array($status, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'admission' => 'That admission action is no longer available. Refresh the page and try again.',
            ]);
        }
        $timestamps = match ($status) {
            'submitted' => ['submitted_at' => now()], 'approved' => ['approved_at' => now()], 'finalized' => ['finalized_at' => now()], 'rejected' => ['rejected_at' => now()], 'cancelled' => ['cancelled_at' => now()], default => []
        };
        $this->update(['status' => $status, 'decision_note' => $note, ...$timestamps]);
        $this->events()->create(['actor_id' => $actor?->id, 'event' => "admission.{$status}", 'note' => $note, 'metadata' => $metadata ?: null]);
    }
}
