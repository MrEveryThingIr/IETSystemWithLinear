<?php

namespace App\Support;

class SpaceContentPresentation
{
    /** @return array<string, array{name: string, tokens: array<string, mixed>}> */
    public function builtIns(): array
    {
        return [
            'article' => [
                'name' => 'Article',
                'tokens' => [
                    'background' => '#fafafa',
                    'surface' => '#ffffff',
                    'text' => '#18181b',
                    'muted' => '#71717a',
                    'accent' => '#2563eb',
                    'border' => '#e4e4e7',
                    'content_width' => 'reading',
                    'font_scale' => 'comfortable',
                    'radius' => 'rounded',
                    'heading_style' => 'plain',
                    'media_style' => 'card',
                    'field_styles' => [],
                ],
            ],
            'lesson' => [
                'name' => 'Lesson',
                'tokens' => [
                    'background' => '#f8fafc',
                    'surface' => '#ffffff',
                    'text' => '#172554',
                    'muted' => '#64748b',
                    'accent' => '#0f766e',
                    'border' => '#dbeafe',
                    'content_width' => 'reading',
                    'font_scale' => 'comfortable',
                    'radius' => 'rounded',
                    'heading_style' => 'plain',
                    'media_style' => 'card',
                    'field_styles' => [],
                ],
            ],
            'book' => [
                'name' => 'Book',
                'tokens' => [
                    'background' => '#f7f3ea',
                    'surface' => '#fffdf8',
                    'text' => '#292524',
                    'muted' => '#78716c',
                    'accent' => '#9a3412',
                    'border' => '#e7e5e4',
                    'content_width' => 'narrow',
                    'font_scale' => 'large',
                    'radius' => 'soft',
                    'heading_style' => 'serif',
                    'media_style' => 'contained',
                    'field_styles' => [],
                ],
            ],
            'minimal' => [
                'name' => 'Minimal',
                'tokens' => [
                    'background' => '#ffffff',
                    'surface' => '#ffffff',
                    'text' => '#09090b',
                    'muted' => '#71717a',
                    'accent' => '#18181b',
                    'border' => '#f4f4f5',
                    'content_width' => 'reading',
                    'font_scale' => 'comfortable',
                    'radius' => 'none',
                    'heading_style' => 'plain',
                    'media_style' => 'contained',
                    'field_styles' => [],
                ],
            ],
            'showcase' => [
                'name' => 'Showcase',
                'tokens' => [
                    'background' => '#111827',
                    'surface' => '#1f2937',
                    'text' => '#f9fafb',
                    'muted' => '#cbd5e1',
                    'accent' => '#a78bfa',
                    'border' => '#374151',
                    'content_width' => 'wide',
                    'font_scale' => 'large',
                    'radius' => 'rounded',
                    'heading_style' => 'display',
                    'media_style' => 'edge',
                    'field_styles' => [],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->builtIns());
    }

    /** @return array<string, mixed> */
    public function defaults(string $baseKey): array
    {
        $builtIns = $this->builtIns();
        abort_unless(isset($builtIns[$baseKey]), 422, 'Choose a supported rendering template.');

        return $builtIns[$baseKey]['tokens'];
    }

    /**
     * @param array<string, mixed> $overrides
     * @param list<string> $fieldKeys
     * @return array<string, mixed>
     */
    public function resolve(string $baseKey, array $overrides, array $fieldKeys = []): array
    {
        $tokens = $this->defaults($baseKey);

        foreach (['background', 'surface', 'text', 'muted', 'accent', 'border'] as $key) {
            if (array_key_exists($key, $overrides)) {
                $tokens[$key] = $this->color($overrides[$key], (string) $tokens[$key]);
            }
        }

        $tokens['content_width'] = $this->choice($overrides['content_width'] ?? $tokens['content_width'], ['narrow', 'reading', 'wide', 'full'], (string) $tokens['content_width']);
        $tokens['font_scale'] = $this->choice($overrides['font_scale'] ?? $tokens['font_scale'], ['compact', 'comfortable', 'large'], (string) $tokens['font_scale']);
        $tokens['radius'] = $this->choice($overrides['radius'] ?? $tokens['radius'], ['none', 'soft', 'rounded'], (string) $tokens['radius']);
        $tokens['heading_style'] = $this->choice($overrides['heading_style'] ?? $tokens['heading_style'], ['plain', 'serif', 'display'], (string) $tokens['heading_style']);
        $tokens['media_style'] = $this->choice($overrides['media_style'] ?? $tokens['media_style'], ['contained', 'card', 'edge'], (string) $tokens['media_style']);

        $allowedFields = array_fill_keys($fieldKeys, true);
        $fieldStyles = [];
        foreach (($overrides['field_styles'] ?? []) as $fieldKey => $style) {
            if (! is_string($fieldKey) || ! isset($allowedFields[$fieldKey]) || ! is_array($style)) {
                continue;
            }

            $fieldStyle = [];
            foreach (['text_color', 'background_color', 'accent_color'] as $colorKey) {
                if (array_key_exists($colorKey, $style)) {
                    $value = $this->nullableColor($style[$colorKey]);
                    if ($value !== null) {
                        $fieldStyle[$colorKey] = $value;
                    }
                }
            }
            $fieldStyle['emphasis'] = $this->choice($style['emphasis'] ?? 'normal', ['normal', 'muted', 'strong', 'callout'], 'normal');
            if ($fieldStyle !== ['emphasis' => 'normal']) {
                $fieldStyles[$fieldKey] = $fieldStyle;
            }
        }
        $tokens['field_styles'] = $fieldStyles;

        return $tokens;
    }

    private function color(mixed $value, string $fallback): string
    {
        $color = $this->nullableColor($value);

        return $color ?? $fallback;
    }

    private function nullableColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : null;
    }

    /** @param list<string> $allowed */
    private function choice(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }
}
