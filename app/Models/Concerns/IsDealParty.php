<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Models\Group;
use App\Support\Parties\Party;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * A deal party is a principal: a person, a Business or a Group. actor_id is the
 * representative/contact (the principal itself for a person); principal_key is the identity.
 */
trait IsDealParty
{
    protected static function bootIsDealParty(): void
    {
        static::creating(function (self $party): void {
            if ($party->business_id !== null && $party->group_id !== null) {
                throw new InvalidArgumentException('A deal party is either a Business or a Group, not both.');
            }

            $party->principal_key = $party->principal()->key();
        });
    }

    public function principal(): Party
    {
        return match (true) {
            $this->business_id !== null => Party::make(Party::BUSINESS, (int) $this->business_id),
            $this->group_id !== null => Party::make(Party::GROUP, (int) $this->group_id),
            default => Party::make(Party::ACTOR, (int) $this->actor_id),
        };
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
