# Phase 5 — Generic Content Context Implementation Report

## Status

Phase 5 is active on `feat/phase-05-content-context`.

Starting baseline: `fef290d2f1d58f69ddab1fbfd00ec68ff2a186d7`.

No runtime Phase 5 changes had been made when this report was initialized.

## Pre-change audit

The accepted Phase 4 Content subsystem is strongly GroupSpace-oriented.

Direct GroupSpace coupling exists in:

- `space_contents.group_space_id`;
- `space_content_definitions.group_space_id`;
- `space_content_render_templates.group_space_id`;
- Content-created `assets.group_space_id`;
- `SpaceContentPolicy` delegating only to `GroupSpacePolicy`;
- Definition creation;
- Content creation;
- saved render-template scoping;
- media upload/storage paths;
- annotation attachment creation;
- parent/child Content “same Space” checks;
- asset delivery controller route validation;
- Group-nested Reader/Studio routes.

Existing Profile Assets already proved that Asset storage can safely exist without a GroupSpace, but they are not Content Context Assets.

## Architectural decision

Phase 5 will introduce first-class Context identity with explicit relational subtype bindings rather than a polymorphic context owner.

See `docs/PHASE_05_GENERIC_CONTENT_CONTEXT.md`.

## Frozen evidence concern

Published Content already carries immutable hashes and sealed manifests.

The Context migration must preserve existing evidence byte-for-byte/semantically. Context identity will not be injected into historical manifest formats during the compatibility migration.

## Initial implementation order

1. Context identity/bindings + policy;
2. GroupSpace Context backfill and proof;
3. Content/Definition/template/Asset context compatibility migration;
4. generic Context Content seam;
5. Personal proof;
6. Admission pre-Membership proof;
7. full regression + browser gate.

## Deferred

No Phase 6 Blueprints, Phase 8 Admission v2, realtime, Workflow, Planner, Matching or Contract work belongs in this report.
