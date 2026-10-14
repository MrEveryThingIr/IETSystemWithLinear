<?php

namespace App\Support;

use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;

class ContentInteractionSettings
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{annotations: bool, reactions: bool, default_annotation_visibility: string, role_rules?: array<int, array{kinds: list<string>, endorse: bool}>}
     */
    public function normalize(?array $settings): array
    {
        $settings ??= [];
        $visibility = $settings['default_annotation_visibility'] ?? SpaceContentAnnotation::VISIBILITY_PRIVATE;

        if (! is_string($visibility)
            || ! in_array($visibility, SpaceContentAnnotation::VISIBILITIES, true)) {
            $visibility = SpaceContentAnnotation::VISIBILITY_PRIVATE;
        }

        $normalized = [
            'annotations' => (bool) ($settings['annotations'] ?? true),
            'reactions' => (bool) ($settings['reactions'] ?? true),
            'default_annotation_visibility' => $visibility,
        ];

        $roleRules = $this->normalizeRoleRules($settings['role_rules'] ?? null);
        if ($roleRules !== []) {
            $normalized['role_rules'] = $roleRules;
        }

        return $normalized;
    }

    /**
     * Per group role: which annotation kinds its members may add and whether
     * they may endorse answers. No rules means everyone may use every kind.
     *
     * @return array<int, array{kinds: list<string>, endorse: bool}>
     */
    public function normalizeRoleRules(mixed $rules): array
    {
        if (! is_array($rules)) {
            return [];
        }

        $normalized = [];
        foreach ($rules as $roleId => $rule) {
            if (! is_numeric($roleId) || (int) $roleId < 1 || ! is_array($rule)) {
                continue;
            }

            $kinds = array_values(array_intersect(
                SpaceContentAnnotation::KINDS,
                array_map('strval', is_array($rule['kinds'] ?? null) ? $rule['kinds'] : []),
            ));

            $normalized[(int) $roleId] = [
                'kinds' => $kinds,
                'endorse' => (bool) ($rule['endorse'] ?? false),
            ];
        }

        ksort($normalized);

        return $normalized;
    }

    /** @return array<int, array{kinds: list<string>, endorse: bool}> */
    public function roleRules(SpaceContent $content): array
    {
        return $this->for($content)['role_rules'] ?? [];
    }

    /** @return array{annotations: bool, reactions: bool, default_annotation_visibility: string, role_rules?: array<int, array{kinds: list<string>, endorse: bool}>} */
    public function for(SpaceContent $content): array
    {
        $settings = $content->interaction_settings;

        return $this->normalize(is_array($settings) ? $settings : null);
    }

    public function annotationsEnabled(SpaceContent $content): bool
    {
        return $this->for($content)['annotations'];
    }

    public function reactionsEnabled(SpaceContent $content): bool
    {
        return $this->for($content)['reactions'];
    }

    public function defaultAnnotationVisibility(SpaceContent $content): string
    {
        return $this->for($content)['default_annotation_visibility'];
    }
}
