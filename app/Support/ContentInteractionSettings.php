<?php

namespace App\Support;

use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;

class ContentInteractionSettings
{
    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{annotations: bool, reactions: bool, default_annotation_visibility: string}
     */
    public function normalize(?array $settings): array
    {
        $settings ??= [];
        $visibility = $settings['default_annotation_visibility'] ?? SpaceContentAnnotation::VISIBILITY_PRIVATE;

        if (! is_string($visibility)
            || ! in_array($visibility, SpaceContentAnnotation::VISIBILITIES, true)) {
            $visibility = SpaceContentAnnotation::VISIBILITY_PRIVATE;
        }

        return [
            'annotations' => (bool) ($settings['annotations'] ?? true),
            'reactions' => (bool) ($settings['reactions'] ?? true),
            'default_annotation_visibility' => $visibility,
        ];
    }

    /** @return array{annotations: bool, reactions: bool, default_annotation_visibility: string} */
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
