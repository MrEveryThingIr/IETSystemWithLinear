<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureSurfaceEvent extends Model
{
    protected $fillable = ['actor_user_id', 'subject_user_id', 'surface_key', 'event', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
