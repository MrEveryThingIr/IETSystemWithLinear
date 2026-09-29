<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\UpdateSpaceContentBlocks;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentBlock;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Document Layout')]
class SpaceContentBlockStudio extends Component
{
    public Group $group;

    public GroupSpace $space;

    public SpaceContent $content;

    public string $compositionMode = SpaceContentRevision::COMPOSITION_FIELDS;

    /** @var list<array<string, mixed>> */
    public array $blocks = [];

    public function mount(Group $group, GroupSpace $space, SpaceContent $content): void
    {
        abort_unless((int) $space->group_id === (int) $group->id, 404);
        abort_unless((int) $content->group_space_id === (int) $space->id, 404);
        Gate::authorize('update', $content);

        $this->group = $group;
        $this->space = $space;
        $this->content = $content;
        $this->reload();
    }

    public function useStructuredFields(): void
    {
        $this->compositionMode = SpaceContentRevision::COMPOSITION_FIELDS;
    }

    public function useBlockDocument(): void
    {
        $this->compositionMode = SpaceContentRevision::COMPOSITION_BLOCKS;
        if ($this->blocks === []) {
            $this->startFromFields();
        }
    }

    public function startFromFields(): void
    {
        $revision = $this->editableRevision();
        $version = $revision->definitionVersion()->firstOrFail();
        $this->blocks = [];

        foreach ($version->schema['fields'] ?? [] as $field) {
            if (! is_array($field) || ! is_string($field['key'] ?? null)) {
                continue;
            }

            $this->blocks[] = [
                'logical_uuid' => (string) Str::uuid(),
                'type' => SpaceContentBlock::TYPE_FIELD,
                'data' => [
                    'field_key' => $field['key'],
                    'label' => is_string($field['label'] ?? null) ? $field['label'] : $field['key'],
                ],
                'style' => [],
            ];
        }

        if ($this->blocks === []) {
            $this->addBlock(SpaceContentBlock::TYPE_PARAGRAPH);
        }
        $this->compositionMode = SpaceContentRevision::COMPOSITION_BLOCKS;
    }

    public function addBlock(string $type): void
    {
        abort_unless(in_array($type, SpaceContentBlock::TYPES, true), 422);

        $data = match ($type) {
            SpaceContentBlock::TYPE_PARAGRAPH => ['text' => ''],
            SpaceContentBlock::TYPE_HEADING => ['text' => '', 'level' => 2],
            SpaceContentBlock::TYPE_QUOTE => ['text' => '', 'attribution' => ''],
            SpaceContentBlock::TYPE_LIST => ['items_text' => '', 'ordered' => false],
            SpaceContentBlock::TYPE_CALLOUT => ['text' => '', 'tone' => 'info'],
            SpaceContentBlock::TYPE_DIVIDER => [],
            default => [],
        };

        $this->blocks[] = [
            'logical_uuid' => (string) Str::uuid(),
            'type' => $type,
            'data' => $data,
            'style' => [],
        ];
        $this->compositionMode = SpaceContentRevision::COMPOSITION_BLOCKS;
    }

    public function addFieldBlock(string $fieldKey): void
    {
        $this->blocks[] = [
            'logical_uuid' => (string) Str::uuid(),
            'type' => SpaceContentBlock::TYPE_FIELD,
            'data' => ['field_key' => $fieldKey],
            'style' => [],
        ];
        $this->compositionMode = SpaceContentRevision::COMPOSITION_BLOCKS;
    }

    public function addMediaBlock(string $placementUuid, string $type): void
    {
        abort_unless(in_array($type, [
            SpaceContentBlock::TYPE_IMAGE,
            SpaceContentBlock::TYPE_AUDIO,
            SpaceContentBlock::TYPE_VIDEO,
            SpaceContentBlock::TYPE_FILE,
        ], true), 422);

        $this->blocks[] = [
            'logical_uuid' => (string) Str::uuid(),
            'type' => $type,
            'data' => ['asset_placement_uuid' => $placementUuid, 'caption' => ''],
            'style' => [],
        ];
        $this->compositionMode = SpaceContentRevision::COMPOSITION_BLOCKS;
    }

    public function removeBlock(int $index): void
    {
        if (! array_key_exists($index, $this->blocks)) {
            return;
        }

        unset($this->blocks[$index]);
        $this->blocks = array_values($this->blocks);
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0 || ! isset($this->blocks[$index], $this->blocks[$index - 1])) {
            return;
        }

        [$this->blocks[$index - 1], $this->blocks[$index]] = [$this->blocks[$index], $this->blocks[$index - 1]];
    }

    public function moveDown(int $index): void
    {
        if (! isset($this->blocks[$index], $this->blocks[$index + 1])) {
            return;
        }

        [$this->blocks[$index + 1], $this->blocks[$index]] = [$this->blocks[$index], $this->blocks[$index + 1]];
    }

    public function save(UpdateSpaceContentBlocks $update): void
    {
        $prepared = $this->blocks;
        foreach ($prepared as &$block) {
            if (($block['type'] ?? null) === SpaceContentBlock::TYPE_LIST) {
                $itemsText = (string) ($block['data']['items_text'] ?? '');
                $block['data']['items'] = collect(preg_split('/\R/u', $itemsText) ?: [])
                    ->map(static fn (string $item): string => trim($item))
                    ->filter()
                    ->values()
                    ->all();
                unset($block['data']['items_text']);
            }
        }
        unset($block);

        $this->content = $update->execute($this->content, $this->user(), $this->compositionMode, $prepared);
        $this->reload();
        session()->flash('status', __('blocks.saved'));
    }

    public function render(): View
    {
        $revision = $this->editableRevision();
        $revision->loadMissing('assets');
        $version = $revision->definitionVersion()->firstOrFail();

        $fields = collect($version->schema['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['key'] ?? null))
            ->values();

        $media = DB::table('space_content_revision_assets as placement')
            ->join('assets as asset', 'asset.id', '=', 'placement.asset_id')
            ->where('placement.space_content_revision_id', $revision->id)
            ->orderBy('placement.position')
            ->get([
                'placement.uuid as placement_uuid',
                'placement.caption',
                'asset.uuid as asset_uuid',
                'asset.original_filename',
                'asset.mime_type',
            ]);

        return view('livewire.groups.space-content-block-studio', compact('revision', 'fields', 'media'));
    }

    private function reload(): void
    {
        $revision = $this->editableRevision();
        $this->compositionMode = $revision->composition_mode;
        $this->blocks = $revision->blocks()->get()->map(function (SpaceContentBlock $block): array {
            $data = $block->data;
            if ($block->type === SpaceContentBlock::TYPE_LIST) {
                $data['items_text'] = implode("\n", is_array($data['items'] ?? null) ? $data['items'] : []);
                unset($data['items']);
            }

            return [
                'logical_uuid' => $block->logical_uuid,
                'type' => $block->type,
                'data' => $data,
                'style' => $block->style ?? [],
            ];
        })->values()->all();
    }

    private function editableRevision(): SpaceContentRevision
    {
        $current = $this->content->fresh();
        abort_unless($current instanceof SpaceContent, 404);
        Gate::forUser($this->user())->authorize('update', $current);
        $this->content = $current;

        $revision = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 404);

        return $revision;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
