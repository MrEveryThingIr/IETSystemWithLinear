<?php

namespace App\Support;

use App\Models\SpaceContentAnnotationAnchor;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpaceContentAnnotationAnchors
{
    /** @var list<string> */
    private const INTENTS = [
        'remember',
        'note',
        'question',
        'translate',
        'comment',
        'file',
        'voice',
        'advanced',
        'answer',
        'reply',
    ];

    /**
     * @param  list<mixed>  $anchors
     * @return list<array{target_type: string, target_uuid: ?string, field_key: ?string, selector: ?array<string, mixed>}>
     */
    public function normalize(SpaceContentRevision $revision, array $anchors): array
    {
        if ($anchors === []) {
            return [[
                'target_type' => SpaceContentAnnotationAnchor::TARGET_REVISION,
                'target_uuid' => $revision->uuid,
                'field_key' => null,
                'selector' => ['label' => 'Entire edition'],
            ]];
        }

        abort_if(count($anchors) > 20, 422, 'An annotation may target at most 20 pieces of Content.');

        $definitionVersion = SpaceContentDefinitionVersion::query()->findOrFail($revision->definition_version_id);
        $fields = collect($definitionVersion->schema['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['key'] ?? null))
            ->keyBy(fn (array $field): string => (string) $field['key']);

        $normalized = [];
        foreach ($anchors as $anchor) {
            abort_unless(is_array($anchor), 422, 'Annotation anchors must be structured values.');
            $type = (string) ($anchor['target_type'] ?? '');
            abort_unless(in_array($type, SpaceContentAnnotationAnchor::TARGET_TYPES, true), 422, 'Choose a supported annotation target.');

            $targetUuid = is_string($anchor['target_uuid'] ?? null) ? trim((string) $anchor['target_uuid']) : null;
            $targetUuid = $targetUuid === '' ? null : $targetUuid;
            $fieldKey = is_string($anchor['field_key'] ?? null) ? trim((string) $anchor['field_key']) : null;
            $fieldKey = $fieldKey === '' ? null : $fieldKey;
            $selector = is_array($anchor['selector'] ?? null) ? $anchor['selector'] : [];
            $intent = $this->intent($selector);

            if ($type === SpaceContentAnnotationAnchor::TARGET_REVISION) {
                $targetUuid = $revision->uuid;
                $fieldKey = null;
                $selector = $this->withIntent([
                    'label' => (string) ($selector['label'] ?? 'Entire edition'),
                ], $intent);
            } elseif ($type === SpaceContentAnnotationAnchor::TARGET_FIELD || $type === SpaceContentAnnotationAnchor::TARGET_TEXT) {
                abort_unless(is_string($fieldKey) && $fields->has($fieldKey), 422, 'The selected Content field is not part of this edition.');
                /** @var array<string, mixed> $field */
                $field = $fields->get($fieldKey);
                $label = is_string($field['label'] ?? null) ? (string) $field['label'] : $fieldKey;
                $targetUuid = null;

                if ($type === SpaceContentAnnotationAnchor::TARGET_TEXT) {
                    $searchable = $this->fieldText($revision, $fieldKey, $field);
                    $selector = $this->rangeSelector($label, $searchable, $selector, $intent);
                } else {
                    $selector = $this->withIntent(['label' => $label], $intent);
                }
            } elseif ($type === SpaceContentAnnotationAnchor::TARGET_ASSET) {
                abort_unless(is_string($targetUuid), 422, 'Choose a media placement to annotate.');
                $placement = DB::table('space_content_revision_assets as placement')
                    ->join('assets as asset', 'asset.id', '=', 'placement.asset_id')
                    ->where('placement.space_content_revision_id', $revision->id)
                    ->where('placement.uuid', $targetUuid)
                    ->first(['placement.uuid', 'placement.caption', 'asset.uuid as asset_uuid', 'asset.original_filename']);
                abort_unless(is_object($placement), 422, 'The selected media is not part of this edition.');
                $fieldKey = null;
                $selector = $this->withIntent([
                    'label' => (string) ($placement->caption ?: $placement->original_filename),
                    'asset_uuid' => (string) $placement->asset_uuid,
                ], $intent);
            } elseif ($type === SpaceContentAnnotationAnchor::TARGET_RELATIONSHIP) {
                abort_unless(is_string($targetUuid), 422, 'Choose an outline relationship to annotate.');
                $relationship = DB::table('space_content_revision_relationships')
                    ->where('parent_revision_id', $revision->id)
                    ->where('uuid', $targetUuid)
                    ->first(['uuid', 'relation_type']);
                abort_unless(is_object($relationship), 422, 'The selected outline relationship is not part of this edition.');
                $fieldKey = null;
                $selector = $this->withIntent([
                    'label' => (string) ($selector['label'] ?? $relationship->relation_type),
                ], $intent);
            } elseif ($type === SpaceContentAnnotationAnchor::TARGET_BLOCK) {
                abort_unless(Schema::hasTable('space_content_blocks') && is_string($targetUuid), 422, 'This edition does not contain addressable blocks yet.');
                $block = DB::table('space_content_blocks')
                    ->where('space_content_revision_id', $revision->id)
                    ->where('uuid', $targetUuid)
                    ->first(['uuid', 'type', 'data']);
                abort_unless(is_object($block), 422, 'The selected block is not part of this edition.');
                $fieldKey = null;
                $label = (string) ($selector['label'] ?? $block->type);
                $exact = is_string($selector['exact'] ?? null) ? (string) $selector['exact'] : '';
                if (trim($exact) !== '') {
                    $data = json_decode((string) $block->data, true);
                    $searchable = $this->blockText(is_array($data) ? $data : []);
                    $selector = $this->rangeSelector($label, $searchable, $selector, $intent);
                } else {
                    $selector = $this->withIntent(['label' => $label], $intent);
                }
            }

            $normalized[] = [
                'target_type' => $type,
                'target_uuid' => $targetUuid,
                'field_key' => $fieldKey,
                'selector' => $selector === [] ? null : $selector,
            ];
        }

        return collect($normalized)
            ->unique(fn (array $anchor): string => implode('|', [
                $anchor['target_type'],
                $anchor['target_uuid'] ?? '',
                $anchor['field_key'] ?? '',
                json_encode($anchor['selector'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $selector
     * @return array<string, mixed>
     */
    private function rangeSelector(string $label, string $searchable, array $selector, ?string $intent): array
    {
        $exact = is_string($selector['exact'] ?? null) ? (string) $selector['exact'] : '';
        abort_unless(trim($exact) !== '' && mb_strlen($exact) <= 2000, 422, 'Select a specific piece of text up to 2000 characters.');
        abort_unless($searchable !== '', 422, 'The selected text no longer matches this edition.');

        $start = $this->integer($selector['start'] ?? null);
        $end = $this->integer($selector['end'] ?? null);

        if ($start !== null || $end !== null) {
            abort_unless($start !== null && $end !== null && $start >= 0 && $end > $start, 422, 'The selected text range is invalid.');
            abort_unless($end <= mb_strlen($searchable), 422, 'The selected text range is outside this edition.');
            abort_unless(mb_substr($searchable, $start, $end - $start) === $exact, 422, 'The selected text occurrence no longer matches this edition.');
        } else {
            $start = $this->resolveLegacyOccurrence($searchable, $exact, $selector);
            abort_unless($start !== null, 422, 'The selected text no longer matches this edition.');
            $end = $start + mb_strlen($exact);
        }

        $prefix = mb_substr($searchable, max(0, $start - 120), min(120, $start));
        $suffix = mb_substr($searchable, $end, 120);

        return $this->withIntent([
            'label' => $label,
            'exact' => $exact,
            'prefix' => $prefix,
            'suffix' => $suffix,
            'start' => $start,
            'end' => $end,
            'text_hash' => hash('sha256', $searchable),
        ], $intent);
    }

    /** @param array<string, mixed> $selector */
    private function resolveLegacyOccurrence(string $searchable, string $exact, array $selector): ?int
    {
        $wantedPrefix = is_string($selector['prefix'] ?? null) ? (string) $selector['prefix'] : '';
        $wantedSuffix = is_string($selector['suffix'] ?? null) ? (string) $selector['suffix'] : '';
        $offset = 0;
        $fallback = null;

        while (($position = mb_strpos($searchable, $exact, $offset)) !== false) {
            $fallback ??= $position;
            $end = $position + mb_strlen($exact);
            $prefix = mb_substr($searchable, max(0, $position - mb_strlen($wantedPrefix)), mb_strlen($wantedPrefix));
            $suffix = mb_substr($searchable, $end, mb_strlen($wantedSuffix));

            if (($wantedPrefix === '' || $prefix === $wantedPrefix)
                && ($wantedSuffix === '' || $suffix === $wantedSuffix)) {
                return $position;
            }

            $offset = $position + max(1, mb_strlen($exact));
        }

        return $fallback;
    }

    /** @param array<string, mixed> $field */
    private function fieldText(SpaceContentRevision $revision, string $fieldKey, array $field): string
    {
        $value = $revision->payload[$fieldKey] ?? null;
        if ($value === null) {
            return '';
        }

        if (($field['type'] ?? null) === 'select') {
            $option = collect($field['options'] ?? [])->firstWhere('value', $value);
            if (is_array($option) && is_scalar($option['label'] ?? null)) {
                return (string) $option['label'];
            }
        }

        if (($field['type'] ?? null) === 'boolean') {
            return (bool) $value ? '1' : '0';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /** @param array<string, mixed> $data */
    private function blockText(array $data): string
    {
        if (is_array($data['items'] ?? null)) {
            return collect($data['items'])
                ->filter(static fn (mixed $item): bool => is_string($item))
                ->map(static fn (string $item): string => $item)
                ->implode("\n");
        }

        $parts = [];
        foreach (['text', 'attribution', 'caption', 'label'] as $key) {
            if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                $parts[] = $data[$key];
            }
        }

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $selector */
    private function intent(array $selector): ?string
    {
        $intent = $selector['intent'] ?? null;

        return is_string($intent) && in_array($intent, self::INTENTS, true) ? $intent : null;
    }

    /**
     * @param  array<string, mixed>  $selector
     * @return array<string, mixed>
     */
    private function withIntent(array $selector, ?string $intent): array
    {
        if ($intent !== null) {
            $selector['intent'] = $intent;
        }

        return $selector;
    }

    private function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
