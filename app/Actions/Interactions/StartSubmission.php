<?php

namespace App\Actions\Interactions;

use App\Models\Actor;
use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StartSubmission
{
    public function execute(InteractionDefinitionVersion $version, User $user): Submission
    {
        return DB::transaction(function () use ($version, $user): Submission {
            $currentVersion = InteractionDefinitionVersion::query()
                ->with(['definition.context'])
                ->lockForUpdate()
                ->findOrFail($version->id);
            $definition = InteractionDefinition::query()
                ->with('context')
                ->lockForUpdate()
                ->findOrFail($currentVersion->interaction_definition_id);

            Gate::forUser($user)->authorize('submit', $definition);

            abort_unless(
                $definition->status === InteractionDefinition::STATUS_ACTIVE
                    && (int) $definition->active_version_id === (int) $currentVersion->id
                    && $currentVersion->published_at !== null,
                409,
                'This Interaction Definition version is not active.',
            );

            $actor = $this->actor($user);
            Actor::query()->lockForUpdate()->findOrFail($actor->id);

            $existing = Submission::query()
                ->where('interaction_definition_version_id', $currentVersion->id)
                ->where('submitted_by_actor_id', $actor->id)
                ->where('status', Submission::STATUS_DRAFT)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Submission) {
                return $existing;
            }

            $attemptCount = Submission::query()
                ->where('interaction_definition_version_id', $currentVersion->id)
                ->where('submitted_by_actor_id', $actor->id)
                ->count();
            $maxAttempts = $currentVersion->settings['max_attempts'] ?? null;

            abort_if(
                is_int($maxAttempts) && $attemptCount >= $maxAttempts,
                422,
                'No further attempts are available for this interaction.',
            );

            return Submission::query()->create([
                'context_id' => $definition->context_id,
                'interaction_definition_version_id' => $currentVersion->id,
                'space_content_revision_id' => $currentVersion->space_content_revision_id,
                'submitted_by_actor_id' => $actor->id,
                'attempt_number' => $attemptCount + 1,
                'status' => Submission::STATUS_DRAFT,
            ]);
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
