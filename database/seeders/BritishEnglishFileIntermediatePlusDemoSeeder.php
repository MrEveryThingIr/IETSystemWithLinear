<?php

namespace Database\Seeders;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\SaveSpaceContentRenderTemplate;
use App\Actions\Groups\UpdateSpaceContentBlocks;
use App\Actions\Groups\UpdateSpaceContentPresentation;
use App\Actions\Groups\UpdateSpaceContentStructure;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentRenderTemplate;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Database\Seeder;

class BritishEnglishFileIntermediatePlusDemoSeeder extends Seeder
{
    private const OWNER_ID = 1;

    private const OWNER_USERNAME = 'testuser';

    private const OWNER_EMAIL = 'test@example.com';

    private const GROUP_NAME = 'British English File Study';

    private const SPACE_NAME = 'Intermediate Plus';

    private const DEFINITION_NAME = 'Course Material';

    private const TEMPLATE_NAME = 'English Workbook · Magenta';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('BritishEnglishFileIntermediatePlusDemoSeeder is intended for local/testing environments only.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        $user = User::query()->with('actor')->find(self::OWNER_ID);
        abort_unless(
            $user instanceof User
            && $user->username === self::OWNER_USERNAME
            && $user->email === self::OWNER_EMAIL
            && $user->actor instanceof Actor,
            422,
            'Demo seeding expects user #1 to be testuser <test@example.com>. Run DatabaseSeeder on a clean local database first.',
        );

        $actor = $user->actor;
        $group = $this->ensureGroup($actor);
        $space = $this->ensureSpace($group, $user);
        $definition = $this->ensureDefinition($space, $user);

        $book = $this->ensureContent(
            $space,
            $definition,
            $user,
            'English File Intermediate Plus — Study Book',
            [
                'kind' => 'course',
                'level' => 'Intermediate Plus',
                'unit' => '',
                'page_number' => '',
                'source_title' => 'English File Intermediate Plus',
                'study_goal' => 'A reusable study-book container for lessons and pages recreated as addressable Content.',
            ],
        );

        $lesson = $this->ensureContent(
            $space,
            $definition,
            $user,
            '1A — Why did they call you that?',
            [
                'kind' => 'lesson',
                'level' => 'Intermediate Plus',
                'unit' => '1A',
                'page_number' => '',
                'source_title' => 'English File Intermediate Plus',
                'study_goal' => 'Names vocabulary, pronunciation of vowel sounds, and discussion about names.',
            ],
        );

        $page = $this->ensureContent(
            $space,
            $definition,
            $user,
            '1A — Page 6 — Why did they call you that?',
            [
                'kind' => 'page',
                'level' => 'Intermediate Plus',
                'unit' => '1A',
                'page_number' => '6',
                'source_title' => 'English File Intermediate Plus',
                'study_goal' => 'Study the first page of lesson 1A as a structured, annotatable workbook page.',
            ],
        );

        $page = app(UpdateSpaceContentPresentation::class)->execute(
            $page,
            $user,
            'builtin:lesson',
            $this->workbookPresentation(),
        );
        $template = $this->ensureWorkbookTemplate($page, $space, $user);
        $page = app(UpdateSpaceContentPresentation::class)->execute($page, $user, 'custom:'.$template->uuid, []);
        $page = app(UpdateSpaceContentBlocks::class)->execute(
            $page,
            $user,
            SpaceContentRevision::COMPOSITION_BLOCKS,
            $this->pageSixBlocks(),
        );
        $page = $this->publishDraft($page, $user);

        $lesson = app(UpdateSpaceContentPresentation::class)->execute($lesson, $user, 'custom:'.$template->uuid, []);
        $lesson = app(UpdateSpaceContentBlocks::class)->execute(
            $lesson,
            $user,
            SpaceContentRevision::COMPOSITION_BLOCKS,
            $this->lessonBlocks(),
        );
        $lesson = app(UpdateSpaceContentStructure::class)->execute($lesson, $user, [$page->id]);
        $lesson = $this->publishDraft($lesson, $user);

        $book = app(UpdateSpaceContentPresentation::class)->execute(
            $book,
            $user,
            'builtin:book',
            [
                'background' => '#fff8fb',
                'surface' => '#ffffff',
                'text' => '#27272a',
                'muted' => '#71717a',
                'accent' => '#d60067',
                'border' => '#f3bfd4',
                'content_width' => 'wide',
                'font_scale' => 'comfortable',
                'radius' => 'soft',
                'heading_style' => 'display',
                'media_style' => 'contained',
            ],
        );
        $book = app(UpdateSpaceContentBlocks::class)->execute(
            $book,
            $user,
            SpaceContentRevision::COMPOSITION_BLOCKS,
            $this->bookBlocks(),
        );
        $book = app(UpdateSpaceContentStructure::class)->execute($book, $user, [$lesson->id]);
        $book = $this->publishDraft($book, $user);

        $this->command?->newLine();
        $this->command?->info('British English File demo seeded successfully.');
        $this->command?->line('Group: '.self::GROUP_NAME);
        $this->command?->line('Space: '.self::SPACE_NAME);
        $this->command?->line('Saved template: '.self::TEMPLATE_NAME);
        $this->command?->line('Book Content ID: '.$book->id);
        $this->command?->line('Lesson Content ID: '.$lesson->id);
        $this->command?->line('Page Content ID: '.$page->id);
        $this->command?->line('Tip: open the page in Reader, then use Studio → Document Layout / Appearance / Outline to see how the fixture was assembled.');
    }

