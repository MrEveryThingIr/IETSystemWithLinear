<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['group_id', 'created_by_actor_id', 'name', 'slug', 'kind', 'access_mode', 'status', 'is_default'])]
class GroupSpace extends Model
{
    use HasFactory;

    protected $attributes = [
        'kind' => 'chat',
        'access_mode' => 'group',
        'status' => 'active',
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<GroupSpaceMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(GroupSpaceMessage::class);
    }

    /** @return HasMany<GroupSpaceParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(GroupSpaceParticipant::class);
    }

    /** @return HasMany<SpaceContentDefinition, $this> */
    public function contentDefinitions(): HasMany
    {
        return $this->hasMany(SpaceContentDefinition::class);
    }

    /** @return HasMany<SpaceContent, $this> */
    public function contents(): HasMany
    {
        return $this->hasMany(SpaceContent::class);
    }
}
