<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceGrantService;

trait PublishesFeatureSurfaces
{
    protected function publishSurfaces(User $user, array $surfaces): array
    {
        return app(FeatureSurfaceGrantService::class)
            ->sync($user, $surfaces, null);
    }
}
