<?php

namespace App\Support;

use App\Models\SpaceContentRevision;

/**
 * The author's chosen reading appearance of an edition, as the variables the
 * reader templates use. Shared by the signed-in reader and the guest reader so
 * a public post looks exactly like the edition its author published.
 */
final class ContentReaderStyle
{
    /** @return array<string, mixed> */
    public static function variables(SpaceContentRevision $revision): array
    {
        $presentation = is_array($revision->presentation) ? $revision->presentation : [];

        return [
            'presentation' => $presentation,
            'readerBackground' => $presentation['background'] ?? '#fafafa',
            'readerSurface' => $presentation['surface'] ?? '#ffffff',
            'readerText' => $presentation['text'] ?? '#18181b',
            'readerMuted' => $presentation['muted'] ?? '#71717a',
            'readerAccent' => $presentation['accent'] ?? '#2563eb',
            'readerBorder' => $presentation['border'] ?? '#e4e4e7',
            'contentWidthClass' => match ($presentation['content_width'] ?? 'reading') {
                'narrow' => 'max-w-2xl',
                'wide' => 'max-w-5xl',
                'full' => 'max-w-none',
                default => 'max-w-3xl',
            },
            'fontClass' => match ($presentation['font_scale'] ?? 'comfortable') {
                'compact' => 'text-[0.98rem] leading-7',
                'large' => 'text-[1.12rem] leading-9',
                default => 'text-[1.05rem] leading-8',
            },
            'radiusClass' => match ($presentation['radius'] ?? 'rounded') {
                'none' => 'rounded-none',
                'soft' => 'rounded-lg',
                default => 'rounded-2xl',
            },
            'headingClass' => match ($presentation['heading_style'] ?? 'plain') {
                'serif' => 'font-serif tracking-tight',
                'display' => 'font-black tracking-tight',
                default => 'font-semibold tracking-tight',
            },
            'mediaStyle' => $presentation['media_style'] ?? 'card',
            'fieldStyles' => is_array($presentation['field_styles'] ?? null) ? $presentation['field_styles'] : [],
        ];
    }
}
