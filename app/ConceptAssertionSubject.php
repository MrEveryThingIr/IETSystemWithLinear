<?php

namespace App;

use App\Models\Actor;
use App\Models\Group;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

enum ConceptAssertionSubject: string
{
    case Actor = 'actor';
    case Group = 'group';
    case SpaceContent = 'space_content';
    case SpaceContentRevision = 'space_content_revision';

    public static function fromModel(Model $subject): self
    {
        return match (true) {
            $subject instanceof Actor => self::Actor,
            $subject instanceof Group => self::Group,
            $subject instanceof SpaceContent => self::SpaceContent,
            $subject instanceof SpaceContentRevision => self::SpaceContentRevision,
            default => throw new InvalidArgumentException('Unsupported Concept assertion subject.'),
        };
    }

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Actor => Actor::class,
            self::Group => Group::class,
            self::SpaceContent => SpaceContent::class,
            self::SpaceContentRevision => SpaceContentRevision::class,
        };
    }
}
