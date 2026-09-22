<?php

namespace App\Actions\Content;

use App\ConceptAssertionSource;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptAssertion;
use App\Models\ContentBlueprintVersion;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\ContentBlueprintAccess;
use App\Support\ContextScope;
use App\Support\SpaceContentBlocks;
use App\Support\SpaceContentSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateContentFromBlueprint
{
    public function __construct(
        private readonly ContentBlueprintAccess $access,
        private readonly SpaceContentBlocks $blocks,
    ) {}

    /** @param array<string, mixed> $payload */
    public function execute(
        Context $context,
        ContentBlueprintVersion $blueprintVersion,
        User $user,
        string $title,
        array $payload,
    ): SpaceContent {
        $title = trim($title);
        abort_if($title === '' || mb_strlen($title) > 255, 422, 'Content title is required and may not exceed 255 characters.');

        return DB::transaction(function () use ($context, $blueprintVersion, $user, $title, $payload): SpaceContent {
            $currentContext = Context::query()->lockForUpdate()->findOrFail($context->id);
            Gate::forUser($user)->authorize('createContent', $currentContext);

            $currentBlueprintVersion = ContentBlueprintVersion::query()
                ->with('blueprint')
                ->lockForUpdate()
                ->findOrFail($blueprintVersion->id);

            abort_unless(
                $this->access->view($user, $currentContext, $currentBlueprintVersion),
                404,
                'The selected Content Blueprint is unavailable in this Context.',
            );

            $actor = $this->actor($user);
            $definition = $this->materializeDefinition(
                $currentContext,
                $currentBlueprintVersion,
                $actor,
            );

            $definitionVersion = $definition->activeVersionRecord();
            abort_unless(
                $definitionVersion instanceof SpaceContentDefinitionVersion
                    && $definitionVersion->published_at !== null,
                500,
                'The Blueprint Definition is not active.',
            );

            $definitionHash = SpaceContentSchema::hashArray($currentBlueprintVersion->definition_schema);
            abort_unless(
                hash_equals($definitionVersion->content_hash, $definitionHash),
                500,
                'The materialized Content Definition no longer matches its Blueprint version.',
            );

            $normalizedPayload = SpaceContentSchema::normalizePayload(
                $definitionVersion->schema,
                $payload,
            );

            $content = SpaceContent::query()->create([
                'context_id' => $currentContext->id,
                'content_blueprint_version_id' => $currentBlueprintVersion->id,
                'group_space_id' => ContextScope::legacyGroupSpaceId($currentContext),
                'space_content_definition_id' => $definition->id,
                'author_actor_id' => $actor->id,
                'status' => 'draft',
                'current_revision' => 1,
                'published_at' => null,
                'archived_at' => null,
            ]);

            $initialBlocks = $currentBlueprintVersion->initial_blocks ?? [];
            $revision = $content->revisions()->create([
                'definition_version_id' => $definitionVersion->id,
                'revision' => 1,
                'title' => $title,
                'payload' => $normalizedPayload,
                'render_template_key' => $currentBlueprintVersion->render_template_key,
                'presentation' => $currentBlueprintVersion->presentation ?? [],
                'composition_mode' => $initialBlocks === []
                    ? SpaceContentRevision::COMPOSITION_FIELDS
                    : SpaceContentRevision::COMPOSITION_BLOCKS,
                'created_by_actor_id' => $actor->id,
            ]);

            $this->createInitialBlocks($revision, $initialBlocks);
            $this->createConceptDefaults($revision, $currentBlueprintVersion, $actor);

            $content->applyLifecycle([
                'current_revision' => 1,
                'active_revision_id' => null,
                'draft_revision_id' => $revision->id,
            ]);

            return $content->refresh()->load([
                'blueprintVersion.blueprint',
                'definition.blueprintVersion',
                'draftRevision.blocks',
            ]);
        }, 3);
    }

    private function materializeDefinition(
        Context $context,
        ContentBlueprintVersion $blueprintVersion,
        Actor $actor,
    ): SpaceContentDefinition {
        $existing = SpaceContentDefinition::query()
            ->where('context_id', $context->id)
            ->where('content_blueprint_version_id', $blueprintVersion->id)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof SpaceContentDefinition) {
            abort_if(
                $existing->status === 'archived',
                422,
                'This Blueprint Definition has been archived in the Context.',
            );

            return $existing;
        }

        $blueprintVersion->loadMissing('blueprint');
        $blueprint = $blueprintVersion->blueprint;
        $slug = 'blueprint-'.$blueprint->slug.'-v'.$blueprintVersion->version;

        $definition = SpaceContentDefinition::query()->create([
            'context_id' => $context->id,
            'content_blueprint_version_id' => $blueprintVersion->id,
            'group_space_id' => ContextScope::legacyGroupSpaceId($context),
            'created_by_actor_id' => $actor->id,
            'name' => $blueprint->name,
            'slug' => Str::limit($slug, 120, ''),
            'description' => $blueprint->description,
            'status' => 'draft',
            'current_version' => 1,
        ]);

        $version = $definition->versions()->create([
            'version' => 1,
            'schema' => $blueprintVersion->definition_schema,
            'display' => null,
            'created_by_actor_id' => $actor->id,
            'published_at' => null,
        ]);
        $version->publish();

        $definition->applyLifecycle([
            'status' => 'active',
            'current_version' => 1,
            'active_version_id' => $version->id,
            'draft_version_id' => null,
        ]);

        return $definition->refresh();
    }

    /** @param list<array<string, mixed>> $blocks */
    private function createInitialBlocks(SpaceContentRevision $revision, array $blocks): void
    {
        if ($blocks === []) {
            return;
        }

        $normalized = $this->blocks->normalize($revision, $blocks);

        foreach ($normalized as $position => $block) {
            $revision->blocks()->create([
                'uuid' => (string) Str::uuid(),
                'logical_uuid' => (string) Str::uuid(),
                'parent_block_id' => null,
                'type' => $block['type'],
                'position' => $position,
                'data' => $block['data'],
                'style' => $block['style'],
            ]);
        }
    }

    private function createConceptDefaults(
        SpaceContentRevision $revision,
        ContentBlueprintVersion $blueprintVersion,
        Actor $actor,
    ): void {
        foreach ($blueprintVersion->concept_defaults ?? [] as $default) {
            if (! is_array($default)) {
                continue;
            }

            $conceptUuid = is_string($default['concept_uuid'] ?? null)
                ? $default['concept_uuid']
                : null;
            $predicate = is_string($default['predicate'] ?? null)
                ? $default['predicate']
                : null;

            if ($conceptUuid === null || $predicate === null) {
                continue;
            }

            $concept = Concept::query()->where('uuid', $conceptUuid)->firstOrFail()->canonical();

            ConceptAssertion::query()->create([
                'subject_type' => ConceptAssertionSubject::SpaceContentRevision->value,
                'subject_id' => $revision->id,
                'concept_id' => $concept->id,
                'predicate' => $predicate,
                'scheme_id' => null,
                'weight' => null,
                'confidence' => null,
                'source' => ConceptAssertionSource::System->value,
                'visibility' => ConceptAssertionVisibility::Inherited->value,
                'valid_from' => null,
                'valid_until' => null,
                'created_by_actor_id' => $actor->id,
                'metadata' => [
                    'content_blueprint_version_id' => $blueprintVersion->id,
                ],
            ]);
        }
    }

    private function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->actor instanceof Actor, 403);

        return $current->actor;
    }
}
