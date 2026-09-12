<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $group_agreement_id
 * @property int $version_number
 * @property int $represented_actor_id
 * @property int|null $acting_user_id
 * @property string $hash_algorithm
 * @property CarbonImmutable|null $version_effective_from
 * @property CarbonImmutable|null $version_effective_until
 * @property bool $required_for_admission
 * @property bool $reacceptance_required
 */
#[Fillable(['admission_id', 'group_agreement_version_id', 'group_agreement_id', 'version_number', 'accepted_by_actor_id', 'represented_actor_id', 'acting_user_id', 'accepted_at', 'evidence_hash', 'hash_algorithm', 'version_effective_from', 'version_effective_until', 'required_for_admission', 'reacceptance_required', 'evidence_schema_version'])]
class AgreementAcceptance extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
            'version_effective_from' => 'immutable_datetime',
            'version_effective_until' => 'immutable_datetime',
            'required_for_admission' => 'boolean',
            'reacceptance_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => abort(422, 'Acceptance evidence is immutable.'));
        static::deleting(fn (): never => abort(422, 'Acceptance evidence is immutable.'));
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(GroupAgreementVersion::class, 'group_agreement_version_id');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(GroupAgreement::class, 'group_agreement_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'accepted_by_actor_id');
    }

    public function representedActor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'represented_actor_id');
    }

    public function actingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_user_id');
    }
}
