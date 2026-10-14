<?php

namespace App\Actions\Content;

use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentAnnotationEndorsement;
use App\Models\User;
use App\Support\ContentRolePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Endorse (or withdraw an endorsement of) an answer, e.g. a teacher marking it correct. */
class ToggleAnnotationEndorsement
{
    public function __construct(private readonly ContentRolePolicy $roles) {}

    public function execute(SpaceContentAnnotation $annotation, User $user): bool
    {
        return DB::transaction(function () use ($annotation, $user): bool {
            /** @var SpaceContentAnnotation $current */
            $current = SpaceContentAnnotation::query()->lockForUpdate()->findOrFail($annotation->id);
            /** @var SpaceContent $content */
            $content = SpaceContent::query()->findOrFail($current->space_content_id);

            Gate::forUser($user)->authorize('view', $content);
            abort_unless($current->status === SpaceContentAnnotation::STATUS_ACTIVE, 422, 'Only active notes can be endorsed.');
            abort_unless(
                in_array($current->kind, [SpaceContentAnnotation::KIND_ANSWER, SpaceContentAnnotation::KIND_REPLY], true),
                422,
                'Only answers and replies can be endorsed.',
            );
            abort_unless($this->roles->canEndorse($content, $user), 403, 'Your role cannot endorse answers here.');

            $actorId = (int) $user->actor?->id;
            abort_if($actorId === 0, 403);

            $existing = SpaceContentAnnotationEndorsement::query()
                ->where('annotation_id', $current->id)
                ->where('actor_id', $actorId)
                ->first();

            if ($existing instanceof SpaceContentAnnotationEndorsement) {
                $existing->delete();

                return false;
            }

            SpaceContentAnnotationEndorsement::query()->create([
                'annotation_id' => $current->id,
                'actor_id' => $actorId,
                'created_at' => now(),
            ]);

            return true;
        });
    }
}
