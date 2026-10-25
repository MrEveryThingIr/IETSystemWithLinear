<?php

namespace App\Support\Market;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

/**
 * The shared field tree: domains (code) containing groups that may contain groups. Fields are
 * placed by reference, so one authoritative field can sit in several groups. Approved changes are
 * append-only rows (new group versions, memberships added or removed) layered over the code seed,
 * so nothing a published post relied on is ever rewritten.
 */
final class IntentFieldTaxonomy
{
    /** @return array<string, array<string, list<string>>> domain => group key => field keys */
    public static function seed(): array
    {
        return [
            'general' => ['general_item' => ['brand', 'model', 'color', 'condition', 'size', 'material', 'weight_kg', 'warranty'], 'general_terms' => ['handover', 'includes', 'licensed', 'certification', 'spoken_languages']],
            'property' => ['property_residential' => ['property_type', 'floor_area', 'land_area', 'rooms', 'bathrooms', 'floor', 'building_age', 'orientation'],
                'property_amenities' => ['parking', 'elevator', 'storage', 'balcony', 'furnished', 'heating', 'cooling', 'pets_allowed'], 'property_rental' => ['deposit', 'monthly_rent']],
            'vehicle' => ['vehicle_identity' => ['brand', 'model', 'trim', 'model_year', 'vehicle_class', 'body_style'],
                'vehicle_condition' => ['condition', 'mileage_km', 'insurance_months', 'previous_owners', 'doors', 'color'], 'vehicle_powertrain' => ['fuel_type', 'gearbox']],
            'device' => ['device_identity' => ['brand', 'model', 'color', 'condition', 'warranty'], 'device_hardware' => ['storage_gb', 'ram_gb', 'screen_size_inch', 'sim_count']],
            'service' => ['service_scope' => ['service_area', 'delivery_mode', 'materials_included', 'skills'], 'service_timing' => ['availability', 'urgency', 'experience_years']],
            'lesson' => ['lesson_content' => ['lesson_subject', 'level', 'teaching_language', 'certificate'], 'lesson_format' => ['group_format', 'session_minutes', 'age_group', 'delivery_mode']],
            'transport' => ['transport_vehicle' => ['vehicle_type', 'passengers'], 'transport_cargo' => ['cargo_type', 'cargo_weight_kg']],
            'work' => ['work_role' => ['employment_type', 'work_mode', 'education_level', 'experience_years', 'skills', 'openings', 'start_date'], 'work_pay' => ['pay', 'pay_period', 'insurance', 'benefits']],
            'activity' => ['activity_people' => ['participants', 'age_range', 'interests'], 'activity_terms' => ['cost_sharing', 'time_commitment']],
            'hospitality' => ['hospitality_basics' => ['capacity', 'cuisine', 'meals_included', 'pets_allowed']],
            'events' => ['events_basics' => ['event_type', 'guest_count', 'venue_type', 'catering_needed', 'equipment_provided']],
            'creative' => ['creative_scope' => ['creative_discipline', 'deliverables', 'revisions_included', 'turnaround_days', 'portfolio_link']],
            'agriculture' => ['agriculture_product' => ['crop_type', 'organic', 'harvest_date', 'grade']],
            'industry' => ['industry_specs' => ['power_kw', 'dimensions', 'material'], 'industry_orders' => ['minimum_order', 'lead_time_days']],
        ];
    }

    /**
     * The current tree. Each domain lists its groups (with optional children); fields in no group fall into "rest".
     *
     * @return array<string, array{groups: list<array<string, mixed>>, rest: list<string>}>
     */
    public static function tree(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $db = self::stored();
        $tree = [];
        $offered = array_keys(IntentDetailCatalog::offered());
        foreach (IntentDetailCatalog::DOMAINS as $domain) {
            $groups = [];
            $placed = [];
            foreach (self::seed()[$domain] ?? [] as $groupKey => $fields) {
                $group = self::build($groupKey, $fields, $db, $locale, $offered);
                foreach ($group['fields'] as $field) {
                    $placed[$field] = true;
                }
                $groups[] = $group;
            }
            foreach ($db['groups'] as $key => $row) {
                if (($row['parent_key'] ?? null) === $domain) {
                    $group = self::build($key, [], $db, $locale, $offered);
                    foreach ($group['fields'] as $field) {
                        $placed[$field] = true;
                    }
                    $groups[] = $group;
                }
            }
            $rest = array_values(array_filter($offered, fn (string $key): bool => (IntentDetailCatalog::all()[$key]['domain'] ?? 'general') === $domain && ! isset($placed[$key])));
            if ($groups !== [] || $rest !== []) {
                $tree[$domain] = ['groups' => $groups, 'rest' => $rest];
            }
        }

        return $tree;
    }

