# IET Living System Handbook

## Purpose

This repository material is the **source/manifests for the IET user manual**, not a separate documentation product. The intended product experience is for these materials to be materialized into ordinary versioned IET Content and read through the normal Content Reader.

Until that materialization pipeline is implemented, these files keep the educational source reviewable in Git.

This handbook explains IET from the user's and operator's point of view.

Architecture documents answer **how the platform is designed**. Phase contracts answer **what a development milestone must deliver**. This handbook answers:

- what each visible/domain object means;
- why it exists;
- who can create, view, change or review it;
- where it lives;
- how it relates to other objects;
- the exact end-to-end workflows a user follows;
- what is authoritative versus merely descriptive;
- how historical evidence remains trustworthy;
- what is implemented today versus planned for a later phase.

The handbook must describe the repository that actually exists. It must not document aspirational behavior as if it is already available.

## Two-track development rule

IET now develops each milestone through two synchronized tracks:

~~~text
Runtime track
feat/phase-XX-...
    ↓
models / migrations / Actions / policies / UI / tests

Documentation-source track
docs/living-system-handbook
    ↓
reviewable source/manifests
    ↓
idempotent seeding/materialization
    ↓
normal IET Book/Lesson/Article Content
    ↓
Reader / revisions / annotations / evidence
~~~

These are parallel workstreams, not separate architectures.

For every milestone:

1. runtime behavior is implemented and tested;
2. the handbook is updated against that behavior;
3. examples identify the exact Actor → Context → object → action → result chain;
4. permissions and visibility are documented;
5. new terminology is added to the object model;
6. changed workflows update their existing handbook pages rather than creating contradictory pages;
7. the milestone cannot be formally closed while material user-facing behavior remains undocumented.

The documentation branch should be synchronized from the active runtime branch frequently and its accepted handbook changes must be merged or cherry-picked back before phase closure.

## Reading order

For a new user/developer:

1. [Object Model](OBJECT_MODEL.md)
2. [Phase 7 — Structured Interactions](WORKFLOWS/PHASE_07_STRUCTURED_INTERACTIONS.md)
3. [Content Editions, Permalinks and Evidence](CONTENT_EDITIONS_AND_EVIDENCE.md)

Future phases add their workflows here as the capability actually becomes usable.

## Documentation standard

Every major workflow page should answer:

~~~text
WHO?
  Which Actor performs each step?

WHERE?
  Which Context contains the work?

WHAT?
  Which domain object is being acted on?

WHY?
  Why does that object exist instead of another one?

ACTION?
  What explicit user/domain action happens?

RESULT?
  What durable state changes?

WHO CAN SEE IT?
  Which audience/role/permission can observe it?

WHAT DOES NOT HAPPEN?
  Which tempting hidden side effects are deliberately forbidden?
~~~

This standard is intended to keep IET understandable as the platform grows.
