<?php

namespace App\Support;

use App\Models\Submission;
use App\Models\SubmissionResponse;
use LogicException;

class SubmissionEvidence
{
    public const SCHEMA_VERSION = 1;

    /** @return array{schema_version: int, evidence: array<string, mixed>, canonical: string, hash: string} */
    public function seal(Submission $submission): array
    {
        $submission->loadMissing([
            'context',
            'submitter',
            'definitionVersion.definition',
            'definitionVersion.contentRevision',
            'responses.asset',
            'responses.contentEvidenceReference.revision',
        ]);

        $version = $submission->definitionVersion;
        $responses = $submission->responses->keyBy('item_key');
        $responseEvidence = [];

        foreach ($version->items as $item) {
            if (! is_array($item) || ! is_string($item['key'] ?? null)) {
                throw new LogicException('Interaction Definition item evidence is invalid.');
            }

            $response = $responses->get($item['key']);
            if (! $response instanceof SubmissionResponse) {
                continue;
            }

            $responseEvidence[] = $this->response($response);
        }

        $contentRevision = $submission->contentRevision;
        $evidence = [
            'submission_uuid' => $submission->uuid,
            'context_uuid' => $submission->context->uuid,
            'submitter_actor_id' => $submission->submitted_by_actor_id,
            'attempt_number' => $submission->attempt_number,
            'interaction_definition_uuid' => $version->definition->uuid,
            'interaction_definition_version' => $version->version,
            'interaction_definition_hash' => $version->content_hash,
            'space_content_revision_uuid' => $contentRevision?->uuid,
            'space_content_manifest_hash' => $contentRevision?->manifest_hash,
            'responses' => $responseEvidence,
        ];

        $manifest = [
            'schema_version' => self::SCHEMA_VERSION,
            'evidence' => $evidence,
        ];
        $canonical = SpaceContentSchema::canonicalJson($manifest);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'evidence' => $evidence,
            'canonical' => $canonical,
            'hash' => hash('sha256', $canonical),
        ];
    }

    /** @return array<string, mixed> */
    private function response(SubmissionResponse $response): array
    {
        $asset = $response->asset;
        $reference = $response->contentEvidenceReference;

        return [
            'response_uuid' => $response->uuid,
            'item_key' => $response->item_key,
            'response_type' => $response->response_type,
            'value' => $response->value,
            'asset' => $asset === null ? null : [
                'uuid' => $asset->uuid,
                'sha256' => $asset->sha256,
                'mime_type' => $asset->mime_type,
                'byte_size' => $asset->byte_size,
                'original_filename' => $asset->original_filename,
                'rights_status' => $asset->rights_status,
            ],
            'content_evidence' => $reference === null ? null : [
                'uuid' => $reference->uuid,
                'space_content_revision_uuid' => $reference->revision->uuid,
                'target_type' => $reference->target_type->value,
                'target_uuid' => $reference->target_uuid,
                'field_key' => $reference->field_key,
            ],
        ];
    }
}
