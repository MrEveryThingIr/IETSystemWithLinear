<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'name', 'required_for_admission'])]
class GroupAgreement extends Model
{
    protected function casts(): array
    {
        return ['required_for_admission' => 'boolean'];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return HasMany<GroupAgreementVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(GroupAgreementVersion::class);
    }
}
