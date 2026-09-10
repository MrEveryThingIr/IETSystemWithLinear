<?php

namespace App\Policies;

use App\Models\Admission;
use App\Models\User;

class AdmissionPolicy
{
    public function view(User $user, Admission $admission): bool
    {
        return $user->status === 'active' && ((int) $user->actor?->id === (int) $admission->candidate_actor_id || $user->can('manageAdmissions', $admission->group));
    }

    public function update(User $user, Admission $admission): bool
    {
        return $this->view($user, $admission);
    }
}
