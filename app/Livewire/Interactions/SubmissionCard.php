<?php

namespace App\Livewire\Interactions;

use App\Actions\Assets\CreateContextAsset;
use App\Actions\Interactions\SaveSubmissionResponse;
use App\Actions\Interactions\StartSubmission;
use App\Actions\Interactions\SubmitSubmission;
use App\Actions\Interactions\WithdrawSubmission;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\ContentEvidenceReference;
use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use App\Models\Submission;
use App\Models\User;
use App\Support\InteractionResponseTypeRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class SubmissionCard extends Component
{
    use WithFileUploads;

    public InteractionDefinition $definition;

    public ?string $submissionUuid = null;

    /** @var array<string, mixed> */
    public array $answers = [];

    /** @var array<string, mixed> */
    public array $assetUploads = [];

    /** @var array<string, string> */
    public array $assetRights = [];

    /** @var array<string, string> */
    public array $evidenceReferences = [];

    public function mount(InteractionDefinition $definition): void
    {
        $definition = InteractionDefinition::query()
            ->with(['context', 'activeVersion'])
            ->findOrFail($definition->id);
        Gate::forUser($this->user())->authorize('view', $definition);

        abort_unless(
            $definition->activeVersion instanceof InteractionDefinitionVersion
                && $definition->activeVersion->published_at !== null,
            404,
        );

        $this->definition = $definition;
        $this->selectLatestSubmission($definition->activeVersion);
    }

    public function start(StartSubmission $start): void
    {
        $submission = $start->execute($this->version(), $this->user());
        $this->submissionUuid = $submission->uuid;
        $this->hydrateDraft($submission);
        $this->resetErrorBag();
    }

    public function saveDraft(SaveSubmissionResponse $save, CreateContextAsset $createAsset): void
    {
        $this->persistDraft($save, $createAsset);
        session()->flash('submission-status', __('structured_interactions.saved'));
    }

    public function submit(
        SaveSubmissionResponse $save,
        CreateContextAsset $createAsset,
        SubmitSubmission $submit,
    ): void {
        $submission = $this->persistDraft($save, $createAsset);
        $submission = $submit->execute($submission, $this->user());

        $this->submissionUuid = $submission->uuid;
        $this->reset('assetUploads');
        $this->resetErrorBag();
        session()->flash('submission-status', __('structured_interactions.submitted'));
    }

    public function withdraw(WithdrawSubmission $withdraw): void
    {
        $submission = $this->submission();
        abort_unless($submission instanceof Submission, 404);

        $withdrawn = $withdraw->execute($submission, $this->user());
        $this->submissionUuid = $withdrawn->uuid;
        $this->resetErrorBag();
        session()->flash('submission-status', __('structured_interactions.withdrawn'));
    }

    public function clearResponse(string $itemKey, SaveSubmissionResponse $save): void
    {
        $submission = $this->submission();
        abort_unless($submission instanceof Submission, 404);

        $save->execute($submission, $this->user(), $itemKey);
        unset(
            $this->answers[$itemKey],
            $this->assetUploads[$itemKey],
            $this->evidenceReferences[$itemKey],
        );
        $this->assetRights[$itemKey] = 'owned';
        $this->resetErrorBag('responses.'.$itemKey);
    }

    public function render(): View
    {
        $definition = InteractionDefinition::query()
            ->with(['context', 'activeVersion'])
            ->findOrFail($this->definition->id);
        Gate::forUser($this->user())->authorize('view', $definition);
        $this->definition = $definition;

        $version = $this->version();
        $submission = $this->submission();

        if (! $submission instanceof Submission) {
            $this->selectLatestSubmission($version);
            $submission = $this->submission();
        }

        if ($submission instanceof Submission) {
            Gate::forUser($this->user())->authorize('view', $submission);
            $submission->loadMissing([
                'responses.asset',
                'responses.contentEvidenceReference',
                'evaluations.evaluator.user',
            ]);
        }

        $canStart = Gate::forUser($this->user())->allows('submit', $definition)
            && (! $submission instanceof Submission || $submission->status === Submission::STATUS_WITHDRAWN);
        $canSubmit = $submission instanceof Submission
            && Gate::forUser($this->user())->allows('submit', $submission);
        $canWithdraw = $submission instanceof Submission
            && Gate::forUser($this->user())->allows('withdraw', $submission);

        return view('livewire.interactions.submission-card', compact(
            'version',
            'submission',
            'canStart',
            'canSubmit',
            'canWithdraw',
        ));
    }

    private function persistDraft(
        SaveSubmissionResponse $save,
        CreateContextAsset $createAsset,
    ): Submission {
        $submission = $this->submission();
        abort_unless($submission instanceof Submission, 404);
        Gate::forUser($this->user())->authorize('update', $submission);

        foreach ($this->version()->items as $item) {
            if (! is_array($item) || ! is_string($item['key'] ?? null) || ! is_string($item['type'] ?? null)) {
                continue;
            }

            $key = $item['key'];
            $type = $item['type'];

            if ($type === InteractionResponseTypeRegistry::ASSET) {
                $upload = $this->assetUploads[$key] ?? null;
                if ($upload instanceof UploadedFile) {
                    $this->validate([
                        'assetUploads.'.$key => ['file', 'max:12288'],
                        'assetRights.'.$key => ['required', 'string', Rule::in(Asset::RIGHTS_STATUSES)],
                    ]);

                    $asset = $createAsset->execute(
                        $submission->context,
                        $this->user(),
                        $upload,
                        $this->assetRights[$key] ?? 'owned',
                    );
                    $save->execute($submission, $this->user(), $key, asset: $asset);
                    unset($this->assetUploads[$key]);
                }

                continue;
            }

            if ($type === InteractionResponseTypeRegistry::CONTENT_EVIDENCE) {
                $input = trim($this->evidenceReferences[$key] ?? '');
                $reference = $input === '' ? null : $this->evidenceReference($input);
                $save->execute($submission, $this->user(), $key, contentEvidence: $reference);

                continue;
            }

            $value = $this->answers[$key] ?? null;
            if ($type === InteractionResponseTypeRegistry::BOOLEAN) {
                $value = match ($value) {
                    true, 1, '1', 'true' => true,
                    false, 0, '0', 'false' => false,
                    default => null,
                };
            }

            $save->execute($submission, $this->user(), $key, $value);
        }

        $this->hydrateDraft($submission->refresh());

        return $submission->refresh();
    }

    private function selectLatestSubmission(InteractionDefinitionVersion $version): void
    {
        $latest = Submission::query()
            ->where('interaction_definition_version_id', $version->id)
            ->where('submitted_by_actor_id', $this->actor()->id)
            ->orderByDesc('attempt_number')
            ->first();

        $this->submissionUuid = $latest?->uuid;

        if ($latest instanceof Submission && $latest->status === Submission::STATUS_DRAFT) {
            $this->hydrateDraft($latest);
        }
    }

    private function hydrateDraft(Submission $submission): void
    {
        $submission->loadMissing(['responses.asset', 'responses.contentEvidenceReference']);
        $this->answers = [];
        $this->evidenceReferences = [];

        foreach ($submission->responses as $response) {
            if ($response->response_type === InteractionResponseTypeRegistry::CONTENT_EVIDENCE) {
                $this->evidenceReferences[$response->item_key] = $response->contentEvidenceReference->uuid;
            } elseif ($response->response_type !== InteractionResponseTypeRegistry::ASSET) {
                $this->answers[$response->item_key] = $response->value;
            }
        }

        foreach ($this->version()->items as $item) {
            if (is_array($item) && is_string($item['key'] ?? null)) {
                $this->assetRights[$item['key']] ??= 'owned';
            }
        }
    }

    private function version(): InteractionDefinitionVersion
    {
        $definition = InteractionDefinition::query()
            ->with('activeVersion')
            ->findOrFail($this->definition->id);

        $version = $definition->activeVersion;
        abort_unless($version instanceof InteractionDefinitionVersion && $version->published_at !== null, 404);

        return $version;
    }

    private function submission(): ?Submission
    {
        if ($this->submissionUuid === null) {
            return null;
        }

        return Submission::query()
            ->with('context')
            ->where('uuid', $this->submissionUuid)
            ->where('interaction_definition_version_id', $this->version()->id)
            ->first();
    }

    private function evidenceReference(string $input): ContentEvidenceReference
    {
        $path = parse_url($input, PHP_URL_PATH);
        $candidate = trim(is_string($path) ? basename($path) : $input);
        abort_unless(Str::isUuid($candidate), 422, __('structured_interactions.evidence_invalid'));

        return ContentEvidenceReference::query()->where('uuid', $candidate)->firstOrFail();
    }

    private function actor(): Actor
    {
        $current = User::query()->with('actor')->find($this->user()->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
