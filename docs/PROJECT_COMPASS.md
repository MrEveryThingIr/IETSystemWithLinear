# IET Project Compass

## Authority

This document is the durable product compass for IET. It defines the product purpose, architectural philosophy, non-negotiable boundaries, and destination. It does not claim that every described capability exists today.

Repository implementation truth is summarized in `docs/CURRENT_STATE.md`. The target technical shape is defined in `docs/TARGET_ARCHITECTURE.md`. Execution order and release gates are defined in `docs/PRODUCTION_ROADMAP.md`.

When a chat, report, issue, agent suggestion, legacy migration, or old document conflicts with these canonical documents, the canonical documents win unless the human owner explicitly approves a new architecture decision.

## Product purpose

IET is a configurable coordination platform for people and organizations to:

- progressively identify and describe themselves;
- form communities, teams, projects, classes, businesses, and other contexts;
- describe knowledge, capabilities, resources, needs, offers, and intentions;
- publish structured, interactive, versioned Content;
- communicate and collaborate around that Content;
- plan recurring and one-time activity;
- submit applications, answers, evidence, reports, and evaluations;
- negotiate agreements and create explicit commitments;
- record fulfillment and evidence;
- account for financial consequences through an immutable ledger;
- experiment with new value and financial-instrument ideas in a separate laboratory;
- reuse successful structures through versioned Blueprints and focused domain packs.

IET is intentionally generic internally, but must feel specific externally. A normal user should experience "Chess School", "Construction Project", "My Life", "Tour Operator", "Hiring", "Interactive Book", or another focused workflow. They should not need to understand generic schemas, assertions, workflow engines, or journal lines.

## Product north star

The target lifecycle is:

~~~text
Identity
→ Representation
→ Context
→ Participation
→ Meaning
→ Content and Communication
→ Interaction
→ Planning
→ Need / Offer
→ Proposal / Negotiation
→ Agreement / Commitment
→ Fulfillment / Evidence
→ Obligation / Settlement
→ Accounting
→ Learning / Reputation / Discovery
→ Reuse through Blueprints
~~~

The platform should support many domains without creating unrelated identity, authorization, content, planning, workflow, or accounting systems for every domain.

The design goal is not "everything is one database object." The design goal is:

> Everything can participate in a small, coherent set of reusable capabilities while specialized domains retain their own invariants.

## Core distinctions

| Concept | Durable meaning |
| --- | --- |
| User | Authentication account: credentials, verification, sessions, account status, locale, timezone, and other login-level concerns. |
| Actor | Domain participant. Today it is primarily a person backed by a User; the target supports person, organization, and system Actors with explicit acting authority. |
| Group | A governed collaboration/community boundary with Memberships, contextual roles, invitations, admissions, agreements, and Spaces. |
| Membership | Participation truth in a Group. It is distinct from authorization. |
| Role / Permission | Contextual authorization. Group authority never grants platform authority or authority in another Group. |
| Context | A bounded collaboration/visibility environment. Group Space is the first implementation; future contexts include Personal, Admission, Negotiation, Contract, and direct collaboration. |
| Concept | Canonical semantic meaning such as Chess, Programming, Electrical Work, Tourism, or English. A Concept is not intrinsically a skill, need, interest, or tag; predicates provide that meaning. |
| Concept Assertion | A typed relation from a subject to a Concept, e.g. Actor has_skill Chess, Content teaches Chess, or Group focuses_on Chess. |
| Content | The universal human-facing artifact layer: authored information that can be structured, revised, styled, published, related, annotated, and interacted with. |
| Content Definition | Versioned structured field schema for Content. |
| Content Blueprint | Reusable package of Definition, initial composition, Presentation, semantic classification, and interaction defaults. |
| Submission | A structured response to a published interaction definition, such as an application, exam attempt, questionnaire, assignment, survey, or inspection. |
| Workflow | Explicit states, transitions, requirements, authorization, and evidence for a process. It does not replace domain objects. |
| Plan | Intended activity, possibly recurrent. |
| Occurrence | A concrete scheduled or executed instance of a Plan. |
| Need | A structured expression of something an Actor or context requires. |
| Offer | A structured expression of something an Actor or context can provide. |
| Agreement | Versioned terms/rules accepted by explicit parties or participants. |
| Commitment | A concrete obligation to provide, perform, pay, deliver, attend, or otherwise fulfill something. |
| Fulfillment | Evidence-bearing record of what actually happened against a Commitment. |
| Ledger | Immutable, balanced accounting truth produced from financial consequences of domain events. |
| Financial Instrument | A unit/claim/credit/asset used in the financial laboratory or approved production flows. It is distinct from fiat currency and from the accounting ledger. |
| Blueprint | Versioned reusable configuration that turns generic kernels into a focused user experience. |
| Domain Pack | Curated collection of Blueprints, capabilities, terminology, and UI for a field such as Learning, Projects, Tourism, or Personal Life. |

