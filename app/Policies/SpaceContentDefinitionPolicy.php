<?php

namespace App\Policies;

use App\Models\SpaceContentDefinition;
use App\Models\User;

class SpaceContentDefinitionPolicy
{
    public function __construct(private readonly GroupSpacePolicy $spaces) {}

    public function view(User $user, SpaceContentDefinition $definition): bool
    {
        $definition->loadMissing('space');

        return $this->spaces->view($user, $definition->space);
    }

    public function manage(User $user, SpaceContentDefinition $definition): bool
    {
        $definition->loadMissing('space');

        return $this->spaces->manage($user, $definition->space);
    }
}
