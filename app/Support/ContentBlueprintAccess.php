<?php

namespace App\Support;

use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
use App\Models\User;

class ContentBlueprintAccess
{
    public function view(User $user, Context $context, ContentBlueprintVersion $version): bool
    {
        $version->loadMissing('blueprint');
        $blueprint = $version->blueprint;

        if ($blueprint->status !== ContentBlueprint::STATUS_ACTIVE
            || (int) $blueprint->active_version_id !== (int) $version->id
            || $version->published_at === null
            || ! $version->supportsContext($context->kind)) {
            return false;
        }

        $current = User::query()->with('actor')->find($user->id);

        if (! $current instanceof User || ! $current->actor instanceof Actor) {
            return false;
        }

        return match ($blueprint->scope) {
            ContentBlueprint::SCOPE_SYSTEM => true,
            ContentBlueprint::SCOPE_ACTOR => (int) $blueprint->owner_actor_id === (int) $current->actor->id,
            ContentBlueprint::SCOPE_CONTEXT => (int) $blueprint->context_id === (int) $context->id,
            default => false,
        };
    }
}
