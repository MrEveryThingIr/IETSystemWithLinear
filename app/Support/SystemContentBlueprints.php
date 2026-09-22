<?php

namespace App\Support;

use App\ContextKind;

class SystemContentBlueprints
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $allContexts = array_map(
            static fn (ContextKind $kind): string => $kind->value,
            ContextKind::cases(),
        );

        $advanced = [
            'structured_fields' => true,
            'blocks' => true,
            'media' => true,
            'appearance' => true,
            'outline' => true,
        ];

        return [
            $this->preset(
                'note-diary',
                'Note / Diary',
                'A quick private or shared note, journal entry, reflection, or lightweight record.',
                'general',
                [$this->field('body', 'Body', 'long_text', true)],
                [$this->fieldBlock('body')],
                'minimal',
                $allContexts,
                $advanced,
            ),
            $this->preset(
                'post',
                'Post',
                'A concise update or announcement with optional media and discussion.',
                'general',
                [$this->field('body', 'Body', 'long_text', true)],
                [$this->fieldBlock('body')],
                'article',
                $allContexts,
                $advanced,
            ),
            $this->preset(
                'article',
                'Article',
                'A structured long-form article with summary, body, media, annotations, and publication history.',
                'general',
                [
                    $this->field('summary', 'Summary', 'long_text', false),
                    $this->field('body', 'Body', 'long_text', true),
                ],
                [$this->fieldBlock('summary'), $this->fieldBlock('body')],
                'article',
                $allContexts,
                $advanced,
            ),
            $this->preset(
                'activity-report',
                'Activity / Report',
                'Record an activity, what happened, outcomes, evidence, and supporting files.',
                'general',
                [
                    $this->field('activity_date', 'Activity date', 'date', false),
                    $this->field('summary', 'Summary', 'long_text', true),
                    $this->field('outcome', 'Outcome', 'long_text', false),
                ],
                [
                    $this->fieldBlock('activity_date'),
                    $this->fieldBlock('summary'),
                    $this->fieldBlock('outcome'),
                ],
                'article',
                $allContexts,
                $advanced,
            ),
            $this->preset(
                'evidence-work-sample',
                'Evidence / Work Sample',
                'Document completed work with role, result, files, media, and exact revision/block evidence targets.',
                'evidence',
                [
                    $this->field('work_date', 'Work date', 'date', false),
                    $this->field('role', 'Your role', 'short_text', false),
                    $this->field('summary', 'What you did', 'long_text', true),
                    $this->field('outcome', 'Result / outcome', 'long_text', false),
                ],
                [
                    $this->fieldBlock('work_date'),
                    $this->fieldBlock('role'),
                    $this->fieldBlock('summary'),
                    $this->fieldBlock('outcome'),
                ],
                'showcase',
                $allContexts,
                $advanced,
            ),
            $this->preset(
                'media-album',
                'Media Album',
                'A media-first collection for photos, audio, video, files, captions, and narrative.',
                'media',
                [$this->field('description', 'Description', 'long_text', false)],
                [$this->fieldBlock('description')],
                'showcase',
                $allContexts,
                $advanced,
            ),
            $this->preset(
                'book-booklet',
                'Book / Booklet',
                'A parent publication that can organize lessons, pages, chapters, or other Content through Outline.',
                'learning',
                [$this->field('summary', 'Introduction / summary', 'long_text', false)],
                [$this->fieldBlock('summary')],
                'book',
                $allContexts,
                $advanced,
            ),
            $this->preset(
                'lesson',
                'Lesson',
                'A teaching unit with objective, lesson body, media, annotations, and child pages.',
                'learning',
                [
                    $this->field('objective', 'Objective', 'long_text', false),
                    $this->field('body', 'Lesson', 'long_text', true),
                ],
                [$this->fieldBlock('objective'), $this->fieldBlock('body')],
                'lesson',
                $allContexts,
                $advanced,
            ),
            $this->preset(
                'workbook-page',
                'Workbook Page',
                'An independently revisioned learning page with instructions and working content.',
                'learning',
                [
                    $this->field('instructions', 'Instructions', 'long_text', true),
                    $this->field('notes', 'Notes', 'long_text', false),
                ],
                [$this->fieldBlock('instructions'), $this->fieldBlock('notes')],
                'lesson',
                $allContexts,
                $advanced,
            ),
            $this->preset(
                'questionnaire-shell',
                'Questionnaire',
                'Author the questionnaire document now; structured respondent submissions are added by the Submission engine in Phase 7.',
                'forms',
                [$this->field('introduction', 'Introduction', 'long_text', true)],
                [$this->fieldBlock('introduction')],
                'minimal',
                $allContexts,
                $advanced,
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param list<array<string, mixed>> $blocks
     * @param list<string> $contextKinds
     * @param array<string, bool> $authoring
     * @return array<string, mixed>
     */
    private function preset(
        string $slug,
        string $name,
        string $description,
        string $category,
        array $fields,
        array $blocks,
        string $renderTemplateKey,
        array $contextKinds,
        array $authoring,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'version' => [
                'definition_schema' => ['fields' => $fields],
                'initial_blocks' => $blocks,
                'render_template_key' => $renderTemplateKey,
                'presentation' => [],
                'context_kinds' => $contextKinds,
                'concept_defaults' => [],
                'interaction_defaults' => [
                    'annotations' => true,
                    'reactions' => true,
                    'default_annotation_visibility' => 'private',
                ],
                'authoring' => $authoring,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function field(
        string $key,
        string $label,
        string $type,
        bool $required,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $required,
            'help' => null,
            'options' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function fieldBlock(string $fieldKey): array
    {
        return [
            'type' => 'field',
            'data' => ['field_key' => $fieldKey],
            'style' => [],
        ];
    }
}
