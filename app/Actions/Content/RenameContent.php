<?php

namespace App\Actions\Content;

use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Changes the title in one step.
 *
 * Published Content without unpublished edits gets a new version that differs
 * only in its title, published right away, so readers see the new name at
 * once. When unpublished edits are waiting, the waiting draft is renamed and
 * the name goes live with the next publish (publishing those edits is the
 * author's choice, never a side effect of renaming).
 */
class RenameContent
{
    public function __construct(
        private readonly ReviseSpaceContent $revise,
        private readonly PublishSpaceContent $publish,
    ) {}

    /** @return array{content: SpaceContent, live: bool} */
    public function execute(SpaceContent $content, User $user, string $title): array
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 255) {
            throw ValidationException::withMessages(['renameTitle' => __('studio.rename.invalid')]);
        }

        return DB::transaction(function () use ($content, $user, $title): array {
            $current = SpaceContent::query()->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('update', $current);

            $source = $current->draftRevisionRecord() ?? $current->activeRevisionRecord();
            abort_unless($source instanceof SpaceContentRevision, 422);

            if ($source->title === $title) {
                return ['content' => $current, 'live' => $current->status === 'published' && $current->draft_revision_id === null];
            }

            $hadDraft = $current->draft_revision_id !== null;
            $updated = $this->revise->execute($current, $user, $title, (array) $source->payload);

            if ($updated->status === 'published' && ! $hadDraft) {
                return ['content' => $this->publish->execute($updated, $user), 'live' => true];
            }

            return ['content' => $updated, 'live' => false];
        }, 3);
    }
}
