<?php

namespace App\Support\Parties;

use App\Models\Actor;
use App\Models\Business;
use App\Models\Group;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Who owns a record: a person (Actor), a Business or a Group. Stored in three
 * columns with one prefix — "{prefix}_actor_id", "{prefix}_business_id",
 * "{prefix}_group_id" — of which exactly one is set. The person who acted is
 * always stored separately (created_by_actor_id) and is never a Party.
 */
final readonly class Party
{
    public const ACTOR = 'actor';

    public const BUSINESS = 'business';

    public const GROUP = 'group';

    private function __construct(public string $kind, public int $id) {}

    public static function of(Actor|Business|Group $owner): self
    {
        return new self(match (true) {
            $owner instanceof Actor => self::ACTOR,
            $owner instanceof Business => self::BUSINESS,
            $owner instanceof Group => self::GROUP,
        }, (int) $owner->getKey());
    }

    public static function make(string $kind, int $id): self
    {
        if (! in_array($kind, [self::ACTOR, self::BUSINESS, self::GROUP], true) || $id < 1) {
            throw new InvalidArgumentException('Unknown party.');
        }

        return new self($kind, $id);
    }

    /** Reads the party from a record that uses the prefixed columns. */
    public static function fromRecord(Model $record, string $prefix): ?self
    {
        foreach ([self::ACTOR, self::BUSINESS, self::GROUP] as $kind) {
            $id = $record->getAttribute($prefix.'_'.$kind.'_id');
            if ($id !== null) {
                return new self($kind, (int) $id);
            }
        }

        return null;
    }

    /** @return array<string, int|null> the three columns, exactly one filled */
    public function columns(string $prefix): array
    {
        return [
            $prefix.'_actor_id' => $this->kind === self::ACTOR ? $this->id : null,
            $prefix.'_business_id' => $this->kind === self::BUSINESS ? $this->id : null,
            $prefix.'_group_id' => $this->kind === self::GROUP ? $this->id : null,
        ];
    }

    public function model(): Actor|Business|Group|null
    {
        return match ($this->kind) {
            self::ACTOR => Actor::query()->find($this->id),
            self::BUSINESS => Business::query()->find($this->id),
            self::GROUP => Group::query()->find($this->id),
            default => throw new InvalidArgumentException('Unknown party.'),
        };
    }

    public function key(): string
    {
        return $this->kind.':'.$this->id;
    }

    public function is(self $other): bool
    {
        return $this->kind === $other->kind && $this->id === $other->id;
    }
}
