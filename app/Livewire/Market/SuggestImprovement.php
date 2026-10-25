<?php

namespace App\Livewire\Market;

use App\Actions\Market\ProposeFieldChange;
use App\Models\User;
use App\Support\Market\FieldSuggestionTypes;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentFieldTaxonomy;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Suggest an improvement" without leaving the composition. It receives only structure (which field,
 * topic, side, arrangement, group, the keys of selected fields) and never an answer or a file.
 */
class SuggestImprovement extends Component
{
    public bool $open = false;

    public string $step = 'choose';

    public ?string $key = null;

    /** @var array<string, mixed> */
    public array $context = [];

    public string $group = '';

    public string $type = '';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $explanation = '';

    public bool $more = false;

    /**
     * @param  list<string>  $selected  keys of fields already chosen in the composition (never their values)
     */
    #[On('open-suggestion')]
    public function show(?string $key = null, string $topic = '', string $intention = '', string $arrangement = '', ?string $group = null, array $selected = []): void
    {
        abort_unless($this->user()->actor !== null, 403);
        $this->reset('step', 'type', 'data', 'explanation', 'more', 'group');
        $this->key = $key !== null && isset(IntentDetailCatalog::all()[$key]) ? $key : null;
        $this->context = ['topic' => $topic, 'intention' => $intention, 'arrangement' => $arrangement, 'group' => $group,
            'selected' => array_values(array_filter($selected, fn ($candidate): bool => isset(IntentDetailCatalog::all()[$candidate])))];
        $this->open = true;
        if ($this->key === null && $group !== null && $group !== '') {
            $this->pick('place');
            $this->data = ['action' => 'add', 'group_key' => $group];
        }
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function pick(string $group): void
    {
        abort_unless(isset(FieldSuggestionTypes::GROUPS[$group]), 422);
        $this->group = $group;
        $this->setType(FieldSuggestionTypes::GROUPS[$group][0]);
        $this->step = 'form';
    }

    public function setType(string $type): void
    {
        abort_unless(in_array($type, FieldSuggestionTypes::all(), true), 422);
        $this->type = $type;
        $this->data = match ($type) {
            'group' => ['action' => 'add'],
            'applicability' => ['effect' => 'hide', 'scoped' => true],
            'validation' => ['where' => 'validation'],
            default => [],
        };
    }

    public function back(): void
    {
        $this->step = 'choose';
    }

    public function submit(): void
    {
        $user = $this->user();
        $data = $this->data;
        $locale = app()->getLocale();
        // Plain-language inputs are written in the person's own language.
        foreach (['label' => 'labels', 'description' => 'descriptions', 'example' => 'examples'] as $type => $field) {
            if ($this->type === $type) {
                $data[$field] = [$locale => (string) ($data['text'] ?? '')];
            }
        }
        if ($this->type === 'aliases') {
            $data['aliases'] = [$locale => (string) ($data['text'] ?? '')];
        }
        if ($this->type === 'applicability') {
            $data['topic'] = ! empty($data['scoped']) ? ($this->context['topic'] ?? null) : null;
            $data['intention'] = ! empty($data['this_side']) ? ($this->context['intention'] ?? null) : null;
            if (($data['effect'] ?? '') === 'show_when') {
                $data['when_values'] = array_values(array_filter(array_map('trim', explode(',', (string) ($data['when_values'] ?? '')))));
            }
        }
        if ($this->type === 'template') {
            $data['fields'] = array_values(array_intersect((array) ($data['fields'] ?? []), (array) ($this->context['selected'] ?? [])));
        }
        try {
            app(ProposeFieldChange::class)->execute($user, $this->type, $this->key, $data, $this->context, $this->explanation);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        }
        $this->step = 'done';
    }

    public function render(): View
    {
        $options = [];
        if ($this->open && $this->key !== null) {
            $options = IntentDetailCatalog::all()[$this->key]['options'] ?? [];
        }
        $groups = [];
        if ($this->open && $this->step === 'form' && in_array($this->type, ['group', 'template'], true)) {
            foreach (IntentFieldTaxonomy::tree() as $domain => $branch) {
                $groups[$domain] = array_map(fn (array $group): array => ['key' => $group['key'], 'label' => $group['label']], $branch['groups']);
            }
        }

        return view('livewire.market.suggest-improvement', [
            'fieldLabel' => $this->key === null ? null : IntentDetailCatalog::label($this->key, null, $this->context['topic'] ?? null),
            'options' => $options, 'taxonomy' => $groups,
            'fields' => $this->open && in_array($this->type, ['duplicate', 'applicability'], true) ? array_keys(IntentDetailCatalog::offered()) : [],
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
