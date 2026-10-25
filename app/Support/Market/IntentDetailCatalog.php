<?php

namespace App\Support\Market;

use Illuminate\Support\Facades\Lang;

/**
 * Canonical keys and filter columns are code-owned, never derived from submitted labels.
 * One key means one thing everywhere (brand, condition, experience), so filters and matching
 * work across profiles. Which keys a person is asked about is decided by IntentProfiles.
 */
final class IntentDetailCatalog
{
    /** @return array<string, array{type: string, filter: string, options?: list<string>, unit?: string}> */
    public static function all(): array
    {
        $text = ['type' => 'text', 'filter' => 'value_text'];
        $number = ['type' => 'number', 'filter' => 'value_number'];
        $toggle = ['type' => 'boolean', 'filter' => 'value_boolean'];
        $choice = fn (array $options): array => ['type' => 'choice', 'filter' => 'value_text', 'options' => $options];

        return [
            // Shared
            'brand' => $text,
            'model' => $text,
            'color' => $text,
            'condition' => $choice(['new', 'like_new', 'used', 'refurbished', 'needs_repair']),
            'warranty' => $toggle,
            'experience_years' => $number,
            'skills' => $text,
            'availability' => $text,
            'urgency' => $choice(['today', 'this_week', 'flexible']),
            'delivery_mode' => $choice(['online', 'in_person', 'either']),
            // Property
            'property_type' => $choice(['apartment', 'house_villa', 'land', 'office', 'shop', 'other']),
            'floor_area' => [...$number, 'unit' => 'm²'],
            'rooms' => $number,
            'floor' => $number,
            'building_age' => [...$number, 'unit' => 'year'],
            'parking' => $toggle,
            'elevator' => $toggle,
            'storage' => $toggle,
            'balcony' => $toggle,
            'furnished' => $toggle,
            'deposit' => ['type' => 'amount', 'filter' => 'value_number', 'unit' => 'IRT'],
            'monthly_rent' => ['type' => 'amount', 'filter' => 'value_number', 'unit' => 'IRT'],
            // Vehicles and devices
            'model_year' => $number,
            'mileage_km' => [...$number, 'unit' => 'km'],
            'fuel_type' => $choice(['petrol', 'diesel', 'hybrid', 'electric', 'gas']),
            'gearbox' => $choice(['manual', 'automatic']),
            'storage_gb' => [...$number, 'unit' => 'GB'],
            'ram_gb' => [...$number, 'unit' => 'GB'],
            // Services, lessons, transport
            'service_area' => $text,
            'materials_included' => $toggle,
            'lesson_subject' => $text,
            'level' => $choice(['beginner', 'intermediate', 'advanced', 'all_levels']),
            'group_format' => $choice(['private', 'small_group', 'class']),
            'teaching_language' => $text,
            'session_minutes' => [...$number, 'unit' => 'minute'],
            'vehicle_type' => $choice(['any', 'car', 'van', 'truck', 'motorcycle']),
            'passengers' => $number,
            'cargo_type' => $choice(['parcel', 'furniture', 'vehicle', 'other']),
            'cargo_weight_kg' => [...$number, 'unit' => 'kg'],
            // Work
            'employment_type' => $choice(['full_time', 'part_time', 'contract', 'internship', 'project']),
            'work_mode' => $choice(['on_site', 'remote', 'mixed']),
            'pay_period' => $choice(['hour', 'day', 'month', 'per_project', 'commission', 'agreed']),
            'pay' => ['type' => 'amount', 'filter' => 'value_number', 'unit' => 'IRT'],
            'insurance' => $toggle,
            // Activities
            'participants' => $number,
            'cost_sharing' => $choice(['free', 'shared', 'paid']),
            'time_commitment' => $choice(['one_time', 'weekly', 'flexible']),
        ];
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
