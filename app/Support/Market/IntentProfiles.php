<?php

namespace App\Support\Market;

/**
 * Code-owned "what is it about" profiles. A profile decides which few questions make sense for a
 * kind of thing; the keys themselves live in IntentDetailCatalog. Profiles never own data: answers
 * are ordinary typed details on the one Need/Offer record, so every other surface keeps working.
 *
 * Field entry: [key, tier, options]. Tier is essential (asked up front) or suggested (offered).
 * Options: need => tier to use for a Need (or null to hide); arrangement => only for this
 * arrangement; only/unless => [key => values] conditions on other answers.
 */
final class IntentProfiles
{
    public const TOPICS = ['home', 'vehicle', 'device', 'goods', 'repair', 'lesson', 'transport', 'job', 'activity'];

    private const OWNERSHIP = 'ownership_transfer';

    private const USE = 'temporary_use';

    /** @return array<string, array{subject: string, arrangements: list<string>, fields: list<array>}> */
    public static function all(): array
    {
        $used = ['like_new', 'used', 'refurbished', 'needs_repair'];

        return [
            'home' => ['subject' => 'property', 'arrangements' => [self::OWNERSHIP, self::USE], 'fields' => [
                ['property_type', 'essential'], ['floor_area', 'essential'], ['rooms', 'essential'],
                ['deposit', 'essential', ['arrangement' => self::USE, 'need' => 'suggested']],
                ['floor', 'suggested'], ['building_age', 'suggested'],
                ['parking', 'suggested'], ['elevator', 'suggested'], ['storage', 'suggested'], ['balcony', 'suggested'],
                ['furnished', 'suggested', ['arrangement' => self::USE]],
            ]],
            'vehicle' => ['subject' => 'good', 'arrangements' => [self::OWNERSHIP, self::USE], 'fields' => [
                ['brand', 'essential'], ['model', 'essential', ['need' => 'suggested']], ['model_year', 'essential', ['need' => 'suggested']],
                ['condition', 'essential', ['arrangement' => self::OWNERSHIP]],
                ['mileage_km', 'essential', ['only' => ['condition' => $used], 'need' => 'suggested', 'arrangement' => self::OWNERSHIP]],
                ['fuel_type', 'suggested'], ['gearbox', 'suggested'], ['color', 'suggested'],
            ]],
            'device' => ['subject' => 'good', 'arrangements' => [self::OWNERSHIP, self::USE], 'fields' => [
                ['brand', 'essential'], ['model', 'essential', ['need' => 'suggested']], ['condition', 'essential', ['arrangement' => self::OWNERSHIP]],
                ['storage_gb', 'suggested'], ['ram_gb', 'suggested'], ['color', 'suggested'],
                ['warranty', 'suggested', ['arrangement' => self::OWNERSHIP, 'only' => ['condition' => ['new', 'like_new']]]],
            ]],
            'goods' => ['subject' => 'good', 'arrangements' => [self::OWNERSHIP, self::USE], 'fields' => [
                ['condition', 'essential', ['arrangement' => self::OWNERSHIP]], ['brand', 'suggested'], ['color', 'suggested'],
            ]],
            'repair' => ['subject' => 'service', 'arrangements' => ['service'], 'fields' => [
                ['service_area', 'essential', ['need' => null]], ['urgency', 'essential', ['need' => 'essential']],
                ['availability', 'suggested', ['need' => null]], ['experience_years', 'suggested', ['need' => null]],
                ['materials_included', 'suggested'], ['warranty', 'suggested', ['need' => null]], ['delivery_mode', 'suggested'],
            ]],
            'lesson' => ['subject' => 'service', 'arrangements' => ['service'], 'fields' => [
                ['lesson_subject', 'essential'], ['level', 'essential'], ['delivery_mode', 'essential'],
                ['group_format', 'suggested'], ['session_minutes', 'suggested'], ['teaching_language', 'suggested'],
                ['experience_years', 'suggested', ['need' => null]],
            ]],
            'transport' => ['subject' => 'service', 'arrangements' => ['service'], 'fields' => [
                ['vehicle_type', 'essential'], ['passengers', 'suggested'], ['cargo_type', 'suggested'],
                ['cargo_weight_kg', 'suggested', ['only' => ['cargo_type' => ['parcel', 'furniture', 'vehicle', 'other']]]],
                ['urgency', 'suggested'],
            ]],
            'job' => ['subject' => 'collaboration', 'arrangements' => ['collaboration'], 'fields' => [
                ['employment_type', 'essential'], ['work_mode', 'essential'], ['pay_period', 'essential'],
                ['pay', 'essential', ['unless' => ['pay_period' => ['agreed']]]],
                ['experience_years', 'suggested'], ['skills', 'suggested'], ['insurance', 'suggested'], ['availability', 'suggested'],
            ]],
            'activity' => ['subject' => 'collaboration', 'arrangements' => ['collaboration'], 'fields' => [
                ['participants', 'essential'], ['cost_sharing', 'essential'],
                ['skills', 'suggested'], ['level', 'suggested'], ['time_commitment', 'suggested'],
            ]],
        ];
    }

