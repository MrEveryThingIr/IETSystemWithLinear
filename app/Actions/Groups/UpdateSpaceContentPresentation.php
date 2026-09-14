<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentRenderTemplate;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\SpaceContentPresentation;
use App\Support\SpaceContentRevisionComposition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateSpaceContentPresentation
{
    public function __construct(
        private readonly SpaceContentPresentation $presentations,
        private readonly SpaceContentRevisionComposition $composition,
    ) {}

    /** @param array<string, mixed> $overrides */
    public function execute(
        SpaceContent $content,
        User $user,
        string $templateSource,
        array $overrides,
    ): SpaceContent {
        return DB::transaction(function () use ($content, $user, $templateSource, $overrides): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);
            abort_if($current->status === 'archived', 422, 'Archived Content cannot change presentation.');

            $source = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
            abort_unless($source instanceof SpaceContentRevision, 422, 'Content has no revision to style.');
            $source = SpaceContentRevision::query()->lockForUpdate()->findOrFail($source->id);

            [$baseKey, $templateUuid, $templateTokens] = $this->resolveTemplateSource($current, $templateSource);
            $version = $source->definitionVersion()->firstOrFail();
            $fieldKeys = collect($version->schema['fields'] ?? [])
                ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['key'] ?? null))
                ->map(fn (array $field): string => (string) $field['key'])
                ->values()
                ->all();
            $resolved = $this->presentations->resolve(
                $baseKey,
                array_replace_recursive($templateTokens, $overrides),
                $fieldKeys,
            );

            $nextRevision = ((int) $current->revisions()->max('revision')) + 1;
            $actor = $this->actor($user);
            $revision = $current->revisions()->create([
                'definition_version_id' => $source->definition_version_id,
                'revision' => $nextRevision,
                'title' => $source->title,
                'payload' => $source->payload,
                'render_template_key' => $baseKey,
                'render_template_uuid' => $templateUuid,
                'presentation' => $resolved,
                'created_by_actor_id' => $actor->id,
            ]);

            $this->composition->copyAssets($source, $revision);
            $this->composition->copyRelationships($source, $revision);

            $current->applyLifecycle([
                'current_revision' => $nextRevision,
                'draft_revision_id' => $revision->id,
            ]);

            return $current->refresh();
        }, 3);
    }

    /** @return array{string, ?string, array<string, mixed>} */
    private function resolveTemplateSource(SpaceContent $content, string $source): array
    {
        if (str_starts_with($source, 'custom:')) {
            $uuid = substr($source, 7);
            $template = SpaceContentRenderTemplate::query()
                ->where('uuid', $uuid)
                ->where('group_space_id', $content->group_space_id)
                ->where('status', SpaceContentRenderTemplate::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();
            abort_unless($template instanceof SpaceContentRenderTemplate, 422, 'Choose an available saved rendering template.');

            return [$template->base_key, $template->uuid, $template->tokens];
        }

        $key = str_starts_with($source, 'builtin:') ? substr($source, 8) : $source;
        abort_unless(in_array($key, $this->presentations->keys(), true), 422, 'Choose a supported rendering template.');

        return [$key, null, []];
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
