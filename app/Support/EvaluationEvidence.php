<?php

namespace App\Support;

use App\Models\Evaluation;
use LogicException;

class EvaluationEvidence
{
    public const SCHEMA_VERSION = 1;

    /** @return array{schema_version: int, canonical: string, hash: string} */
    public function seal(Evaluation $evaluation): array
    {
        $evaluation->loadMissing([
            'evaluator',
            'submission.definitionVersion.definition',
        ]);

        $submission = $evaluation->submission;
        if ($submission->evidence_hash === null || $submission->canonical_evidence === null) {
            throw new LogicException('Evaluation requires sealed Submission evidence.');
        }

        $manifest = [
            'schema_version' => self::SCHEMA_VERSION,
            'evidence' => [
                'evaluation_uuid' => $evaluation->uuid,
                'submission_uuid' => $submission->uuid,
                'submission_evidence_hash' => $submission->evidence_hash,
                'interaction_definition_uuid' => $submission->definitionVersion->definition->uuid,
                'interaction_definition_version' => $submission->definitionVersion->version,
                'interaction_definition_hash' => $submission->definitionVersion->content_hash,
                'evaluator_actor_id' => $evaluation->evaluator_actor_id,
                'score' => $evaluation->score,
                'criterion_results' => $evaluation->criterion_results ?? [],
                'feedback' => $evaluation->feedback,
            ],
        ];

        $canonical = SpaceContentSchema::canonicalJson($manifest);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'canonical' => $canonical,
            'hash' => hash('sha256', $canonical),
        ];
    }
}
