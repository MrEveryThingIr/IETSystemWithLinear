<?php

namespace App\Support;

use App\ConceptAssertionPredicate;
use App\ContextKind;
use App\Models\SpaceContentBlock;
use Illuminate\Support\Str;
use LogicException;

class ContentBlueprintConfig
{
    /**
     * @param  array<string, mixed>  $definitionSchema
     * @param  list<mixed>  $initialBlocks
     * @param  array<string, mixed>  $presentation
     * @param  list<mixed>  $contextKinds
     * @param  list<mixed>  $conceptDefaults
     * @param  array<string, mixed>  $interactionDefaults
     * @param  array<string, mixed>  $authoring
     * @return array{
     *   definition_schema: array<string, mixed>,
     *   initial_blocks: list<array<string, mixed>>,
     *   render_template_key: string,
     *   presentation: array<string, mixed>,
     *   context_kinds: list<string>,
     *   concept_defaults: list<array<string, mixed>>,
     *   interaction_defaults: array<string, mixed>,
     *   authoring: array<string, mixed>,
     *   content_hash: string
     * }
     */
    public function normalize(
        array $definitionSchema,
        array $initialBlocks,
        string $renderTemplateKey,
        array $presentation,
        array $contextKinds,
        array $conceptDefaults,
        array $interactionDefaults,
        array $authoring,
    ): array {
        $schema = SpaceContentSchema::normalizeDefinitionFields($definitionSchema['fields'] ?? []);
        $fieldKeys = collect($schema['fields'])
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key))
            ->values()
            ->all();

        $renderTemplateKey = trim($renderTemplateKey);
        $resolvedPresentation = app(SpaceContentPresentation::class)->resolve(
            $renderTemplateKey,
            $presentation,
            $fieldKeys,
        );

        $normalized = [
            'definition_schema' => $schema,
            'initial_blocks' => $this->blocks($initialBlocks, $schema),
            'render_template_key' => $renderTemplateKey,
            'presentation' => $resolvedPresentation,
            'context_kinds' => $this->contextKinds($contextKinds),
            'concept_defaults' => $this->conceptDefaults($conceptDefaults),
            'interaction_defaults' => $this->interactionDefaults($interactionDefaults),
            'authoring' => $this->authoring($authoring),
        ];

        $normalized['content_hash'] = SpaceContentSchema::hashArray($normalized);

        return $normalized;
    }

    /**
     * @param  list<mixed>  $blocks
     * @param  array<string, mixed>  $schema
     * @return list<array<string, mixed>>
     */
    private function blocks(array $blocks, array $schema): array
    {
        if (count($blocks) > 100) {
            throw new LogicException('A Content Blueprint may define at most 100 initial blocks.');
        }

        $fieldKeys = collect($schema['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['key'] ?? null))
            ->pluck('key')
            ->flip();

        $allowed = [
            SpaceContentBlock::TYPE_PARAGRAPH,
            SpaceContentBlock::TYPE_HEADING,
            SpaceContentBlock::TYPE_QUOTE,
            SpaceContentBlock::TYPE_LIST,
            SpaceContentBlock::TYPE_CALLOUT,
            SpaceContentBlock::TYPE_DIVIDER,
            SpaceContentBlock::TYPE_FIELD,
        ];

        $normalized = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                throw new LogicException('Every Content Blueprint block must be structured data.');
            }

            $type = (string) ($block['type'] ?? '');
            if (! in_array($type, $allowed, true)) {
                throw new LogicException('Blueprint initial blocks may use text/layout/field blocks only; media requires a real Asset.');
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if ($type === SpaceContentBlock::TYPE_FIELD) {
                $fieldKey = trim((string) ($data['field_key'] ?? ''));
                if ($fieldKey === '' || ! $fieldKeys->has($fieldKey)) {
                    throw new LogicException('Blueprint field blocks must reference a defined field.');
                }
                $data = ['field_key' => $fieldKey];
            } elseif ($type === SpaceContentBlock::TYPE_PARAGRAPH) {
                $data = ['text' => $this->text($data['text'] ?? '', 20000, true)];
            } elseif ($type === SpaceContentBlock::TYPE_HEADING) {
                $level = (int) ($data['level'] ?? 2);
                if (! in_array($level, [2, 3, 4], true)) {
                    throw new LogicException('Blueprint heading level must be 2, 3, or 4.');
                }
                $data = [
                    'text' => $this->text($data['text'] ?? '', 500, true),
                    'level' => $level,
                ];
            } elseif ($type === SpaceContentBlock::TYPE_QUOTE) {
                $data = [
                    'text' => $this->text($data['text'] ?? '', 10000, true),
                    'attribution' => $this->text($data['attribution'] ?? '', 500, false),
                ];
            } elseif ($type === SpaceContentBlock::TYPE_LIST) {
                $items = is_array($data['items'] ?? null) ? $data['items'] : [];
                if ($items === [] || count($items) > 100) {
                    throw new LogicException('Blueprint list blocks require between 1 and 100 items.');
                }
                $data = [
                    'items' => collect($items)
                        ->map(fn (mixed $item): string => $this->text($item, 2000, true))
                        ->values()
                        ->all(),
                    'ordered' => (bool) ($data['ordered'] ?? false),
                ];
            } elseif ($type === SpaceContentBlock::TYPE_CALLOUT) {
                $tone = (string) ($data['tone'] ?? 'info');
                if (! in_array($tone, ['info', 'success', 'warning', 'danger'], true)) {
                    throw new LogicException('Blueprint callout tone is invalid.');
                }
                $data = [
                    'text' => $this->text($data['text'] ?? '', 10000, true),
                    'tone' => $tone,
                ];
            } else {
                $data = [];
            }

            $normalized[] = [
                'type' => $type,
                'data' => $data,
                'style' => $this->style(is_array($block['style'] ?? null) ? $block['style'] : []),
            ];
        }

        return $normalized;
    }

    /** @param list<mixed> $kinds @return list<string> */
    private function contextKinds(array $kinds): array
    {
        $allowed = array_map(
            static fn (ContextKind $kind): string => $kind->value,
            ContextKind::cases(),
        );

        $normalized = collect($kinds)
            ->filter(fn (mixed $kind): bool => is_string($kind) && in_array($kind, $allowed, true))
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            throw new LogicException('A Content Blueprint must support at least one Context kind.');
        }

        return $normalized;
    }

    /** @param list<mixed> $defaults @return list<array<string, mixed>> */
    private function conceptDefaults(array $defaults): array
    {
        $allowedPredicates = array_map(
            static fn (ConceptAssertionPredicate $predicate): string => $predicate->value,
            ConceptAssertionPredicate::cases(),
        );

        $normalized = [];

        foreach ($defaults as $default) {
            if (! is_array($default)) {
                throw new LogicException('Content Blueprint Concept defaults must be structured data.');
            }

            $conceptUuid = trim((string) ($default['concept_uuid'] ?? ''));
            $predicate = trim((string) ($default['predicate'] ?? ''));

            if (! Str::isUuid($conceptUuid) || ! in_array($predicate, $allowedPredicates, true)) {
                throw new LogicException('Content Blueprint Concept default is invalid.');
            }

            $normalized[] = [
                'concept_uuid' => $conceptUuid,
                'predicate' => $predicate,
            ];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $defaults @return array<string, mixed> */
    private function interactionDefaults(array $defaults): array
    {
        $allowed = ['annotations', 'reactions', 'default_annotation_visibility'];
        if (array_diff(array_keys($defaults), $allowed) !== []) {
            throw new LogicException('Unknown Content Blueprint interaction default.');
        }

        $visibility = $defaults['default_annotation_visibility'] ?? 'private';
        if (! is_string($visibility) || ! in_array($visibility, ['private', 'space'], true)) {
            throw new LogicException('Content Blueprint annotation visibility is invalid.');
        }

        return [
            'annotations' => (bool) ($defaults['annotations'] ?? true),
            'reactions' => (bool) ($defaults['reactions'] ?? true),
            'default_annotation_visibility' => $visibility,
        ];
    }

    /** @param array<string, mixed> $authoring @return array<string, mixed> */
    private function authoring(array $authoring): array
    {
        $keys = ['structured_fields', 'blocks', 'media', 'appearance', 'outline'];
        if (array_diff(array_keys($authoring), $keys) !== []) {
            throw new LogicException('Unknown Content Blueprint authoring capability.');
        }

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => (bool) ($authoring[$key] ?? true)])
            ->all();
    }

    /** @param array<string, mixed> $style @return array<string, mixed> */
    private function style(array $style): array
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

        if ($required && $value === '') {
            throw new LogicException('Blueprint block content is required.');
        }
        if (mb_strlen($value) > $max) {
            throw new LogicException('Blueprint block content is too long.');
        }

        return $value;
    }
}
