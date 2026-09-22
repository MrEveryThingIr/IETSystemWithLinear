<?php

namespace App\Actions\Content;

use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
use App\Models\User;
use App\Support\ContentBlueprintAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CloneContentBlueprint
{
    public function __construct(private readonly ContentBlueprintAccess $access) {}

    public function execute(
        Context $context,
        ContentBlueprintVersion $sourceVersion,
        User $user,
        string $name,
        ?string $description = null,
    ): ContentBlueprint {
        $name = trim($name);
        abort_if($name === '' || mb_strlen($name) > 120, 422, 'Content Blueprint name is required and may not exceed 120 characters.');

        $description = $description !== null ? trim($description) : null;
        $description = $description === '' ? null : $description;
        abort_if($description !== null && mb_strlen($description) > 2000, 422, 'Content Blueprint description may not exceed 2000 characters.');

        return DB::transaction(function () use ($context, $sourceVersion, $user, $name, $description): ContentBlueprint {
            $currentContext = Context::query()->lockForUpdate()->findOrFail($context->id);
            Gate::forUser($user)->authorize('view', $currentContext);

            $source = ContentBlueprintVersion::query()
                ->with('blueprint')
                ->lockForUpdate()
                ->findOrFail($sourceVersion->id);

            abort_unless(
                $this->access->view($user, $currentContext, $source),
                404,
                'The selected Content Blueprint is unavailable in this Context.',
            );

            $actor = $this->actor($user);
            $uuid = (string) Str::uuid();
            $baseSlug = Str::slug($name);
            abort_if($baseSlug === '', 422, 'Content Blueprint name must produce a valid slug.');
            $slug = mb_substr($baseSlug, 0, 100).'-'.substr(str_replace('-', '', $uuid), 0, 12);

            $blueprint = ContentBlueprint::query()->create([
                'uuid' => $uuid,
                'slug' => $slug,
                'name' => $name,
                'description' => $description ?? $source->blueprint->description,
                'category' => $source->blueprint->category,
                'scope' => ContentBlueprint::SCOPE_ACTOR,
                'owner_actor_id' => $actor->id,
                'context_id' => null,
                'status' => ContentBlueprint::STATUS_ACTIVE,
                'current_version' => 1,
                'cloned_from_version_id' => $source->id,
            ]);

            $version = $blueprint->versions()->create([
                'version' => 1,
                'definition_schema' => $source->definition_schema,
                'initial_blocks' => $source->initial_blocks,
                'render_template_key' => $source->render_template_key,
                'presentation' => $source->presentation,
                'context_kinds' => $source->context_kinds,
                'concept_defaults' => $source->concept_defaults,
                'interaction_defaults' => $source->interaction_defaults,
                'authoring' => $source->authoring,
                'created_by_actor_id' => $actor->id,
                'published_at' => null,
            ]);
            $version->publish();

            $blueprint->applyLifecycle([
                'status' => ContentBlueprint::STATUS_ACTIVE,
                'current_version' => 1,
                'active_version_id' => $version->id,
                'draft_version_id' => null,
            ]);

            return $blueprint->refresh()->load(['activeVersion', 'clonedFromVersion']);
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
