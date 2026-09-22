<?php

namespace App\Support;

use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
use App\Models\User;
use Illuminate\Support\Collection;

class ContentBlueprintCatalog
{
    public function __construct(
        private readonly EnsureSystemContentBlueprints $ensureSystem,
        private readonly ContentBlueprintAccess $access,
    ) {}

    /** @return Collection<int, ContentBlueprint> */
    public function availableFor(User $user, Context $context, ?string $search = null): Collection
    {
        $this->ensureSystem->execute();

        $current = User::query()->with('actor')->findOrFail($user->id);
        abort_unless($current->actor instanceof Actor, 403);

        $needle = mb_strtolower(trim((string) $search));

        return ContentBlueprint::query()
            ->where('status', ContentBlueprint::STATUS_ACTIVE)
            ->with('activeVersion')
            ->orderBy('category')
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->filter(function (ContentBlueprint $blueprint) use ($current, $context, $needle): bool {
                $version = $blueprint->activeVersion;
                if (! $version instanceof ContentBlueprintVersion
                    || ! $this->access->view($current, $context, $version)) {
                    return false;
                }

                if ($needle === '') {
                    return true;
                }

                return str_contains(mb_strtolower($blueprint->name), $needle)
                    || str_contains(mb_strtolower((string) $blueprint->description), $needle)
                    || str_contains(mb_strtolower($blueprint->category), $needle);
            })
            ->values();
    }
}