## Architectural rules

1. Build a modular Laravel monolith until evidence requires otherwise. Do not introduce microservices merely because the domain is broad.
2. User and Actor remain distinct.
3. Platform authority, Group participation, and Context access remain distinct.
4. Content is the universal human-facing artifact layer, not a replacement for every domain entity.
5. Domain facts and lifecycles stay in domain models; Content describes/presents them and may provide immutable evidence.
6. Concepts describe meaning. Predicates describe how something relates to that meaning. Schemes describe classification perspectives.
7. Do not encode "skill", "need", "interest", "service", and similar contextual meanings as duplicate Concept branches when a predicate expresses them.
8. Group Space must eventually become one Content Context implementation rather than the only place Content can exist.
9. Published Content revisions, accepted agreements, posted accounting entries, and comparable evidence are immutable. Corrections create new revisions or reversals.
10. Critical multi-model transitions live in explicit transactional Actions.
11. Authorization is rechecked at protected boundaries and mutations; possession of IDs or URLs grants no authority.
12. Generic engines are accepted only after at least two meaningfully different domains demonstrate them.
13. Generic internals must be hidden behind purpose-specific UX.
14. Dynamic definitions and templates may configure trusted registries and data. They must not execute arbitrary PHP, Blade, JavaScript, SQL, or CSS from the database.
15. Real-time transport is never authoritative. The database and domain actions remain the source of truth.
16. Accounting and the Financial Laboratory are separate bounded systems. Experimental valuation must never corrupt production accounting truth.
17. External real-money redemption is a controlled production capability, not an automatic property of every Group-created instrument.
18. Production readiness includes operations, security, privacy, observability, backups, accessibility, performance, and recovery—not only feature completeness.
19. No roadmap phase begins merely because it is interesting. Its dependency and prior phase exit gates must be satisfied.
20. No AI agent may silently change these boundaries.

## "Generic inside, specific outside"

The kernel may expose abstractions such as ConceptAssertion, ContentBlueprint, SubmissionDefinition, ScheduleRule, or JournalEntry.

The UI should instead say:

~~~text
Add a skill
Create a lesson
Apply for this job
Schedule weekly class
Record an expense
Invite a student
~~~

Domain Packs are responsible for making the generic kernel feel native to a specific field.

## Generic-abstraction proof rule

Every major generic abstraction must be validated by at least two unrelated use cases before being considered stable.

Examples:

| Kernel | Proof A | Proof B |
| --- | --- | --- |
| Concept Assertions | Actor skills/interests | Content classification |
| Submissions | School exam | Employment application |
| Planner | Personal chess-study habit | Construction worker shift |
| Need / Offer | Construction labor | Tourism/restaurant service |
| Workflow | Admission | Content/application review |
| Ledger | Personal expense | Payroll/payable |
| Group Blueprint | Chess learning group | Project/work group |

If one abstraction cannot model both cases cleanly without field abuse, it is not yet generic enough.

## Product boundaries

IET is not:

- one giant universal Entity table;
- a low-code system where JSON replaces business invariants;
- a blockchain or cryptocurrency product by default;
- a financial institution merely because it has a ledger;
- an LMS, project manager, social network, marketplace, accounting package, or tourism application with unrelated modules bolted together;
- a requirement that every person use every feature.

It is a platform kernel from which focused applications can be instantiated.

## Success criterion

Technical success means that several unrelated real workflows can operate comfortably over the same kernels without bypassing invariants or exposing generic complexity to users.

Product adoption cannot be guaranteed by architecture. The release process therefore uses measurable usability, reliability, security, and operational gates and then validates the system with real domain pilots.
