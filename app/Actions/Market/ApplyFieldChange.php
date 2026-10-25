<?php

namespace App\Actions\Market;

use App\Models\IntentFieldDefinition;
use App\Models\IntentFieldProposal;
use App\Models\User;
use App\Support\Market\FieldSuggestionTypes;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentFieldTaxonomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Turns an approved suggestion into a compatibility-safe change. Definitions only ever gain a new
 * version; a different meaning (type or unit) becomes a successor key; taxonomy and rules are
 * append-only rows. Published posts keep the snapshot they were published with.
 */
final class ApplyFieldChange
{
    private const FILTERS = ['text' => 'value_text', 'choice' => 'value_text', 'number' => 'value_number', 'boolean' => 'value_boolean',
        'date' => 'value_date', 'long_text' => null, 'link' => null, 'year' => 'value_date'];

    private const LOCALES = ['en', 'fa', 'ar', 'zh_CN'];

    /**
     * @param  array<string, mixed>  $edit  the reviewer's adjustments: new_key, group_key, labels, payload overrides
     */
    public function apply(User $admin, IntentFieldProposal $proposal, array $edit = []): ?IntentFieldDefinition
    {
        $type = (string) $proposal->change_type;
        $payload = array_replace($proposal->payload, (array) ($edit['payload'] ?? []));
        $key = $proposal->target_key;

        return DB::transaction(function () use ($admin, $proposal, $type, $payload, $key, $edit): ?IntentFieldDefinition {
            $definition = match ($type) {
                'label' => $this->overlay($admin, $proposal, (string) $key, fn (array $base): array => [...$base, 'labels' => [...$base['labels'], ...$payload['labels']]]),
                'description' => $this->overlay($admin, $proposal, (string) $key, fn (array $base): array => [...$base, 'descriptions' => [...$base['descriptions'], ...$payload['descriptions']]]),
                'aliases' => $this->overlay($admin, $proposal, (string) $key, fn (array $base): array => [...$base, 'aliases' => $this->mergeAliases($base['aliases'], $payload['aliases'])]),
                'options' => $this->overlay($admin, $proposal, (string) $key, fn (array $base): array => [...$base,
                    'options' => array_values(array_unique([...$base['options'], ...($payload['add'] ?? [])])),
                    'hidden_options' => array_values(array_unique([...$base['hidden_options'], ...($payload['remove'] ?? [])]))]),
                'data_type', 'unit' => $this->successor($admin, $proposal, (string) $key, $payload, (string) ($edit['new_key'] ?? '')),
                'duplicate' => $this->merge($admin, $proposal, (string) $key, (string) $payload['other_key']),
                default => null,
            };
            match ($type) {
                'group' => $this->taxonomy($proposal, $payload, $edit, (string) $key),
                'template' => $this->newGroup($proposal, $payload, $edit),
                'applicability' => $this->rule($proposal, (string) $key, $payload),
                default => null,
            };
            $advisory = FieldSuggestionTypes::advisory($type);
            $proposal->update(['status' => $advisory ? IntentFieldProposal::APPROVED : IntentFieldProposal::PUBLISHED, 'definition_id' => $definition?->id,
                'reviewer_actor_id' => $admin->actor?->id, 'reviewed_at' => now(), 'payload' => $payload]);
            IntentDetailCatalog::bumpVersion();

            return $definition;
        });
    }

    /** Hide a field from the library for good; a successor may be named. Posts that used it keep working. */
    public function retire(User $admin, string $key, ?string $successor = null, ?IntentFieldProposal $proposal = null): IntentFieldDefinition
    {
        return $this->overlay($admin, $proposal, $key, fn (array $base): array => [...$base, 'retired' => true, 'superseded_by' => $successor]);
    }

