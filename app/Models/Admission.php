<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'candidate_actor_id', 'source_invitation_id', 'status', 'submitted_at', 'approved_at', 'rejected_at', 'cancelled_at', 'decision_note'])]
class Admission extends Model
{
    use HasFactory;
    public const TRANSITIONS = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['clarification_required', 'under_review', 'rejected', 'cancelled'], 'clarification_required' => ['submitted', 'cancelled'], 'under_review' => ['clarification_required', 'approved', 'rejected'], 'approved' => [], 'rejected' => [], 'cancelled' => []];
    protected function casts(): array { return ['submitted_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime']; }
    protected static function booted(): void { static::updating(function (self $admission): void { abort_if($admission->isDirty('source_invitation_id'), 422, 'Admission provenance is immutable.'); }); }
    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
    public function candidate(): BelongsTo { return $this->belongsTo(Actor::class, 'candidate_actor_id'); }
    public function sourceInvitation(): BelongsTo { return $this->belongsTo(GroupInvitation::class, 'source_invitation_id'); }
    public function acceptances(): HasMany { return $this->hasMany(AgreementAcceptance::class); }
    public function events(): HasMany { return $this->hasMany(AdmissionEvent::class); }
    public function transitionTo(string $status, ?Actor $actor = null, ?string $note = null): void
    {
        abort_unless(in_array($status, self::TRANSITIONS[$this->status] ?? [], true), 422, 'Invalid admission transition.');
        $timestamps = match ($status) { 'submitted' => ['submitted_at' => now()], 'approved' => ['approved_at' => now()], 'rejected' => ['rejected_at' => now()], 'cancelled' => ['cancelled_at' => now()], default => [] };
        $this->update(['status' => $status, 'decision_note' => $note, ...$timestamps]);
        $this->events()->create(['actor_id' => $actor?->id, 'event' => "admission.{$status}", 'note' => $note]);
    }
}
