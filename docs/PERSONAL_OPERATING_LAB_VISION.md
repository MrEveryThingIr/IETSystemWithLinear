# Personal Operating Laboratory Vision

## Status

This document records an approved future product direction. It does not claim that the described experience, automation, media pipeline, or learning analysis is implemented.

The first objective is intentionally personal: one verified owner uses IET as a private daily operating environment for at least 21 days. The system earns additional structure from observed use instead of requiring the owner to model their whole life in advance.

## Core decision

IET must not be forked into a separate single-user architecture and must not delete or weaken its multi-user kernels.

The target is a **Personal Routine Mode**: a focused composition profile that exposes only the capabilities useful to one owner while keeping the existing User/Actor, Context, Content, Planner, evidence, asset, accounting, contract, and collaboration boundaries intact for later activation.

The initial experience should feel like a private journal and planner, not like administration of a multi-party platform.

## Narrative-first operating loop

The owner should be able to record life with minimal structure:

1. capture a short description, long-form story, voice recording, image, or file;
2. optionally place it at a date, time, duration, or broad period;
3. optionally mention people, places, purposes, costs, decisions, or external concepts as plain narrative;
4. relate the entry to an existing Plan only when that is already useful;
5. review the private Today, calendar, timeline, and Content views;
6. continue using the system without being forced to create new domain records;
7. periodically inspect repeated terms, relationships, transitions, and missing actions;
8. promote a repeated narrative concept into structured domain truth only after evidence shows that the structure will be useful.

Mentioning a person does not create an Actor, Relationship, Membership, permission, Contract, or obligation. Mentioning a payment does not create authoritative accounting or settlement truth. Narrative remains Content until the owner deliberately promotes it through an appropriate domain Action.

## Simplified personal Content Blueprint

The first focused Blueprint should be a private personal log with a deliberately small required surface.

Suggested fields and blocks:

- title, optional;
- narrative/body, required;
- occurred or intended date/time, optional;
- broad period or duration, optional;
- mood/energy or personal reflection, optional and private;
- mentions/keywords, optional and initially non-authoritative;
- related existing Plan, optional;
- voice recording, optional;
- images, optional;
- files, optional;
- follow-up note or question, optional;
- visibility fixed to the owner in the initial mode.

The Blueprint is an experience over the existing Content kernel, not a new diary table. Published revisions and evidence references should retain their existing meaning.

## Media rule

Original uploaded assets are durable evidence and must not be destructively resized or replaced.

Image presentation should use generated derivatives such as thumbnail, reading, and display sizes while retaining:

- the original file;
- checksum and provenance;
- MIME type and dimensions;
- rights/privacy status;
- processing status;
- a retryable media-processing path.

Voice recordings should retain the original recording. Later optional derivatives may include normalized audio, waveform, duration, transcription, chapters, or a learning-video composition. Generated media must identify its source Content and generation provenance.

## Twenty-one-day learning protocol

The owner uses the Personal Routine Mode as the primary private capture and planning surface for at least 21 days.

The system should record ordinary product evidence, not hidden surveillance:

- entries created and revised;
- Plans created, rescheduled, completed, skipped, or abandoned;
- recurring validation errors and dead ends;
- features repeatedly visited or ignored;
- narrative keywords explicitly marked by the owner;
- requested links between Content and Planner;
- media-processing failures;
- manual notes describing friction, missing concepts, or unwanted complexity.

At review checkpoints, classify discoveries as:

1. wording or presentation correction;
2. navigation or workflow improvement;
3. optional field or Blueprint improvement;
4. reusable relation to an existing domain object;
5. evidence for a genuinely new model, lifecycle, policy, or migration;
6. idea requiring more observation.

A keyword alone is not sufficient evidence for a migration. New authoritative structure should require repeated use, a clear lifecycle, distinct authorization or integrity rules, and a demonstrated query/workflow need that Content metadata cannot safely satisfy.

## Documentation and in-app Content policy

Repository documentation and in-app Content have different responsibilities and should coexist.

### Repository Markdown

