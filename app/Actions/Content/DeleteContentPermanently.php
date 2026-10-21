<?php

namespace App\Actions\Content;

use App\Models\Asset;
use App\Models\SpaceContent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Removes a piece of Content and everything that belongs only to it:
 * every version, its sections, files, the author's own notes, sharing links
 * and placements.
 *
 * Content is kept as history everywhere else in the system, so this is the
 * one deliberate exception and it is only allowed while nobody else has
 * engaged with it: no other person's notes, answers, reactions or form
 * responses, and nothing elsewhere (a course, an agreement, evidence, a
 * business page) that points to it. Otherwise the author can only archive it.
 *
 * Rows are removed with the query builder on purpose: the models refuse
 * deletes so that nothing else can erase history by accident.
 */
class DeleteContentPermanently
{
    /**
     * @return array{
     *     blockers: array<string, int>,
     *     removes: array<string, int>,
     * }
     */
    public function assess(SpaceContent $content, User $user): array
    {
        $actorId = $this->actorId($user);
        $revisionIds = $this->revisionIds($content);
        $definitionIds = DB::table('interaction_definitions')->where('space_content_id', $content->id)->pluck('id');
        $definitionVersionIds = DB::table('interaction_definition_versions')
            ->whereIn('interaction_definition_id', $definitionIds)
            ->orWhereIn('space_content_revision_id', $revisionIds)
            ->pluck('id');
        $submissions = DB::table('submissions')
            ->where(fn ($query) => $query->whereIn('space_content_revision_id', $revisionIds)->orWhereIn('interaction_definition_version_id', $definitionVersionIds));
        $evidenceIds = DB::table('content_evidence_references')->where('space_content_id', $content->id)->pluck('id');
        $ownSubmissionIds = (clone $submissions)->where('submitted_by_actor_id', $actorId)->pluck('id');

        $blockers = array_filter([
            'others_notes' => DB::table('space_content_annotations')->where('space_content_id', $content->id)->where('author_actor_id', '!=', $actorId)->count()
                + DB::table('space_content_annotation_endorsements')
                    ->whereIn('annotation_id', DB::table('space_content_annotations')->where('space_content_id', $content->id)->pluck('id'))
                    ->where('actor_id', '!=', $actorId)->count(),
            'others_reactions' => DB::table('space_content_reactions')->where('space_content_id', $content->id)->where('actor_id', '!=', $actorId)->count(),
            'others_responses' => (clone $submissions)->where('submitted_by_actor_id', '!=', $actorId)->count()
                + DB::table('evaluations')->whereIn('submission_id', $ownSubmissionIds)->where('evaluator_actor_id', '!=', $actorId)->count(),
            'used_as_evidence' => DB::table('fulfillment_evidence_references')->whereIn('content_evidence_reference_id', $evidenceIds)->count()
                + DB::table('plan_occurrence_evidence_references')->whereIn('content_evidence_reference_id', $evidenceIds)->count()
                + DB::table('conversation_message_evidence_references')->whereIn('content_evidence_reference_id', $evidenceIds)->count()
                + DB::table('submission_responses')->whereIn('content_evidence_reference_id', $evidenceIds)->whereNotIn('submission_id', $ownSubmissionIds)->count(),
            'used_in_agreements' => DB::table('contract_versions')->whereIn('terms_content_revision_id', $revisionIds)->count()
                + DB::table('proposal_versions')->whereIn('terms_content_revision_id', $revisionIds)->count(),
            'part_of_other_content' => DB::table('space_content_revision_relationships')
                ->where(fn ($query) => $query->where('child_content_id', $content->id)->orWhereIn('child_revision_id', $revisionIds))
                ->whereNotIn('parent_revision_id', $revisionIds)
                ->count(),
            'business_page' => DB::table('business_listing_versions')->where('presentation_content_id', $content->id)->count(),
        ]);

        $removes = array_filter([
            'versions' => $revisionIds->count(),
            'own_notes' => DB::table('space_content_annotations')->where('space_content_id', $content->id)->count(),
            'files' => DB::table('space_content_revision_assets')->whereIn('space_content_revision_id', $revisionIds)->distinct()->count('asset_id'),
            'invitation_links' => DB::table('content_invitations')->where('space_content_id', $content->id)->whereNull('revoked_at')->count(),
            'placements' => DB::table('content_placements')->where('space_content_id', $content->id)->where('status', 'active')->count(),
            'public' => $content->isPubliclyReadable() ? 1 : 0,
        ]);

        return ['blockers' => $blockers, 'removes' => $removes];
    }

