<?php

namespace App\Actions\Groups;

use App\Models\SpaceContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishSpaceContent
{
    public function execute(SpaceContent $content, User $user): SpaceContent
    {
        return DB::transaction(function () use ($content, $user): SpaceContent {
            $current = SpaceContent::query()->with('space')->lockForUpdate()->findOrFail($content->id);
            Gate::forUser($user)->authorize('publish', $current);
            abort_unless($current->status === 'draft', 422, 'Only draft Content may be published.');

            $current->currentRevisionRecord();
            $current->applyLifecycle([
                'status' => 'published',
                'published_at' => now(),
            ]);

            return $current->refresh();
        }, 3);
    }
}
