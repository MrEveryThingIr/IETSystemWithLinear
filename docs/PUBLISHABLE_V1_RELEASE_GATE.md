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
12. Businesses provide a private operating workspace plus a separate public website boundary; Real Estate is a specialized Business vertical, not a top-level parallel application.
13. A public Real Estate Business can receive a guest property/need intake through its branded public website without exposing platform navigation or private office data.

## Publication hardening

Before the immutable candidate is handed to the owner:

- no known authorization-before-pagination defect;
- **invisible-if-unavailable rule:** a user must never be shown a navigation item, button, link, form control, card, or Business feature block for an unpublished/unauthorized capability merely to receive a 403/404 after clicking it;
- public Business pages must never expose private platform navigation, internal Business operations, private contacts/addresses, unpublished Listings, office cases, Planner, Deals, Money, or administration;
- internal `/businesses/{business}` workspaces are operator/member surfaces; public discovery uses the dedicated `/b/{slug}` Business website;
- Real Estate remains under Businesses, with the established intake field contract, private office/case workflow, media, one-time preview, and promotion into the Business catalog;
- a private/inactive Business must not expose its linked public Real Estate intake channel;
- one-time preview pages must not render actions that become invalid once that preview is consumed;
- Business and Real Estate release views use translation catalogs rather than Persian/English binary branches or hard-coded language-specific UI copy; English, Persian, Arabic, and Simplified Chinese catalogs remain structurally available;
- public/landing presentation carries the Everything brand promise (Everything for Everyone / همه چیز برای همه) and identifies the platform as the work-and-capital sharing system while individual Business public sites remain branded as that Business rather than the wider platform;
- authenticated chrome presents the live time/context treatment as a distinct ambient bar rather than mixing it into ordinary navigation;
- reserved-email Access Invitations are single-use;
- Intent create → highlight → manage is coherent;
- the Profile editor can manage the same subject/arrangement/value facets created by the guided Intent journey;
- full CI is green on the exact candidate;
- canonical docs describe the candidate rather than an old phase;
- production configuration uses `APP_ENV=production`, `APP_DEBUG=false`, `IET_RELEASE_PROFILE=full`, a real `APP_KEY`, HTTPS `APP_URL`, real database/mail/private-storage configuration, supervised queue workers and scheduler;
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

Remote completion ends at one immutable release-candidate SHA with green CI, synchronized acceptance/deployment docs, and an owner browser pass covering visibility/publication, Business public/private boundaries, multilingual rendering, Real Estate intake, and the full invitation-to-interaction journey. The owner then runs the cumulative browser worksheet against a continuing database. Any discovered defect receives a regression test and correction commit before the stable release tag.


## Standalone Business website acceptance amendment

Before formal v1 acceptance, verify all of the following:

- every active public Business, regardless of kind, has a discoverable
  `/b/{slug}` standalone website from its operator workspace;
- private/paused Businesses render no public-site link that can lead to 404;
- the public site contains no platform workspace navigation or product chrome;
- Business-owned Real Estate intake is advertised through
  `/b/{slug}/property-intake`, while legacy token URLs remain compatible;
- owner/manager-selected cross-Business recommendations are opt-in and can only
  expose active public Businesses;
- Superadmin can inspect/manage any Business without being inserted into that
  Business's membership table;
- non-admin users still require publication plus Business role/domain access.
