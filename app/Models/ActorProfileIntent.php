<?php

namespace App\Models;

use App\ProfileIntentArrangementKind;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentStatus;
use App\ProfileIntentSubjectKind;
use App\ProfileItemVisibility;
use App\ProfileVisibility;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\RequestMemo;
use Database\Factories\ActorProfileIntentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'title',
    'description',
    'importance_percent',
    'subject_kind',
    'arrangement_kind',
    'exchange_preference',
    'cash_min',
    'cash_max',
    'currency_code',
    'cash_basis',
    'exchange_notes',
    'quantity',
    'unit',
    'location_text',
    'origin_text',
    'destination_text',
    'round_trip',
    'return_after_days',
    'schedule_kind',
    'starts_on',
    'ends_on',
    'timezone',
    'recurrence_interval',
    'recurrence_weekdays',
    'recurrence_day_of_month',
    'time_window_start',
    'time_window_end',
    'visibility',
    'metadata',
    'publisher_actor_id',
    'publisher_business_id',
    'publisher_group_id',
    'capability_id',
    'business_listing_id',
    'business_listing_version_id',
    'negotiable',
])]
class ActorProfileIntent extends Model
{
    /** @use HasFactory<ActorProfileIntentFactory> */
    use HasFactory;

    protected $attributes = [
        'round_trip' => false,
        'schedule_kind' => ProfileIntentScheduleKind::Once->value,
        'recurrence_interval' => 1,
        'visibility' => ProfileItemVisibility::Inherited->value,
        'status' => ProfileIntentStatus::Active->value,
        'subject_kind' => ProfileIntentSubjectKind::Other->value,
        'arrangement_kind' => ProfileIntentArrangementKind::Other->value,
        'exchange_preference' => ProfileIntentExchangePreference::DiscussLater->value,
    ];

    private bool $applyingStatus = false;

