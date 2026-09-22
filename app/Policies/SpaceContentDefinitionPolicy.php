<?php

namespace App\Policies;

use App\Models\SpaceContentDefinition;
use App\Models\User;

class SpaceContentDefinitionPolicy
{
    public function __construct(private readonly ContextPolicy $contexts) {}

    public function view(User $user, SpaceContentDefinition $definition): bool
    {
        $definition->loadMissing('context');

        return $this->contexts->view($user, $definition->context);
    }

    public function manage(User $user, SpaceContentDefinition $definition): bool
    {
        $definition->loadMissing('context');

        return $this->contexts->manageDefinitions($user, $definition->context);
    }
}
