<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => abort(422, 'Agreement event evidence is immutable.'));
        static::deleting(fn (): never => abort(422, 'Agreement event evidence is immutable.'));
    }

    /** @return BelongsTo<GroupAgreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(GroupAgreement::class, 'group_agreement_id');
    }

    /** @return BelongsTo<GroupAgreementVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(GroupAgreementVersion::class, 'group_agreement_version_id');
    }
}
