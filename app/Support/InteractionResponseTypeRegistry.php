<?php

namespace App\Support;

class InteractionResponseTypeRegistry
{
    public const SHORT_TEXT = 'short_text';

    public const LONG_TEXT = 'long_text';

    public const BOOLEAN = 'boolean';

    public const NUMBER = 'number';

    public const DATE = 'date';

    public const SINGLE_CHOICE = 'single_choice';

    public const MULTIPLE_CHOICE = 'multiple_choice';

    public const ASSET = 'asset';

    public const CONTENT_EVIDENCE = 'content_evidence';

    /** @return list<string> */
    public function all(): array
    {
        return [
            self::SHORT_TEXT,
            self::LONG_TEXT,
            self::BOOLEAN,
            self::NUMBER,
            self::DATE,
            self::SINGLE_CHOICE,
            self::MULTIPLE_CHOICE,
            self::ASSET,
            self::CONTENT_EVIDENCE,
        ];
    }

    public function supports(string $type): bool
    {
        return in_array($type, $this->all(), true);
    }

    public function usesOptions(string $type): bool
    {
        return in_array($type, [self::SINGLE_CHOICE, self::MULTIPLE_CHOICE], true);
    }
}
