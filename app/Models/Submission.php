<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'context_id',
    'interaction_definition_version_id',
    'space_content_revision_id',
    'submitted_by_actor_id',
    'attempt_number',
    'status',
    'evidence_schema_version',
    'evidence_hash',
    'canonical_evidence',
    'submitted_at',
    'withdrawn_at',
])]
class Submission extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_WITHDRAWN = 'withdrawn';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_WITHDRAWN,
    ];

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'evidence_schema_version' => 'integer',
            'submitted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $submission): void {
            $submission->uuid ??= (string) Str::uuid();
            $submission->assertProvenance();
            $submission->assertLifecycle();
        });

        static::updating(function (self $submission): void {
            if ($submission->isDirty([
                'uuid',
                'context_id',
                'interaction_definition_version_id',
                'space_content_revision_id',
                'submitted_by_actor_id',
                'attempt_number',
            ])) {
                throw new LogicException('Submission identity and provenance are immutable.');
            }

            if ($submission->isDirty([
                'status',
                'evidence_schema_version',
                'evidence_hash',
                'canonical_evidence',
                'submitted_at',
                'withdrawn_at',
            ]) && ! $submission->applyingLifecycle) {
                throw new LogicException('Submission lifecycle changes require a dedicated Action.');
            }

            $submission->assertLifecycle();
        });

        static::deleting(function (): never {
            throw new LogicException('Submissions are durable interaction history.');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function applyLifecycle(array $attributes): void
    {
        $allowed = [
            'status',
            'evidence_schema_version',
            'evidence_hash',
            'canonical_evidence',
            'submitted_at',
            'withdrawn_at',
        ];

        if (array_diff(array_keys($attributes), $allowed) !== []) {
            throw new LogicException('Only Submission lifecycle attributes may change through this operation.');
        }

        $this->applyingLifecycle = true;

        try {
            $this->update($attributes);
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<InteractionDefinitionVersion, $this> */
    public function definitionVersion(): BelongsTo
    {
        return $this->belongsTo(InteractionDefinitionVersion::class, 'interaction_definition_version_id');
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function contentRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'space_content_revision_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'submitted_by_actor_id');
    }

    /** @return HasMany<SubmissionResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(SubmissionResponse::class)->orderBy('id');
    }

    /** @return HasMany<Evaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class)->orderBy('id');
    }

    private function assertProvenance(): void
    {
        $version = InteractionDefinitionVersion::query()
            ->with('definition')
            ->findOrFail($this->interaction_definition_version_id);

        if ((int) $version->definition->context_id !== (int) $this->context_id) {
            throw new LogicException('Submission Context must match its Interaction Definition.');
        }

        if ($version->published_at === null
            || $version->definition->status !== InteractionDefinition::STATUS_ACTIVE
            || (int) $version->definition->active_version_id !== (int) $version->id) {
            throw new LogicException('Submissions require the active published Interaction Definition version.');
        }

        if ($version->space_content_revision_id === null) {
            if ($this->space_content_revision_id !== null) {
                throw new LogicException('Submission Content revision must match its Interaction Definition version.');
            }
        } elseif ((int) $version->space_content_revision_id !== (int) $this->space_content_revision_id) {
            throw new LogicException('Submission Content revision must match its Interaction Definition version.');
        }

        Actor::query()->findOrFail($this->submitted_by_actor_id);

        if ((int) $this->attempt_number < 1) {
            throw new LogicException('Submission attempt number must be positive.');
        }
    }

    private function assertLifecycle(): void
    {
        if (! in_array($this->status, self::STATUSES, true)) {
            throw new LogicException('Unknown Submission status.');
        }

        if ($this->evidence_hash !== null
            && preg_match('/^[a-f0-9]{64}$/', (string) $this->evidence_hash) !== 1) {
            throw new LogicException('Submission evidence hash must be SHA-256.');
        }

        if ($this->status === self::STATUS_DRAFT) {
            if ($this->submitted_at !== null
                || $this->withdrawn_at !== null
                || $this->evidence_schema_version !== null
                || $this->evidence_hash !== null
                || $this->canonical_evidence !== null) {
                throw new LogicException('Draft Submissions cannot carry sealed evidence.');
            }

            return;
        }

        if ($this->submitted_at === null
            || $this->evidence_schema_version === null
            || $this->evidence_hash === null
            || $this->canonical_evidence === null) {
            throw new LogicException('Submitted Submissions require complete immutable evidence.');
        }

        if ($this->status === self::STATUS_SUBMITTED && $this->withdrawn_at !== null) {
            throw new LogicException('A submitted Submission cannot already be withdrawn.');
        }

        if ($this->status === self::STATUS_WITHDRAWN && $this->withdrawn_at === null) {
            throw new LogicException('A withdrawn Submission requires a withdrawal timestamp.');
        }
    }
}
