<?php

namespace App\Support;

use App\Models\SpaceContentAnnotationAnchor;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SpaceContentAnnotationAnchors
{
    /**
     * @param list<array<string, mixed>> $anchors
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

            if ($type === SpaceContentAnnotationAnchor::TARGET_REVISION) {
                $targetUuid = $revision->uuid;
                $fieldKey = null;
                $selector = ['label' => (string) ($selector['label'] ?? 'Entire edition')];
            } elseif ($type === SpaceContentAnnotationAnchor::TARGET_FIELD || $type === SpaceContentAnnotationAnchor::TARGET_TEXT) {
                abort_unless(is_string($fieldKey) && $fields->has($fieldKey), 422, 'The selected Content field is not part of this edition.');
                /** @var array<string, mixed> $field */
                $field = $fields->get($fieldKey);
                $label = is_string($field['label'] ?? null) ? (string) $field['label'] : $fieldKey;
                $selector['label'] = $label;
                $targetUuid = null;

                if ($type === SpaceContentAnnotationAnchor::TARGET_TEXT) {
                    $exact = trim((string) ($selector['exact'] ?? ''));
                    abort_unless($exact !== '' && mb_strlen($exact) <= 2000, 422, 'Select a specific piece of text up to 2000 characters.');
                    $value = $revision->payload[$fieldKey] ?? null;
                    abort_unless(is_string($value) && str_contains($value, $exact), 422, 'The selected text no longer matches this edition.');
                    $selector = $this->quoteSelector($label, $exact, $selector);
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
                $selector = [
                    'label' => (string) ($placement->caption ?: $placement->original_filename),
                    'asset_uuid' => (string) $placement->asset_uuid,
                ];
            } elseif ($type === SpaceContentAnnotationAnchor::TARGET_RELATIONSHIP) {
                abort_unless(is_string($targetUuid), 422, 'Choose an outline relationship to annotate.');
                $relationship = DB::table('space_content_revision_relationships')
                    ->where('parent_revision_id', $revision->id)
                    ->where('uuid', $targetUuid)
                    ->first(['uuid', 'relation_type']);
                abort_unless(is_object($relationship), 422, 'The selected outline relationship is not part of this edition.');
                $fieldKey = null;
                $selector = ['label' => (string) ($selector['label'] ?? $relationship->relation_type)];
            } elseif ($type === SpaceContentAnnotationAnchor::TARGET_BLOCK) {
                abort_unless(Schema::hasTable('space_content_blocks') && is_string($targetUuid), 422, 'This edition does not contain addressable blocks yet.');
                $block = DB::table('space_content_blocks')
                    ->where('space_content_revision_id', $revision->id)
                    ->where('uuid', $targetUuid)
                    ->first(['uuid', 'type', 'data']);
                abort_unless(is_object($block), 422, 'The selected block is not part of this edition.');
                $fieldKey = null;
                $label = (string) ($selector['label'] ?? $block->type);
                $exact = trim((string) ($selector['exact'] ?? ''));
                if ($exact !== '') {
                    abort_unless(mb_strlen($exact) <= 2000, 422, 'Select a specific piece of text up to 2000 characters.');
                    $data = json_decode((string) $block->data, true);
                    $searchable = $this->blockText(is_array($data) ? $data : []);
                    abort_unless($searchable !== '' && str_contains($searchable, $exact), 422, 'The selected block text no longer matches this edition.');
                    $selector = $this->quoteSelector($label, $exact, $selector);
                } else {
                    $selector = ['label' => $label];
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

    /** @param array<string, mixed> $selector @return array<string, string> */
    private function quoteSelector(string $label, string $exact, array $selector): array
    {
        return [
            'label' => $label,
            'exact' => $exact,
            'prefix' => mb_substr((string) ($selector['prefix'] ?? ''), -120),
            'suffix' => mb_substr((string) ($selector['suffix'] ?? ''), 0, 120),
        ];
    }

    /** @param array<string, mixed> $data */
    private function blockText(array $data): string
    {
        $parts = [];
        foreach (['text', 'attribution', 'caption', 'label'] as $key) {
            if (is_string($data[$key] ?? null)) {
                $parts[] = $data[$key];
            }
        }
        if (is_array($data['items'] ?? null)) {
            foreach ($data['items'] as $item) {
                if (is_string($item)) {
                    $parts[] = $item;
                }
            }
        }

        return implode("\n", $parts);
    }
}