    /**
     * Writes the next version of a definition from its current state plus a change.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutate
     */
    private function overlay(User $admin, ?IntentFieldProposal $proposal, string $key, callable $mutate): IntentFieldDefinition
    {
        $builtin = IntentDetailCatalog::codeDefinitions()[$key] ?? null;
        $published = IntentDetailCatalog::published()[$key] ?? null;
        if ($builtin === null && $published === null) {
            throw ValidationException::withMessages(['key' => __('field_review.errors.key_unknown')]);
        }
        $base = ['type' => $published['type'] ?? $builtin['type'], 'filter' => $published ? $published['filter'] : ($builtin['filter'] ?? null),
            'unit' => $published ? $published['unit'] : ($builtin['unit'] ?? null), 'options' => $published['options'] ?? [],
            'domains' => $published['domains'] ?? [$builtin['domain'] ?? 'general'], 'labels' => $published['labels'] ?? [], 'descriptions' => $published['descriptions'] ?? [],
            'aliases' => $published['aliases'] ?? [], 'hidden_options' => $published['hidden_options'] ?? [], 'superseded_by' => $published['superseded_by'] ?? null, 'retired' => false];
        $next = $mutate($base);
        $version = (int) IntentFieldDefinition::query()->where('field_key', $key)->max('version') + 1;
        IntentFieldDefinition::query()->where('field_key', $key)->where('status', 'published')->each(
            fn (IntentFieldDefinition $old) => $old->update(['status' => 'retired', 'retired_at' => now()]));

        return IntentFieldDefinition::query()->create(['field_key' => $key, 'version' => $version, 'status' => $next['retired'] ? 'retired' : 'published',
            'type' => $next['type'], 'filter' => $next['filter'], 'unit' => $next['unit'], 'options' => $next['options'], 'domains' => $next['domains'],
            'labels' => $next['labels'], 'descriptions' => $next['descriptions'], 'aliases' => $next['aliases'], 'hidden_options' => $next['hidden_options'],
            'superseded_by' => $next['superseded_by'], 'proposal_id' => $proposal?->id, 'published_by_actor_id' => $admin->actor?->id, 'published_at' => now(),
            'retired_at' => $next['retired'] ? now() : null]);
    }

    /**
     * @param  array<string, list<string>>  $current
     * @param  array<string, mixed>  $added
     * @return array<string, list<string>>
     */
    private function mergeAliases(array $current, array $added): array
    {
        foreach ($added as $locale => $list) {
            $current[$locale] = array_slice(array_values(array_unique([...($current[$locale] ?? []), ...array_map('strval', (array) $list)])), 0, 24);
        }

        return $current;
    }

    /**
     * A corrected type or unit is a different meaning, so it becomes a new field. The old one is
     * retired and points to its successor; posts that used it are untouched.
     *
     * @param  array<string, mixed>  $payload
     */
    private function successor(User $admin, IntentFieldProposal $proposal, string $key, array $payload, string $newKey): IntentFieldDefinition
    {
        $this->assertKey($newKey);
        $current = IntentDetailCatalog::all()[$key];
        $type = (string) ($payload['type'] ?? $current['type']);
        $unit = array_key_exists('unit', $payload) ? $payload['unit'] : ($current['unit'] ?? null);
        $labels = [];
        foreach (self::LOCALES as $locale) {
            $labels[$locale] = IntentDetailCatalog::label($key, $locale);
        }
        $created = IntentFieldDefinition::query()->create(['field_key' => $newKey, 'version' => 1, 'status' => 'published', 'type' => $type, 'filter' => self::FILTERS[$type] ?? null,
            'unit' => $unit ?: null, 'options' => $type === 'choice' ? ($current['options'] ?? []) : [], 'domains' => [$current['domain'] ?? 'general'], 'labels' => $labels,
            'descriptions' => [], 'aliases' => [], 'proposal_id' => $proposal->id, 'published_by_actor_id' => $admin->actor?->id, 'published_at' => now()]);
        $this->retire($admin, $key, $newKey, $proposal);

        return $created;
    }