    /**
     * Recommended term blocks per topic, most useful first. A term can carry the same conditions as a field.
     *
     * @return list<array{0: string, 1?: array<string, mixed>}>
     */
    private static function termRecommendations(string $topic): array
    {
        $online = ['unless' => ['delivery_mode' => ['online'], 'work_mode' => ['remote']]];

        return match ($topic) {
            'home', 'vehicle', 'device' => [['price'], ['place'], ['media']],
            'goods' => [['price'], ['place'], ['quantity'], ['media']],
            'repair' => [['place', $online], ['when'], ['price'], ['media']],
            'lesson' => [['price'], ['when'], ['place', $online]],
            'transport' => [['route'], ['when'], ['price']],
            'job' => [['place', $online], ['when']],
            'activity' => [['when'], ['place'], ['quantity']],
            default => [['price'], ['place'], ['when'], ['media']],
        };
    }

    /**
     * Terms worth suggesting now. Never inserted automatically.
     *
     * @param  array<string, mixed>  $answers
     * @return list<string>
     */
    public static function terms(string $topic, array $answers): array
    {
        $result = [];
        foreach (self::termRecommendations($topic) as $entry) {
            if (self::conditionsHold($entry[1] ?? [], $answers)) {
                $result[] = $entry[0];
            }
        }

        return $result;
    }

    /**
     * Selected detail keys that no longer fit the current context (a rent-only question on a sale).
     * They stay visible with a warning but are not published or searched.
     *
     * @param  array<string, mixed>  $answers
     * @return list<string>
     */
    public static function inapplicable(string $topic, string $intention, string $arrangement, array $answers): array
    {
        $profile = self::all()[$topic] ?? null;
        if ($profile === null) {
            return [];
        }
        $isNeed = in_array($intention, ['need', 'person'], true);
        $applicable = [];
        $constrained = [];
        foreach ($profile['fields'] as $entry) {
            $options = $entry[2] ?? [];
            $constrained[$entry[0]] = ($constrained[$entry[0]] ?? false) || isset($options['arrangement']);
            $tier = $isNeed && array_key_exists('need', $options) ? $options['need'] : $entry[1];
            if ($tier !== null && (! isset($options['arrangement']) || $options['arrangement'] === $arrangement)) {
                $applicable[$entry[0]] = true;
            }
        }

        // Only arrangement-bound fields are ever inapplicable; everything else is merely not recommended.
        return array_keys(array_filter($constrained, fn (bool $bound, string $key): bool => $bound && ! isset($applicable[$key]), ARRAY_FILTER_USE_BOTH));
    }

    /** Topics worth offering for an intention, most likely first; empty means ask nothing extra. */
    public static function topics(string $intention): array
    {
        return match ($intention) {
            'sell', 'rent', 'exchange' => ['home', 'vehicle', 'device', 'goods'],
            'service' => ['repair', 'lesson', 'transport'],
            'person' => ['job', 'repair', 'lesson', 'activity'],
            'activity' => ['activity'],
            default => self::TOPICS,
        };
    }

    /**
     * @param  array<string, mixed>  $answers  canonical key => current answer
     * @return array{essential: list<string>, suggested: list<string>}
     */
    public static function fields(string $topic, string $intention, string $arrangement, array $answers): array
    {
        $result = ['essential' => [], 'suggested' => []];
        $profile = self::all()[$topic] ?? null;
        if ($profile === null) {
            return $result;
        }
        $isNeed = in_array($intention, ['need', 'person'], true);
        foreach ($profile['fields'] as $entry) {
            [$key, $tier] = $entry;
            $options = $entry[2] ?? [];
            if (isset($options['arrangement']) && $options['arrangement'] !== $arrangement) {
                continue;
            }
            if ($isNeed && array_key_exists('need', $options)) {
                $tier = $options['need'];
            }
            if ($tier === null || ! self::conditionsHold($options, $answers)) {
                continue;
            }
            $result[$tier][] = $key;
        }

        return $result;
    }

    /** The first arrangement a profile allows, or the intention's own when the profile allows it. */
    public static function arrangement(string $topic, string $preferred): string
    {
        $allowed = self::all()[$topic]['arrangements'] ?? [];

        return in_array($preferred, $allowed, true) ? $preferred : ($allowed[0] ?? $preferred);
    }

    /** @param array<string, mixed> $options */
    private static function conditionsHold(array $options, array $answers): bool
    {
        foreach ($options['only'] ?? [] as $key => $values) {
            if (! in_array($answers[$key] ?? null, $values, true)) {
                return false;
            }
        }
        foreach ($options['unless'] ?? [] as $key => $values) {
            if (in_array($answers[$key] ?? null, $values, true)) {
                return false;
            }
        }

        return true;
    }
}
