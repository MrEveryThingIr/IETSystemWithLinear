<?php

namespace App\Actions\Ai;

use App\Models\Actor;
use App\Models\AiAssistanceRun;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\Ai\OpenAiContentPlanner;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\Gate;

class PlanContentChanges
{
    public function __construct(private readonly OpenAiContentPlanner $planner) {}

    public function execute(SpaceContent $content, User $user, string $prompt): AiAssistanceRun
    {
        $prompt = trim($prompt);
        abort_if($prompt === '' || mb_strlen($prompt) > 8000, 422, 'Describe the change you want in 8,000 characters or fewer.');

        $current = SpaceContent::query()
            ->with(['context', 'definition'])
            ->findOrFail($content->id);

        Gate::forUser($user)->authorize('update', $current);

        $revision = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
        abort_unless($revision instanceof SpaceContentRevision, 422, 'Content has no revision for AI assistance.');
        $revision->loadMissing(['definitionVersion', 'blocks', 'assets']);

        $snapshot = $this->snapshot($current, $revision);
        $planned = $this->planner->plan($snapshot, $prompt);
        $actor = $this->actor($user);

        return AiAssistanceRun::query()->create([
            'context_id' => $current->context_id,
            'space_content_id' => $current->id,
            'base_revision_id' => $revision->id,
            'requested_by_actor_id' => $actor->id,
            'status' => AiAssistanceRun::STATUS_PLANNED,
            'provider' => $planned['provider'],
            'model' => $planned['model'],
            'external_response_id' => $planned['external_response_id'],
            'prompt' => $prompt,
            'request_hash' => SpaceContentSchema::hashArray([
                'prompt' => $prompt,
                'snapshot' => $snapshot,
            ]),
            'proposal' => $planned['proposal'],
            'planned_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(SpaceContent $content, SpaceContentRevision $revision): array
    {
        return [
            'content_uuid' => $content->uuid,
            'revision_uuid' => $revision->uuid,
            'title' => $revision->title,
            'fields' => $revision->definitionVersion->schema['fields'] ?? [],
            'payload' => $revision->payload,
            'composition_mode' => $revision->composition_mode,
            'blocks' => $revision->blocks->map(static fn ($block): array => [
                'logical_uuid' => $block->logical_uuid,
                'type' => $block->type,
                'data' => $block->data,
                'style' => $block->style ?? [],
            ])->values()->all(),
            'presentation' => $revision->presentation ?? [],
            'render_template_key' => $revision->render_template_key,
            'assets' => $revision->assets->map(static fn ($asset): array => [
                'uuid' => $asset->uuid,
                'kind' => $asset->mediaKind(),
                'filename' => $asset->original_filename,
            ])->values()->all(),
        ];
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