    private function ensureGroup(Actor $actor): Group
    {
        $group = Group::query()
            ->where('created_by_actor_id', $actor->id)
            ->where('name', self::GROUP_NAME)
            ->first();

        return $group instanceof Group
            ? $group
            : app(CreateGroup::class)->execute(
                $actor,
                self::GROUP_NAME,
                'A local study workspace demonstrating reusable course, lesson and page Content with Reader annotations.',
                'UTC',
            );
    }

    private function ensureSpace(Group $group, User $user): GroupSpace
    {
        $space = $group->spaces()->where('slug', 'intermediate-plus')->first();

        return $space instanceof GroupSpace
            ? $space
            : app(CreateGroupSpace::class)->execute($group, $user, self::SPACE_NAME, 'group');
    }

    private function ensureDefinition(GroupSpace $space, User $user): SpaceContentDefinition
    {
        $definition = $space->contentDefinitions()->where('slug', 'course-material')->first();

        if (! $definition instanceof SpaceContentDefinition) {
            $definition = app(CreateSpaceContentDefinition::class)->execute(
                $space,
                $user,
                self::DEFINITION_NAME,
                'Generic educational Content that can represent a course/book, lesson/unit or individual page while the detailed lesson body lives in revisioned blocks.',
                [
                    [
                        'key' => 'kind',
                        'label' => 'Material type',
                        'type' => 'select',
                        'required' => true,
                        'help' => 'Course/book, lesson/unit, or page.',
                        'options' => [
                            ['value' => 'course', 'label' => 'Course / book'],
                            ['value' => 'lesson', 'label' => 'Lesson / unit'],
                            ['value' => 'page', 'label' => 'Page'],
                        ],
                    ],
                    [
                        'key' => 'level',
                        'label' => 'Level',
                        'type' => 'short_text',
                        'required' => true,
                        'help' => null,
                        'options' => [],
                    ],
                    [
                        'key' => 'unit',
                        'label' => 'Unit / lesson',
                        'type' => 'short_text',
                        'required' => false,
                        'help' => null,
                        'options' => [],
                    ],
                    [
                        'key' => 'page_number',
                        'label' => 'Page',
                        'type' => 'short_text',
                        'required' => false,
                        'help' => null,
                        'options' => [],
                    ],
                    [
                        'key' => 'source_title',
                        'label' => 'Source title',
                        'type' => 'short_text',
                        'required' => true,
                        'help' => null,
                        'options' => [],
                    ],
                    [
                        'key' => 'study_goal',
                        'label' => 'Study goal',
                        'type' => 'long_text',
                        'required' => false,
                        'help' => null,
                        'options' => [],
                    ],
                ],
            );
        }

        if ($definition->activeVersionRecord() === null) {
            $definition = app(ActivateSpaceContentDefinition::class)->execute($definition, $user);
        }

        return $definition->refresh();
    }

    /** @param array<string, mixed> $payload */
    private function ensureContent(
        GroupSpace $space,
        SpaceContentDefinition $definition,
        User $user,
        string $title,
        array $payload,
    ): SpaceContent {
        $existing = SpaceContent::query()
            ->where('group_space_id', $space->id)
            ->where('space_content_definition_id', $definition->id)
            ->whereHas('revisions', fn ($query) => $query->where('title', $title))
            ->first();

        return $existing instanceof SpaceContent
            ? $existing
            : app(CreateSpaceContent::class)->execute($space, $definition, $user, $title, $payload);
    }

