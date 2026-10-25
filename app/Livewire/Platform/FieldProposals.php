<?php

namespace App\Livewire\Platform;

use App\Actions\Market\ReviewIntentFields;
use App\Models\IntentFieldDefinition;
use App\Models\IntentFieldProposal;
use App\Models\User;
use App\Support\Market\IntentDetailCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Administrators review fields people suggested for everyone, publish them as versions, or retire them. */
#[Layout('layouts.app')]
class FieldProposals extends Component
{
    public const LOCALES = ['en', 'fa', 'ar', 'zh_CN'];

    #[Url]
    public string $status = IntentFieldProposal::PROPOSED;

    public ?string $reviewing = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public string $note = '';

    public function mount(ReviewIntentFields $review): void
    {
        abort_unless($review->canReview($this->user()), 403);
        if (! in_array($this->status, ['proposed', 'reviewed', 'published', 'rejected', 'library'], true)) {
            $this->status = IntentFieldProposal::PROPOSED;
        }
    }

    public function open(string $uuid): void
    {
        $proposal = IntentFieldProposal::query()->where('uuid', $uuid)->firstOrFail();
        $payload = $proposal->payload;
        $this->reviewing = $uuid;
        $this->note = '';
        $this->form = [
            'key' => (string) ($proposal->target_key ?? ''),
            'labels' => array_replace(array_fill_keys(self::LOCALES, ''), $payload['labels'] ?? []),
            'aliases' => array_replace(array_fill_keys(self::LOCALES, ''), array_map(fn ($list): string => is_array($list) ? implode(', ', $list) : (string) $list, $payload['aliases'] ?? [])),
            'domains' => $payload['domains'] ?? [],
            'options' => implode(', ', $payload['options'] ?? []),
            'unit' => $payload['unit'] ?? null,
            'new_key' => '', 'group_key' => '',
            'payload' => [
                'labels' => array_replace(array_fill_keys(self::LOCALES, ''), $payload['labels'] ?? []),
                'descriptions' => array_replace(array_fill_keys(self::LOCALES, ''), $payload['descriptions'] ?? []),
                'aliases' => array_replace(array_fill_keys(self::LOCALES, ''), array_map(fn ($list): string => is_array($list) ? implode(', ', $list) : (string) $list, $payload['aliases'] ?? [])),
            ],
        ];
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->reviewing = null;
    }

    public function saveReview(ReviewIntentFields $review): void
    {
        $review->review($this->user(), $this->proposal(), $this->edit());
        session()->flash('status', __('field_review.saved'));
    }

    public function approve(ReviewIntentFields $review): void
    {
        $review->approve($this->user(), $this->proposal(), $this->edit());
        $this->reviewing = null;
        session()->flash('status', __('field_review.published'));
    }

    public function reject(ReviewIntentFields $review): void
    {
        $review->reject($this->user(), $this->proposal(), $this->note);
        $this->reviewing = null;
        session()->flash('status', __('field_review.rejected'));
    }

    public function rejectAsDuplicate(ReviewIntentFields $review, string $key): void
    {
        $review->reject($this->user(), $this->proposal(), __('field_review.duplicate_note', ['key' => $key]), $key);
        $this->reviewing = null;
        session()->flash('status', __('field_review.rejected'));
    }

    public function retire(ReviewIntentFields $review, string $key): void
    {
        $review->retire($this->user(), $key);
        session()->flash('status', __('field_review.retired'));
    }

    public function render(ReviewIntentFields $review): View
    {
        abort_unless($review->canReview($this->user()), 403);
        $proposals = $this->status === 'library' ? collect() : IntentFieldProposal::query()->where('status', $this->status)->with('proposedBy.user')->latest('id')->limit(100)->get();
        $open = $this->reviewing === null ? null : IntentFieldProposal::query()->where('uuid', $this->reviewing)->first();

        return view('livewire.platform.field-proposals', [
            'proposals' => $proposals,
            'open' => $open,
            'duplicates' => $open === null ? [] : $review->duplicates($open),
            'definitions' => $this->status === 'library' ? IntentFieldDefinition::query()->latestVersion()->orderBy('field_key')->get() : collect(),
            'builtin' => IntentDetailCatalog::codeDefinitions(),
            'original' => $open?->target_key !== null ? $this->original((string) $open->target_key) : null,
            'counts' => IntentFieldProposal::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ])->title(__('field_review.title'));
    }

    /** @return array<string, mixed> */
    private function edit(): array
    {
        $options = array_values(array_filter(array_map('trim', explode(',', (string) ($this->form['options'] ?? '')))));

        $overrides = [];
        foreach (['labels', 'descriptions'] as $field) {
            $overrides[$field] = array_filter(array_map('trim', (array) ($this->form['payload'][$field] ?? [])));
        }
        $overrides['aliases'] = array_map(fn ($text): array => array_values(array_filter(array_map('trim', explode(',', (string) $text)))), (array) ($this->form['payload']['aliases'] ?? []));
        $proposal = $this->reviewing === null ? null : IntentFieldProposal::query()->where('uuid', $this->reviewing)->first();
        // Only the parts a proposal actually carries may be overridden by the reviewer.
        $overrides = array_intersect_key($overrides, $proposal->payload ?? []);

        return ['key' => trim((string) ($this->form['key'] ?? '')), 'labels' => array_map('trim', (array) ($this->form['labels'] ?? [])),
            'aliases' => (array) ($this->form['aliases'] ?? []), 'domains' => array_values((array) ($this->form['domains'] ?? [])), 'options' => $options,
            'new_key' => trim((string) ($this->form['new_key'] ?? '')), 'group_key' => trim((string) ($this->form['group_key'] ?? '')), 'payload' => $overrides];
    }

    /**
     * The definition a suggestion is about, as people see it today.
     *
     * @return array<string, mixed>|null
     */
    private function original(string $key): ?array
    {
        $spec = IntentDetailCatalog::all()[$key] ?? null;
        if ($spec === null) {
            return null;
        }
        $labels = [];
        $aliases = [];
        foreach (self::LOCALES as $locale) {
            $labels[$locale] = IntentDetailCatalog::label($key, $locale);
            $aliases[$locale] = IntentDetailCatalog::aliases($key, $locale);
        }

        return ['key' => $key, 'type' => $spec['type'], 'unit' => $spec['unit'] ?? null, 'options' => $spec['options'] ?? [], 'hidden' => $spec['hidden_options'] ?? [],
            'domain' => $spec['domain'] ?? 'general', 'source' => $spec['source'] ?? 'house', 'labels' => $labels, 'aliases' => $aliases, 'retired' => ! empty($spec['retired']),
            'uses' => DB::table('intent_search_values')->where('canonical_key', $key)->distinct()->count('intent_version_id')];
    }

    private function proposal(): IntentFieldProposal
    {
        return IntentFieldProposal::query()->where('uuid', $this->reviewing)->firstOrFail();
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
