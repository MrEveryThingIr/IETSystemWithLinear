<?php

namespace App;

use InvalidArgumentException;

enum ProfileDisclosureItemKind: string
{
    case Field = 'field';
    case ConceptAssertion = 'concept_assertion';
    case ProfileIntent = 'profile_intent';

    public static function fromItemKey(string $key): self
    {
        return match (true) {
            str_starts_with($key, 'field:') => self::Field,
            str_starts_with($key, 'assertion:') => self::ConceptAssertion,
            str_starts_with($key, 'intent:') => self::ProfileIntent,
            default => throw new InvalidArgumentException('Unsupported profile disclosure item key.'),
        };
    }
}
