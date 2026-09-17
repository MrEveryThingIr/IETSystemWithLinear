<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\SpaceContentRenderTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleSpaceContentRenderTemplateFavorite
{
    public function execute(SpaceContentRenderTemplate $template, User $user): bool
    {
        return DB::transaction(function () use ($template, $user): bool {
            $current = SpaceContentRenderTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_unless($current->status === SpaceContentRenderTemplate::STATUS_ACTIVE, 422, 'Archived templates cannot be favorited.');
            $actor = $this->actor($user);
            $exists = DB::table('space_content_render_template_favorites')
                ->where('render_template_id', $current->id)
                ->where('actor_id', $actor->id)
                ->exists();

            if ($exists) {
                DB::table('space_content_render_template_favorites')
                    ->where('render_template_id', $current->id)
                    ->where('actor_id', $actor->id)
                    ->delete();

                return false;
            }

            DB::table('space_content_render_template_favorites')->insert([
                'render_template_id' => $current->id,
                'actor_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        }, 3);
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
