<?php

namespace App\Actions\DomainBlueprints;

use App\Models\DomainBlueprint;
use App\Models\DomainBlueprintVersion;
use App\Support\DomainBlueprintConfig;
use App\Support\SystemDomainBlueprints;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnsureSystemDomainBlueprints
{
    public function __construct(
        private readonly SystemDomainBlueprints $presets,
        private readonly DomainBlueprintConfig $config,
    ) {}

    /** @return Collection<int, DomainBlueprint> */
    public function execute(): Collection
    {
        return DB::transaction(function (): Collection {
            $ensured = collect();

            foreach ($this->presets->all() as $preset) {
                $blueprint = DomainBlueprint::query()
                    ->where('slug', $preset['slug'])
                    ->lockForUpdate()
                    ->first();

                if (! $blueprint instanceof DomainBlueprint) {
                    $blueprint = DomainBlueprint::query()->create([
                        'slug' => $preset['slug'],
                        'name' => $preset['name'],
                        'description' => $preset['description'],
                        'category' => $preset['category'],
                        'status' => DomainBlueprint::STATUS_ACTIVE,
                        'current_version' => 0,
                    ]);
                } else {
                    $blueprint->update([
                        'name' => $preset['name'],
                        'description' => $preset['description'],
                        'category' => $preset['category'],
                        'status' => DomainBlueprint::STATUS_ACTIVE,
                    ]);
                }

                $versionData = $preset['version'];
                $normalized = $this->config->normalize(
                    $versionData['journey_kind'],
                    $versionData['terminology'],
                    $versionData['capabilities'],
                    $versionData['content_blueprint_slugs'],
                    $versionData['guided_entry'],
                );

                $active = $blueprint->activeVersionRecord();

                if (! $active instanceof DomainBlueprintVersion
                    || ! hash_equals($active->content_hash, $normalized['content_hash'])) {
                    $nextVersion = ((int) $blueprint->versions()->max('version')) + 1;

                    $active = $blueprint->versions()->create([
                        'version' => $nextVersion,
                        ...$normalized,
                        'created_by_actor_id' => null,
                        'published_at' => null,
                    ]);

                    $active->publish();

                    $blueprint->update([
                        'status' => DomainBlueprint::STATUS_ACTIVE,
                        'current_version' => $nextVersion,
                    ]);
                }

                $ensured->push($blueprint->refresh());
            }

            return $ensured;
        }, attempts: 3);
    }
}
