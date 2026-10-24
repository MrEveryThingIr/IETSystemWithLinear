<?php

namespace App\Support\Market;

use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\DB;

/** Shared snapshot/backfill shape; unknown legacy metadata stays untouched on its source record. */
final class IntentSnapshot
{
    public const CORE = ['title', 'description', 'importance_percent', 'subject_kind', 'arrangement_kind',
        'exchange_preference', 'cash_min', 'cash_max', 'currency_code', 'cash_basis', 'exchange_notes',
        'quantity', 'unit', 'location_text', 'origin_text', 'destination_text', 'round_trip', 'return_after_days',
        'schedule_kind', 'starts_on', 'ends_on', 'timezone', 'recurrence_interval', 'recurrence_weekdays',
        'recurrence_day_of_month', 'time_window_start', 'time_window_end', 'visibility', 'negotiable'];

    /** @return array<string, mixed> */
    public static function fromRecord(object $record): array
    {
        $values = get_object_vars($record);
        $core = array_intersect_key($values, array_flip(self::CORE));
        foreach (['recurrence_weekdays'] as $field) {
            if (is_string($core[$field] ?? null)) {
                $core[$field] = json_decode($core[$field], true, flags: JSON_THROW_ON_ERROR);
            }
        }
        $details = DB::table('intent_details')->where('actor_profile_intent_id', $record->id)->orderBy('position')->orderBy('id')->get()
            ->map(function ($row): array {
                $data = (array) $row;
                $data['payload'] = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
                unset($data['id'], $data['uuid'], $data['actor_profile_intent_id'], $data['created_at'], $data['updated_at']);

                return $data;
            })->all();
        $media = DB::table('actor_profile_intent_media')->where('actor_profile_intent_id', $record->id)
            ->orderBy('position')->orderBy('id')->get(['uuid', 'asset_id', 'position', 'caption', 'visibility'])->map(fn ($row): array => (array) $row)->all();

        return ['core' => $core, 'details' => $details, 'media' => $media, 'kind' => $record->kind,
            'concept_id' => $record->concept_id,
            'publisher' => array_intersect_key($values, array_flip(['publisher_actor_id', 'publisher_business_id', 'publisher_group_id'])),
            'sources' => array_intersect_key($values, array_flip(['capability_id', 'business_listing_id', 'business_listing_version_id']))];
    }

    /** @param array<string, mixed> $snapshot */
    public static function searchText(array $snapshot): string
    {
        $parts = [(string) data_get($snapshot, 'core.title', ''), (string) data_get($snapshot, 'core.description', ''),
            (string) data_get($snapshot, 'core.location_text', '')];
        foreach ($snapshot['details'] ?? [] as $detail) {
            if (($detail['visibility'] ?? 'same') === 'private' || in_array($detail['type'], ['reference', 'file', 'media'], true)) {
                continue;
            }
            $parts[] = $detail['label'];
            $parts[] = SpaceContentSchema::canonicalJson($detail['payload']['value'] ?? null);
        }

        return implode(' ', $parts);
    }
}
