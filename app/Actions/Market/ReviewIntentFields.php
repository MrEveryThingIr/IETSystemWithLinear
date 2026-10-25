<?php

namespace App\Actions\Market;

use App\Models\IntentFieldDefinition;
use App\Models\IntentFieldProposal;
use App\Models\User;
use App\PlatformCapability;
use App\Support\Market\IntentDetailCatalog;
use App\Support\Market\IntentFieldLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Administrators turn suggestions into shared, versioned definitions: proposed, reviewed,
 * approved or rejected, then published. A built-in key keeps its type, unit and filter forever;
 * review can only improve its words and add choices. Published versions are never edited, so a
 * post published earlier keeps the meaning it had.
 */
final class ReviewIntentFields
{
    private const FILTERS = ['text' => 'value_text', 'choice' => 'value_text', 'number' => 'value_number', 'boolean' => 'value_boolean',
        'date' => 'value_date', 'long_text' => null, 'link' => null];

    private const LOCALES = ['en', 'fa', 'ar', 'zh_CN'];

    public function canReview(?User $user): bool
    {
        return $user instanceof User && $user->hasPlatformCapability(PlatformCapability::ManageConcepts);
    }

    /**
     * Existing fields that already mean the same thing: same words in any language, or an alias.
     *
     * @return list<string>
     */
    public function duplicates(IntentFieldProposal $proposal): array
    {
        $words = [];
        foreach ($proposal->payload['labels'] ?? [] as $label) {
            $words[] = IntentFieldLibrary::normalize((string) $label);
        }
        $words = array_filter($words);
        $found = [];
        foreach (IntentDetailCatalog::offered() as $key => $spec) {
            foreach (['en', 'fa', 'ar', 'zh_CN'] as $locale) {
                $candidates = [IntentDetailCatalog::label($key, $locale), ...IntentDetailCatalog::aliases($key, $locale)];
                foreach ($candidates as $candidate) {
                    if (in_array(IntentFieldLibrary::normalize($candidate), $words, true)) {
                        $found[$key] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }

    /** Save the reviewer's edits and mark the proposal reviewed. @param array<string, mixed> $edit */
    public function review(User $admin, IntentFieldProposal $proposal, array $edit): void
    {
        $this->authorize($admin, $proposal);
        $proposal->update(['status' => IntentFieldProposal::REVIEWED, 'payload' => $this->merge($proposal, $edit), 'reviewer_actor_id' => $admin->actor?->id,
            'reviewed_at' => now(), 'duplicate_of' => $edit['duplicate_of'] ?? null]);
    }

    /** Approve and publish as the next version of the key. @param array<string, mixed> $edit */
    public function approve(User $admin, IntentFieldProposal $proposal, array $edit): ?IntentFieldDefinition
    {
        $this->authorize($admin, $proposal);
        if ($proposal->change_type !== null) {
            return app(ApplyFieldChange::class)->apply($admin, $proposal, $edit);
        }
        $payload = $this->merge($proposal, $edit);
        $key = (string) ($edit['key'] ?? $proposal->target_key ?? '');
        $builtin = IntentDetailCatalog::codeDefinitions()[$key] ?? null;
        $published = IntentDetailCatalog::published()[$key] ?? null;
        if ($proposal->kind === 'new' && ($builtin !== null || $published !== null)) {
            throw ValidationException::withMessages(['key' => __('field_review.errors.key_taken')]);
        }
        if ($proposal->kind === 'change' && $builtin === null && $published === null) {
            throw ValidationException::withMessages(['key' => __('field_review.errors.key_unknown')]);
        }
        $type = $builtin['type'] ?? $published['type'] ?? $payload['type'];
        $unit = $builtin !== null ? ($builtin['unit'] ?? null) : ($published['unit'] ?? $payload['unit'] ?? null);
        $data = validator(['key' => $key, 'type' => $type, 'labels' => $payload['labels'], 'domains' => $payload['domains'] ?? [],
            'options' => $payload['options'] ?? [], 'unit' => $unit], [
                'key' => ['required', 'regex:/^[a-z][a-z0-9_]{2,60}$/'], 'type' => ['required', Rule::in(array_keys(self::FILTERS))],
                'labels' => ['required', 'array'], 'labels.*' => ['required', 'string', 'min:2', 'max:120'],
                'domains' => ['array', 'max:3'], 'domains.*' => [Rule::in(IntentDetailCatalog::DOMAINS)],
                'options' => ['array', 'max:30'], 'options.*' => ['string', 'max:60'], 'unit' => ['nullable', 'string', 'max:64'],
            ])->validate();
        foreach (self::LOCALES as $locale) {
            if (trim((string) ($data['labels'][$locale] ?? '')) === '') {
                throw ValidationException::withMessages(['labels' => __('field_review.errors.translations')]);
            }
        }
        if ($data['type'] === 'choice' && count($data['options']) < 2) {
            throw ValidationException::withMessages(['options' => __('field_review.errors.options')]);
        }

        return DB::transaction(function () use ($admin, $proposal, $payload, $data): IntentFieldDefinition {
            $version = (int) IntentFieldDefinition::query()->where('field_key', $data['key'])->max('version') + 1;
            IntentFieldDefinition::query()->where('field_key', $data['key'])->where('status', 'published')->each(
                fn (IntentFieldDefinition $old) => $old->update(['status' => 'retired', 'retired_at' => now()]));
            $definition = IntentFieldDefinition::query()->create(['field_key' => $data['key'], 'version' => $version, 'status' => 'published',
                'type' => $data['type'], 'filter' => self::FILTERS[$data['type']], 'unit' => $data['unit'] ?: null, 'options' => $data['options'],
                'domains' => $data['domains'] ?: ['general'], 'labels' => $data['labels'], 'descriptions' => $payload['descriptions'] ?? [],
                'aliases' => $this->aliasLists($payload['aliases'] ?? []), 'proposal_id' => $proposal->id,
                'published_by_actor_id' => $admin->actor?->id, 'published_at' => now()]);
            $proposal->update(['status' => IntentFieldProposal::PUBLISHED, 'definition_id' => $definition->id, 'reviewer_actor_id' => $admin->actor?->id,
                'reviewed_at' => now(), 'payload' => $payload, 'target_key' => $data['key']]);
            IntentDetailCatalog::bumpVersion();

            return $definition;
        });
    }

    public function reject(User $admin, IntentFieldProposal $proposal, string $note, ?string $duplicateOf = null): void
    {
        $this->authorize($admin, $proposal);
        validator(['note' => trim($note)], ['note' => ['required', 'string', 'max:1000']])->validate();
        $proposal->update(['status' => IntentFieldProposal::REJECTED, 'review_note' => trim($note), 'duplicate_of' => $duplicateOf,
            'reviewer_actor_id' => $admin->actor?->id, 'reviewed_at' => now()]);
    }

    /** Hide a shared field from suggestions and pickers; posts that already use it keep working. */
    public function retire(User $admin, string $key): void
    {
        abort_unless($this->canReview($admin), 403);
        if (isset(IntentDetailCatalog::codeDefinitions()[$key])) {
            // A built-in is hidden by an overlay version; the code definition itself never changes.
            app(ApplyFieldChange::class)->retire($admin, $key);
        } else {
            IntentFieldDefinition::query()->where('field_key', $key)->orderByDesc('id')->firstOrFail()->update(['status' => 'retired', 'retired_at' => now()]);
        }
        IntentDetailCatalog::bumpVersion();
    }

    private function authorize(User $admin, IntentFieldProposal $proposal): void
    {
        abort_unless($this->canReview($admin), 403);
        abort_if(in_array($proposal->status, [IntentFieldProposal::PUBLISHED, IntentFieldProposal::REJECTED], true), 409);
    }

    /**
     * @param  array<string, mixed>  $edit
     * @return array<string, mixed>
     */
    private function merge(IntentFieldProposal $proposal, array $edit): array
    {
        $payload = $proposal->payload;
        foreach (['labels', 'descriptions', 'aliases', 'domains', 'options', 'unit'] as $field) {
            if (array_key_exists($field, $edit)) {
                $payload[$field] = $edit[$field];
            }
        }
        $payload['type'] = $payload['type'] ?? 'text';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @return array<string, list<string>>
     */
    private function aliasLists(array $aliases): array
    {
        $lists = [];
        foreach ($aliases as $locale => $value) {
            $items = is_array($value) ? $value : explode(',', (string) $value);
            $items = array_values(array_filter(array_map(fn ($item): string => mb_substr(trim((string) $item), 0, 60), $items)));
            if ($items !== []) {
                $lists[(string) $locale] = array_slice($items, 0, 12);
            }
        }

        return $lists;
    }
}