    protected static function booted(): void
    {
        static::creating(function (self $intent): void {
            $intent->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $intent): void {
            if ($intent->isDirty([
                'uuid',
                'actor_profile_id',
                'concept_id',
                'kind',
                'created_by_actor_id',
                'publisher_actor_id',
                'publisher_business_id',
                'publisher_group_id',
            ])) {
                throw new LogicException('Profile intent semantic identity and provenance cannot be reassigned.');
            }

            if ($intent->isDirty(['status', 'closed_at']) && ! $intent->applyingStatus) {
                throw new LogicException('Profile intent lifecycle changes require the dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Profile intents are preserved; close them instead.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<ActorProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ActorProfile::class, 'actor_profile_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** Who publishes it: the person, or the business or group they acted for. */
    public function publisher(): Party
    {
        return Party::fromRecord($this, 'publisher')
            ?? Party::make(Party::ACTOR, (int) $this->profile->actor_id);
    }

    /** @return BelongsTo<Actor, $this> */
    public function publisherActor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'publisher_actor_id');
    }

    /** @return BelongsTo<Business, $this> */
    public function publisherBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'publisher_business_id');
    }

    /** @return BelongsTo<Group, $this> */
    public function publisherGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'publisher_group_id');
    }

    /** @return BelongsTo<Capability, $this> */
    public function capability(): BelongsTo
    {
        return $this->belongsTo(Capability::class);
    }

    /** @return BelongsTo<BusinessListing, $this> */
    public function businessListing(): BelongsTo
    {
        return $this->belongsTo(BusinessListing::class);
    }

    /** @return BelongsTo<BusinessListingVersion, $this> */
    public function businessListingVersion(): BelongsTo
    {
        return $this->belongsTo(BusinessListingVersion::class);
    }

    /** @return HasMany<ActorProfileIntentMedia, $this> photos and short videos, in order */
    public function media(): HasMany
    {
        return $this->hasMany(ActorProfileIntentMedia::class)->orderBy('position')->orderBy('id');
    }

    /**
     * The single rule for who can see a Need or Offer (also used by the policy):
     * its creator and those who may act for its publisher always; others only
     * while it is active and its visibility allows them.
     *
     * @param  Builder<ActorProfileIntent>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        $current = $user instanceof User ? RequestMemo::user($user) : null;
        $actorId = $current?->actor?->id;
        $verified = $current instanceof User && $current->status === 'active' && $current->email_verified_at !== null;
        $memberships = $actorId !== null ? self::membershipsOf((int) $actorId) : ['businesses' => [], 'managed_businesses' => [], 'groups' => [], 'managed_groups' => []];

        $query->where(function (Builder $visible) use ($actorId, $verified, $memberships): void {
            if ($actorId !== null) {
                $visible->whereHas('profile', fn (Builder $profile) => $profile->where('actor_id', $actorId))
                    ->orWhere('publisher_actor_id', $actorId)
                    ->orWhereIn('publisher_business_id', $memberships['managed_businesses'])
                    ->orWhereIn('publisher_group_id', $memberships['managed_groups']);
            }

            $visible->orWhere(function (Builder $discoverable) use ($verified, $memberships): void {
                $discoverable->where('status', ProfileIntentStatus::Active->value)
                    ->where(function (Builder $publisher): void {
                        $publisher->where(fn (Builder $person) => $person->whereNull('publisher_business_id')->whereNull('publisher_group_id')
                            ->whereHas('profile.actor', fn (Builder $actor) => $actor->where('status', 'active')))
                            ->orWhereHas('publisherBusiness', fn (Builder $business) => $business->where('status', 'active'))
                            ->orWhereNotNull('publisher_group_id');
                    })
                    ->where(function (Builder $rule) use ($verified, $memberships): void {
                        $rule->where('visibility', ProfileItemVisibility::Public->value);
                        if ($verified) {
                            $rule->orWhere('visibility', ProfileItemVisibility::Authenticated->value);
                        }
                        $rule->orWhere(fn (Builder $members) => $members->where('visibility', ProfileItemVisibility::Members->value)
                            ->where(fn (Builder $of) => $of->whereIn('publisher_business_id', $memberships['businesses'])
                                ->orWhereIn('publisher_group_id', $memberships['groups'])));
                        $rule->orWhere(fn (Builder $inherited) => $inherited->where('visibility', ProfileItemVisibility::Inherited->value)
                            ->whereHas('profile', function (Builder $profile) use ($verified): void {
                                $profile->where('visibility', ProfileVisibility::Public->value);
                                if ($verified) {
                                    $profile->orWhere('visibility', ProfileVisibility::Authenticated->value);
                                }
                            }));
                    });
            });
        });
    }

    /** @return array{businesses: list<int>, managed_businesses: list<int>, groups: list<int>, managed_groups: list<int>} */
    public static function membershipsOf(int $actorId): array
    {
        return RequestMemo::remember('intent-memberships:'.$actorId, function () use ($actorId): array {
            $business = DB::table('business_memberships')->where('actor_id', $actorId)->where('status', 'active')->get(['business_id', 'role']);
            $groups = DB::table('group_memberships')->where('actor_id', $actorId)->where('status', 'active')->pluck('group_id')->map(fn ($id): int => (int) $id)->all();
            $user = User::query()->whereHas('actor', fn (Builder $actor) => $actor->whereKey($actorId))->first();
            $managedGroups = $user instanceof User
                ? array_values(array_filter($groups, fn (int $groupId): bool => app(PartyAuthority::class)->canActFor($user, Party::make(Party::GROUP, $groupId))))
                : [];

            return [
                'businesses' => $business->pluck('business_id')->map(fn ($id): int => (int) $id)->all(),
                'managed_businesses' => $business->whereIn('role', ['owner', 'manager'])->pluck('business_id')->map(fn ($id): int => (int) $id)->values()->all(),
                'groups' => $groups,
                'managed_groups' => $managedGroups,
            ];
        });
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    public function applyStatus(ProfileIntentStatus $status): void
    {
        $this->applyingStatus = true;

        try {
            $this->forceFill([
                'status' => $status,
                'closed_at' => $status === ProfileIntentStatus::Closed ? now() : null,
            ])->save();
        } finally {
            $this->applyingStatus = false;
        }
    }

    protected function casts(): array
    {
        return [
            'kind' => ProfileIntentKind::class,
            'subject_kind' => ProfileIntentSubjectKind::class,
            'arrangement_kind' => ProfileIntentArrangementKind::class,
            'exchange_preference' => ProfileIntentExchangePreference::class,
            'schedule_kind' => ProfileIntentScheduleKind::class,
            'visibility' => ProfileItemVisibility::class,
            'status' => ProfileIntentStatus::class,
            'quantity' => 'decimal:4',
            'cash_min' => 'decimal:2',
            'cash_max' => 'decimal:2',
            'importance_percent' => 'integer',
            'round_trip' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'recurrence_interval' => 'integer',
            'recurrence_weekdays' => 'array',
            'recurrence_day_of_month' => 'integer',
            'return_after_days' => 'integer',
            'closed_at' => 'datetime',
            'metadata' => 'array',
            'negotiable' => 'boolean',
        ];
    }
}