    /**
     * Field keys of one group, including children.
     *
     * @return list<string>
     */
    public static function fieldsOf(string $groupKey): array
    {
        foreach (self::tree() as $domain) {
            $found = self::find($domain['groups'], $groupKey);
            if ($found !== null) {
                return self::flatten($found);
            }
        }

        return [];
    }

    public static function groupLabel(string $groupKey, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $labels = self::stored()['groups'][$groupKey]['labels'] ?? [];
        if (($labels[$locale] ?? '') !== '') {
            return (string) $labels[$locale];
        }
        $path = 'composer.group_names.'.$groupKey;
        if (Lang::has($path, $locale, false)) {
            return (string) __($path, [], $locale);
        }

        return (string) (($labels['en'] ?? '') !== '' ? $labels['en'] : $groupKey);
    }

    /** @return list<string> domain keys and group keys that exist, for validation of suggestions */
    public static function knownGroupKeys(): array
    {
        $keys = [];
        foreach (self::seed() as $groups) {
            array_push($keys, ...array_keys($groups));
        }

        return array_values(array_unique([...$keys, ...array_keys(self::stored()['groups'])]));
    }

    /** @return array{groups: array<string, array<string, mixed>>, added: array<string, list<string>>, removed: array<string, list<string>>} */
    public static function stored(): array
    {
        if (! IntentDetailCatalog::hasTable('intent_field_groups')) {
            return ['groups' => [], 'added' => [], 'removed' => []];
        }

        return Cache::remember('intent-fields:taxonomy:'.IntentDetailCatalog::version(), 3600, function (): array {
            $groups = [];
            foreach (DB::table('intent_field_groups')->orderBy('version')->get() as $row) {
                if ($row->status === 'published' && $row->retired_at === null) {
                    $groups[$row->group_key] = ['parent_key' => $row->parent_key, 'labels' => json_decode($row->labels, true) ?: []];
                } else {
                    unset($groups[$row->group_key]);
                }
            }
            // The latest row for a group and field decides: a later add undoes an earlier removal.
            $state = [];
            foreach (DB::table('intent_field_memberships')->orderBy('id')->get() as $row) {
                $state[$row->group_key][$row->field_key] = $row->removed_at === null;
            }
            $added = [];
            $removed = [];
            foreach ($state as $group => $fields) {
                foreach ($fields as $field => $present) {
                    $present ? $added[$group][] = $field : $removed[$group][] = $field;
                }
            }

            return ['groups' => $groups, 'added' => $added, 'removed' => $removed];
        });
    }

    /**
     * @param  list<string>  $seedFields
     * @param  array<string, mixed>  $db
     * @param  list<string>  $offered
     * @return array<string, mixed>
     */
    private static function build(string $groupKey, array $seedFields, array $db, string $locale, array $offered): array
    {
        $fields = array_values(array_diff([...$seedFields, ...($db['added'][$groupKey] ?? [])], $db['removed'][$groupKey] ?? []));
        $fields = array_values(array_unique(array_filter($fields, fn (string $key): bool => in_array($key, $offered, true))));
        $children = [];
        foreach ($db['groups'] as $key => $row) {
            if (($row['parent_key'] ?? null) === $groupKey) {
                $children[] = self::build($key, [], $db, $locale, $offered);
            }
        }

        return ['key' => $groupKey, 'label' => self::groupLabel($groupKey, $locale), 'fields' => $fields, 'children' => $children];
    }

    /** @param list<array<string, mixed>> $groups */
    private static function find(array $groups, string $key): ?array
    {
        foreach ($groups as $group) {
            if ($group['key'] === $key) {
                return $group;
            }
            $inner = self::find($group['children'], $key);
            if ($inner !== null) {
                return $inner;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $group @return list<string> */
    private static function flatten(array $group): array
    {
        $keys = $group['fields'];
        foreach ($group['children'] as $child) {
            array_push($keys, ...self::flatten($child));
        }

        return array_values(array_unique($keys));
    }
}
