<?php

namespace App\Actions\Market;

use App\Models\IntentFieldProposal;
use App\Models\User;
use App\Support\Market\FieldSuggestionTypes;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentFieldLibrary;
use App\Support\Market\IntentFieldTaxonomy;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A person's suggestion to improve a field, its group or its fit. Only structure travels: which field,
 * the proposed wording, choices or rule, the context (topic, side, arrangement) and an optional
 * explanation. An answer, a file or a draft is never part of a suggestion.
 */
final class ProposeFieldChange
{
    private const OPEN_LIMIT = 20;

    private const TYPES_OF_VALUE = ['text', 'long_text', 'number', 'date', 'boolean', 'choice', 'link', 'year'];

    /**
     * @param  array<string, mixed>  $data  type-specific content
     * @param  array<string, mixed>  $context  topic, intention, arrangement, group
     */
    public function execute(User $user, string $type, ?string $key, array $data, array $context = [], string $explanation = ''): IntentFieldProposal
    {
        $actor = $user->actor;
        abort_unless($actor !== null && $user->status === 'active' && $user->hasVerifiedEmail(), 403);
        abort_unless(in_array($type, FieldSuggestionTypes::all(), true), 422);
        $needsField = ! in_array(FieldSuggestionTypes::groupOf($type), FieldSuggestionTypes::WITHOUT_FIELD, true);
        if ($needsField && ($key === null || ! isset(IntentDetailCatalog::all()[$key]))) {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.field')]);
        }
        $payload = $this->payload($type, $key, $data);
        $note = mb_substr(trim($explanation), 0, 1000);
        $context = array_intersect_key($context, array_flip(['topic', 'intention', 'arrangement', 'group', 'domain']));
        $open = IntentFieldProposal::query()->where('proposed_by_actor_id', $actor->id)->whereIn('status', [IntentFieldProposal::PROPOSED, IntentFieldProposal::REVIEWED]);
        $fingerprint = hash('sha256', json_encode([$type, $key, $payload]));
        foreach ((clone $open)->get() as $existing) {
            if (($existing->payload['_fingerprint'] ?? null) === $fingerprint) {
                return $existing;
            }
        }
        if ($open->count() >= self::OPEN_LIMIT) {
            throw ValidationException::withMessages(['field' => __('composer.suggest_limit')]);
        }

        return IntentFieldProposal::query()->create(['proposed_by_actor_id' => $actor->id, 'status' => IntentFieldProposal::PROPOSED, 'kind' => 'change',
            'change_type' => $type, 'target_key' => $key, 'payload' => [...$payload, '_fingerprint' => $fingerprint], 'context' => $context, 'explanation' => $note === '' ? null : $note]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(string $type, ?string $key, array $data): array
    {
        $text = fn (mixed $value, int $max = 240): string => mb_substr(trim((string) $value), 0, $max);
        $localized = function (mixed $values) use ($text): array {
            $clean = [];
            foreach ((array) $values as $locale => $value) {
                if (in_array($locale, ['en', 'fa', 'ar', 'zh_CN'], true) && $text($value) !== '') {
                    $clean[$locale] = $text($value);
                }
            }

            return $clean;
        };
        $words = fn (mixed $list): array => array_slice(array_values(array_unique(array_filter(array_map(fn ($item): string => $text($item, 60), is_array($list) ? $list : explode(',', (string) $list))))), 0, 20);

        return match ($type) {
            'label' => $this->nonEmpty(['labels' => $localized($data['labels'] ?? [])], 'labels'),
            'description' => $this->nonEmpty(['descriptions' => $localized($data['descriptions'] ?? [])], 'descriptions'),
            'example' => $this->nonEmpty(['examples' => $localized($data['examples'] ?? [])], 'examples'),
            'aliases' => $this->nonEmpty(['aliases' => array_filter(array_map(fn ($list): array => $words($list), (array) ($data['aliases'] ?? [])))], 'aliases'),
            'unit' => $this->nonEmpty(['unit' => $text($data['unit'] ?? '', 64)], 'unit'),
            'data_type' => $this->dataType($data, $text),
            'options' => $this->options($key, $data, $words),
            'group' => $this->group($data, $text),
            'applicability' => $this->applicability($data),
            'duplicate' => $this->duplicate($key, $data),
            'template' => $this->template($data, $text),
            'validation', 'input_control' => $this->nonEmpty(['message' => $text($data['message'] ?? '', 500), 'where' => in_array($data['where'] ?? '', ['validation', 'presentation', 'input'], true) ? $data['where'] : 'validation'], 'message'),
            default => throw ValidationException::withMessages(['field' => __('composer.suggest.errors.type')]),
        };
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function nonEmpty(array $payload, string $required): array
    {
        if (blank($payload[$required] ?? null)) {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.empty')]);
        }

        return $payload;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function dataType(array $data, callable $text): array
    {
        $validated = validator(['type' => $data['type'] ?? '', 'unit' => $text($data['unit'] ?? '', 64)], ['type' => ['required', Rule::in(self::TYPES_OF_VALUE)], 'unit' => ['nullable', 'string', 'max:64']])->validate();

        return ['type' => $validated['type'], 'unit' => $validated['unit'] ?: null];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function options(?string $key, array $data, callable $words): array
    {
        $spec = IntentDetailCatalog::all()[$key] ?? [];
        if (($spec['type'] ?? '') !== 'choice') {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.not_choice')]);
        }
        $add = array_values(array_diff($words($data['add'] ?? []), $spec['options'] ?? []));
        $remove = array_values(array_intersect($words($data['remove'] ?? []), $spec['options'] ?? []));
        if ($add === [] && $remove === []) {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.empty')]);
        }

        return ['add' => $add, 'remove' => $remove];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function group(array $data, callable $text): array
    {
        $action = (string) ($data['action'] ?? '');
        if (! in_array($action, ['add', 'remove', 'move', 'new'], true)) {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.empty')]);
        }
        $known = [...IntentFieldTaxonomy::knownGroupKeys(), ...IntentDetailCatalog::DOMAINS];
        $groupKey = (string) ($data['group_key'] ?? '');
        $toKey = (string) ($data['to_group_key'] ?? '');
        if ($action !== 'new' && ! in_array($groupKey, $known, true)) {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.group')]);
        }
        if ($action === 'move' && ! in_array($toKey, $known, true)) {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.group')]);
        }
        $label = $text($data['group_label'] ?? '', 80);
        if ($action === 'new' && ($label === '' || ! in_array((string) ($data['parent_key'] ?? ''), $known, true))) {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.group')]);
        }

        return array_filter(['action' => $action, 'group_key' => $groupKey ?: null, 'to_group_key' => $toKey ?: null, 'group_label' => $label ?: null, 'parent_key' => $data['parent_key'] ?? null]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function applicability(array $data): array
    {
        $validated = validator($data, ['effect' => ['required', Rule::in(['hide', 'only_need', 'only_offer', 'show_when'])], 'topic' => ['nullable', 'string', 'max:24'],
            'intention' => ['nullable', Rule::in(['need', 'offer'])], 'arrangement' => ['nullable', 'string', 'max:24'],
            'when_key' => ['nullable', 'string', 'max:64'], 'when_values' => ['nullable', 'array', 'max:10']])->validate();
        $condition = [];
        if ($validated['effect'] === 'show_when') {
            if (empty($validated['when_key']) || empty($validated['when_values']) || ! isset(IntentDetailCatalog::all()[$validated['when_key']])) {
                throw ValidationException::withMessages(['field' => __('composer.suggest.errors.empty')]);
            }
            $condition = ['only' => [$validated['when_key'] => array_values(array_map('strval', $validated['when_values']))]];
        }

        return array_filter(['effect' => $validated['effect'], 'topic' => $validated['topic'] ?? null, 'intention' => $validated['intention'] ?? null,
            'arrangement' => $validated['arrangement'] ?? null, 'condition' => $condition ?: null]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function duplicate(?string $key, array $data): array
    {
        $other = (string) ($data['other_key'] ?? '');
        if ($other === $key || ! isset(IntentDetailCatalog::all()[$other])) {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.other')]);
        }

        return ['other_key' => $other, 'note_words' => IntentFieldLibrary::normalize(IntentDetailCatalog::label($key ?? $other))];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function template(array $data, callable $text): array
    {
        $fields = array_values(array_filter((array) ($data['fields'] ?? []), fn ($candidate): bool => is_string($candidate) && isset(IntentDetailCatalog::all()[$candidate])));
        $name = $text($data['name'] ?? '', 80);
        if ($name === '' || count($fields) < 2) {
            throw ValidationException::withMessages(['field' => __('composer.suggest.errors.template')]);
        }

        return ['name' => $name, 'fields' => array_slice(array_values(array_unique($fields)), 0, 30), 'parent_key' => $data['parent_key'] ?? null];
    }
}
