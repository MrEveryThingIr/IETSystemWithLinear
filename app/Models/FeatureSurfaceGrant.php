<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureSurfaceGrant extends Model
{
    protected $fillable = ['user_id', 'surface_key', 'granted_by_user_id', 'granted_at'];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
