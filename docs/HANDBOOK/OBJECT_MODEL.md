# IET Object Model — Human Reference

This page is the living glossary for IET. It describes both implemented objects and important future objects, but every entry is labeled so future architecture is not mistaken for current product behavior.

## Identity and participation

| Object | Status | Meaning | Why it exists |
| --- | --- | --- | --- |
| User | Implemented | Login/account identity: credentials, verification, locale, timezone, account status. | Authentication concerns must not become domain participation facts. |
| Actor | Implemented | Domain participant attributed in actions/content/history. Today usually one person backed by a User. | Domain history should refer to a participant, not directly to login credentials. |
| Group | Implemented | Governed community/team/project boundary. | Shared governance, roles, memberships, invitations and agreements need a stable boundary. |
| Membership | Implemented | Durable fact that an Actor participates in a Group. | Membership is participation truth; it is not the same as authorization in every Context. |
| Group role / permission | Implemented | Contextual authority inside one Group. | Owner/manager/member capabilities must be explicit and isolated between Groups. |

## Context and collaboration

| Object | Status | Meaning | Why it exists |
| --- | --- | --- | --- |
| Context | Implemented | Bounded environment deciding who can view/interact/manage artifacts. Current kinds: Personal, GroupSpace, Admission. | A person may collaborate before/without Group Membership; access therefore needs a boundary distinct from Group. |
| GroupSpace Context | Implemented | Context backed by a Group Space. | Hosts Group collaboration/content while reusing Context authorization. |
| Admission Context | Implemented | Pre-membership candidate/reviewer collaboration boundary. | Applicants need a protected place to collaborate without becoming Group members. |
| Personal Context | Implemented | Actor-owned private/general content environment. | Personal artifacts should not require fake one-person Groups. |
| Conversation | Future Phase 8/9 composition | Context-scoped human discussion stream. | Discussion, clarification and negotiation need persistence/realtime, but messages must not secretly perform domain transitions. |

## Content

| Object | Status | Meaning | Why it exists |
| --- | --- | --- | --- |
| Content | Implemented | Stable human-facing artifact: article, lesson, report, diary, questionnaire shell, evidence/work sample, etc. | Rich authored information needs stable identity while allowing new editions. |
| Content Revision / Edition | Implemented | One immutable revision of Content. Published revisions are sealed evidence. | Historical truth must not change when the author publishes a newer edition. |
| Content Blueprint | Implemented | Versioned recipe for creating Content structure/presentation/defaults. | Reusable authoring without creating separate backend models for every document type. |
| Edition permalink | Implemented | URL selecting one exact sealed Content revision. | A link to historical material should continue showing that exact edition after newer editions exist. |
| Content Evidence Reference | Implemented | Immutable domain locator to an exact sealed revision, optionally narrowed to a field/block/asset/relationship. | Other domains must be able to cite exact evidence, not merely a mutable latest-Content URL. |
| Annotation | Implemented | Comment/note/question/etc. anchored to Content/revision/field/block/selection. | Human discussion about content is different from structured answers or authoritative transitions. |

## Structured interactions — Phase 7

| Object | Status | Meaning | Why it exists |
| --- | --- | --- | --- |
| InteractionDefinition | Implemented | Stable identity of a structured interaction attached to a Context and optionally Content. | This exam/application/questionnaire needs stable identity across versions. |
| InteractionDefinitionVersion | Implemented | Immutable published contract defining exact questions/items, settings and evaluation rubric. | A later edit must never reinterpret an old Submission. |
| Submission | Implemented | One Actor's attempt against one exact InteractionDefinitionVersion. Lifecycle: draft → submitted, optionally withdrawn. | Applications/exams need authoritative structured response state that comments cannot represent. |
| Response | Implemented | One answer/evidence response belonging to a Submission item. | Structured answers need validation, type and exact provenance. |
| Evaluation | Implemented | Explicit reviewer assessment/feedback of one submitted attempt. | Review is its own evidence; it must not secretly approve Admission, create Membership or accept Contracts. |

## Governance and obligations

| Object | Status | Meaning | Why it exists |
| --- | --- | --- | --- |
| Group Agreement | Implemented | Versioned Group-wide participation rules. | Shared governance terms differ from private negotiated contracts. |
| Negotiated Contract | Future Phase 13 | Versioned terms between explicit parties. | Direct work/service relationships require accepted party-specific terms. |
| Commitment | Future Phase 13 | Concrete obligation such as perform work or pay compensation. | An accepted Contract may produce several explicit obligations. |
| Fulfillment | Future Phase 13 | Evidence-bearing record of actual performance against a Commitment. | What actually happened must be separate from what was planned/promised. |
| Plan / Occurrence | Future Phase 11 | Intended schedule and concrete scheduled/executed instance. | Time/repetition/execution need structured temporal truth. |
| Financial obligation / Settlement / Ledger | Future Phase 15 | Money earned/owed, payment/settlement and immutable accounting evidence. | The system must derive trustworthy earned/paid/outstanding figures instead of maintaining a magic editable balance. |

## Key rule

IET's phrase **“everything is one thing”** does not mean every concept is stored in one generic table.

It means the objects above should form one connected graph:

~~~text
Actor
→ Context
→ Conversation / Content
→ Contract / Commitment
→ Plan / Occurrence
→ Fulfillment / Evidence
→ Obligation
→ Settlement / Accounting
~~~

Each object keeps the invariants that make its facts trustworthy.
