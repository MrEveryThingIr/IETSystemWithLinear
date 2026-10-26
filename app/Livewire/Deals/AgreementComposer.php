<?php

namespace App\Livewire\Deals;

use App\Actions\Deals\ProposeDealTerms;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Relationship;
use App\Models\User;
use App\Support\Deals\AgreementDrafter;
use App\Support\Deals\DealNote;
use App\Support\Deals\DealTerms;
use App\Support\Deals\DealTermsDiff;
use App\Support\Deals\DealTermsDraft;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentProfiles;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Drafts the next version of a Deal's Agreement as an ordered list of terms. Typed terms (price, payment rule, dates, place,
 * quantity, repeats, completion rule, details) carry canonical values that later work and money features may read; narrative
 * clauses are text and stay text. A draft is private working state of the drafter; only "Send for review" creates a version.
 */
class AgreementComposer extends Component
{
    /** Library, grouped the way people think about an agreement: [category => [item ids]]. Narrative clauses use "clause:<kind>". */
    public const LIBRARY = [
        'subject' => ['clause:scope', 'clause:deliverables', 'fields', 'quantity'],
        'duties' => ['clause:duties'],
        'money' => ['price', 'payment'],
        'time' => ['time', 'place', 'route', 'recurrence'],
        'completion' => ['completion', 'clause:evidence'],
        'ending' => ['clause:cancellation', 'clause:disputes'],
        'other' => ['clause:confidentiality', 'clause:special', 'clause:custom', 'notes'],
    ];

    #[Locked]
    public Relationship $relationship;

    public bool $open = false;

    /** @var array<string, mixed> */
    public array $draft = [];

    public string $versionNote = '';

    public string $search = '';

    public bool $picking = false;

    public bool $libraryOpen = false;

    public string $savedAt = '';

    public function mount(Relationship $relationship): void
    {
        $this->relationship = $relationship;
    }

    #[On('open-agreement')]
    public function start(): void
    {
        $this->authorizeDrafting();
        $saved = $this->relationship->metadata['agreement_draft']['draft'] ?? null;
        $saved = is_array($saved) && $saved !== [] ? $saved : null;
        $this->draft = is_array($saved) ? array_replace_recursive(DealTermsDraft::blank(), $saved) : DealTermsDraft::start($this->relationship, $this->latestVersion());
        $this->draft['items'] = array_values($saved['items'] ?? $this->draft['items'] ?? []);
        $this->savedAt = (string) ($this->relationship->metadata['agreement_draft']['at'] ?? '');
        $this->versionNote = '';
        $this->open = true;
    }

    public function close(): void
    {
        $this->reset('open', 'picking', 'search', 'libraryOpen');
    }

    /** Keeps the working copy for later. It is visible only to people who may draft, and is not an Agreement. */
    public function saveDraft(): void
    {
        $this->authorizeDrafting();
        if (! $this->open || $this->draft === []) {
            return; // never store an empty working copy over a real one
        }
        $this->relationship->refresh();
        $meta = $this->relationship->metadata ?? [];
        $meta['agreement_draft'] = ['draft' => $this->draft, 'by_actor_id' => $this->user()->actor->id, 'at' => now()->toIso8601String()];
        $this->relationship->update(['metadata' => $meta]);
        $this->savedAt = $meta['agreement_draft']['at'];
        $this->dispatch('deal-changed');
    }

    public function discardDraft(): void
    {
        $this->authorizeDrafting();
        $this->relationship->refresh();
        $meta = $this->relationship->metadata ?? [];
        unset($meta['agreement_draft']);
        $this->relationship->update(['metadata' => $meta]);
        $this->reset('open', 'draft', 'savedAt');
        $this->dispatch('deal-changed');
    }

    /** Adds a typed term ("price") or a narrative clause ("clause:scope"). */
    public function addItem(string $id): void
    {
        abort_unless($this->knownItem($id), 422);

        if (str_starts_with($id, 'clause:')) {
            abort_if(count($this->draft['clauses']) >= 40, 422);
            $kind = substr($id, 7);
            $key = $kind;
            for ($n = 2; in_array($key, array_column($this->draft['clauses'], 'key'), true); $n++) {
                $key = $kind.'-'.$n;
            }
            $this->draft['clauses'][] = ['key' => $key, 'kind' => $kind, 'title' => '', 'text' => ''];
            $this->draft['items'][] = 'clause:'.$key;
        } elseif (! in_array($id, $this->draft['items'], true)) {
            $this->draft['items'][] = $id;
            $this->defaultsFor($id);
        }
        $this->libraryOpen = false;
    }

