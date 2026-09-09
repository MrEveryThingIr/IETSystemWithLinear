# Everything Project Compass

## Purpose and authority

Everything is a modular Laravel platform in which Users authenticate as Actors; Actors participate in Groups through Memberships with contextual authorization; first-class objects receive Entity identity and reusable Concepts, relations, notes, and media; Groups organize activity through Spaces, Content, Agreements, and invitation-based admission; Plans turn intent into execution, Results, and Evidence; validated history produces learning and Reputation; Agreements can mature into Contracts with obligations and settlement; Commerce and accounting exchange value over the same contextual foundation; and successful structures become reusable templates without duplicating identity, membership, content, or authorization systems for each domain.

The product exists to let people, organizations, and systems describe themselves, form contexts, communicate, organize activity, learn, collaborate, agree on terms, exchange value, demonstrate results, build trust, and reuse successful patterns through one coherent platform.

This document is the durable product and architecture compass. It distinguishes the intended destination from the verified implementation. It is not a claim that every capability described here currently exists.

## Product north star

Everything should support many applications—personal organization, education, travel, projects, recruitment, communities, services, commerce, manufacturing, and knowledge sharing—without building unrelated identity, participation, content, or authorization systems for each one.

The complete platform lifecycle is:

```text
Identity → Representation → Context → Participation → Meaning
→ Communication → Agreement → Intent and Planning → Execution
→ Result → Evidence → Trust and Reputation → Value Exchange
→ Learning and Improvement → Reuse
```

A mature user should experience one connected view of their identity, profile, groups, spaces, plans, work, learning, agreements, contracts, offers, needs, transactions, evidence, reputation, and history—not a collection of disconnected applications.

## Foundational distinctions

| Concept | Durable meaning |
| --- | --- |
| **User** | The authentication account: credentials, email verification, account status, sessions, and other authentication concerns. |
| **UserIdentity** | Account-level personal identity and presentation, such as given, family, and display names. It keeps `User` deliberately small. |
| **Actor** | The domain participant that joins Groups, creates material, accepts terms, performs work, and receives attribution. An Actor may be accountless when the domain genuinely requires it. |
| **Entity** | The stable universal identity and attachment surface for a first-class object. It enables generic addressing, classification, relations, notes, media, and references; it does not own every object's business state. |
| **Group** | The context answering who participates together. It is reusable across domains and does not absorb travel, education, commerce, or other specialized fields. |
| **Membership** | The operational truth that an Actor participates in a Group, including participation lifecycle state. It is not the authorization system. |
| **Contextual Role / Permission** | The authorization truth within a Group context. Authority in one Group has no effect in another. Active Membership remains a prerequisite. |
| **Concept** | Shared semantic meaning used for classification, hierarchy, discovery, and relationships. A Concept never grants access or substitutes for workflow state. |
| **Space** | A purpose-specific place where a Group communicates or operates. It uses Group participation rather than creating a separate membership universe. |
| **Content** | Authored intrinsic information such as an article, announcement, question, proposal, or instruction. |
| **ContentBlock** | A first-class structural part of Content that may carry media, meaning, notes, or reactions at block level. |
| **Note** | Supplemental information about a target. It is not an intrinsic ContentBlock and does not replace the document model. |
| **Agreement** | Versioned rules or terms that participants explicitly accept. Published versions point to immutable Content revisions. |
| **Invitation** | A controlled admission context that exposes only an authorized preview and pathway. Possession of a link is not Membership. |
| **Application** | An authenticated Actor's request for admission through an Invitation. Membership is created only when admission succeeds. |
| **Plan** | An Actor's or Group's intended activity, optionally with assignments, dependencies, scheduling, and recurrence. Personal plans do not require a fake Group. |
| **Occurrence** | One scheduled or executed instance of a Plan item. It is distinct from the reusable plan definition. |
| **Result** | What actually happened during execution, distinct from what was planned. |
| **Evidence** | Material supporting a Result, obligation, skill, or claim, with an explicit validation lifecycle when required. |
| **Contract** | Versioned binding obligations and value exchange between explicit parties. It is not merely a Plan or general Agreement. |
| **Reputation** | A projection derived from verified history, evidence, fulfillment, reliability, and disputes—not a self-declared score. |
| **Ledger** | Strongly typed, balanced, immutable accounting records of value movement, settlement, allocation, reversal, and provenance. |

