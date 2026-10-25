<?php

namespace App\Actions\Items;

use App\Models\Actor;
use App\Models\Interest;
use App\Models\User;
use App\Support\Categories\CategoryCatalog;
use App\Support\Parties\Party;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The one service that creates, changes and removes a person's interests.
 * An interest always belongs to the signed-in person; one per category.
 */
class SaveInterest
{
    public function __construct(private readonly CategoryCatalog $catalog) {}

    /** @param array<string, mixed> $input */
    public function create(User $user, array $input): Interest
    {
        $actor = $this->actor($user);
        $data = $this->validate($input, $actor);

        return DB::transaction(function () use ($actor, $data): Interest {
            $existing = Interest::query()->heldBy($actor)->where('concept_id', $data['concept_id'])->lockForUpdate()->first();
            if ($existing instanceof Interest) {
                throw ValidationException::withMessages(['conceptId' => __('items.interests.errors.duplicate')]);
            }

            return Interest::query()->create([
                ...$data,
                'holder_actor_id' => $actor->id,
                'created_by_actor_id' => $actor->id,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, Interest $interest, array $input): Interest
    {
        Gate::forUser($user)->authorize('update', $interest);
        $actor = $this->actor($user);
        $data = $this->validate($input, $actor);

        if (Interest::query()->heldBy($actor)->whereKeyNot($interest->id)->where('concept_id', $data['concept_id'])->exists()) {
            throw ValidationException::withMessages(['conceptId' => __('items.interests.errors.duplicate')]);
        }

        $interest->update($data);

        return $interest->refresh();
    }

    public function remove(User $user, Interest $interest): void
    {
        Gate::forUser($user)->authorize('delete', $interest);
        $interest->delete();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{concept_id: int, purposes: list<string>, visibility: string, note: ?string}
     */
    private function validate(array $input, Actor $actor): array
    {
        $data = Validator::make($input, [
            'concept_id' => ['required', 'integer'],
            'purposes' => ['required', 'array', 'min:1'],
            'purposes.*' => ['string', Rule::in(Interest::PURPOSES)],
            'visibility' => ['required', Rule::in(Interest::VISIBILITIES)],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'purposes.required' => __('items.interests.errors.purpose'),
            'purposes.min' => __('items.interests.errors.purpose'),
        ], [
            'concept_id' => __('items.interests.fields.category'),
            'purposes' => __('items.interests.fields.purposes'),
            'visibility' => __('items.interests.fields.visibility'),
            'note' => __('items.interests.fields.note'),
        ])->validate();

        if (! $this->catalog->isUsableBy((int) $data['concept_id'], Party::of($actor))) {
            throw ValidationException::withMessages(['conceptId' => __('items.interests.errors.category')]);
        }

        return [
            'concept_id' => (int) $data['concept_id'],
            'purposes' => Interest::normalizePurposes($data['purposes']),
            'visibility' => (string) $data['visibility'],
            'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
        ];
    }

    private function actor(User $user): Actor
    {
        $actor = RequestMemo::user($user)?->actor;
        abort_unless($actor instanceof Actor && $user->status === 'active', 403);

        return $actor;
    }
}
