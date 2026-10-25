<?php

namespace App\Support\Market;

use App\Models\IntentFieldDefinition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;

/**
 * The field library: one definition per meaning (brand, condition, experience), shared by every
 * profile. Code owns the built-ins (type, unit and filter column never change under a key); approved
 * shared definitions from the database either add new keys or overlay labels, aliases and options.
 * Canonical money, schedule, place and Deal terms are NOT fields: see IntentTerms.
 *
 * Spec: type, filter (value_text | value_number | value_boolean | value_date | null = not searchable),
 * options, unit, domain, source (research, schema, house, community), since.
 */
final class IntentDetailCatalog
{
    public const DOMAINS = ['general', 'property', 'vehicle', 'device', 'service', 'lesson', 'transport', 'work', 'activity',
        'hospitality', 'events', 'creative', 'agriculture', 'industry'];

    /** @return array<string, array<string, mixed>> */
    public static function codeDefinitions(): array
    {
        $text = ['type' => 'text', 'filter' => 'value_text'];
        $number = ['type' => 'number', 'filter' => 'value_number'];
        $toggle = ['type' => 'boolean', 'filter' => 'value_boolean'];
        $date = ['type' => 'date', 'filter' => 'value_date'];
        $choice = fn (array $options): array => ['type' => 'choice', 'filter' => 'value_text', 'options' => $options];
        $d = fn (string $domain, array $spec, string $source = 'house'): array => [...$spec, 'domain' => $domain, 'source' => $source, 'since' => 1];

        return [
            // General
            'brand' => $d('general', $text, 'research'),
            'model' => $d('general', $text, 'research'),
            'color' => $d('general', $text, 'research'),
            'condition' => $d('general', $choice(['new', 'like_new', 'used', 'refurbished', 'needs_repair']), 'research'),
            'warranty' => $d('general', $toggle, 'research'),
            'size' => $d('general', $text),
            'weight_kg' => $d('general', [...$number, 'unit' => 'kg']),
            'material' => $d('general', $text),
            'handover' => $d('general', $choice(['collect', 'delivery', 'either'])),
            'includes' => $d('general', ['type' => 'long_text', 'filter' => null]),
            'spoken_languages' => $d('general', $text),
            'licensed' => $d('general', $toggle),
            'certification' => $d('general', $text),
            // Property
            'property_type' => $d('property', $choice(['apartment', 'house_villa', 'land', 'office', 'shop', 'other']), 'schema'),
            'floor_area' => $d('property', [...$number, 'unit' => 'm²'], 'schema'),
            'land_area' => $d('property', [...$number, 'unit' => 'm²']),
            'rooms' => $d('property', $number, 'schema'),
            'bathrooms' => $d('property', $number, 'schema'),
            'floor' => $d('property', $number, 'schema'),
            'building_age' => $d('property', [...$number, 'unit' => 'year'], 'research'),
            'orientation' => $d('property', $choice(['north', 'south', 'east', 'west']), 'research'),
            'parking' => $d('property', $toggle, 'research'),
            'elevator' => $d('property', $toggle, 'research'),
            'storage' => $d('property', $toggle, 'research'),
            'balcony' => $d('property', $toggle, 'research'),
            'furnished' => $d('property', $toggle),
            'heating' => $d('property', $text, 'research'),
            'cooling' => $d('property', $text, 'research'),
            'pets_allowed' => $d('property', $toggle, 'schema'),
            'deposit' => $d('property', ['type' => 'amount', 'filter' => 'value_number', 'unit' => 'IRT'], 'research'),
            'monthly_rent' => $d('property', ['type' => 'amount', 'filter' => 'value_number', 'unit' => 'IRT']),
            // Vehicles
            'model_year' => $d('vehicle', $number, 'research'),
            'mileage_km' => $d('vehicle', [...$number, 'unit' => 'km'], 'research'),
            'fuel_type' => $d('vehicle', $choice(['petrol', 'diesel', 'hybrid', 'electric', 'gas']), 'research'),
            'gearbox' => $d('vehicle', $choice(['manual', 'automatic']), 'research'),
            'body_type' => $d('vehicle', $choice(['sedan', 'hatchback', 'suv', 'pickup_truck', 'van', 'truck', 'motorcycle', 'other']), 'schema'),
            'doors' => $d('vehicle', $number, 'schema'),
            'previous_owners' => $d('vehicle', $number, 'schema'),
            'insurance_months' => $d('vehicle', [...$number, 'unit' => 'month'], 'research'),
            // Devices
            'storage_gb' => $d('device', [...$number, 'unit' => 'GB'], 'research'),
            'ram_gb' => $d('device', [...$number, 'unit' => 'GB'], 'research'),
            'screen_size_inch' => $d('device', [...$number, 'unit' => 'inch'], 'research'),
            'sim_count' => $d('device', $number, 'research'),
            // Services
            'service_area' => $d('service', $text),
            'experience_years' => $d('service', [...$number, 'unit' => 'year'], 'research'),
            'availability' => $d('service', $text),
            'delivery_mode' => $d('service', $choice(['online', 'in_person', 'either'])),
            'urgency' => $d('service', $choice(['today', 'this_week', 'flexible'])),
            'materials_included' => $d('service', $toggle),
            'skills' => $d('service', $text, 'schema'),
            // Lessons
            'lesson_subject' => $d('lesson', $text),
            'level' => $d('lesson', $choice(['beginner', 'intermediate', 'advanced', 'all_levels'])),
            'group_format' => $d('lesson', $choice(['private', 'small_group', 'class'])),
            'teaching_language' => $d('lesson', $text, 'schema'),
            'session_minutes' => $d('lesson', [...$number, 'unit' => 'minute']),
            'certificate' => $d('lesson', $toggle),
            'age_group' => $d('lesson', $text),
            // Transport
            'vehicle_type' => $d('transport', $choice(['any', 'car', 'van', 'truck', 'motorcycle'])),
            'passengers' => $d('transport', $number),
            'cargo_type' => $d('transport', $choice(['parcel', 'furniture', 'vehicle', 'other'])),
            'cargo_weight_kg' => $d('transport', [...$number, 'unit' => 'kg']),
            // Work
            'employment_type' => $d('work', $choice(['full_time', 'part_time', 'contract', 'internship', 'project']), 'schema'),
            'work_mode' => $d('work', $choice(['on_site', 'remote', 'mixed']), 'schema'),
            'pay_period' => $d('work', $choice(['hour', 'day', 'month', 'per_project', 'commission', 'agreed']), 'research'),
            'pay' => $d('work', ['type' => 'amount', 'filter' => 'value_number', 'unit' => 'IRT']),
            'insurance' => $d('work', $toggle, 'research'),
            'education_level' => $d('work', $choice(['none', 'diploma', 'bachelor', 'master', 'doctorate']), 'schema'),
            'start_date' => $d('work', $date, 'schema'),
            'openings' => $d('work', $number, 'schema'),
            'benefits' => $d('work', ['type' => 'long_text', 'filter' => null], 'schema'),
            // Activities and collaboration
            'participants' => $d('activity', $number),
            'cost_sharing' => $d('activity', $choice(['free', 'shared', 'paid'])),
            'time_commitment' => $d('activity', $choice(['one_time', 'weekly', 'flexible'])),
            'interests' => $d('activity', $text),
            'age_range' => $d('activity', $text),
            // Hospitality
            'capacity' => $d('hospitality', $number),
            'cuisine' => $d('hospitality', $text),
            'meals_included' => $d('hospitality', $toggle),
            // Events
            'event_type' => $d('events', $choice(['wedding', 'birthday', 'conference', 'workshop', 'concert', 'party', 'other'])),
            'guest_count' => $d('events', $number),
            'venue_type' => $d('events', $choice(['indoor', 'outdoor', 'either'])),
            'catering_needed' => $d('events', $toggle),
            'equipment_provided' => $d('events', $toggle),
            // Creative work
            'creative_discipline' => $d('creative', $choice(['design', 'photo', 'video', 'music', 'writing', 'illustration', 'animation', 'other'])),
            'deliverables' => $d('creative', ['type' => 'long_text', 'filter' => null]),
            'revisions_included' => $d('creative', $number),
            'turnaround_days' => $d('creative', [...$number, 'unit' => 'day']),
            'portfolio_link' => $d('creative', ['type' => 'link', 'filter' => null]),
            // Agriculture
            'crop_type' => $d('agriculture', $text),
            'organic' => $d('agriculture', $toggle),
            'harvest_date' => $d('agriculture', $date),
            'grade' => $d('agriculture', $text),
            // Industry
            'power_kw' => $d('industry', [...$number, 'unit' => 'kW']),
            'minimum_order' => $d('industry', $number),
            'lead_time_days' => $d('industry', [...$number, 'unit' => 'day']),
            'dimensions' => $d('industry', $text),
        ];
    }