    private function ensureWorkbookTemplate(
        SpaceContent $page,
        GroupSpace $space,
        User $user,
    ): SpaceContentRenderTemplate {
        $existing = SpaceContentRenderTemplate::query()
            ->where('group_space_id', $space->id)
            ->where('name', self::TEMPLATE_NAME)
            ->first();

        if ($existing instanceof SpaceContentRenderTemplate) {
            return $existing;
        }

        return app(SaveSpaceContentRenderTemplate::class)->execute(
            $page,
            $user,
            self::TEMPLATE_NAME,
            'lesson',
            $this->workbookPresentation(),
        );
    }

    private function publishDraft(SpaceContent $content, User $user): SpaceContent
    {
        $content = $content->refresh();

        return $content->draftRevisionRecord() !== null
            ? app(PublishSpaceContent::class)->execute($content, $user)
            : $content;
    }

    /** @return array<string, mixed> */
    private function workbookPresentation(): array
    {
        return [
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
            'field_styles' => [
                'study_goal' => [
                    'background_color' => '#fce7f3',
                    'accent_color' => '#d60067',
                    'emphasis' => 'callout',
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function bookBlocks(): array
    {
        return [
            $this->heading('book-title', 'English File Intermediate Plus', 2, '#d60067'),
            $this->paragraph(
                'book-intro',
                'British English study workspace. Lessons and pages are separate Content objects, so each page can be revised, annotated and published independently while the book keeps an ordered Outline.',
            ),
            $this->callout(
                'book-tip',
                'Use Outline to add more lessons. For each lesson, create page Contents and add them to that lesson in order. Appearance can reuse the saved “English Workbook · Magenta” template.',
                'info',
                '#fff0f6',
                '#d60067',
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lessonBlocks(): array
    {
        return [
            $this->heading('lesson-title', '1A  Why did they call you that?', 2, '#d60067'),
            $this->paragraph(
                'lesson-focus',
                'G pronouns   ·   V names   ·   P vowel sounds',
                ['background_color' => '#fce7f3', 'text_color' => '#9d174d', 'emphasis' => 'strong'],
            ),
            $this->paragraph(
                'lesson-intro',
                'Lesson 1A is represented as its own Content container. Page 6 is its first child in the Outline; add later pages as sibling page Contents instead of expanding one giant document.',
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function pageSixBlocks(): array
    {
        return [
            $this->heading('page-title', '1A  Why did they call you that?', 2, '#d60067'),
            $this->paragraph(
                'page-tags',
                'G pronouns   V names   P vowel sounds',
                ['background_color' => '#fce7f3', 'text_color' => '#9d174d', 'emphasis' => 'strong'],
            ),
            $this->heading('vocabulary-title', '1  VOCABULARY — names', 3, '#27272a'),
            $this->paragraph(
                'vocabulary-instructions',
                'Read about the people and match photos A–H to the texts. Compare with a partner and together work out the meaning of the bold words and phrases.',
            ),
            $this->paragraph(
                'people-list',
                'A Marie Curie · B Winona Ryder · C Tolkien · D Paul McCartney · E J.K. Rowling · F Miley Cyrus · G Ed Sheeran · H Lupita Nyong’o',
                ['background_color' => '#fff7fa', 'accent_color' => '#d60067'],
            ),
            $this->callout(
                'clue-1',
                '1. Her full name is Lupita Amondi Nyong’o. She was born in Mexico and her parents gave her a Spanish name which is short for Guadalupe.',
                'info',
                '#fbe1ea',
                '#d60067',
            ),
            $this->callout(
                'clue-2',
                '2. He was an English writer, poet and university professor, and author of The Lord of the Rings. His full initials were J.R.R.T., but he was known as Ronald to his family.',
                'info',
                '#fbe1ea',
                '#d60067',
            ),
            $this->callout(
                'clue-3',
                '3. Her maiden name was Sklodowska, but she was awarded the Nobel Prize under her married name.',
                'info',
                '#fbe1ea',
                '#d60067',
            ),
            $this->callout(
                'clue-4',
                '4. Her name comes from her childhood nickname of “Smiley”. She changed her name legally, from Destiny, in 2008.',
                'info',
                '#fbe1ea',
                '#d60067',
            ),
            $this->callout(
                'clue-5',
                '5. After she became a famous novelist, she published detective stories under the pseudonym Robert Galbraith.',
                'info',
                '#fbe1ea',
                '#d60067',
            ),
            $this->callout(
                'clue-6',
                '6. She is an award-winning actress who is named after a city near where she was born, in the state of Minnesota, USA.',
                'info',
                '#fbe1ea',
                '#d60067',
            ),
            $this->callout(
                'clue-7',
                '7. His first name is James, after his father, but his family used his middle name to avoid confusion.',
                'info',
                '#fbe1ea',
                '#d60067',
            ),
            $this->callout(
                'clue-8',
                '8. His first three albums are called + (Plus), × (Multiply), and ÷ (Divide). His name is Edward, but he’s called Ed for short.',
                'info',
                '#fbe1ea',
                '#d60067',
            ),
            $this->paragraph('listen-check', '1.2  Listen and check.'),
            $this->paragraph(
                'discussion-prompts',
                "Tell a partner about someone you know who…\n• has a nickname.\n• is named after a place.\n• is named after a famous person.\n• is called something for short.\n• has a very old-fashioned name.\n• has changed his / her name.",
            ),
            $this->heading('pronunciation-title', '2  PRONUNCIATION — vowel sounds', 3, '#27272a'),
            $this->paragraph(
                'pronunciation-instructions',
                '1.3  Look at the first names in the chart. Listen and circle the name which doesn’t have the sound in the sound picture.',
            ),
            $this->listBlock(
                'pronunciation-groups',
                [
                    '1 — Chris · Bill · Linda · Diana',
                    '2 — Peter · Steve · Emily · Eve',
                    '3 — Alex · Amy · Andrew · Anna',
                    '4 — George · Paula · Charlotte · Sean',
                    '5 — Adele · Edward · Leo · Jessica',
                    '6 — Sam · Grace · James · Kate',
                    '7 — Tony · Joe · Nicole · Sophie',
                    '8 — Caroline · Mia · Mike · Simon',
                ],
            ),
            $this->paragraph(
                'pronunciation-partner',
                'With a partner, decide if the names in a are men’s names, women’s names, or both. Write M, W, or B next to each name. Are any of them short for another name?',
            ),
            $this->callout(
                'communication',
                'Communication — Middle names quiz, p.106. Do the quiz.',
                'success',
                '#f3f7df',
                '#6f9f2f',
            ),
            $this->callout(
                'study-note',
                'Study idea: select any word, phrase or instruction in Reader and use Remember / Add note / Ask question. Your simple notes default to Private and stay attached to this exact published edition.',
                'info',
                '#eef6ff',
                '#0b6fa4',
            ),
        ];
    }

    /** @param array<string, mixed> $style */
    private function paragraph(string $key, string $text, array $style = []): array
    {
        return $this->block($key, 'paragraph', ['text' => $text], $style);
    }

    private function heading(string $key, string $text, int $level, string $color): array
    {
        return $this->block(
            $key,
            'heading',
            ['text' => $text, 'level' => $level],
            ['text_color' => $color, 'emphasis' => 'strong'],
        );
    }

    private function callout(
        string $key,
        string $text,
        string $tone,
        string $background,
        string $accent,
    ): array {
        return $this->block(
            $key,
            'callout',
            ['text' => $text, 'tone' => $tone],
            [
                'background_color' => $background,
                'accent_color' => $accent,
                'emphasis' => 'callout',
            ],
        );
    }

    /** @param list<string> $items */
    private function listBlock(string $key, array $items): array
    {
        return $this->block($key, 'list', ['items' => $items, 'ordered' => false], []);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $style
     * @return array<string, mixed>
     */
    private function block(string $key, string $type, array $data, array $style): array
    {
        return [
            'logical_uuid' => $this->deterministicUuid('english-file-demo:'.$key),
            'type' => $type,
            'data' => $data,
            'style' => $style,
        ];
    }

    private function deterministicUuid(string $name): string
    {
        $hash = sha1('IET/BritishEnglishFileIntermediatePlusDemo/'.$name);

        return sprintf(
            '%s-%s-5%s-%s%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex((hexdec($hash[16]) & 0x3) | 0x8),
            substr($hash, 17, 3),
            substr($hash, 20, 12),
        );
    }
}