Markdown remains the canonical developer and architecture source because it is reviewable with code, available before the database exists, usable by humans and development agents, diffable across releases, and recoverable independently from application data.

### Versioned IET Content

Selected guidance should also be materialized as ordinary versioned Content so the owner can read, search, annotate, relate, and eventually watch it inside IET.

The preferred publication pipeline is:

~~~text
canonical repository source
→ idempotent materialization Action
→ private/reference Content and exact revision
→ optional learning media derived from that revision
~~~

Do not make a seeder the authority for the text. The seeder or command should invoke an idempotent Action that creates or synchronizes normal Content through existing Blueprint, revision, publication, Context, and authorization rules.

Initially, the Personal Operating Laboratory vision and learning material should be visible only to the owner/super-admin. This must use an explicit existing or reviewed platform/Context authorization boundary; it must not infer platform authority from Group roles.

`DatabaseSeeder` must remain minimal. Any demonstration or learning materialization belongs in an explicit opt-in command or seeder and must be safe to rerun without deleting unrelated records or creating unnecessary revisions.

## Future Scenario Laboratory and teaching bot

The previously discussed browser-driven Scenario Laboratory remains a complementary future capability.

Its intended responsibilities are:

- operate real rendered pages through isolated user sessions;
- execute deterministic stories and bounded behavioral variations;
- populate fields and activate actual controls;
- preserve authorization boundaries between simulated users;
- collect traces, screenshots, timings, errors, and domain invariants;
- associate exercised journeys with System Map nodes and edges;
- create normal Content through the UI when a story requires it;
- optionally generate narrated demonstrations or learning videos from a proven scenario and exact application version.

The bot must never become an authorization bypass or a production super-admin impersonation mechanism. It should operate only in an explicitly enabled disposable local/testing/staging laboratory and record the exact Git SHA, scenario version, random seed, identities, and artifacts for every run.

Learning videos should be derived from successful deterministic scenarios, reviewed before publication, and linked to the exact Content/manual revision and application version they demonstrate.

## Reuse-first development rule

When the 21-day experiment identifies a missing capability:

1. state the observed problem and evidence;
2. search the current accepted assembly and System Map;
3. inspect existing branches, commits, Actions, models, migrations, views, tests, and reports;
4. decide whether the capability already exists, partially exists, or conflicts with the accepted architecture;
5. selectively recover the smallest proven implementation;
6. revise it against current authorization, localization, temporal, Content, and capability-mesh rules;
7. validate it independently;
8. browser-test it in Personal Routine Mode;
9. merge only the accepted exact head.

Do not rewrite a capability from scratch while an accepted or recoverable implementation already exists.

## Atomic delivery sequence

The Personal Routine Mode should advance in small browser-gated atoms:

1. freeze and accept the exact foundation branch;
2. define the Personal Routine Mode navigation/profile without deleting capabilities;
3. provide one private Personal Log Blueprint using the existing Content kernel;
4. support quick narrative capture with optional date/time;
5. connect an entry optionally to an existing Plan;
6. verify voice, image, and file attachment through existing Asset rules;
7. add non-destructive image derivatives where current media processing is insufficient;
8. provide a focused Today view for personal entries and Plans;
9. add a private weekly review Content experience;
10. run the 21-day protocol and record evidence;
11. promote only evidence-backed recurring concepts;
12. later introduce the Scenario Laboratory, in-app learning materialization, and reviewed learning-video generation.

Each atom must have one branch, focused tests, remote validation, local browser acceptance, and an exact accepted SHA.

## Success criteria

The direction is successful when the owner can consistently:

- capture what happened or is intended in seconds;
- plan and review daily life without premature modeling;
- attach and later find relevant media;
- understand why a record exists and who can see it;
- connect narrative to structured Plans when useful;
- review 21 days of evidence without losing chronology or provenance;
- identify improvements from observed friction rather than speculation;
- activate existing richer capabilities only when real use requires them.

The long-term measure is not the number of fields or models. It is whether IET becomes a trustworthy personal memory, planning, reflection, and gradual-structuring environment while preserving a clean path to later collaboration.
