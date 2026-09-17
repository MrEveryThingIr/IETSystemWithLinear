# British English File / Intermediate Plus demo

This fixture is an **opt-in local development example**. `DatabaseSeeder` remains intentionally clean and only bootstraps the local Superadmin.

Run the demo with:

```bash
php artisan db:seed --class=BritishEnglishFileIntermediatePlusDemoSeeder
```

It expects local user `#1` to be:

- username: `testuser`
- email: `test@example.com`

The seeder creates this product-shaped hierarchy:

```text
British English File Study                 Group
└── Intermediate Plus                      Space
    ├── Course Material                    Content Definition
    ├── English Workbook · Magenta         saved/favorited Appearance template
    └── English File Intermediate Plus     Content: book/course
        └── 1A — Why did they call you that?   Content: lesson
            └── 1A — Page 6 — Why did they call you that?  Content: page
```

## Why it is structured this way

Do not make an entire textbook one enormous Content revision. A Book/Course is a Content container; a Lesson is independently revisioned Content; and every Page can also be independently styled, annotated, revised and published. `Outline` (`contains` relationships) connects them.

This means adding page 7 later does not rewrite the identity/history of page 6, and annotations on page 6 remain bound to the exact page-6 edition they were created against.

## Where the textbook-like appearance comes from

The fixture first creates a safe Space rendering template named **English Workbook · Magenta**. It extends the built-in `lesson` presentation through validated design tokens rather than arbitrary CSS/Blade:

```php
[
    'background' => '#fff8fb',
    'surface' => '#ffffff',
    'text' => '#27272a',
    'muted' => '#6b7280',
    'accent' => '#d60067',
    'border' => '#f3bfd4',
    'content_width' => 'wide',
    'font_scale' => 'comfortable',
    'radius' => 'soft',
    'heading_style' => 'display',
    'media_style' => 'contained',
]
```

The Page then adds block-level styling for the visual vocabulary of the supplied first page:

- vivid magenta lesson/title accents;
- pale-pink exercise cards;
- white reading surface;
- dark neutral body text;
- green Communication callout;
- blue study/help callout.

Open the seeded Page and compare:

- **Studio → Document**: semantic fields (material type, level, unit, page, source, goal)
- **Studio → Document Layout**: heading / paragraph / list / callout blocks and their per-block styles
- **Studio → Appearance**: reusable template and page-wide design tokens
- **Studio → Outline**: Book → Lesson → Page relationships
- **Reader**: final published edition + contextual annotations

## Adding another page

Use the fixture as a pattern rather than copying database inserts. A new page should normally follow this sequence:

1. `CreateSpaceContent` using the same `Course Material` definition.
2. `UpdateSpaceContentPresentation` using `custom:<English Workbook template UUID>`.
3. `UpdateSpaceContentBlocks` with stable/deterministic `logical_uuid` values.
4. `PublishSpaceContent`.
5. Add the new Page Content to the Lesson's ordered Outline with `UpdateSpaceContentStructure`.
6. Republish the Lesson so its new Outline edition is sealed.

A useful next refactor, once you have several real pages, is to move the page descriptions into a data array such as:

```php
[
    'unit' => '1A',
    'page' => 7,
    'title' => '...',
    'blocks' => [...],
]
```

and let one helper build every page from those records. Keep page-specific content in data; keep workflow logic in one place.

## About the source scan/image

The demo intentionally **does not mark a textbook scan as `owned` or otherwise publishable on your behalf**. The seeded Reader page is reconstructed as native blocks from the supplied reference image, so it is already useful for testing layout and annotations.

If you want the original scan stored for your own study/reference, upload it from Studio with its truthful rights status. `private_study_only` is appropriate when it must remain private; such an Asset is deliberately prevented from entering a public/shared publication. If you have a publication right that fits another supported rights status, set that status explicitly yourself.
