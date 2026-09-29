# Roadmap Revision — Capability Mesh Reassembly

## Trigger

Owner browser review of the certified selective-assembly baseline showed the old accepted system correctly, but made clear that the current roadmap was sequencing already-developed improvements as though they had not existed.

Known missing later candidate behavior includes profile-aware date/time/calendar rendering throughout the system, Gregorian equivalence, live permanent header date/time and rotating text, fractal calendar drill-down to minute partitions, and Planner temporal/evidence hardening.

## Architectural correction

The previous M0→M15 list was too pipeline-oriented.

The revised architecture treats IET as:

~~~text
independent capability nodes
+ explicit optional seams
+ shared projection surfaces
+ human-chosen runtime composition
~~~

A simple Plan must remain simple.

Finance, Contracts, Agreements, evidence, Relationships, automation and other layers are attached only when the human/domain meaning calls for them.

## Candidate evidence confirmed

Relevant branches exist:

- codex/release-first-publication-hardening;
- integration/ideal-v1-planner-temporal-candidate;
- integration/ideal-v1-temporal-calendar-reconcile;
- fix/planner-execution-window-calendar-evidence;
- fix/planner-temporal-evidence-calendar-hardening.

The publication-hardening candidate contains TemporalCalendar, local date/time Blade components, profile-aware datetime input, substantial resources/js/temporal.js, ambient-status, broad temporal substitutions across many views, TemporalPresentationConsistencyTest and evolved Planner calendar behavior.

The temporal/calendar reconciliation branch contains the earlier focused fractal calendar work.

## M00 interpretation

M00 remote quality remains valid.

Owner browser observation:

- technical baseline: healthy;
- old baseline behavior: reproduced;
- product acceptance: not closed;
- reason: roadmap/source-selection mismatch, not a discovered crash.

## New immediate priority

Recover and second-review the shared temporal fabric before other domain-node review:

1. Temporal Kernel;
2. Ambient Capability Rail;
3. Fractal Calendar Fabric;
4. Capability Launcher/composition contract.

## Durable docs

- docs/CAPABILITY_MESH_ARCHITECTURE.md
- docs/SELECTIVE_ASSEMBLY_ROADMAP.md

The original planning history remains in Git and the M00 report; it is not erased.
