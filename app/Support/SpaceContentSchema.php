<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Validation\ValidationException;
use JsonException;

class SpaceContentSchema
{
    /** @var list<string> */
    public const FIELD_TYPES = [
        'short_text',
        'long_text',
        'number',
        'date',
        'boolean',
        'select',
    ];

    /**
     * @param  array<int, mixed>  $fields
     * @return array{fields: list<array{key: string, label: string, type: string, required: bool, help: string|null, options: list<array{value: string, label: string}>}>}
     */
    public static function normalizeDefinitionFields(array $fields): array
    {
        if ($fields === [] || count($fields) > 50) {
            self::invalid('definitionFields', 'A Content Definition must contain between 1 and 50 fields.');
        }

        $normalized = [];
        $keys = [];

        foreach (array_values($fields) as $index => $field) {
            if (! is_array($field)) {
                self::invalid("definitionFields.{$index}", 'Each field definition must be an object.');
            }

            $allowed = ['key', 'label', 'type', 'required', 'help', 'options'];
            $unknown = array_diff(array_keys($field), $allowed);
            if ($unknown !== []) {
                self::invalid("definitionFields.{$index}", 'Unknown field attributes are not allowed.');
            }

            $key = is_string($field['key'] ?? null) ? trim($field['key']) : '';
            if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
                self::invalid("definitionFields.{$index}.key", 'Field keys must start with a lowercase letter and contain only lowercase letters, numbers, and underscores.');
            }
            if (isset($keys[$key])) {
                self::invalid("definitionFields.{$index}.key", 'Field keys must be unique within a Content Definition.');
            }
            $keys[$key] = true;

            $label = is_string($field['label'] ?? null) ? trim($field['label']) : '';
            if ($label === '' || mb_strlen($label) > 120) {
                self::invalid("definitionFields.{$index}.label", 'Field labels are required and may not exceed 120 characters.');
            }

            $type = is_string($field['type'] ?? null) ? $field['type'] : '';
            if (! in_array($type, self::FIELD_TYPES, true)) {
                self::invalid("definitionFields.{$index}.type", 'Unknown Content field type.');
            }

            $required = $field['required'] ?? false;
            if (! is_bool($required)) {
                self::invalid("definitionFields.{$index}.required", 'The required flag must be boolean.');
            }

            $help = $field['help'] ?? null;
            if ($help !== null && ! is_string($help)) {
                self::invalid("definitionFields.{$index}.help", 'Help text must be text or null.');
            }
            $help = is_string($help) ? trim($help) : null;
            if ($help === '') {
                $help = null;
            }
            if ($help !== null && mb_strlen($help) > 500) {
                self::invalid("definitionFields.{$index}.help", 'Help text may not exceed 500 characters.');
            }

            $options = self::normalizeOptions($field['options'] ?? [], $type, $index);

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => $required,
                'help' => $help,
                'options' => $options,
            ];
        }

        return ['fields' => $normalized];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizePayload(array $schema, array $payload): array
    {
        $fields = $schema['fields'] ?? null;
        if (! is_array($fields) || ! array_is_list($fields)) {
            self::invalid('payload', 'The referenced Content Definition schema is invalid.');
        }

        $definedKeys = [];
        foreach ($fields as $field) {
            if (! is_array($field) || ! is_string($field['key'] ?? null)) {
                self::invalid('payload', 'The referenced Content Definition schema is invalid.');
            }
            $definedKeys[] = $field['key'];
        }

        $unknown = array_diff(array_keys($payload), $definedKeys);
        if ($unknown !== []) {
            self::invalid('payload', 'Payload contains fields that are not defined by the referenced Content Definition version.');
        }

        $normalized = [];
        foreach ($fields as $field) {
            /** @var array{key: string, label: string, type: string, required: bool, options: array<int, array{value: string, label: string}>} $field */
            $key = $field['key'];
            $value = $payload[$key] ?? null;

            if ($field['required'] && self::missingRequiredValue($value, $field['type'])) {
                self::invalid("payload.{$key}", "{$field['label']} is required.");
            }

            if ($value === null || ($value === '' && ! $field['required'])) {
                $normalized[$key] = null;

                continue;
            }

            $normalized[$key] = self::normalizeFieldValue($field, $value);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $value */
    public static function hashArray(array $value): string
    {
        return hash('sha256', self::canonicalJson($value));
    }

    public static function hashRevision(string $title, array $payload): string
    {
        return self::hashArray([
            'payload' => $payload,
            'title' => trim($title),
        ]);
    }

    /** @throws JsonException */
    public static function canonicalJson(mixed $value): string
    {
        return json_encode(
            self::canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function normalizeOptions(mixed $options, string $type, int $fieldIndex): array
    {
        if (! is_array($options) || ! array_is_list($options)) {
            self::invalid("definitionFields.{$fieldIndex}.options", 'Select options must be an ordered list.');
        }

        if ($type !== 'select') {
            if ($options !== []) {
                self::invalid("definitionFields.{$fieldIndex}.options", 'Only select fields may define options.');
            }

            return [];
        }

        if ($options === [] || count($options) > 100) {
            self::invalid("definitionFields.{$fieldIndex}.options", 'Select fields must define between 1 and 100 options.');
        }

        $seen = [];
        $normalized = [];
        foreach ($options as $optionIndex => $option) {
            if (! is_array($option) || array_diff(array_keys($option), ['value', 'label']) !== []) {
                self::invalid("definitionFields.{$fieldIndex}.options.{$optionIndex}", 'Each select option may contain only value and label.');
            }

            $value = is_string($option['value'] ?? null) ? trim($option['value']) : '';
            $label = is_string($option['label'] ?? null) ? trim($option['label']) : '';

            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/', $value)) {
                self::invalid("definitionFields.{$fieldIndex}.options.{$optionIndex}.value", 'Select option values may contain only letters, numbers, underscores, and hyphens.');
            }
            if ($label === '' || mb_strlen($label) > 120) {
                self::invalid("definitionFields.{$fieldIndex}.options.{$optionIndex}.label", 'Select option labels are required and may not exceed 120 characters.');
            }
            if (isset($seen[$value])) {
                self::invalid("definitionFields.{$fieldIndex}.options.{$optionIndex}.value", 'Select option values must be unique.');
            }

            $seen[$value] = true;
            $normalized[] = ['value' => $value, 'label' => $label];
        }

        return $normalized;
    }

    /** @param array{key: string, label: string, type: string, required: bool, options: array<int, array{value: string, label: string}>} $field */
    private static function normalizeFieldValue(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'short_text' => self::textValue($field, $value, 255),
            'long_text' => self::textValue($field, $value, 20_000),
            'number' => self::numberValue($field, $value),
            'date' => self::dateValue($field, $value),
            'boolean' => self::booleanValue($field, $value),
            'select' => self::selectValue($field, $value),
            default => self::invalid("payload.{$field['key']}", 'Unknown Content field type.'),
        };
    }

    /** @param array{key: string, label: string} $field */
    private static function textValue(array $field, mixed $value, int $max): string
    {
        if (! is_string($value) || mb_strlen($value) > $max) {
            self::invalid("payload.{$field['key']}", "{$field['label']} must be text no longer than {$max} characters.");
        }

        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    /** @param array{key: string, label: string} $field */
    private static function numberValue(array $field, mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value) || ! is_numeric(trim($value))) {
            self::invalid("payload.{$field['key']}", "{$field['label']} must be a number.");
        }

        $value = trim($value);

        return preg_match('/^-?\d+$/', $value) ? (int) $value : (float) $value;
    }

    /** @param array{key: string, label: string} $field */
    private static function dateValue(array $field, mixed $value): string
    {
        if (! is_string($value)) {
            self::invalid("payload.{$field['key']}", "{$field['label']} must be a date in YYYY-MM-DD format.");
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            self::invalid("payload.{$field['key']}", "{$field['label']} must be a valid date in YYYY-MM-DD format.");
        }

        return $value;
    }

    /** @param array{key: string, label: string} $field */
    private static function booleanValue(array $field, mixed $value): bool
    {
        if (! is_bool($value)) {
            self::invalid("payload.{$field['key']}", "{$field['label']} must be true or false.");
        }

        return $value;
    }

    /** @param array{key: string, label: string, options: array<int, array{value: string, label: string}>} $field */
    private static function selectValue(array $field, mixed $value): string
    {
        if (! is_string($value)) {
            self::invalid("payload.{$field['key']}", "{$field['label']} must be one of the defined options.");
        }

        $allowed = array_column($field['options'], 'value');
        if (! in_array($value, $allowed, true)) {
            self::invalid("payload.{$field['key']}", "{$field['label']} must be one of the defined options.");
        }

        return $value;
    }

    private static function missingRequiredValue(mixed $value, string $type): bool
    {
        if ($value === null) {
            return true;
        }

        return in_array($type, ['short_text', 'long_text', 'date', 'select'], true)
            && is_string($value)
            && trim($value) === '';
    }

    private static function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
