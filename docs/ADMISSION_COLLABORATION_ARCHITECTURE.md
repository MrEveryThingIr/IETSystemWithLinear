# Admission Collaboration Architecture

## Status and authority

This document records the approved target direction discovered during Phase 1 closure. It is architecture, not a request to implement later phases early.

Phase 1 remains the production onboarding kernel:

~~~text
Invitation → identity/verification → Admission → exact Agreement evidence → approved finalization → Membership
~~~

Future work extends this kernel. It must not bypass it, grant premature Group Membership, or weaken immutable evidence.

## Why this exists

The current Admission clarification flow proves the lifecycle but human review is naturally conversational. Candidates and reviewers need to ask questions, request evidence, upload files, discuss terms, annotate specific material, and converge on an acceptable result without forcing every human message to become a workflow state transition.

The target therefore separates:

- **formal lifecycle state** — where the Admission/process is;
- **conversation** — what humans are discussing;
- **requirements/submissions/evidence** — what structured material has been requested/provided;
- **agreement/contract actions** — which exact terms have been authoritatively proposed/accepted/activated.

## Non-negotiable boundaries

1. `Admission` does not imply `Membership`.
2. A candidate never receives ordinary Membership-gated Group access merely to communicate with reviewers.
3. Admission collaboration is authorized through an Admission Context.
4. Conversation messages are not authoritative domain transitions.
5. Files/messages/annotations do not count as Agreement or Contract acceptance merely because their wording says “I agree”.
6. Accepted/effective terms are immutable evidence; amendments create new versions.
7. Group-wide Agreement and candidate/party-specific negotiated Contract are different domains.
8. Real-time delivery is transport after database truth commits.

## Target Admission workspace

~~~text
Admission
├── formal lifecycle
│   ├── draft
│   ├── submitted
│   ├── under_review
│   ├── approved / rejected
│   └── finalized / cancelled
│
└── Admission Context
    ├── Shared Conversation
    │   candidate ↔ authorized reviewers
    ├── Internal Review Conversation
    │   authorized reviewers only
    ├── System Timeline
    │   read-only durable domain events
    ├── Requirements
    │   profile facts / questions / requested evidence
    ├── Submissions / Responses
    ├── Assets / Evidence
    │   images / audio / video / documents / files
    ├── Annotations
    │   messages / assets / responses / exact revisions
    └── Proposed Terms
        Group Agreement revision references and/or
        candidate-specific negotiated Contract versions
~~~

Not every Admission must enable every capability. A simple Group may keep the Phase 1 flow.

## Conversation audiences

### Shared candidate/reviewer conversation

Used for normal onboarding discussion, questions, explanations, evidence requests, and responses.

### Internal reviewer conversation

Optional. Visible only to authorized reviewers/managers. It supports internal deliberation without leaking private review discussion to the candidate.

### System timeline

Read-only entries derived from durable domain actions/events, for example:

- Admission submitted;
- evidence requested;
- Submission received;
- exact Agreement version accepted;
- reviewer approved/rejected;
- Membership finalized;
- Contract version proposed/accepted/activated.

System entries are projections of authoritative facts; they do not create those facts.

## Communication versus authority

Human conversation may lead to a decision, but only an explicit domain Action makes the decision authoritative.

~~~text
Conversation / evidence
→ human decision
→ explicit authorized domain Action
→ transaction commits
→ durable event/outbox
→ system timeline entry / notification / broadcast
~~~

Examples:

- reviewer writes “approved” → no state change until the reviewer executes Approve;
- candidate writes “I accept” → no Agreement evidence until the candidate accepts the exact version explicitly;
- reviewer asks for a certificate → requirement is created explicitly if it must be tracked structurally;
- candidate uploads a file → file is evidence only after it is bound to the relevant Submission/requirement according to that domain.

## Requirements, Submissions and evidence

Phase 7 supplies the structured response model rather than turning chat messages into forms.

Requirements may request:

- Profile facts/assertions;
- answers to admission questions;
- exact documents;
- images/audio/video/files;
- confirmations/attestations.

Assets use private reusable storage with MIME/size/security processing, hashes/provenance, and context-authorized retrieval. A message references an Asset; the message does not own a public blob.

Annotations should be able to target stable addressable objects such as:

- a message;
- an Asset/evidence item;
- a Submission Response;
- a Content revision/block;
- an Agreement/Contract proposed version.

## Group Agreement evolution

A Group Agreement represents rules applying to Group participation.

Rules:

- active/accepted versions are immutable;
- discussion/annotations may motivate a new version but never edit the active one;
- a change creates a new draft/proposed version;
- authorized Group governance approves it;
- activation/effective timing is explicit and may begin at a future cycle boundary;
- prior versions remain historical evidence;
- supersession is explicit;
- reacceptance policy determines whether existing members must accept the new exact version.

A candidate may suggest a Group-wide improvement during Admission, but one candidate/reviewer negotiation cannot unilaterally change the Group Agreement for everyone.

## Negotiated Agreement / Contract

Candidate-specific terms belong to a separate negotiated Agreement/Contract between explicit parties, for example special participation conditions, duties, availability, or reciprocal obligations.

Target lifecycle:

~~~text
conversation / negotiation
→ proposed Contract version N
→ exact immutable terms
→ required party acceptances
→ authorized activation
→ active Contract version N
→ later amendment creates version N+1
~~~

Acceptance evidence binds the exact Contract/version/terms plus Actor/party, acting authority/User where relevant, time, and integrity evidence.

Future Commitments and Fulfillment attach to the negotiated Contract rather than being inferred from chat.

## Real-time behavior

Phase 9 makes the collaboration live through the shared event/outbox infrastructure:

~~~text
Domain Action
→ transaction commits
→ durable event/outbox
→ queue
→ authorized broadcast/notification
→ client update
~~~

Requirements:

- WebSocket/broadcast payload is never source of truth;
- every channel/subscription is authorized;
- reconnect/reload reconstructs the same state from DB;
- messages/replies/annotations/submission status/system events may update live;
- broadcast failure does not undo already committed business truth;
- clients consume duplicate events safely where required.

## Roadmap ownership

This architecture is deliberately split across dependencies:

- **Phase 5 — Context:** Admission Context and context authorization boundary.
- **Phase 7 — Submission/Response/Evaluation:** structured requirements, responses, attachments/evidence.
- **Phase 8 — Admission v2:** candidate/reviewer collaboration over Admission Context using Profile/Submission/Agreement capabilities.
- **Phase 9 — Real-time:** outbox/queues/broadcasting/notifications for conversation and domain events.
- **Phase 14 — Negotiation/Agreement/Commitment/Fulfillment:** generic party-specific negotiated Contract and obligation lifecycle.

Do not create speculative generic Conversation/Contract/Workflow tables in Phase 2. Generalize only when the prerequisite phases provide at least two meaningful proof cases.

## Proof expectations

A reusable context-scoped Conversation capability should eventually prove at least:

1. ordinary GroupSpace collaboration;
2. pre-membership Admission candidate/reviewer collaboration.

A negotiated Contract model must prove a party-specific relationship without corrupting Group-wide Agreement semantics.