    /** "This field duplicates that one": its words become synonyms of the survivor and it is retired. */
    private function merge(User $admin, IntentFieldProposal $proposal, string $key, string $survivor): IntentFieldDefinition
    {
        $aliases = [];
        foreach (self::LOCALES as $locale) {
            $aliases[$locale] = [IntentDetailCatalog::label($key, $locale), ...IntentDetailCatalog::aliases($key, $locale)];
        }
        $result = $this->overlay($admin, $proposal, $survivor, fn (array $base): array => [...$base, 'aliases' => $this->mergeAliases($base['aliases'], $aliases)]);
        $this->retire($admin, $key, $survivor, $proposal);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $edit
     */
    private function taxonomy(IntentFieldProposal $proposal, array $payload, array $edit, string $key): void
    {
        $action = (string) $payload['action'];
        if ($action === 'new') {
            $this->createGroup($proposal, (string) ($edit['group_key'] ?? ''), (string) ($payload['parent_key'] ?? ''), (array) ($edit['labels'] ?? []), $key !== '' ? [$key] : []);

            return;
        }
        if (in_array($action, ['remove', 'move'], true)) {
            DB::table('intent_field_memberships')->insert(['group_key' => $payload['group_key'], 'field_key' => $key, 'proposal_id' => $proposal->id, 'added_at' => now(), 'removed_at' => now()]);
        }
        if (in_array($action, ['add', 'move'], true)) {
            $target = $action === 'move' ? $payload['to_group_key'] : $payload['group_key'];
            DB::table('intent_field_memberships')->insert(['group_key' => $target, 'field_key' => $key, 'proposal_id' => $proposal->id, 'added_at' => now()]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $edit
     */
    private function newGroup(IntentFieldProposal $proposal, array $payload, array $edit): void
    {
        $labels = (array) ($edit['labels'] ?? []);
        $labels = array_filter($labels) === [] ? ['en' => (string) $payload['name']] : $labels;
        $this->createGroup($proposal, (string) ($edit['group_key'] ?? ''), (string) ($edit['payload']['parent_key'] ?? $payload['parent_key'] ?? ''), $labels, (array) $payload['fields']);
    }

    /** @param array<string, string> $labels @param list<string> $fields */
    private function createGroup(IntentFieldProposal $proposal, string $groupKey, string $parentKey, array $labels, array $fields): void
    {
        $this->assertKey($groupKey);
        $known = [...IntentFieldTaxonomy::knownGroupKeys(), ...IntentDetailCatalog::DOMAINS];
        if (in_array($groupKey, IntentFieldTaxonomy::knownGroupKeys(), true) || ! in_array($parentKey, $known, true)) {
            throw ValidationException::withMessages(['key' => __('field_review.errors.group')]);
        }
        $labels = array_filter(array_map(fn ($label): string => trim((string) $label), $labels));
        if (($labels['en'] ?? '') === '' && count($labels) === 0) {
            throw ValidationException::withMessages(['labels' => __('field_review.errors.translations')]);
        }
        DB::table('intent_field_groups')->insert(['uuid' => (string) Str::uuid(), 'group_key' => $groupKey, 'version' => 1, 'parent_key' => $parentKey, 'status' => 'published',
            'labels' => json_encode($labels, JSON_UNESCAPED_UNICODE), 'position' => 0, 'proposal_id' => $proposal->id, 'published_at' => now()]);
        foreach ($fields as $position => $field) {
            DB::table('intent_field_memberships')->insert(['group_key' => $groupKey, 'field_key' => $field, 'position' => $position, 'proposal_id' => $proposal->id, 'added_at' => now()]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function rule(IntentFieldProposal $proposal, string $key, array $payload): void
    {
        validator($payload, ['effect' => ['required', Rule::in(['hide', 'only_need', 'only_offer', 'show_when'])]])->validate();
        DB::table('intent_field_rules')->insert(['uuid' => (string) Str::uuid(), 'field_key' => $key, 'topic' => $payload['topic'] ?? null, 'intention' => $payload['intention'] ?? null,
            'arrangement' => $payload['arrangement'] ?? null, 'effect' => $payload['effect'], 'condition' => isset($payload['condition']) ? json_encode($payload['condition']) : null,
            'proposal_id' => $proposal->id, 'created_at' => now()]);
    }

    private function assertKey(string $key): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{2,60}$/', $key) !== 1) {
            throw ValidationException::withMessages(['key' => __('field_review.errors.key_format')]);
        }
        if (isset(IntentDetailCatalog::all()[$key])) {
            throw ValidationException::withMessages(['key' => __('field_review.errors.key_taken')]);
        }
    }
}
