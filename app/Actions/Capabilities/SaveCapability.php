<?php

namespace App\Actions\Capabilities;

use App\Models\Actor;
use App\Models\BusinessMembership;
use App\Models\Capability;
use App\Models\User;
use App\Support\Categories\CategoryCatalog;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Declares or edits what a person, business or group can do. Ability only:
 * prices, availability and places belong to Offers.
 */
class SaveCapability
{
    public function __construct(
        private readonly CategoryCatalog $catalog,
        private readonly PartyAuthority $authority,
    ) {}

    /** @param array<string, mixed> $input */
    public function create(User $user, Party $holder, array $input): Capability
    {
        $data = $this->validate($input, $holder);
        abort_unless($this->mayMaintain($user, $holder, $data['business_membership_id']), 403);

        if (Capability::query()->heldBy($holder)->active()
            ->where('concept_id', $data['concept_id'])
            ->where('business_membership_id', $data['business_membership_id'])
            ->exists()) {
            throw ValidationException::withMessages(['conceptId' => __('capabilities.errors.duplicate')]);
        }

        return DB::transaction(function () use ($user, $holder, $data): Capability {
            if ($data['is_primary']) {
                Capability::query()->heldBy($holder)->update(['is_primary' => false]);
            }

            return Capability::query()->create([
                'holder_actor_id' => $holder->kind === Party::ACTOR ? $holder->id : null,
                'holder_business_id' => $holder->kind === Party::BUSINESS ? $holder->id : null,
                'holder_group_id' => $holder->kind === Party::GROUP ? $holder->id : null,
                ...$data,
                'sort_order' => (int) Capability::query()->heldBy($holder)->max('sort_order') + 1,
                'created_by_actor_id' => $this->actor($user)->id,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, Capability $capability, array $input): Capability
    {
        $holder = $capability->holder();
        abort_unless($this->mayMaintain($user, $holder, $capability->business_membership_id), 403);
        $data = $this->validate([...$input, 'business_membership_id' => $capability->business_membership_id], $holder);

        if (Capability::query()->heldBy($holder)->active()->whereKeyNot($capability->id)
            ->where('concept_id', $data['concept_id'])
            ->where('business_membership_id', $data['business_membership_id'])
            ->exists()) {
            throw ValidationException::withMessages(['conceptId' => __('capabilities.errors.duplicate')]);
        }

        return DB::transaction(function () use ($holder, $capability, $data): Capability {
            if ($data['is_primary']) {
                Capability::query()->heldBy($holder)->whereKeyNot($capability->id)->update(['is_primary' => false]);
            }
            $capability->update($data);

            return $capability->refresh();
        }, 3);
    }

    public function archive(User $user, Capability $capability): void
    {
        abort_unless($this->mayMaintain($user, $capability->holder(), $capability->business_membership_id), 403);
        $capability->update(['status' => Capability::STATUS_ARCHIVED, 'is_primary' => false]);
    }

    public function restore(User $user, Capability $capability): void
    {
        abort_unless($this->mayMaintain($user, $capability->holder(), $capability->business_membership_id), 403);
        $capability->update(['status' => Capability::STATUS_ACTIVE]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{concept_id: int, title: ?string, description: ?string, level: ?string, years_experience: ?int, self_confidence_percent: ?int, visibility: string, is_primary: bool, business_membership_id: ?int}
     */
    private function validate(array $input, Party $holder): array
    {
        $data = Validator::make($input, [
            'concept_id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'level' => ['nullable', Rule::in(Capability::LEVELS)],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'self_confidence_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'visibility' => ['required', Rule::in(Capability::VISIBILITIES)],
            'is_primary' => ['boolean'],
            'business_membership_id' => ['nullable', 'integer'],
        ], [], [
            'concept_id' => __('capabilities.fields.category'),
            'title' => __('capabilities.fields.title'),
            'description' => __('capabilities.fields.description'),
            'level' => __('capabilities.fields.level'),
            'years_experience' => __('capabilities.fields.years'),
            'self_confidence_percent' => __('capabilities.fields.confidence'),
            'visibility' => __('capabilities.fields.visibility'),
        ])->validate();

        $membershipId = $data['business_membership_id'] ?? null;
        $membership = null;
        if ($membershipId !== null) {
            // "Practised as a member of a business": only a person, only their own active membership.
            $membership = BusinessMembership::query()->whereKey($membershipId)->where('status', 'active')->first();
            abort_unless($holder->kind === Party::ACTOR && $membership instanceof BusinessMembership && (int) $membership->actor_id === $holder->id, 422);
        }

        $usable = $this->catalog->isUsableBy((int) $data['concept_id'], $holder)
            || ($membership instanceof BusinessMembership && $this->catalog->isUsableBy((int) $data['concept_id'], Party::make(Party::BUSINESS, (int) $membership->business_id)));
        if (! $usable) {
            throw ValidationException::withMessages(['conceptId' => __('capabilities.errors.category')]);
        }

        return [
            'concept_id' => (int) $data['concept_id'],
            'title' => filled($data['title'] ?? null) ? trim((string) $data['title']) : null,
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'level' => $data['level'] ?? null,
            'years_experience' => isset($data['years_experience']) ? (int) $data['years_experience'] : null,
            'self_confidence_percent' => isset($data['self_confidence_percent']) ? (int) $data['self_confidence_percent'] : null,
            'visibility' => $data['visibility'],
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'business_membership_id' => $membershipId !== null ? (int) $membershipId : null,
        ];
    }

    /** The holder, or — for a skill practised in a business — that business's managers. */
    public function mayMaintain(User $user, Party $holder, ?int $membershipId): bool
    {
        if ($this->authority->canActFor($user, $holder)) {
            return true;
        }
        if ($membershipId === null) {
            return false;
        }
        $businessId = BusinessMembership::query()->whereKey($membershipId)->value('business_id');

        return $businessId !== null && $this->authority->canActFor($user, Party::make(Party::BUSINESS, (int) $businessId));
    }

    private function actor(User $user): Actor
    {
        $actor = RequestMemo::user($user)?->actor;
        abort_unless($actor instanceof Actor, 403);

        return $actor;
    }
}
