<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentRenderTemplate;
use App\Models\User;
use App\Support\SpaceContentPresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveSpaceContentRenderTemplate
{
    public function __construct(private readonly SpaceContentPresentation $presentations) {}

    /** @param array<string, mixed> $tokens */
    public function execute(
        SpaceContent $content,
        User $user,
        string $name,
        string $baseKey,
        array $tokens,
    ): SpaceContentRenderTemplate {
        $name = trim($name);
        abort_if($name === '' || mb_strlen($name) > 120, 422, 'Template name is required and may not exceed 120 characters.');
        abort_unless(in_array($baseKey, $this->presentations->keys(), true), 422, 'Choose a supported base template.');

        return DB::transaction(function () use ($content, $user, $name, $baseKey, $tokens): SpaceContentRenderTemplate {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);
            $revision = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
            abort_unless($revision !== null, 422, 'Content has no revision to use as a template source.');
            $version = $revision->definitionVersion()->firstOrFail();
            $fieldKeys = collect($version->schema['fields'] ?? [])
                ->filter(fn (mixed $field): bool => is_array($field) && is_string($field['key'] ?? null))
                ->map(fn (array $field): string => (string) $field['key'])
                ->values()
                ->all();
            $resolved = $this->presentations->resolve($baseKey, $tokens, $fieldKeys);
            $actor = $this->actor($user);

            $template = SpaceContentRenderTemplate::query()->create([
                'group_space_id' => $current->group_space_id,
                'creator_actor_id' => $actor->id,
                'name' => $name,
                'base_key' => $baseKey,
                'tokens' => $resolved,
                'status' => SpaceContentRenderTemplate::STATUS_ACTIVE,
            ]);

            $template->favoritedBy()->syncWithoutDetaching([$actor->id]);

            return $template->refresh();
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
