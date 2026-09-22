<?php

namespace App\Actions\Interactions;

use App\Models\Asset;
use App\Models\ContentEvidenceReference;
use App\Models\Submission;
use App\Models\SubmissionResponse;
use App\Models\User;
use App\Support\InteractionResponseNormalizer;
use App\Support\InteractionResponseTypeRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveSubmissionResponse
{
    public function __construct(private readonly InteractionResponseNormalizer $normalizer) {}

    public function execute(
        Submission $submission,
        User $user,
        string $itemKey,
        mixed $value = null,
        ?Asset $asset = null,
        ?ContentEvidenceReference $contentEvidence = null,
    ): ?SubmissionResponse {
        return DB::transaction(function () use (
            $submission,
            $user,
            $itemKey,
            $value,
            $asset,
            $contentEvidence,
        ): ?SubmissionResponse {
            $current = Submission::query()
                ->with('definitionVersion')
                ->lockForUpdate()
                ->findOrFail($submission->id);

            Gate::forUser($user)->authorize('update', $current);

            $itemKey = strtolower(trim($itemKey));
            $item = collect($current->definitionVersion->items)
                ->first(fn (mixed $candidate): bool => is_array($candidate)
                    && ($candidate['key'] ?? null) === $itemKey);

            abort_unless(is_array($item), 422, 'Response item is not part of this Interaction Definition version.');

            $type = (string) ($item['type'] ?? '');
            $existing = SubmissionResponse::query()
                ->where('submission_id', $current->id)
                ->where('item_key', $itemKey)
                ->lockForUpdate()
                ->first();

            if ($type === InteractionResponseTypeRegistry::ASSET) {
                if (! $asset instanceof Asset) {
                    $existing?->delete();

                    return null;
                }

                $currentAsset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
                abort_unless(
                    (int) $currentAsset->context_id === (int) $current->context_id
                        && (int) $currentAsset->uploaded_by_actor_id === (int) $current->submitted_by_actor_id,
                    404,
                );

                return $this->persist($existing, $current, $itemKey, $type, null, $currentAsset->id, null);
            }

            if ($type === InteractionResponseTypeRegistry::CONTENT_EVIDENCE) {
                if (! $contentEvidence instanceof ContentEvidenceReference) {
                    $existing?->delete();

                    return null;
                }

                $reference = ContentEvidenceReference::query()
                    ->with(['content', 'revision'])
                    ->lockForUpdate()
                    ->findOrFail($contentEvidence->id);
                Gate::forUser($user)->authorize('view', $reference->content);
                abort_unless($reference->revision->hasVerifiableManifest(), 422, 'Content evidence must reference a sealed revision.');

                return $this->persist($existing, $current, $itemKey, $type, null, null, $reference->id);
            }

            $normalized = $this->normalizer->normalize($item, $value);
            if ($normalized === null) {
                $existing?->delete();

                return null;
            }

            return $this->persist($existing, $current, $itemKey, $type, $normalized, null, null);
        }, 3);
    }

    private function persist(
        ?SubmissionResponse $response,
        Submission $submission,
        string $itemKey,
        string $type,
        mixed $value,
        ?int $assetId,
        ?int $evidenceId,
    ): SubmissionResponse {
        if ($response instanceof SubmissionResponse) {
            $response->update([
                'value' => $value,
                'asset_id' => $assetId,
                'content_evidence_reference_id' => $evidenceId,
            ]);

            return $response->refresh();
        }

        return SubmissionResponse::query()->create([
            'submission_id' => $submission->id,
            'item_key' => $itemKey,
            'response_type' => $type,
            'value' => $value,
            'asset_id' => $assetId,
            'content_evidence_reference_id' => $evidenceId,
        ]);
    }
}
