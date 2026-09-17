<?php

namespace App\Support;

use InvalidArgumentException;

class SpaceContentFieldRegistry
{
    /** @var array<string, string> */
    private const COMPONENTS = [
        'short_text' => 'space-content.fields.short-text',
        'long_text' => 'space-content.fields.long-text',
        'number' => 'space-content.fields.number',
        'date' => 'space-content.fields.date',
        'boolean' => 'space-content.fields.boolean',
        'select' => 'space-content.fields.select',
    ];

    /** @return list<string> */
    public function types(): array
    {
        return array_keys(self::COMPONENTS);
    }

    public function componentFor(string $type): string
    {
        return self::COMPONENTS[$type]
            ?? throw new InvalidArgumentException("Unknown trusted Content field type [{$type}].");
    }
}
