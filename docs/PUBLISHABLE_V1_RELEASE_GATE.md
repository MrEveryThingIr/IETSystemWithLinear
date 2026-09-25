# Publishable v1 Release Gate

## Release intent

The first public IET release is a deliberately bounded learning release. It must support useful human workflows end to end, preserve durable domain evidence, and operate safely long enough to learn from real usage. AI Copilot is not part of this release.

The release does **not** require speculative Phase 23–27 abstractions. Generic Workflow, Reputation, Recommendations and AI remain future work. Their extension seams must remain compatible with the authoritative Actions/Context/Content/domain-event architecture.

## Required product journeys

The release candidate must preserve the cumulative automated and manual proof for:

1. Access Invitation → registration → email verification → Get Started.
2. Profile → Concept-backed skill/interest/goal → Need/Offer Intent.
3. Intent Directory → authorized discovery → Match explanation → proposed Relationship → participant consent.
4. Relationship → Proposal negotiation → explicit ContractVersion acceptance.
5. Contract → Commitment → Planner occurrence → Fulfillment → beneficiary review.
6. Accepted economic event → Financial Obligation → Settlement → explicit accounting posting/derived balances.
7. Group Invitation → Admission/Agreement → Membership → Community/Spaces.
8. Context Content → immutable revision → publish → placement/reference/evidence.
9. Structured interaction → Submission → authorized Evaluation.
10. Conversation/Timeline → durable Notifications; realtime is transport only.
11. Home/Today composes authoritative records without creating parallel truth.

## Publication hardening

Before the immutable candidate is handed to the owner:

- no known authorization-before-pagination defect;
- reserved-email Access Invitations are single-use;
- Intent create → highlight → manage is coherent;
- the Profile editor can manage the same subject/arrangement/value facets created by the guided Intent journey;
- full CI is green on the exact candidate;
- canonical docs describe the candidate rather than an old phase;
- production configuration uses `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_KEY`, HTTPS `APP_URL`, real database/mail/private-storage configuration, supervised queue workers and scheduler;
- backups are automated and an isolated restore drill is performed on the selected production infrastructure;
- logs/failed jobs/health are observable without exposing secrets/private content.

## First-month learning boundary

Keep authoritative business/domain history needed to reconstruct user workflows. Do not add invasive analytics or third-party behavioral tracking merely to collect more data.

For the initial observation period, learn primarily from existing durable records and events: registrations/invitations, Profiles/Intents, Relationships, Proposals, Contracts, Plans/Occurrences, Fulfillments, financial obligations/settlements/accounting, Content revisions/publication/interactions, Submissions/Evaluations, Context timeline/domain events, and notification delivery outcomes.

Operational logs are diagnostic data, not product history. Use bounded log rotation and never log plaintext invitation/reset tokens, passwords, credentials or private uploaded content.

Any later analytics/telemetry, retention change, or external monitoring provider that materially changes privacy should be an explicit post-v1 decision.

## AI placeholder

Phase 26 remains reserved for an optional AI Copilot. Future AI must prepare validated drafts for existing Actions and may never become an alternative authority for submit/approve/accept/publish/finalize/pay/accounting transitions.

No AI provider, credential, runtime call, or AI-generated autonomous mutation is required for publishable v1.

## Owner handoff

Remote completion ends at one immutable release-candidate SHA with green CI and synchronized acceptance/deployment docs. The owner then runs the cumulative browser worksheet against a continuing database. Any discovered defect receives a regression test and correction commit before the stable release tag.