    public function removeItem(string $id): void
    {
        $this->draft['items'] = array_values(array_diff($this->draft['items'], [$id]));
        if (str_starts_with($id, 'clause:')) {
            $key = substr($id, 7);
            $this->draft['clauses'] = array_values(array_filter($this->draft['clauses'], fn ($c) => $c['key'] !== $key));
        }
    }

    public function move(string $id, int $direction): void
    {
        $items = $this->draft['items'];
        $i = array_search($id, $items, true);
        $j = $i === false ? false : $i + ($direction < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($items)) {
            return;
        }
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        $this->draft['items'] = $items;
    }

    /** A library field, never twice. */
    public function addField(string $key): void
    {
        $spec = IntentDetailCatalog::offered()[$key] ?? null;
        abort_unless($spec !== null, 422);
        abort_if(count($this->draft['fields']) >= 50, 422);

        if (! in_array($key, array_column($this->draft['fields'], 'key'), true)) {
            $type = in_array($spec['type'] ?? 'text', DealTerms::FIELD_TYPES, true) ? $spec['type'] : 'text';
            $this->draft['fields'][] = [
                'key' => $key,
                'label' => IntentDetailCatalog::label($key, null, $this->topic()),
                'type' => $type,
                'unit' => (string) ($spec['unit'] ?? ''),
                'value' => $type === 'boolean' ? 'true' : '',
            ];
        }
        $this->ensureItem('fields');
        $this->reset('picking', 'search');
    }

    /** Something the library does not know yet: a plain text detail the two sides agree on. */
    public function addCustom(): void
    {
        $label = trim($this->search);
        abort_if($label === '' || mb_strlen($label) > 120 || count($this->draft['fields']) >= 50, 422);
        $key = 'custom_'.substr(md5(mb_strtolower($label)), 0, 10);

        if (! in_array($key, array_column($this->draft['fields'], 'key'), true)) {
            $this->draft['fields'][] = ['key' => $key, 'label' => $label, 'type' => 'text', 'unit' => '', 'value' => ''];
        }
        $this->ensureItem('fields');
        $this->reset('picking', 'search');
    }

    public function removeField(int $index): void
    {
        unset($this->draft['fields'][$index]);
        $this->draft['fields'] = array_values($this->draft['fields']);
    }

    /** Creates the next immutable version for review. The drafter's own acceptance is recorded by the existing action. */
    public function send(ProposeDealTerms $propose): void
    {
        $this->resetErrorBag();
        $this->authorizeDrafting();
        if (! $this->open || $this->draft === []) {
            return;
        }

        if (in_array('payment', $this->draft['items'], true) && ($this->draft['payment']['mode'] ?? '') === '') {
            $this->addError('draft', __('deals.composer.choose_payment'));

            return;
        }
        if (in_array('completion', $this->draft['items'], true) && ($this->draft['completion']['confirmed_by'] ?? '') === '') {
            $this->addError('draft', __('deals.composer.choose_completion'));

            return;
        }

        $terms = DealTermsDraft::toTerms($this->draft);
        if (array_intersect(array_keys($terms), ['price', 'payment', 'place', 'time', 'quantity', 'route', 'fields', 'recurrence', 'completion', 'clauses', 'notes']) === []) {
            $this->addError('draft', __('deals.terms_empty'));

            return;
        }

        try {
            $version = $propose->execute($this->relationship, $this->user(), $terms, trim($this->versionNote) !== '' ? trim($this->versionNote) : null);
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 422) {
                throw $e;
            }
            $this->addError('draft', __('deals.terms_invalid').' '.$e->getMessage());

            return;
        }

        $this->relationship->refresh();
        $meta = $this->relationship->metadata ?? [];
        unset($meta['agreement_draft'], $meta['agreement_requests']);
        $this->relationship->update(['metadata' => $meta]);

