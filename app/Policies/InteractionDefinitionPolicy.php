<?php

namespace App\Policies;

use App\Models\InteractionDefinition;
use App\Models\User;

class InteractionDefinitionPolicy
{
    public function __construct(private readonly ContextPolicy $contexts) {}

    public function view(User $user, InteractionDefinition $definition): bool
    {
        $definition->loadMissing('context');

        return $this->contexts->view($user, $definition->context);
    }

    public function update(User $user, InteractionDefinition $definition): bool
    {
        $definition->loadMissing('context');

        return $definition->status !== InteractionDefinition::STATUS_RETIRED
            && $this->contexts->manageInteractions($user, $definition->context);
    }

    public function activate(User $user, InteractionDefinition $definition): bool
    {
        return $this->update($user, $definition);
    }

    public function submit(User $user, InteractionDefinition $definition): bool
    {
        $definition->loadMissing('context');

        return $definition->status === InteractionDefinition::STATUS_ACTIVE
            && $definition->active_version_id !== null
            && $this->contexts->submitInteractions($user, $definition->context);
    }
}
