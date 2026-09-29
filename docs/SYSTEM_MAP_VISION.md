# System Map — Current v1 and long-term living-system direction

## Purpose

IET needs a durable way to understand the platform as one connected system rather than as a list of routes, migrations, models or roadmap phases.

The System Map exists to answer, visually:

- what major parts exist;
- why each part exists for a human;
- which part owns authoritative truth;
- what depends on or hands off to what;
- where a workflow crosses multiple kernels;
- which boundaries must not be collapsed;
- what should be reviewed when improving one module;
- how a small local change may affect the wider product.

This is especially important as the platform becomes broad enough that a developer or product owner can no longer hold the entire topology in working memory.

## Current v1

The first implementation is deliberately small and reliable.

Route:

~~~text
/system-map
~~~

It is a curated repository-backed projection, not a new authority layer.

Current capabilities:

- large X/Y scrollable canvas;
- drag-pan;
- zoom in/out;
- fit whole graph;
- search;
- module filter;
- current/future-direction filter;
- focus one node and its immediate neighborhood;
- quick review lenses:
  - north-star lifecycle;
  - financial flow;
  - content/knowledge;
  - community/governance;
  - execution;
- right-side inspector with:
  - human purpose;
  - authoritative truth;
  - related documentation;
  - code anchors;
  - connected nodes and relationship labels;
  - focused review questions;
- clear visual distinction between implemented capabilities and long-term directions.

The curated map intentionally references the same boundaries defined by:

- `docs/PROJECT_COMPASS.md`;
- `docs/CURRENT_STATE.md`;
- phase documents;
- `docs/FINANCIAL_ARCHITECTURE.md`;
- current Laravel routes/models/actions.

The map does not replace those sources.

## Design principle

The map must connect four perspectives:

~~~text
human need
↕
product workflow
↕
domain truth / lifecycle
↕
implementation
~~~

A useful System Map is not merely a class diagram.

For example, the financial review should let a human trace:

~~~text
planned/expected activity and cost
→ actual execution
→ accepted fulfillment
→ explicit financial obligation
→ settlement claim/confirmation
→ explicit accounting posting
→ immutable ledger truth
~~~

while still showing that each stage has a different authority boundary.

## Long-term target

The target is a **Visual Interactive Focusable Concept Network** that can feel like a living organism without sacrificing technical precision.

Potential views:

### Structure

Modules, entities, Actions, policies, events, routes, UI surfaces, tests, docs and external integrations.

### Flow

Animated or stepwise journeys showing how state and evidence move through the system.

### Human meaning

Human need → interaction → outcome → evidence of satisfaction.

### Organism

A living-system visualization where:

- domains behave like organs;
- Actions behave like movements;
- events/signals behave like nerves;
- data/evidence behaves like durable memory;
- notifications/realtime behave like signal transport;
- health and maturity overlays reveal weak or incomplete areas.

This metaphor must remain explanatory. It must never hide real domain boundaries.

## Future data model direction

Do not rush into a large editable graph database.

Preferred evolution:

1. v1 curated builder in code;
2. add stable metadata/attributes/manifests near modules, Actions, routes and docs;
3. derive more nodes/edges automatically;
4. allow authorized documentation overlays and review notes;
5. add release/version snapshots and diff mode;
6. add health/evidence metrics;
7. connect exact tests, docs, issues and change history;
8. later allow an AI assistant to use the map as contextual topology for explanation and proposed work.

The system must always distinguish:

- **actual/current** architecture;
- **target/direction** architecture;
- **experimental ideas**.

## Review workflow

When reviewing a module:

1. open System Map;
2. search/select the module;
3. inspect human purpose and authoritative truth;
4. inspect directly connected modules;
5. open the quick lens if available;
6. review the listed questions;
7. follow code/doc anchors;
8. record concrete defects or improvements as issues/work rather than changing architecture speculatively;
9. update this map when a new durable module/boundary becomes part of the system.

## Financial-review lens

The Financial flow lens is intentionally first-class because financial UX spans multiple truths:

- Planner expected/actual expense observations;
- Fulfillment and evidence;
- Financial Obligation recognition;
- Settlement confirmation;
- Personal Accounting;
- future external payment/reconciliation;
- future Financial Laboratory.

The Accounting Kernel, Financial Obligation/Settlement domain, external payments and Financial Laboratory must remain distinct even when the map shows them as one human story.

## Non-goals

The System Map must not:

- become authorization;
- become a workflow engine;
- infer dependencies merely because two classes reference each other;
- claim future capabilities are implemented;
- encourage collapsing separate domain truths into one generic object;
- execute arbitrary code stored in map data;
- replace canonical architecture documentation.

## Maintenance rule

Whenever a major durable module, authoritative lifecycle or cross-kernel handoff is added or materially changed, update the map in the same milestone.

Small classes/helpers do not each require a visible node. The map should operate at the level of concepts a human or maintainer can reason about, with deeper drill-down added only when it improves understanding.

The long-term goal is not maximum node count.

The goal is **maximum explainability of the living system**.
