<?php

namespace App\Livewire\Deals;

use App\Actions\Deals\ProposeDealTerms;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Relationship;
use App\Models\User;
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
 * Drafts the next version of a Deal's Terms the way the Intent Composer drafts a Need or Offer:
 * a few core terms you switch on, typed fields from the same field library, free notes. It always
 * starts from what is on the table now (the latest version, or the originating Need/Offer).
 */
class TermsComposer extends Component
{
    public const BLOCKS = ['price', 'place', 'time', 'quantity', 'route', 'recurrence'];

    #[Locked]
    public Relationship $relationship;

    public bool $open = false;

    /** @var array<string, mixed> */
    public array $draft = [];

    /** @var list<string> */
    public array $shown = [];

    public string $versionNote = '';

    public string $search = '';

    public bool $picking = false;

    public function mount(Relationship $relationship): void
    {
        $this->relationship = $relationship;
    }

    #[On('open-terms')]
    public function start(): void
    {
        $this->authorizeParticipation();
        $this->draft = DealTermsDraft::start($this->relationship, $this->latestVersion());
        $this->shown = $this->filledBlocks($this->draft);
        $this->versionNote = '';
        $this->open = true;
    }

    public function close(): void
    {
        $this->reset('open', 'picking', 'search');
    }

    public function showBlock(string $block): void
    {
        abort_unless(in_array($block, self::BLOCKS, true), 422);
        if (! in_array($block, $this->shown, true)) {
            $this->shown[] = $block;
        }
        if ($block === 'price' && ($this->draft['core']['currency'] ?? '') === '') {
            $this->draft['core']['currency'] = 'IRT';
        }
        if ($block === 'recurrence' && ($this->draft['recurrence']['unit'] ?? '') === '') {
            $this->draft['recurrence'] = ['every' => '1', 'unit' => 'month', 'count' => ''];
        }
    }

    public function hideBlock(string $block): void
    {
        $this->shown = array_values(array_diff($this->shown, [$block]));
        $keys = match ($block) {
            'price' => ['price_min', 'price_max', 'currency', 'basis'],
            'place' => ['place'],
            'time' => ['starts_on', 'ends_on'],
            'quantity' => ['quantity', 'unit'],
            'route' => ['from', 'to'],
            default => [],
        };
        foreach ($keys as $key) {
            $this->draft['core'][$key] = '';
        }
        if ($block === 'recurrence') {
            $this->draft['recurrence'] = ['every' => '', 'unit' => '', 'count' => ''];
        }
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
        $this->reset('picking', 'search');
    }

    /** Something the library does not know yet: a plain text term the two sides agree on. */
    public function addCustom(): void
    {
        $label = trim($this->search);
        abort_if($label === '' || mb_strlen($label) > 120 || count($this->draft['fields']) >= 50, 422);
        $key = 'custom_'.substr(md5(mb_strtolower($label)), 0, 10);

        if (! in_array($key, array_column($this->draft['fields'], 'key'), true)) {
            $this->draft['fields'][] = ['key' => $key, 'label' => $label, 'type' => 'text', 'unit' => '', 'value' => ''];
        }
        $this->reset('picking', 'search');
    }

    public function removeField(int $index): void
    {
        unset($this->draft['fields'][$index]);
        $this->draft['fields'] = array_values($this->draft['fields']);
    }

    public function propose(ProposeDealTerms $propose): void
    {
        $this->resetErrorBag();
        $terms = DealTermsDraft::toTerms($this->draft);

        if (count($terms) === 1) {
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

        DealNote::post($this->relationship->contextBinding?->context, $this->user(), 'terms_proposed', ['version' => $version->version, 'proposal' => $version->proposal->uuid]);
        $this->reset('open', 'picking', 'search', 'versionNote');
        $this->dispatch('deal-changed');
    }

    public function render(): View
    {
        $latest = $this->open ? $this->latestVersion() : null;
        $base = $latest?->terms_data !== null ? DealTermsDiff::rows($latest->terms_data) : [];

        return view('livewire.deals.terms-composer', [
            'base' => $base,
            'baseVersion' => $latest?->version,
            'fromOrigin' => $this->open && $latest === null,
            'library' => $this->open && $this->picking ? $this->library() : [],
            'missingBlocks' => array_values(array_diff(self::BLOCKS, $this->shown)),
        ]);
    }

    /** @return list<array{key: string, label: string}> */
    private function library(): array
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

    /** @param array<string, mixed> $d @return list<string> */
    private function filledBlocks(array $d): array
    {
        $c = $d['core'];
        $filled = fn (array $keys): bool => collect($keys)->contains(fn ($k) => trim((string) ($c[$k] ?? '')) !== '');

        return array_values(array_filter(self::BLOCKS, fn (string $b): bool => match ($b) {
            'price' => $filled(['price_min', 'price_max', 'basis']),
            'place' => $filled(['place']),
            'time' => $filled(['starts_on', 'ends_on']),
            'quantity' => $filled(['quantity', 'unit']),
            'route' => $filled(['from', 'to']),
            'recurrence' => ($d['recurrence']['unit'] ?? '') !== '',
        }));
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

    private function authorizeParticipation(): void
    {
        Gate::forUser($this->user())->authorize('participate', $this->relationship);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
