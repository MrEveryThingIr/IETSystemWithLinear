<?php

namespace App\Actions\Content;

use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Support\ContentBlueprintConfig;
use App\Support\SystemContentBlueprints;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnsureSystemContentBlueprints
{
    public function __construct(
        private readonly SystemContentBlueprints $presets,
        private readonly ContentBlueprintConfig $config,
    ) {}

    /** @return Collection<int, ContentBlueprint> */
    public function execute(): Collection
    {
        return DB::transaction(function (): Collection {
            $ensured = collect();

            foreach ($this->presets->all() as $preset) {
                $blueprint = ContentBlueprint::query()
                    ->where('scope', ContentBlueprint::SCOPE_SYSTEM)
                    ->where('slug', $preset['slug'])
                    ->lockForUpdate()
                    ->first();

                if (! $blueprint instanceof ContentBlueprint) {
                    $blueprint = ContentBlueprint::query()->create([
                        'slug' => $preset['slug'],
                        'name' => $preset['name'],
                        'description' => $preset['description'],
                        'category' => $preset['category'],
                        'scope' => ContentBlueprint::SCOPE_SYSTEM,
                        'owner_actor_id' => null,
                        'context_id' => null,
                        'status' => ContentBlueprint::STATUS_ACTIVE,
                        'current_version' => 1,
                    ]);
                } else {
                    $blueprint->update([
                        'name' => $preset['name'],
                        'description' => $preset['description'],
                        'category' => $preset['category'],
                    ]);
                }

                $versionData = $preset['version'];
                $normalized = $this->config->normalize(
                    $versionData['definition_schema'],
                    $versionData['initial_blocks'],
                    $versionData['render_template_key'],
                    $versionData['presentation'],
                    $versionData['context_kinds'],
                    $versionData['concept_defaults'],
                    $versionData['interaction_defaults'],
                    $versionData['authoring'],
                );

                $active = $blueprint->activeVersionRecord();

                if (! $active instanceof ContentBlueprintVersion
                    || ! hash_equals($active->content_hash, $normalized['content_hash'])) {
                    $nextVersion = ((int) $blueprint->versions()->max('version')) + 1;

                    $active = $blueprint->versions()->create([
                        'version' => $nextVersion,
                        'definition_schema' => $normalized['definition_schema'],
                        'initial_blocks' => $normalized['initial_blocks'],
                        'render_template_key' => $normalized['render_template_key'],
                        'presentation' => $normalized['presentation'],
                        'context_kinds' => $normalized['context_kinds'],
                        'concept_defaults' => $normalized['concept_defaults'],
                        'interaction_defaults' => $normalized['interaction_defaults'],
                        'authoring' => $normalized['authoring'],
                        'created_by_actor_id' => null,
                        'published_at' => null,
                    ]);
                    $active->publish();

                    $blueprint->applyLifecycle([
                        'status' => ContentBlueprint::STATUS_ACTIVE,
                        'current_version' => $nextVersion,
                        'active_version_id' => $active->id,
                        'draft_version_id' => null,
                    ]);
                } elseif ($blueprint->status !== ContentBlueprint::STATUS_ACTIVE) {
                    $blueprint->applyLifecycle([
                        'status' => ContentBlueprint::STATUS_ACTIVE,
                        'current_version' => $active->version,
                        'active_version_id' => $active->id,
                        'draft_version_id' => null,
                    ]);
                }

                $ensured->push($blueprint->refresh()->load('activeVersion'));
            }

            return $ensured;
        }, 3);
    }
}
