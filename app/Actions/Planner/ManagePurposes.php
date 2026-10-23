<?php

namespace App\Actions\Planner;

use App\Models\Plan;
use App\Models\Planner\Purpose;
use App\Models\User;
use App\Support\Planner\LabAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

final class ManagePurposes
{
    public function create(User $user, string $title, ?string $description = null, ?string $targetOn = null): Purpose
    {
        $actor = LabAuthority::actor($user);
        $data = Validator::make(['title' => trim($title), 'description' => $description, 'target_on' => $targetOn], [
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:10000'],
            'target_on' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        return Purpose::query()->create(['title' => $data['title'], 'description' => $data['description'],
            'target_on' => $data['target_on'], 'owner_actor_id' => $actor->id]);
    }

    public function link(User $user, Purpose $purpose, Plan $plan): void
    {
        $actor = LabAuthority::owns($user, (int) $purpose->owner_actor_id);
        Gate::forUser($user)->authorize('view', $plan);
        abort_unless($purpose->status === 'active', 422);
        DB::table('planner_purpose_contributions')->insertOrIgnore([
            'purpose_id' => $purpose->id, 'plan_id' => $plan->id, 'linked_by_actor_id' => $actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function archive(User $user, Purpose $purpose): void
    {
        LabAuthority::owns($user, (int) $purpose->owner_actor_id);
        $purpose->update(['status' => 'archived']);
    }

    public function update(User $user, Purpose $purpose, string $title, ?string $description, ?string $targetOn): Purpose
    {
        LabAuthority::owns($user, (int) $purpose->owner_actor_id);
        $data = Validator::make(['title' => trim($title), 'description' => $description, 'target_on' => $targetOn], [
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:10000'],
            'target_on' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();
        $purpose->update($data);

        return $purpose->fresh();
    }

    public function unlink(User $user, Purpose $purpose, Plan $plan): void
    {
        LabAuthority::owns($user, (int) $purpose->owner_actor_id);
        $purpose->plans()->detach($plan->id);
    }
}
