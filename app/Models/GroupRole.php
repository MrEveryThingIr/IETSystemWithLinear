<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_id', 'name', 'permissions'])]
class GroupRole extends Model
{
    protected function casts(): array
    {
        return ['permissions' => AsArrayObject::class];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
