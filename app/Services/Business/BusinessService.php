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
    ) {}

    public function create(Actor $owner, array $data): Business
    {
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
                'user' => 'کاربر فعال و تأییدشده‌ای با این ایمیل یا نام کاربری پیدا نشد.',
            ]);
        }

        if (! $user->actor instanceof Actor || $user->actor->status !== 'active') {
            throw ValidationException::withMessages([
                'user' => 'این حساب، هویت کنشگر فعال ندارد.',
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
                'role' => 'برای تغییر مالک از عملیات انتقال مالکیت استفاده کنید.',
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
                'role' => 'نقش مالک فقط از طریق انتقال مالکیت تغییر می‌کند.',
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
                'member' => 'مالک کسب‌وکار را نمی‌توان حذف کرد. ابتدا مالکیت را منتقل کنید.',
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
                'owner' => 'فقط مالک فعلی می‌تواند مالکیت را منتقل کند.',
            ]);
        }

        if ((int) $currentOwner->getKey() === (int) $newOwner->getKey()) {
            throw ValidationException::withMessages([
                'owner' => 'مالک جدید باید شخص دیگری باشد.',
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
                    'owner' => 'مالکیت در همین لحظه تغییر کرده است؛ صفحه را تازه کنید.',
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
                    'owner' => 'مالک جدید باید ابتدا عضو فعال این کسب‌وکار باشد.',
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
