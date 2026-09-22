<?php

namespace App\Actions\Contexts;

use App\Models\Actor;
use App\Models\Context;
use App\Models\SpaceContentDefinition;
use App\Models\User;
use App\Support\ContextScope;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateContextContentDefinition
{
    /** @param array<int, mixed> $fields */
    public function execute(
        Context $context,
        User $user,
        string $name,
        ?string $description,
        array $fields,
    ): SpaceContentDefinition {
        $name = trim($name);
        abort_if($name === '' || mb_strlen($name) > 120, 422, 'Content Definition name is required and may not exceed 120 characters.');
        $description = $description !== null ? trim($description) : null;
        $description = $description === '' ? null : $description;
        abort_if($description !== null && mb_strlen($description) > 2000, 422, 'Content Definition description may not exceed 2000 characters.');

        $schema = SpaceContentSchema::normalizeDefinitionFields($fields);
        $slug = Str::slug($name);
        abort_if($slug === '' || mb_strlen($slug) > 120, 422, 'Content Definition name must produce a valid slug.');

        return DB::transaction(function () use ($context, $user, $name, $description, $schema, $slug): SpaceContentDefinition {
            $currentContext = Context::query()->lockForUpdate()->findOrFail($context->id);
            Gate::forUser($user)->authorize('manageDefinitions', $currentContext);

            $actor = $this->actor($user);
            abort_if(
                $currentContext->contentDefinitions()->where('slug', $slug)->exists(),
                422,
                'A Content Definition with this slug already exists in the Context.',
            );

            $definition = SpaceContentDefinition::query()->create([
                'context_id' => $currentContext->id,
                'group_space_id' => ContextScope::legacyGroupSpaceId($currentContext),
                'created_by_actor_id' => $actor->id,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'status' => 'draft',
                'current_version' => 1,
            ]);

            $version = $definition->versions()->create([
                'version' => 1,
                'schema' => $schema,
                'display' => null,
                'created_by_actor_id' => $actor->id,
                'published_at' => null,
            ]);

            $definition->applyLifecycle([
                'current_version' => 1,
                'active_version_id' => null,
                'draft_version_id' => $version->id,
            ]);

            return $definition->refresh();
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
