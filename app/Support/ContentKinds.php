<?php

namespace App\Support;

/**
 * The everyday kinds of Content a person chooses from when creating.
 *
 * Each kind resolves to a system Content Blueprint by slug. Blueprints remain
 * the authority for fields and layout; people never have to see them unless
 * they open "More templates".
 */
final class ContentKinds
{
    /** @var array<string, array{slug: string, icon: string}> */
    public const KINDS = [
        'note' => ['slug' => 'note-diary', 'icon' => 'pencil-square'],
        'article' => ['slug' => 'article', 'icon' => 'document-text'],
        'lesson' => ['slug' => 'lesson', 'icon' => 'academic-cap'],
        'course' => ['slug' => 'course', 'icon' => 'book-open'],
        'evidence' => ['slug' => 'evidence-work-sample', 'icon' => 'check-badge'],
    ];

    /** Blueprint slugs that count as learning material on the Content page. */
    public const LEARNING_CATEGORY = 'learning';

    public static function slugFor(string $kind): ?string
    {
        return self::KINDS[$kind]['slug'] ?? null;
    }

    public static function kindForSlug(?string $slug): ?string
    {
        foreach (self::KINDS as $kind => $definition) {
            if ($definition['slug'] === $slug) {
                return $kind;
            }
        }

        return null;
    }
}