    /**
     * Built-ins plus approved shared definitions. A shared definition may add a key, or extend a
     * built-in choice with more options; it never changes a built-in's type, unit or filter.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $all = self::codeDefinitions();
        foreach (self::published() as $key => $definition) {
            if (isset($all[$key])) {
                if (($all[$key]['type'] ?? '') === 'choice' && $definition['options'] !== []) {
                    $all[$key]['options'] = array_values(array_unique([...$all[$key]['options'], ...$definition['options']]));
                }

                continue;
            }
            $spec = ['type' => $definition['type'], 'filter' => $definition['filter'], 'domain' => $definition['domains'][0] ?? 'general',
                'source' => 'community', 'since' => $definition['version'], 'retired' => $definition['retired']];
            if ($definition['unit'] !== null) {
                $spec['unit'] = $definition['unit'];
            }
            if ($definition['options'] !== []) {
                $spec['options'] = $definition['options'];
            }
            $all[$key] = $spec;
        }

        return $all;
    }

    /** @return array<string, array<string, mixed>> */
    public static function published(): array
    {
        if (! Schema::hasTable('intent_field_definitions')) {
            return [];
        }

        return Cache::remember('intent-fields:published:'.self::version(), 3600, function (): array {
            $rows = [];
            foreach (IntentFieldDefinition::query()->latestVersion()->get() as $row) {
                $rows[$row->field_key] = ['type' => $row->type, 'filter' => $row->filter, 'unit' => $row->unit, 'options' => $row->options ?? [],
                    'domains' => $row->domains ?? [], 'labels' => $row->labels ?? [], 'descriptions' => $row->descriptions ?? [],
                    'aliases' => $row->aliases ?? [], 'version' => $row->version, 'retired' => $row->status === 'retired'];
            }

            return $rows;
        });
    }

