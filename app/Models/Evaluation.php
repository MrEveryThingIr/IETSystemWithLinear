<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'submission_id',
    'evaluator_actor_id',
    'status',
    'score',
    'criterion_results',
    'feedback',
    'evidence_schema_version',
    'evidence_hash',
    'canonical_evidence',
    'finalized_at',
])]
class Evaluation extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    private bool $applyingDraft = false;

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'criterion_results' => 'array',
            'evidence_schema_version' => 'integer',
            'finalized_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $evaluation): void {
            $evaluation->uuid ??= (string) Str::uuid();

            $submission = Submission::query()->findOrFail($evaluation->submission_id);
            Actor::query()->findOrFail($evaluation->evaluator_actor_id);

            if ($submission->status !== Submission::STATUS_SUBMITTED) {
                throw new LogicException('Evaluations require a submitted Submission.');
            }
            if ((int) $submission->submitted_by_actor_id === (int) $evaluation->evaluator_actor_id) {
                throw new LogicException('A submitter cannot evaluate their own Submission.');
            }
            if ($evaluation->status !== self::STATUS_DRAFT
                || $evaluation->score !== null
                || ($evaluation->criterion_results ?? []) !== []
                || $evaluation->feedback !== null
                || $evaluation->evidence_schema_version !== null
                || $evaluation->evidence_hash !== null
                || $evaluation->canonical_evidence !== null
                || $evaluation->finalized_at !== null) {
                throw new LogicException('Evaluations must begin as empty drafts.');
            }
        });

        static::updating(function (self $evaluation): void {
            if ($evaluation->getOriginal('status') === self::STATUS_FINALIZED) {
                throw new LogicException('Finalized Evaluations are immutable evidence.');
            }

            if ($evaluation->isDirty(['uuid', 'submission_id', 'evaluator_actor_id'])) {
                throw new LogicException('Evaluation identity and provenance are immutable.');
            }

            if ($evaluation->isDirty(['score', 'criterion_results', 'feedback'])
                && ! $evaluation->applyingDraft) {
                throw new LogicException('Evaluation draft content must change through a dedicated Action.');
            }

            if ($evaluation->isDirty([
                'status',
                'evidence_schema_version',
                'evidence_hash',
                'canonical_evidence',
                'finalized_at',
            ]) && ! $evaluation->applyingLifecycle) {
                throw new LogicException('Evaluation lifecycle changes require a dedicated Action.');
            }

            if ($evaluation->status === self::STATUS_FINALIZED
                && ($evaluation->evidence_schema_version === null
                    || ! is_string($evaluation->evidence_hash)
                    || preg_match('/^[a-f0-9]{64}$/', $evaluation->evidence_hash) !== 1
                    || ! is_string($evaluation->canonical_evidence)
                    || $evaluation->canonical_evidence === ''
                    || $evaluation->finalized_at === null)) {
                throw new LogicException('Finalized Evaluations require complete immutable evidence.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Evaluations are durable review history.');
        });
    }

    /** @param list<array<string, mixed>> $criterionResults */
    public function applyDraft(?string $feedback, int|float|string|null $score, array $criterionResults): void
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new LogicException('Only draft Evaluations can be edited.');
        }

        $this->applyingDraft = true;

        try {
            $this->update([
                'feedback' => $feedback,
                'score' => $score,
                'criterion_results' => $criterionResults,
            ]);
        } finally {
            $this->applyingDraft = false;
        }
    }

    public function finalizeEvidence(int $schemaVersion, string $hash, string $canonical): void
    {
        if ($this->status === self::STATUS_FINALIZED) {
            if ($this->evidence_hash !== null && hash_equals($this->evidence_hash, $hash)) {
                return;
            }

            throw new LogicException('Finalized Evaluations cannot be resealed.');
        }

        $this->applyingLifecycle = true;

        try {
            $this->update([
                'status' => self::STATUS_FINALIZED,
                'evidence_schema_version' => $schemaVersion,
                'evidence_hash' => $hash,
                'canonical_evidence' => $canonical,
                'finalized_at' => now(),
            ]);
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'evaluator_actor_id');
    }
}