        DealNote::post($this->relationship->contextBinding?->context, $this->user(), 'terms_proposed', ['version' => $version->version, 'proposal' => $version->proposal->uuid]);
        $this->reset('open', 'picking', 'search', 'versionNote', 'libraryOpen', 'draft', 'savedAt');
        $this->dispatch('deal-changed');
    }

    public function render(): View
    {
        $latest = $this->open ? $this->latestVersion() : null;

        return view('livewire.deals.agreement-composer', [
            'base' => $latest?->terms_data !== null ? DealTermsDiff::rows($latest->terms_data) : [],
            'baseVersion' => $latest?->version,
            'fromOrigin' => $this->open && $latest === null,
            'library' => $this->open && $this->picking ? $this->fieldLibrary() : [],
            'partyNames' => $this->open ? $this->partyNames() : [],
            'categories' => self::LIBRARY,
            'present' => $this->draft['items'] ?? [],
        ]);
    }

    /** @return list<array{key: string, label: string}> */
    private function fieldLibrary(): array
    {
        $have = array_column($this->draft['fields'], 'key');
        $needle = mb_strtolower(trim($this->search));
        $topicKeys = $this->topic() !== null ? array_column(IntentProfiles::all()[$this->topic()]['fields'] ?? [], 0) : [];
        $rows = [];

        foreach (IntentDetailCatalog::offered() as $key => $spec) {
            if (in_array($key, $have, true) || ! in_array($spec['type'] ?? 'text', DealTerms::FIELD_TYPES, true)) {
                continue;
            }
            $label = IntentDetailCatalog::label($key, null, $this->topic());
            if ($needle !== '' && ! str_contains(mb_strtolower($label), $needle) && ! str_contains($key, $needle)) {
                continue;
            }
            $rows[] = ['key' => $key, 'label' => $label, 'rank' => in_array($key, $topicKeys, true) ? 0 : 1];
        }

        usort($rows, fn ($a, $b) => [$a['rank'], $a['label']] <=> [$b['rank'], $b['label']]);

        return array_map(fn ($r) => ['key' => $r['key'], 'label' => $r['label']], array_slice($rows, 0, 12));
    }

    /** Who is party to the Agreement, inferred from the Deal and the pinned post, never typed in. @return list<string> */
    private function partyNames(): array
    {
        $deal = Relationship::query()->with(['originatingIntent.profile', 'matchedIntent.profile', 'participants.actor.user'])->find($this->relationship->id);
        $names = [];
        foreach ($deal?->participants ?? [] as $p) {
            $party = \App\Support\Deals\DealPrincipals::forActor($deal, $p->actor);
            $model = $party->model();
            $names[] = $model instanceof \App\Models\Actor ? (string) ($model->user?->username ?? '') : (string) ($model->name ?? '');
        }

        return array_values(array_filter($names));
    }

    private function defaultsFor(string $id): void
    {
        match ($id) {
            'price' => $this->draft['core']['currency'] = $this->draft['core']['currency'] ?: 'IRT',
            'recurrence' => $this->draft['recurrence'] = ['every' => '1', 'unit' => 'month', 'count' => ''],
            default => null,
        };
    }

    private function ensureItem(string $id): void
    {
        if (! in_array($id, $this->draft['items'], true)) {
            $this->draft['items'][] = $id;
        }
    }

    private function knownItem(string $id): bool
    {
        if (str_starts_with($id, 'clause:')) {
            return in_array(substr($id, 7), DealTerms::CLAUSE_KINDS, true);
        }

        return in_array($id, DealTerms::SECTION_IDS, true);
    }

    private function topic(): ?string
    {
        $topic = $this->relationship->originatingIntent?->metadata['composer_topic'] ?? null;

        return is_string($topic) && isset(IntentProfiles::all()[$topic]) ? $topic : null;
    }

    private function latestVersion(): ?ProposalVersion
    {
        return Proposal::query()->where('relationship_id', $this->relationship->id)->latest('id')->first()?->currentVersionRecord();
    }

    private function authorizeDrafting(): void
    {
        $deal = Relationship::query()->with('originatingIntent.profile')->findOrFail($this->relationship->id);
        Gate::forUser($this->user())->authorize('participate', $deal);
        abort_unless(AgreementDrafter::canDraft($deal, $this->user()), 403);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