## Frozen architectural boundaries

These rules are architectural constraints, not optional implementation preferences:

1. Build a modular Laravel monolith with one coherent domain model. Do not default to microservices, generic repositories, or a universal workflow interpreter.
2. `Entity` is a universal identity and attachment surface, not a universal business table or JSON dumping ground. Specialized domain models own behavior, state machines, and invariants.
3. Semantic relations describe meaning and provenance; they never replace operational records such as Membership, Application, acceptance, Contract, transaction, or ledger entry.
4. Concepts never grant authorization or represent operational workflow state.
5. Membership is participation truth. Contextual RBAC and policies are authorization truth. Both must be satisfied where participation is required.
6. Group or Entity relations never imply Membership, visibility, or permission inheritance.
7. Group, Space, Plan, Agreement, and Contract remain distinct objects with distinct responsibilities.
8. User and Actor remain distinct. Anonymous visitors do not automatically become Actors.
9. Objects evolve through relations and provenance; one domain object does not shapeshift into another.
10. Published Content revisions and accepted Agreement or Contract versions are immutable. Corrections create new versions or explicit reversals.
11. Critical multi-model operations live in explicit transactional domain actions. Controllers, Livewire components, APIs, and administrative UI call those actions rather than reimplementing invariants.
12. Authorization is explicit at every protected boundary. Knowing an ID, receiving a URL, sharing a Concept, or relating to an accessible Entity confers no access.
13. Accounting remains strongly typed, balanced, immutable after posting, idempotent at settlement boundaries, and corrected through explicit reversals.
14. Dynamic forms, tables, templates, and automation sit above stable business domains. They collect or present domain data; they do not replace domain objects or rules.
15. Reusable media, rich-content, audit, and identity capabilities should not be rebuilt independently in each domain.

## Verified current state

The repository is an early-stage Laravel 13 monolith. The following statements reflect the verified audit and its critical review as of 2026-09-09.

### Established foundation

- Authentication, account-status enforcement, email verification, password reset, and session handling have substantive feature coverage.
- Registration creates a conventional `User` and an associated `Actor` transactionally.
- The User/Actor separation and an accountless-Actor option exist.
- A reusable Blade/Livewire/Flux application shell exists.
- Group, Membership, reusable Invitation, contextual Spatie role, role-change request, and Story responsibility schemas or prototypes exist.
- The current suite passes 71 tests with 319 assertions.

Historical implementation detail remains in `Development-CodexReports/`. Those files are milestone evidence, not the current product compass.

### Current limitations and release blockers

- Actor administration is intentionally but only temporarily available to every active, verified account.
- Active Membership is not rechecked on every Group Livewire mutation; removal also leaves contextual role records attached.
- Invitation acceptance overwrites an existing member's role and does not correctly reactivate a removed Membership.
- Concurrent Owner removal can leave a Group without an Owner; role-request creation and review also have race windows.
- Configurable permissions are stored but application actions authorize only the literal Owner role.
- Multi-Group role resolution can reuse a stale loaded Spatie relation.
- Group rendering provisions and synchronizes roles and permissions, so a read path performs writes.
- Group, invitation, membership, role, request, story, tenant-isolation, and concurrency behavior has no tests.
- Static analysis currently reports five errors in Group and Invitation code.
- The frontend dependency graph has no lockfile.

There is no verified production deployment, no complete Entity/UserIdentity kernel, no semantic kernel, no Spaces/Content/Agreement system, no admission Application workflow, and no production-ready Planner, Evidence, Contract, Reputation, Commerce, Accounting, or builder capability.

## Development stages

The stages express dependency order, not promises that every speculative feature will be built exactly as named.

