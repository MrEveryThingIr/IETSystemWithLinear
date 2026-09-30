<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicIntakePortalGrant extends Model
{
    protected $fillable = [
        'public_intake_portal_id',
        'user_id',
        'role',
    ];

    public function portal(): BelongsTo
    {
        return $this->belongsTo(PublicIntakePortal::class, 'public_intake_portal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
