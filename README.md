# IET / EveryThing

IET is a Laravel-based coordination platform for connected personal, social, community, work, Content, planning, relationship and financial activity.

The architecture is **generic inside and specific outside**: users should experience understandable actions such as “record a need”, “publish an article”, “start work”, “add an expense”, “propose terms” or “join a project”, while specialized kernels preserve trustworthy domain state underneath.

## Active development line

Continuous remote Ideal-v1 integration:

~~~text
integration/ideal-v1
~~~

The cumulative Phase 1–22 capability set is implemented. The current release checkpoint is the bounded **Publishable Ideal-v1** hardening/integration pass: correctness, release operations, synchronized documentation and cumulative 0→100 acceptance take priority over new kernels. Generic Workflow extraction, Reputation, Recommendations and AI Copilot are post-v1 work unless a concrete existing-flow defect requires otherwise.

The line is rooted at the accepted pre-AI baseline and intentionally excludes the unused AI-assistance / Development-Origin runtime.

The first reconstructed foundation retains:

- standalone Access Invitations for new accounts;
- Group Invitations for existing verified users;
- User/Actor/Profile;
- Groups/Membership/permissions;
- Contexts;
- one independent versioned Content system;
- Content Blueprints, blocks, Assets, publication/evidence;
- Submission/Response/Evaluation;
- system manual as versioned Content;
- guided Need/Offer capture;
- permission-aware Intent Directory.

AI Copilot is explicitly deferred until after the first publishable Ideal-v1 release.

## Canonical reading order

1. [Project Compass](docs/PROJECT_COMPASS.md)
2. [Current State](docs/CURRENT_STATE.md)
3. [Target Architecture](docs/TARGET_ARCHITECTURE.md)
4. [Production Roadmap](docs/PRODUCTION_ROADMAP.md)
5. [Continuous Remote Execution](docs/CONTINUOUS_REMOTE_EXECUTION.md)
6. [Example Story World](docs/EXAMPLE_STORY_WORLD.md)
7. [Local Acceptance Worksheet](docs/LOCAL_ACCEPTANCE_WORKSHEET.md)
8. [Development Circuit](docs/DEVELOPMENT_CIRCUIT.md)
9. [Operations Runbook](docs/OPERATIONS_RUNBOOK.md)

Historical phase contracts/reports remain under `docs/` and `Development-CodexReports/`.

## Product direction

Content is independent of Groups. A Content item has a home Context for authoring/authorization, but published Content or exact blocks/revisions may be presented/referenced anywhere the viewer is authorized. Evidence pins exact historical revisions.

User journeys progressively reveal capabilities. A simple sale stays simple; a paid-work relationship may compose Contract, Planner, Fulfillment and Accounting; a construction partnership can additionally compose property, service, capital and collaboration.

The canonical end-to-end examples reuse Diego, Alice, Bob, Carol, Maple Housing Office and Riverside Home Project so documentation and final browser acceptance form one coherent story.

## Development mode

Remote milestones are implemented, tested, documented and integrated one at a time. Owner-local/browser acceptance is deferred until the cumulative Ideal-v1 candidate is ready.

Automated testing is **not** deferred.

See `AGENTS.md` and `docs/CONTINUOUS_REMOTE_EXECUTION.md`.

## Laravel

Laravel 13 / PHP 8.4. Inspect repository package versions before relying on version-specific APIs.
