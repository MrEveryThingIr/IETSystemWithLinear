<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentBlocks;
use App\Support\SpaceContentRevisionComposition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UpdateSpaceContentBlocks
{
    public function __construct(
        private readonly SpaceContentBlocks $blocks,
        private readonly SpaceContentRevisionComposition $composition,
    ) {}

    /** @param list<array<string, mixed>> $blocks */
    public function execute(
        SpaceContent $content,
        User $user,
        string $compositionMode,
        array $blocks,
    ): SpaceContent {
        abort_unless(in_array($compositionMode, SpaceContentRevision::COMPOSITION_MODES, true), 422, 'Choose a supported Content composition mode.');

        return DB::transaction(function () use ($content, $user, $compositionMode, $blocks): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content cannot change its document blocks.');

            $source = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
            abort_unless($source instanceof SpaceContentRevision, 422, 'Content has no revision to compose.');
            $source = SpaceContentRevision::query()->lockForUpdate()->findOrFail($source->id);

            $normalized = $compositionMode === SpaceContentRevision::COMPOSITION_BLOCKS
                ? $this->blocks->normalize($source, $blocks)
                : [];
            abort_if($compositionMode === SpaceContentRevision::COMPOSITION_BLOCKS && $normalized === [], 422, 'A block document needs at least one block.');

            $actor = $this->actor($user);
            $nextRevision = ((int) $current->revisions()->max('revision')) + 1;
            $revision = $current->revisions()->create([
                'definition_version_id' => $source->definition_version_id,
                'revision' => $nextRevision,
                'title' => $source->title,
                'payload' => $source->payload,
                'render_template_key' => $source->render_template_key,
                'render_template_uuid' => $source->render_template_uuid,
                'presentation' => $source->presentation,
                'composition_mode' => $compositionMode,
                'created_by_actor_id' => $actor->id,
            ]);

            $placementMap = $this->composition->copyAssets($source, $revision);
            $this->composition->copyRelationships($source, $revision);

            foreach ($normalized as $position => $block) {
                $data = $block['data'];
                if (isset($data['asset_placement_uuid']) && is_string($data['asset_placement_uuid'])) {
                    $mapped = $placementMap[$data['asset_placement_uuid']] ?? null;
                    abort_unless(is_string($mapped), 422, 'A media block references media that is no longer available.');
                    $data['asset_placement_uuid'] = $mapped;
                }

                DB::table('space_content_blocks')->insert([
                    'uuid' => (string) Str::uuid(),
                    'logical_uuid' => $block['logical_uuid'],
                    'space_content_revision_id' => $revision->id,
                    'parent_block_id' => null,
                    'type' => $block['type'],
                    'position' => $position,
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'style' => $block['style'] !== []
                        ? json_encode($block['style'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $current->applyLifecycle([
                'current_revision' => $nextRevision,
                'draft_revision_id' => $revision->id,
            ]);

            return $current->refresh();
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
