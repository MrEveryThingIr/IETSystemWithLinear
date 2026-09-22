<?php

namespace App\Actions\Interactions;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WithdrawSubmission
{
    public function execute(Submission $submission, User $user): Submission
    {
        return DB::transaction(function () use ($submission, $user): Submission {
            $current = Submission::query()
                ->with(['context', 'definitionVersion'])
                ->lockForUpdate()
                ->findOrFail($submission->id);

            Gate::forUser($user)->authorize('view', $current);

            if ($current->status === Submission::STATUS_WITHDRAWN) {
                return $current;
            }

            abort_unless($current->status === Submission::STATUS_SUBMITTED, 409, 'Only a submitted Submission can be withdrawn.');
            Gate::forUser($user)->authorize('withdraw', $current);

            $current->applyLifecycle([
                'status' => Submission::STATUS_WITHDRAWN,
                'withdrawn_at' => now(),
            ]);

            return $current->refresh();
        }, 3);
    }
}
