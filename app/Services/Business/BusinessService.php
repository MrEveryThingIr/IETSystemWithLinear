<?php

namespace App\Services\Business;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\Actions\Contexts\EnsureBusinessContext;
use App\Models\Actor;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Profession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BusinessService
{
    public function __construct(
        private readonly EnsureBusinessContext $businessContexts,
        private readonly EnsureMonetaryUnit $monetaryUnits,
        private readonly EnsureRealEstateBusinessIntake $realEstateIntake,
    ) {}

    public function create(
        Actor $owner,
        array $data,
        bool $bootstrapVertical = true,
    ): Business {
        $iet = $this->monetaryUnits->execute('IET');
        $data['default_monetary_unit_id'] ??= $iet->id;
        $data['settings'] = array_replace_recursive([
            'finance' => [
                'internal_settlement_unit' => 'IET',
                'external_money_gateways' => 'placeholder',
            ],
        ], is_array($data['settings'] ?? null) ? $data['settings'] : []);

        $business = DB::transaction(function () use ($owner, $data): Business {
            $business = new Business($data);
            $business->owner()->associate($owner);
            $business->save();

            $business->memberships()->create([
                'actor_id' => $owner->getKey(),
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $business->refresh();
        });

        $this->businessContexts->execute($business);

        $business = $business->refresh();

        if ($bootstrapVertical && $business->kind === 'real_estate') {
            $this->realEstateIntake->execute($business);
        }

        return $business->refresh();
    }

    public function findActor(string $identifier): Actor
    {
        $identifier = trim($identifier);

        $query = User::query()->with('actor')->where('email', $identifier);

        if (Schema::hasColumn('users', 'username')) {
            $query->orWhere('username', $identifier);
        }

        $user = $query->first();

        if (! $user || $user->status !== 'active' || $user->email_verified_at === null) {
            throw ValidationException::withMessages([
                'user' => __('business.validation.user_not_found'),
            ]);
        }

        if (! $user->actor instanceof Actor || $user->actor->status !== 'active') {
            throw ValidationException::withMessages([
                'user' => __('business.validation.actor_inactive'),
            ]);
        }

        return $user->actor;
    }

    public function addMember(
        Business $business,
        Actor $actor,
        string $role = 'member',
        ?string $jobTitle = null
    ): BusinessMembership {
        if ($role === 'owner') {
            throw ValidationException::withMessages([
                'role' => __('business.validation.owner_role_via_transfer'),
            ]);
        }

        return $business->memberships()->updateOrCreate(
            ['actor_id' => $actor->getKey()],
            [
                'role' => $role,
                'job_title' => $jobTitle,
                'status' => 'active',
                'joined_at' => now(),
                'ended_at' => null,
            ]
        );
    }

    public function updateMember(
        Business $business,
        BusinessMembership $membership,
        array $data
    ): BusinessMembership {
        $this->guardMembership($business, $membership);

        if ($membership->role === 'owner') {
            throw ValidationException::withMessages([
                'role' => __('business.validation.owner_role_locked'),
            ]);
        }

        $membership->update([
            'role' => $data['role'],
            'job_title' => $data['job_title'] ?? null,
        ]);

        return $membership->refresh();
    }

    public function removeMember(
        Business $business,
        BusinessMembership $membership
    ): void {
        $this->guardMembership($business, $membership);

        if ($membership->role === 'owner') {
            throw ValidationException::withMessages([
                'member' => __('business.validation.owner_remove_forbidden'),
            ]);
        }

        $membership->update([
            'status' => 'inactive',
            'ended_at' => now(),
        ]);
    }

    public function assignProfession(
        Business $business,
        BusinessMembership $membership,
        Profession $profession,
        bool $primary = false
    ): void {
        $this->guardMembership($business, $membership);

        DB::transaction(function () use ($membership, $profession, $primary): void {
            if ($primary) {
                DB::table('business_membership_profession')
                    ->where('business_membership_id', $membership->getKey())
                    ->update(['is_primary' => false]);
            }

            $membership->professions()->syncWithoutDetaching([
                $profession->getKey() => ['is_primary' => $primary],
            ]);
        });
    }

    public function transferOwnership(
        Business $business,
        Actor $currentOwner,
        Actor $newOwner
    ): Business {
        if ((int) $business->owner_actor_id !== (int) $currentOwner->getKey()) {
            throw ValidationException::withMessages([
                'owner' => __('business.validation.only_owner_transfer'),
            ]);
        }

        if ((int) $currentOwner->getKey() === (int) $newOwner->getKey()) {
            throw ValidationException::withMessages([
                'owner' => __('business.validation.new_owner_different'),
            ]);
        }

        return DB::transaction(function () use ($business, $currentOwner, $newOwner): Business {
            /** @var Business $locked */
            $locked = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->owner_actor_id !== (int) $currentOwner->getKey()) {
                throw ValidationException::withMessages([
                    'owner' => __('business.validation.ownership_changed'),
                ]);
            }

            $oldMembership = $locked->memberships()
                ->where('actor_id', $currentOwner->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $newMembership = $locked->memberships()
                ->where('actor_id', $newOwner->getKey())
                ->lockForUpdate()
                ->first();

            if (! $newMembership || $newMembership->status !== 'active') {
                throw ValidationException::withMessages([
                    'owner' => __('business.validation.new_owner_active_member'),
                ]);
            }

            $oldMembership->update(['role' => 'manager']);
            $newMembership->update(['role' => 'owner']);
            $locked->owner_actor_id = $newOwner->getKey();
            $locked->save();

            return $locked->refresh();
        });
    }

    private function guardMembership(
        Business $business,
        BusinessMembership $membership
    ): void {
        abort_unless(
            (int) $membership->business_id === (int) $business->getKey(),
            404
        );
    }
}
