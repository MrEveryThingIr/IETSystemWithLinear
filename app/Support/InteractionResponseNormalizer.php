<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Validation\ValidationException;

class InteractionResponseNormalizer
{
    /** @param array<string, mixed> $item */
    public function normalize(array $item, mixed $value): mixed
    {
        $type = (string) ($item['type'] ?? '');

        return match ($type) {
            InteractionResponseTypeRegistry::SHORT_TEXT => $this->text($item, $value, 255),
            InteractionResponseTypeRegistry::LONG_TEXT => $this->text($item, $value, 20000),
            InteractionResponseTypeRegistry::BOOLEAN => $this->boolean($item, $value),
            InteractionResponseTypeRegistry::NUMBER => $this->number($item, $value),
            InteractionResponseTypeRegistry::DATE => $this->date($item, $value),
            InteractionResponseTypeRegistry::SINGLE_CHOICE => $this->singleChoice($item, $value),
            InteractionResponseTypeRegistry::MULTIPLE_CHOICE => $this->multipleChoice($item, $value),
            default => $this->invalid((string) ($item['key'] ?? 'response'), 'This response type does not accept a scalar value.'),
        };
    }

    /** @param array<string, mixed> $item */
    private function text(array $item, mixed $value, int $absoluteMax): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            return $this->invalid((string) $item['key'], 'Response must be text.');
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $length = mb_strlen($value);
        $constraints = is_array($item['constraints'] ?? null) ? $item['constraints'] : [];
        $min = isset($constraints['min_length']) ? (int) $constraints['min_length'] : 0;
        $max = isset($constraints['max_length'])
            ? min((int) $constraints['max_length'], $absoluteMax)
            : $absoluteMax;

        if ($length < $min || $length > $max) {
            return $this->invalid((string) $item['key'], "Response must contain between {$min} and {$max} characters.");
        }

        return trim($value) === '' ? null : $value;
    }

    /** @param array<string, mixed> $item */
    private function boolean(array $item, mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (! is_bool($value)) {
            return $this->invalid((string) $item['key'], 'Response must be true or false.');
        }

        return $value;
    }

    /** @param array<string, mixed> $item */
    private function number(array $item, mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $number = $value;
        } elseif (is_string($value) && is_numeric(trim($value))) {
            $value = trim($value);
            $number = preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : (float) $value;
        } else {
            return $this->invalid((string) $item['key'], 'Response must be a number.');
        }

        $constraints = is_array($item['constraints'] ?? null) ? $item['constraints'] : [];
        if (isset($constraints['min']) && $number < $constraints['min']) {
            return $this->invalid((string) $item['key'], 'Response is below the allowed minimum.');
        }
        if (isset($constraints['max']) && $number > $constraints['max']) {
            return $this->invalid((string) $item['key'], 'Response exceeds the allowed maximum.');
        }

        return $number;
    }

    /** @param array<string, mixed> $item */
    private function date(array $item, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            return $this->invalid((string) $item['key'], 'Response must be a date in YYYY-MM-DD format.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            return $this->invalid((string) $item['key'], 'Response must be a valid date in YYYY-MM-DD format.');
        }

        return $value;
    }

    /** @param array<string, mixed> $item */
    private function singleChoice(array $item, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $options = is_array($item['options'] ?? null) ? $item['options'] : [];
        if (! is_string($value) || ! in_array($value, $options, true)) {
            return $this->invalid((string) $item['key'], 'Response must be one of the defined options.');
        }

        return $value;
    }

    /** @param array<string, mixed> $item @return list<string>|null */
    private function multipleChoice(array $item, mixed $value): ?array
    {
        if ($value === null || $value === []) {
            return null;
        }
        if (! is_array($value) || ! array_is_list($value)) {
            return $this->invalid((string) $item['key'], 'Response must be a list of defined options.');
        }

        $options = is_array($item['options'] ?? null) ? $item['options'] : [];
        $normalized = [];

        foreach ($value as $choice) {
            if (! is_string($choice) || ! in_array($choice, $options, true)) {
                return $this->invalid((string) $item['key'], 'Response contains an undefined option.');
            }
            $normalized[] = $choice;
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized === [] ? null : $normalized;
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages(["responses.{$key}" => $message]);
    }
}