    /** @return array{title: string} */
    public function execute(SpaceContent $content, User $user, string $confirmation): array
    {
        Gate::forUser($user)->authorize('delete', $content);

        $title = (string) ($content->draftRevisionRecord()->title ?? $content->activeRevisionRecord()->title ?? '');
        if (trim($confirmation) !== trim($title)) {
            throw ValidationException::withMessages(['deleteConfirmation' => __('studio.delete.confirmation_mismatch')]);
        }

        $files = DB::transaction(function () use ($content, $user): array {
            $locked = SpaceContent::query()->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('delete', $locked);

            if ($this->assess($locked, $user)['blockers'] !== []) {
                throw ValidationException::withMessages(['deleteConfirmation' => __('studio.delete.blocked')]);
            }

            return $this->purge($locked);
        }, 3);

        foreach ($files as [$disk, $key]) {
            try {
                Storage::disk($disk)->delete($key);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return ['title' => $title];
    }

    /** @return list<array{0: string, 1: string}> files to remove after commit */
    private function purge(SpaceContent $content): array
    {
        $id = $content->id;
        $revisionIds = $this->revisionIds($content);
        $annotationIds = DB::table('space_content_annotations')->where('space_content_id', $id)->pluck('id');
        $definitionIds = DB::table('interaction_definitions')->where('space_content_id', $id)->pluck('id');
        $definitionVersionIds = DB::table('interaction_definition_versions')
            ->whereIn('interaction_definition_id', $definitionIds)
            ->orWhereIn('space_content_revision_id', $revisionIds)
            ->pluck('id');
        $submissionIds = DB::table('submissions')
            ->whereIn('space_content_revision_id', $revisionIds)
            ->orWhereIn('interaction_definition_version_id', $definitionVersionIds)
            ->pluck('id');
        $assetIds = DB::table('space_content_revision_assets')->whereIn('space_content_revision_id', $revisionIds)->pluck('asset_id')
            ->merge(DB::table('space_content_annotation_assets')->whereIn('annotation_id', $annotationIds)->pluck('asset_id'))
            ->merge(DB::table('media_upload_sessions')->where('space_content_id', $id)->whereNotNull('asset_id')->pluck('asset_id'))
            ->unique()->values();
        $files = DB::table('media_upload_sessions')->where('space_content_id', $id)->whereNotNull('temp_key')
            ->pluck('temp_key')->map(fn ($key): array => ['local', (string) $key])->all();

        // Notifications about this Content, delivered or waiting.
        $outbox = DB::table('notification_outbox')->where('subject_uuid', $content->uuid)->pluck('uuid');
        DB::table('notifications')->whereIn('id', $outbox)->delete();
        DB::table('notification_outbox')->whereIn('uuid', $outbox)->delete();

        DB::table('content_invitations')->where('space_content_id', $id)->delete();
        DB::table('content_placements')->where('space_content_id', $id)->delete();
        DB::table('media_upload_sessions')->where('space_content_id', $id)->delete();
        DB::table('ai_assistance_runs')->where('space_content_id', $id)->delete();

        DB::table('submission_responses')->whereIn('submission_id', $submissionIds)->delete();
        DB::table('evaluations')->whereIn('submission_id', $submissionIds)->delete();
        DB::table('submissions')->whereIn('id', $submissionIds)->delete();
        DB::table('interaction_definitions')->whereIn('id', $definitionIds)->update(['active_version_id' => null, 'draft_version_id' => null]);
        DB::table('interaction_definition_versions')->whereIn('id', $definitionVersionIds)->delete();
        DB::table('interaction_definitions')->whereIn('id', $definitionIds)->delete();
        DB::table('content_evidence_references')->where('space_content_id', $id)->delete();

        DB::table('space_content_annotation_endorsements')->whereIn('annotation_id', $annotationIds)->delete();
        DB::table('space_content_annotation_dispositions')->whereIn('annotation_id', $annotationIds)->delete();
        DB::table('space_content_annotation_dispositions')->whereIn('incorporated_revision_id', $revisionIds)->delete();
        DB::table('space_content_annotation_anchors')->whereIn('annotation_id', $annotationIds)->delete();
        DB::table('space_content_annotation_assets')->whereIn('annotation_id', $annotationIds)->delete();
        DB::table('space_content_annotations')->whereIn('id', $annotationIds)->update(['parent_annotation_id' => null]);
        DB::table('space_content_annotations')->whereIn('id', $annotationIds)->delete();
        DB::table('space_content_reactions')->where('space_content_id', $id)->delete();

        DB::table('concept_assertions')->where(fn ($query) => $query
            ->where(fn ($q) => $q->where('subject_type', 'space_content')->where('subject_id', $id))
            ->orWhere(fn ($q) => $q->where('subject_type', 'space_content_revision')->whereIn('subject_id', $revisionIds)))
            ->delete();

        DB::table('space_content_revision_relationships')->whereIn('parent_revision_id', $revisionIds)->delete();
        DB::table('space_contents')->where('id', $id)->update(['active_revision_id' => null, 'draft_revision_id' => null]);
        DB::table('space_content_blocks')->whereIn('space_content_revision_id', $revisionIds)->update(['parent_block_id' => null]);
        DB::table('space_content_blocks')->whereIn('space_content_revision_id', $revisionIds)->delete();
        DB::table('space_content_revision_assets')->whereIn('space_content_revision_id', $revisionIds)->delete();
        DB::table('space_content_lifecycle_events')->where('space_content_id', $id)->delete();
        DB::table('space_content_revisions')->whereIn('id', $revisionIds)->delete();
        DB::table('space_contents')->where('id', $id)->delete();

        return [...$files, ...$this->purgeUnusedAssets($assetIds)];
    }

    /**
     * Files no other record points to are removed; shared ones stay.
     *
     * @param  Collection<int, mixed>  $assetIds
     * @return list<array{0: string, 1: string}>
     */
    private function purgeUnusedAssets(Collection $assetIds): array
    {
        $files = [];

        foreach ($assetIds as $assetId) {
            $used = collect(Asset::REFERENCE_TABLES)
                ->contains(fn (string $table): bool => DB::table($table)->where('asset_id', $assetId)->exists());
            if ($used) {
                continue;
            }

            $asset = DB::table('assets')->where('id', $assetId)->first(['disk', 'storage_key']);
            if ($asset === null) {
                continue;
            }

            foreach (['asset_media_variants', 'asset_media_tracks'] as $table) {
                foreach (DB::table($table)->where('asset_id', $assetId)->get(['disk', 'storage_key']) as $derived) {
                    if ($derived->storage_key) {
                        $files[] = [(string) $derived->disk, (string) $derived->storage_key];
                    }
                }
                DB::table($table)->where('asset_id', $assetId)->delete();
            }

            DB::table('assets')->where('id', $assetId)->delete();
            $files[] = [(string) $asset->disk, (string) $asset->storage_key];
        }

        return $files;
    }

    /** @return Collection<int, mixed> */
    private function revisionIds(SpaceContent $content): Collection
    {
        return DB::table('space_content_revisions')->where('space_content_id', $content->id)->pluck('id');
    }

    private function actorId(User $user): int
    {
        return (int) User::query()->with('actor')->findOrFail($user->id)->actor?->id;
    }
}
