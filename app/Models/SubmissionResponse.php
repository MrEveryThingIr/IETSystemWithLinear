<?php

namespace App\Models;

use App\Support\InteractionResponseTypeRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'submission_id',
    'item_key',
    'response_type',
    'value',
    'asset_id',
    'content_evidence_reference_id',
])]
class SubmissionResponse extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function (self $response): void {
            $response->uuid ??= (string) Str::uuid();
            $response->assertDraft();
            $response->assertContract();
        });

        static::updating(function (self $response): void {
            $response->assertDraft();

            if ($response->isDirty([
                'uuid',
                'submission_id',
                'item_key',
                'response_type',
            ])) {
                throw new LogicException('Submission Response identity and item contract are immutable.');
            }

            $response->assertContract();
        });

        static::deleting(function (self $response): void {
            $response->assertDraft();
        });
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<ContentEvidenceReference, $this> */
    public function contentEvidenceReference(): BelongsTo
    {
        return $this->belongsTo(ContentEvidenceReference::class);
    }

    private function assertDraft(): void
    {
        $submission = Submission::query()->findOrFail($this->submission_id);

        if ($submission->status !== Submission::STATUS_DRAFT) {
            throw new LogicException('Submitted Responses are immutable evidence.');
        }
    }

    private function assertContract(): void
    {
        $submission = Submission::query()
            ->with('definitionVersion')
            ->findOrFail($this->submission_id);

        $item = collect($submission->definitionVersion->items)
            ->first(fn (mixed $candidate): bool => is_array($candidate)
                && ($candidate['key'] ?? null) === $this->item_key);

        if (! is_array($item) || ($item['type'] ?? null) !== $this->response_type) {
            throw new LogicException('Submission Response must match an item in the exact Interaction Definition version.');
        }

        if ($this->response_type === InteractionResponseTypeRegistry::ASSET) {
            if ($this->asset_id === null
                || $this->content_evidence_reference_id !== null
                || $this->value !== null) {
                throw new LogicException('Asset Responses must reference exactly one Asset.');
            }

            $asset = Asset::query()->findOrFail($this->asset_id);
            if ((int) $asset->context_id !== (int) $submission->context_id
                || (int) $asset->uploaded_by_actor_id !== (int) $submission->submitted_by_actor_id) {
                throw new LogicException('Submission Asset must belong to the submitter in the same Context.');
            }

            return;
        }

        if ($this->response_type === InteractionResponseTypeRegistry::CONTENT_EVIDENCE) {
            if ($this->content_evidence_reference_id === null
                || $this->asset_id !== null
                || $this->value !== null) {
                throw new LogicException('Content-evidence Responses must reference exactly one immutable evidence locator.');
            }

            ContentEvidenceReference::query()->findOrFail($this->content_evidence_reference_id);

            return;
        }

        if ($this->asset_id !== null || $this->content_evidence_reference_id !== null) {
            throw new LogicException('Scalar Responses cannot carry Asset or Content-evidence references.');
        }
    }
}
