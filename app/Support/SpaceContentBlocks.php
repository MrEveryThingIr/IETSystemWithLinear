<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\SpaceContentBlock;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpaceContentBlocks
{
    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array{logical_uuid: string, type: string, data: array<string, mixed>, style: array<string, mixed>}>
     */
    public function normalize(SpaceContentRevision $revision, array $blocks): array
    {
        abort_if(count($blocks) > 300, 422, 'A block document may contain at most 300 blocks.');

        $definition = SpaceContentDefinitionVersion::query()->findOrFail($revision->definition_version_id);
        $fields = collect($definition->schema['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['key'] ?? null))
            ->keyBy(fn (array $field): string => (string) $field['key']);

        $placements = DB::table('space_content_revision_assets as placement')
            ->join('assets as asset', 'asset.id', '=', 'placement.asset_id')
            ->where('placement.space_content_revision_id', $revision->id)
            ->get([
                'placement.uuid',
                'asset.mime_type',
                'asset.uuid as asset_uuid',
                'asset.original_filename',
            ])
            ->keyBy('uuid');

        $normalized = [];
        $logicalUuids = [];

        foreach ($blocks as $block) {
            abort_unless(is_array($block), 422, 'Every block must be structured data.');
            $type = (string) ($block['type'] ?? '');
            abort_unless(in_array($type, SpaceContentBlock::TYPES, true), 422, 'Choose a supported block type.');

            $logicalUuid = is_string($block['logical_uuid'] ?? null) && Str::isUuid($block['logical_uuid'])
                ? (string) $block['logical_uuid']
                : (string) Str::uuid();
            abort_if(isset($logicalUuids[$logicalUuid]), 422, 'Block logical identifiers must be unique within an edition.');
            $logicalUuids[$logicalUuid] = true;

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $style = $this->normalizeStyle(is_array($block['style'] ?? null) ? $block['style'] : []);

            if ($type === SpaceContentBlock::TYPE_PARAGRAPH) {
                $data = ['text' => $this->text($data['text'] ?? '', 20000, true)];
            } elseif ($type === SpaceContentBlock::TYPE_HEADING) {
                $level = (int) ($data['level'] ?? 2);
                abort_unless(in_array($level, [2, 3, 4], true), 422, 'Heading level must be 2, 3, or 4.');
                $data = ['text' => $this->text($data['text'] ?? '', 500, true), 'level' => $level];
            } elseif ($type === SpaceContentBlock::TYPE_QUOTE) {
                $data = [
                    'text' => $this->text($data['text'] ?? '', 10000, true),
                    'attribution' => $this->text($data['attribution'] ?? '', 500, false),
                ];
            } elseif ($type === SpaceContentBlock::TYPE_LIST) {
                $items = is_array($data['items'] ?? null) ? $data['items'] : [];
                abort_if(count($items) > 100, 422, 'A list block may contain at most 100 items.');
                $items = collect($items)
                    ->map(fn (mixed $item): string => $this->text($item, 2000, true))
                    ->values()
                    ->all();
                abort_if($items === [], 422, 'A list block needs at least one item.');
                $data = [
                    'items' => $items,
                    'ordered' => (bool) ($data['ordered'] ?? false),
                ];
            } elseif ($type === SpaceContentBlock::TYPE_CALLOUT) {
                $tone = (string) ($data['tone'] ?? 'info');
                abort_unless(in_array($tone, ['info', 'success', 'warning', 'danger'], true), 422, 'Choose a supported callout tone.');
                $data = ['text' => $this->text($data['text'] ?? '', 10000, true), 'tone' => $tone];
            } elseif ($type === SpaceContentBlock::TYPE_DIVIDER) {
                $data = [];
            } elseif ($type === SpaceContentBlock::TYPE_FIELD) {
                $fieldKey = trim((string) ($data['field_key'] ?? ''));
                abort_unless($fieldKey !== '' && $fields->has($fieldKey), 422, 'The selected field block is not part of this Content definition.');
                $field = $fields->get($fieldKey);
                $data = [
                    'field_key' => $fieldKey,
                    'label' => is_array($field) && is_string($field['label'] ?? null) ? $field['label'] : $fieldKey,
                ];
            } else {
                $placementUuid = trim((string) ($data['asset_placement_uuid'] ?? ''));
                $placement = $placements->get($placementUuid);
                abort_unless(is_object($placement), 422, 'The selected media block is not part of this edition.');
                $kind = $this->mediaKind((string) $placement->mime_type);
                if ($type !== SpaceContentBlock::TYPE_FILE) {
                    abort_unless($type === $kind, 422, 'The selected media does not match this block type.');
                }
                $data = [
                    'asset_placement_uuid' => $placementUuid,
                    'asset_uuid' => (string) $placement->asset_uuid,
                    'filename' => (string) $placement->original_filename,
                    'caption' => $this->text($data['caption'] ?? '', 1000, false),
                ];
            }

            $normalized[] = [
                'logical_uuid' => $logicalUuid,
                'type' => $type,
                'data' => $data,
                'style' => $style,
            ];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $style @return array<string, mixed> */
    private function normalizeStyle(array $style): array
    {
        $normalized = [];
        foreach (['text_color', 'background_color', 'accent_color'] as $key) {
            $value = $style[$key] ?? null;
            if (is_string($value)) {
                $value = strtolower(trim($value));
                if (preg_match('/^#[0-9a-f]{6}$/', $value) === 1) {
                    $normalized[$key] = $value;
                }
            }
        }

        $alignment = $style['alignment'] ?? null;
        if (is_string($alignment) && in_array($alignment, ['start', 'center', 'end'], true)) {
            $normalized['alignment'] = $alignment;
        }
        $emphasis = $style['emphasis'] ?? null;
        if (is_string($emphasis) && in_array($emphasis, ['normal', 'muted', 'strong', 'callout'], true)) {
            $normalized['emphasis'] = $emphasis;
        }

        return $normalized;
    }

    private function text(mixed $value, int $max, bool $required): string
    {
        $value = trim(is_scalar($value) ? (string) $value : '');
        abort_if($required && $value === '', 422, 'This block needs content.');
        abort_if(mb_strlen($value) > $max, 422, 'Block content is too long.');

        return $value;
    }

    private function mediaKind(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return SpaceContentBlock::TYPE_IMAGE;
        }
        if (str_starts_with($mime, 'audio/')) {
            return SpaceContentBlock::TYPE_AUDIO;
        }
        if (str_starts_with($mime, 'video/')) {
            return SpaceContentBlock::TYPE_VIDEO;
        }

        return SpaceContentBlock::TYPE_FILE;
    }
}