| Horizon | Stage | Outcome |
| --- | --- | --- |
| **Completed foundation** | Laravel/authentication, User/Actor foundation, test harness, UI shell, early Group and Invitation prototype | A verified development baseline with working authentication and initial domain scaffolding. |
| **Current stage** | Kernel hardening | Make the existing Actor, Group, Membership, contextual authorization, Invitation, and role workflows safe, transactional, isolated, and tested. |
| **Near-term kernel** | Identity completion; secure Group kernel; semantic kernel; Spaces and Content; Agreements; invitation-based admission; operational hardening | Establish stable identity, participation, meaning, communication, terms, and admission before broader domains. |
| **Later platform** | Planner and execution; Results and Evidence; Contracts; Reputation and Learning; Commerce; Economy; templates and repetition | Build specialized capabilities only on the proven kernel and preserve domain boundaries. |
| **Optional mature layer** | Configurable builders, automation, and advanced value/governance capabilities | Introduce only when stable domains demonstrate real reusable behavior. |

The next recommended milestone is **Authorization and Group-Integrity Correction**. It does not include Entity, Concepts, Spaces, Content, Filament, Stories UI, Planner, Commerce, or other expansion work.

## North-star acceptance stories

These stories express architectural capability, not immediate implementation scope.

### Secure Group kernel

A verified User has an Actor, creates a Group, receives an active creator Membership and contextual authority, and admits another Actor with different authority. The same Actor can hold a different role in another Group. Policies enforce active Membership, permission, target scope, and Group isolation on every request, including after page load. No role or permission leaks across Groups, and no workflow can remove the last Owner.

### Invitation and admission kernel

An authorized Group participant issues an Invitation containing only approved preview material, available roles, responsibilities, and required Agreement versions. A guest sees only that invitation-scoped preview, then authenticates or registers and verifies. The Actor submits an Application, accepts exact immutable Agreement versions, completes any review or negotiation, and receives Membership and contextual authority only after approval. Redemption is idempotent, race-safe, auditable, and preserves existing membership state correctly.

### Marta and Elizabeth across domains

Elizabeth participates in a Training Class, then joins a derived Trip Group without inheriting access merely from the relationship. Through a real introduction she discovers Marta's Tailoring Workshop, reviews an invitation-authorized curriculum and Agreement, applies, and becomes a learner. Plans, assignments, Results, and validated Evidence establish skill. Later, a customer Contract assigns Elizabeth an obligation; fulfillment, customer validation, and settlement create evidence and reputation. Marta independently participates in a Tour Group as a chef and in supplier/customer Groups. Shared identity, Concepts, Membership, Content, Agreements, Plans, Evidence, Reputation, Contracts, and accounting support the whole story without duplicating domain kernels.

## Unresolved decisions

The following decisions must be resolved before implementation whose behavior depends on them:

- Who may administer Actors: platform administrators, the associated User, Group-scoped administrators, or a defined combination?
- Must every authenticated User always retain exactly one Actor, or may an account temporarily be Actor-less?
- Does an Actor hold exactly one contextual role per Group or multiple roles?
- When Membership ends and later resumes, are prior roles discarded, restored, or explicitly reassigned?
- What are the exact Membership lifecycle states and allowed transitions?
- Is registration permanently invitation-oriented, and what controlled bootstrap mechanism creates the first platform administrator?
- Which Group permissions and operations form the initial frozen authorization matrix?
- Which database engine and deployment topology define production concurrency and migration constraints?

Unresolved product semantics must not be silently decided by an AI arm, Linear, or incidental implementation.

## What Everything is not

Everything is not a travel application with modules, a marketplace with social features, an LMS with commerce, a project manager with chat, a social network with tasks, a giant universal Entity table, a blockchain product, or a low-code builder that pretends business rules do not exist.

It is a composable contextual platform whose reusable primitives support many domains while preserving specialized operational models and invariants.

## Governance

This compass defines the durable destination, vocabulary, dependency order, and frozen architectural boundaries. It does not make speculative stages current commitments and does not override an accepted task-specific decision recorded later.

A change to a frozen boundary requires explicit human approval and an architecture decision record, or an equivalent durable documented decision linked from the relevant task handoff. When the compass, an accepted decision, a Linear issue, and implementation evidence conflict, work stops until the human owner resolves the conflict.
