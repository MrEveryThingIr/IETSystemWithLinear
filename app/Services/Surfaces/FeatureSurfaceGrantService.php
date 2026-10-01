<?php

namespace App\Services\Surfaces;

use App\Models\FeatureSurfaceEvent;
use App\Models\FeatureSurfaceGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FeatureSurfaceGrantService
{
    public function __construct(private readonly FeatureSurfaceRegistry $registry) {}

    public function sync(User $subject, array $requestedKeys, ?User $actor): array
    {
        foreach ($requestedKeys as $key) {
            if (! is_string($key) || ! in_array($key, $this->registry->grantableKeys(), true)) {
                throw new InvalidArgumentException('Only grantable feature surfaces may be assigned.');
            }
        }

        $requestedKeys = array_values(array_unique(array_filter($requestedKeys)));
        $resolved = $this->registry->dependencyClosure($requestedKeys);
        DB::transaction(function () use ($subject, $requestedKeys, $resolved, $actor): void {
            $existing = FeatureSurfaceGrant::query()->where('user_id', $subject->getKey())->pluck('surface_key')->all();
            foreach (array_diff($resolved, $existing) as $key) {
                FeatureSurfaceGrant::query()->create(['user_id' => $subject->getKey(), 'surface_key' => $key, 'granted_by_user_id' => $actor?->getKey(), 'granted_at' => now()]);
                FeatureSurfaceEvent::query()->create(['actor_user_id' => $actor?->getKey(), 'subject_user_id' => $subject->getKey(), 'surface_key' => $key, 'event' => 'granted']);
            }
            $revoke = array_values(array_diff($existing, $resolved));
            if ($revoke) {
                FeatureSurfaceGrant::query()->where('user_id', $subject->getKey())->whereIn('surface_key', $revoke)->delete();
                foreach ($revoke as $key) {
                    FeatureSurfaceEvent::query()->create(['actor_user_id' => $actor?->getKey(), 'subject_user_id' => $subject->getKey(), 'surface_key' => $key, 'event' => 'revoked']);
                }
            }
            FeatureSurfaceEvent::query()->create(['actor_user_id' => $actor?->getKey(), 'subject_user_id' => $subject->getKey(), 'event' => 'synced', 'metadata' => ['requested' => $requestedKeys, 'resolved' => $resolved]]);
        });

        return $resolved;
    }
}
