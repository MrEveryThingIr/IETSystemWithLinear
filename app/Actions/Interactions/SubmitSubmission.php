<?php

namespace App\Actions\Interactions;

use App\Models\Asset;
use App\Models\Submission;
use App\Models\SubmissionResponse;
use App\Models\User;
use App\Support\InteractionResponseNormalizer;
use App\Support\InteractionResponseTypeRegistry;
use App\Support\SubmissionEvidence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SubmitSubmission
{
    public function __construct(
        private readonly InteractionResponseNormalizer $normalizer,
        private readonly SubmissionEvidence $evidence,
    ) {}

    public function execute(Submission $submission, User $user): Submission
    {
        return DB::transaction(function () use ($submission, $user): Submission {
            $current = Submission::query()
                ->with([
                    'context',
                    'definitionVersion.definition',
                    'definitionVersion.contentRevision',
                    'responses.asset',
                    'responses.contentEvidenceReference.revision',
                ])
                ->lockForUpdate()
                ->findOrFail($submission->id);

            Gate::forUser($user)->authorize('view', $current);

            if ($current->status === Submission::STATUS_SUBMITTED) {
                return $current;
            }

            abort_unless($current->status === Submission::STATUS_DRAFT, 409, 'Only a draft Submission can be submitted.');
            Gate::forUser($user)->authorize('submit', $current);

            $responses = $current->responses->keyBy('item_key');

            foreach ($current->definitionVersion->items as $item) {
                if (! is_array($item) || ! is_string($item['key'] ?? null) || ! is_string($item['type'] ?? null)) {
                    throw ValidationException::withMessages(['submission' => 'Interaction Definition items are invalid.']);
                }

                $response = $responses->get($item['key']);
                if (! $response instanceof SubmissionResponse) {
                    if ((bool) ($item['required'] ?? false)) {
                        throw ValidationException::withMessages([
                            "responses.{$item['key']}" => "{$item['label']} is required.",
                        ]);
                    }

                    continue;
                }

                abort_unless($response->response_type === $item['type'], 409, 'A Response no longer matches its immutable item contract.');

                if ($response->response_type === InteractionResponseTypeRegistry::ASSET) {
                    $asset = $response->asset;
                    abort_unless($asset instanceof Asset && $asset->isReadyForPublication(), 422, 'Submission files must finish security processing before submit.');

                    continue;
                }

                if ($response->response_type === InteractionResponseTypeRegistry::CONTENT_EVIDENCE) {
                    abort_unless(
                        $response->contentEvidenceReference?->revision?->hasVerifiableManifest() === true,
                        422,
                        'Submission evidence must reference a sealed Content revision.',
                    );

                    continue;
                }

                $normalized = $this->normalizer->normalize($item, $response->value);
                if ($normalized === null && (bool) ($item['required'] ?? false)) {
                    throw ValidationException::withMessages([
                        "responses.{$item['key']}" => "{$item['label']} is required.",
                    ]);
                }
            }

            $sealed = $this->evidence->seal($current);

            $current->applyLifecycle([
                'status' => Submission::STATUS_SUBMITTED,
                'evidence_schema_version' => $sealed['schema_version'],
                'evidence_hash' => $sealed['hash'],
                'canonical_evidence' => $sealed['canonical'],
                'submitted_at' => now(),
                'withdrawn_at' => null,
            ]);

            return $current->refresh()->load(['responses.asset', 'responses.contentEvidenceReference']);
        }, 3);
    }
}
