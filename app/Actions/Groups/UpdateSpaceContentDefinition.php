<?php

namespace App\Actions\Groups;

use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\User;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateSpaceContentDefinition
{
    /** @param array<int, mixed> $fields */
    public function execute(
        SpaceContentDefinition $definition,
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

        return DB::transaction(function () use ($definition, $user, $name, $description, $schema): SpaceContentDefinition {
            $current = SpaceContentDefinition::query()->with('space')->lockForUpdate()->findOrFail($definition->id);
            Gate::forUser($user)->authorize('manage', $current);
            abort_unless($current->status === 'draft', 422, 'Only draft Content Definitions may be changed.');

            /** @var SpaceContentDefinitionVersion $version */
            $version = $current->versions()
                ->where('version', $current->current_version)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($version->published_at === null, 422, 'Published Content Definition versions are immutable.');

            $version->update([
                'schema' => $schema,
                'display' => null,
            ]);
            $current->update([
                'name' => $name,
                'description' => $description,
            ]);

            return $current->refresh();
        }, 3);
    }

}