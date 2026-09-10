<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admission_id', 'group_agreement_version_id', 'accepted_by_actor_id', 'accepted_at', 'evidence_hash'])]
class AgreementAcceptance extends Model
{
    protected function casts(): array { return ['accepted_at' => 'datetime']; }
    public function admission(): BelongsTo { return $this->belongsTo(Admission::class); }
    public function version(): BelongsTo { return $this->belongsTo(GroupAgreementVersion::class, 'group_agreement_version_id'); }
    public function acceptedBy(): BelongsTo { return $this->belongsTo(Actor::class, 'accepted_by_actor_id'); }
}
