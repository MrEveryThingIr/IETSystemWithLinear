<?php

namespace App\Actions\Content;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use App\Models\SpaceContentAnnotationDisposition;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecordContentAnnotationDisposition
{
    public function execute(
        SpaceContentAnnotation $annotation,
        User $user,
        string $status,
        ?SpaceContentRevision $incorporatedRevision = null,
        ?string $note = null,
    ): SpaceContentAnnotationDisposition {
        $note = $note !== null ? trim($note) : null;
        $note = $note === '' ? null : $note;

        abort_unless(in_array($status, SpaceContentAnnotationDisposition::STATUSES, true), 422);
        abort_if($note !== null && mb_strlen($note) > 2000, 422);

        return DB::transaction(function () use (
            $annotation,
            $user,
            $status,
            $incorporatedRevision,
            $note,
        ): SpaceContentAnnotationDisposition {
            $currentAnnotation = SpaceContentAnnotation::query()
                ->with(['content.context', 'revision'])
                ->lockForUpdate()
                ->findOrFail($annotation->id);

            abort_if($currentAnnotation->parent_annotation_id !== null, 422);

            /** @var SpaceContent $content */
            $content = $currentAnnotation->content;
            Gate::forUser($user)->authorize('update', $content);

            $revision = null;
            if ($incorporatedRevision instanceof SpaceContentRevision) {
                $revision = SpaceContentRevision::query()
                    ->lockForUpdate()
                    ->findOrFail($incorporatedRevision->id);
            }

            if ($status === SpaceContentAnnotationDisposition::STATUS_INCORPORATED) {
                abort_unless($revision instanceof SpaceContentRevision, 422);
                abort_unless(
                    (int) $revision->space_content_id === (int) $currentAnnotation->space_content_id
                        && $revision->revision > $currentAnnotation->revision->revision
                        && $revision->hasVerifiableManifest(),
                    422,
                );
            } else {
                abort_if($revision instanceof SpaceContentRevision, 422);
            }

            $actor = $this->actor($user);

            $existing = SpaceContentAnnotationDisposition::query()
                ->where('annotation_id', $currentAnnotation->id)
                ->where('status', $status)
                ->where('incorporated_revision_id', $revision?->id)
                ->where('note', $note)
                ->where('resolved_by_actor_id', $actor->id)
                ->latest('id')
                ->first();

            if ($existing instanceof SpaceContentAnnotationDisposition) {
                return $existing;
            }

            return SpaceContentAnnotationDisposition::query()->create([
                'annotation_id' => $currentAnnotation->id,
                'status' => $status,
                'incorporated_revision_id' => $revision?->id,
                'note' => $note,
                'resolved_by_actor_id' => $actor->id,
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
