<?php

namespace App;

use InvalidArgumentException;

enum ProfileDisclosureItemKind: string
{
    case Field = 'field';
    case ConceptAssertion = 'concept_assertion';
    case ProfileIntent = 'profile_intent';
    case Ability = 'ability';
    case Interest = 'interest';

    public static function fromItemKey(string $key): self
    {
        return match (true) {
            str_starts_with($key, 'field:') => self::Field,
            str_starts_with($key, 'assertion:') => self::ConceptAssertion,
            str_starts_with($key, 'intent:') => self::ProfileIntent,
            str_starts_with($key, 'ability:') => self::Ability,
            str_starts_with($key, 'interest:') => self::Interest,
            default => throw new InvalidArgumentException('Unsupported profile disclosure item key.'),
        };
    }
}
