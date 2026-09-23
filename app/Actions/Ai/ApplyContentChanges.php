<?php

namespace App\Actions\Ai;

use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\UpdateSpaceContentBlocks;
use App\Actions\Groups\UpdateSpaceContentPresentation;
use App\Models\AiAssistanceRun;
use App\Models\SpaceContent;
use App\Models\SpaceContentBlock;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ApplyContentChanges
{
    public function __construct(
        private readonly ReviseSpaceContent $revise,
        private readonly UpdateSpaceContentBlocks $blocks,
        private readonly UpdateSpaceContentPresentation $presentation,
    ) {}

    public function execute(AiAssistanceRun $run, User $user): AiAssistanceRun
    {
        abort_unless((bool) config('ai.enabled'), 404);
        return DB::transaction(function () use ($run, $user): AiAssistanceRun {
            $currentRun = AiAssistanceRun::query()->lockForUpdate()->findOrFail($run->id);
            abort_unless($currentRun->status === AiAssistanceRun::STATUS_PLANNED, 409, 'This AI proposal is no longer pending.');

            $content = SpaceContent::query()->lockForUpdate()->findOrFail($currentRun->space_content_id);
            Gate::forUser($user)->authorize('update', $content);

            $editable = $content->draftRevisionRecord() ?? $content->activeRevisionRecord();
            abort_unless($editable instanceof SpaceContentRevision, 422, 'Content has no editable revision.');
            abort_unless((int) $editable->id === (int) $currentRun->base_revision_id, 409, 'Content changed after this AI proposal was created. Create a fresh proposal instead.');

            $proposal = is_array($currentRun->proposal) ? $currentRun->proposal : [];
            $content = $this->applyDocument($content, $user, $proposal);

            if (is_array($proposal['block_operations'] ?? null) && $proposal['block_operations'] !== []) {
                $content = $this->applyBlocks($content, $user, $proposal['block_operations']);
            }

            if (($proposal['apply_presentation'] ?? false) === true && is_array($proposal['presentation'] ?? null)) {
                $presentation = $proposal['presentation'];
                $baseKey = is_string($presentation['base_key'] ?? null) ? $presentation['base_key'] : 'article';
                unset($presentation['base_key']);

                $content = $this->presentation->execute(
                    $content,
                    $user,
                    'builtin:'.$baseKey,
                    $presentation,
                );
            }

            $applied = $content->draftRevisionRecord() ?? $content->activeRevisionRecord();
            abort_unless($applied instanceof SpaceContentRevision, 500);

            $currentRun->update([
                'status' => AiAssistanceRun::STATUS_APPLIED,
                'applied_revision_id' => $applied->id,
                'applied_at' => now(),
            ]);

            return $currentRun->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $proposal */
    private function applyDocument(SpaceContent $content, User $user, array $proposal): SpaceContent
    {
        $revision = $content->draftRevisionRecord() ?? $content->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 422);

        $title = $revision->title;
        if (($proposal['apply_title'] ?? false) === true && is_string($proposal['title'] ?? null)) {
            $candidate = trim($proposal['title']);
            if ($candidate !== '') {
                $title = $candidate;
            }
        }

        $payload = $revision->payload;
        $fields = collect($revision->definitionVersion()->firstOrFail()->schema['fields'] ?? [])
            ->filter(static fn (mixed $field): bool => is_array($field) && is_string($field['key'] ?? null))
            ->keyBy(static fn (array $field): string => $field['key']);

        foreach (($proposal['field_updates'] ?? []) as $update) {
            if (! is_array($update) || ! is_string($update['key'] ?? null) || ! $fields->has($update['key'])) {
                abort(422, 'AI proposal references an unavailable Content field.');
            }

            $payload[$update['key']] = $update['value'] ?? null;
        }

        if ($title === $revision->title && $payload === $revision->payload) {
            return $content->refresh();
        }

        return $this->revise->execute($content, $user, $title, $payload);
    }

    /** @param list<mixed> $operations */
    private function applyBlocks(SpaceContent $content, User $user, array $operations): SpaceContent
    {
        $revision = $content->draftRevisionRecord() ?? $content->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 422);
        $revision->loadMissing('blocks');

        $blocks = $revision->blocks->map(static fn (SpaceContentBlock $block): array => [
            'logical_uuid' => $block->logical_uuid,
            'type' => $block->type,
            'data' => $block->data,
            'style' => $block->style ?? [],
        ])->values()->all();

        foreach ($operations as $operation) {
            abort_unless(is_array($operation), 422, 'AI block operation is invalid.');
            $kind = (string) ($operation['operation'] ?? '');
            $target = (string) ($operation['target_logical_uuid'] ?? '');

            if ($kind === 'append') {
                $blocks[] = $this->block($operation['block'] ?? null, null);

                continue;
            }

            $index = collect($blocks)->search(static fn (array $block): bool => ($block['logical_uuid'] ?? null) === $target);
            abort_if($index === false, 409, 'AI proposal targets a block that no longer exists.');

            if ($kind === 'remove') {
                array_splice($blocks, (int) $index, 1);

                continue;
            }

            abort_unless($kind === 'replace', 422, 'AI block operation is invalid.');
            $blocks[(int) $index] = $this->block($operation['block'] ?? null, $target);
        }

        abort_if($blocks === [], 422, 'AI proposal cannot remove every block from a block document.');

        return $this->blocks->execute(
            $content,
            $user,
            SpaceContentRevision::COMPOSITION_BLOCKS,
            array_values($blocks),
        );
    }

    /** @return array<string, mixed> */
    private function block(mixed $raw, ?string $logicalUuid): array
    {
        abort_unless(is_array($raw), 422, 'AI proposal block is invalid.');

        $type = (string) ($raw['type'] ?? '');
        abort_unless(in_array($type, [
            SpaceContentBlock::TYPE_PARAGRAPH,
            SpaceContentBlock::TYPE_HEADING,
            SpaceContentBlock::TYPE_QUOTE,
            SpaceContentBlock::TYPE_LIST,
            SpaceContentBlock::TYPE_CALLOUT,
            SpaceContentBlock::TYPE_DIVIDER,
            SpaceContentBlock::TYPE_FIELD,
        ], true), 422, 'AI proposal uses an unsupported block type.');

        $data = match ($type) {
            SpaceContentBlock::TYPE_PARAGRAPH => ['text' => (string) ($raw['text'] ?? '')],
            SpaceContentBlock::TYPE_HEADING => [
                'text' => (string) ($raw['text'] ?? ''),
                'level' => (int) ($raw['level'] ?? 2),
            ],
            SpaceContentBlock::TYPE_QUOTE => [
                'text' => (string) ($raw['text'] ?? ''),
                'attribution' => (string) ($raw['attribution'] ?? ''),
            ],
            SpaceContentBlock::TYPE_LIST => [
                'items' => is_array($raw['items'] ?? null) ? $raw['items'] : [],
                'ordered' => (bool) ($raw['ordered'] ?? false),
            ],
            SpaceContentBlock::TYPE_CALLOUT => [
                'text' => (string) ($raw['text'] ?? ''),
                'tone' => (string) ($raw['tone'] ?? 'info'),
            ],
            SpaceContentBlock::TYPE_FIELD => ['field_key' => (string) ($raw['field_key'] ?? '')],
            default => [],
        };

        $style = is_array($raw['style'] ?? null)
            ? array_filter($raw['style'], static fn (mixed $value): bool => $value !== '' && $value !== null)
            : [];

        return [
            'logical_uuid' => $logicalUuid ?? (string) Str::uuid(),
            'type' => $type,
            'data' => $data,
            'style' => $style,
        ];
    }
}