    public static function version(): int
    {
        return (int) Cache::get('intent-fields:version', 1);
    }

    public static function bumpVersion(): void
    {
        Cache::forever('intent-fields:version', self::version() + 1);
    }

    /** Fields offered to people now: everything except retired definitions. */
    public static function offered(): array
    {
        return array_filter(self::all(), fn (array $spec): bool => empty($spec['retired']));
    }

    /** Keys a viewer can filter the Directory by (dates and unsearchable fields are excluded). */
    public static function searchable(): array
    {
        return array_filter(self::offered(), fn (array $spec): bool => in_array($spec['filter'] ?? null, ['value_text', 'value_number', 'value_boolean'], true));
    }

    public static function label(string $key, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $shared = self::published()[$key]['labels'] ?? [];
        if (($shared[$locale] ?? '') !== '') {
            return (string) $shared[$locale];
        }
        $path = 'composer.details.'.$key;
        if (Lang::has($path, $locale, false)) {
            return (string) __($path, [], $locale);
        }

        return (string) (($shared['en'] ?? '') !== '' ? $shared['en'] : (Lang::has($path, 'en', false) ? __($path, [], 'en') : $key));
    }

    public static function description(string $key, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $shared = self::published()[$key]['descriptions'] ?? [];
        if (($shared[$locale] ?? '') !== '') {
            return (string) $shared[$locale];
        }
        $path = 'composer.help.'.$key;

        return Lang::has($path, $locale, false) ? (string) __($path, [], $locale) : null;
    }

    /** @return list<string> */
    public static function aliases(string $key, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $path = 'composer.aliases.'.$key;
        $builtin = Lang::has($path, $locale, false) ? explode(',', (string) __($path, [], $locale)) : [];
        $shared = self::published()[$key]['aliases'][$locale] ?? [];

        return array_values(array_filter(array_map('trim', [...$builtin, ...$shared])));
    }

    /** A person-readable label for a stored choice value; free-text and custom options pass through. */
    public static function optionLabel(string $option): string
    {
        return Lang::has('composer.options.'.$option) ? (string) __('composer.options.'.$option) : $option;
    }

    public static function unitLabel(?string $unit): ?string
    {
        if ($unit === null || $unit === '') {
            return null;
        }

        return Lang::has('composer.units.'.$unit) ? (string) __('composer.units.'.$unit) : $unit;
    }
}
