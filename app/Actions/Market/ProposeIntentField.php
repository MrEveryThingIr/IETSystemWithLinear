<?php

namespace App\Actions\Market;

use App\Models\IntentFieldProposal;
use App\Models\User;
use App\Support\Market\IntentFieldLibrary;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Anyone may suggest a field for everyone. Only the field's shape travels (name, kind, unit, choices):
 * never an answer, so a suggestion cannot leak what someone wrote in their own post.
 */
final class ProposeIntentField
{
    public const TYPES = ['text', 'long_text', 'number', 'date', 'boolean', 'choice', 'link'];

    private const OPEN_LIMIT = 20;

    /** @param array<string, mixed> $row a custom detail row */
    public function execute(User $user, array $row, string $locale): IntentFieldProposal
    {
        $actor = $user->actor;
        abort_unless($actor !== null && $user->status === 'active' && $user->hasVerifiedEmail(), 403);
        $data = validator([
            'label' => trim((string) ($row['label'] ?? '')), 'type' => (string) ($row['type'] ?? ''), 'unit' => (string) ($row['unit'] ?? ''),
            'options' => array_values(array_filter(array_map(fn ($option): string => trim((string) $option), (array) ($row['options'] ?? [])))),
        ], ['label' => ['required', 'string', 'min:2', 'max:120'], 'type' => ['required', Rule::in(self::TYPES)], 'unit' => ['nullable', 'string', 'max:64'],
            'options' => ['array', 'max:30'], 'options.*' => ['string', 'max:60']])->validate();
        if ($data['type'] === 'choice' && count($data['options']) < 2) {
            throw ValidationException::withMessages(['details' => __('composer.suggest_needs_options')]);
        }
        $normalized = IntentFieldLibrary::normalize($data['label']);
        $open = IntentFieldProposal::query()->where('proposed_by_actor_id', $actor->id)->whereIn('status', [IntentFieldProposal::PROPOSED, IntentFieldProposal::REVIEWED]);
        foreach ((clone $open)->get() as $existing) {
            foreach ($existing->payload['labels'] ?? [] as $label) {
                if (IntentFieldLibrary::normalize((string) $label) === $normalized && ($existing->payload['type'] ?? '') === $data['type']) {
                    return $existing;
                }
            }
        }
        if ($open->count() >= self::OPEN_LIMIT) {
            throw ValidationException::withMessages(['details' => __('composer.suggest_limit')]);
        }

        return IntentFieldProposal::query()->create(['proposed_by_actor_id' => $actor->id, 'kind' => 'new', 'status' => IntentFieldProposal::PROPOSED,
            'payload' => ['labels' => [$locale => $data['label']], 'type' => $data['type'], 'unit' => $data['unit'] ?: null, 'options' => $data['options']]]);
    }
}
