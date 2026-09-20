# Phase 1 — Production Invitation, Registration and Admission Journey

## Status

Ready for implementation after Phase 0 documentation is synchronized.

## Objective

A completely new person who has never used IET should be able to receive one invitation link, understand what they are being invited to, register or log in, verify their email, return automatically to the invitation/admission flow, complete all currently required admission steps, and reach the Group when membership is legitimately finalized.

The user should not need developer guidance.

## Why this phase comes first

The platform already contains substantial backend infrastructure for:

- invitation issuance;
- targeted/reusable invitations;
- registration;
- verification;
- Admission;
- Agreement acceptance;
- review;
- membership finalization.

Before expanding into Concepts, Profile, Planner, Finance, or Blueprints, this existing path must become a reliable product experience.

This phase intentionally improves the current architecture without waiting for the future AdmissionContext/Profile systems. Those later phases will extend rather than invalidate this journey.

## Existing flow to preserve

Current semantics:

~~~text
Invitation preview
→ authenticate/register
→ verify
→ redeem invitation
→ create/reuse Admission
→ accept required Group Agreement versions
→ submit
→ manager review
→ approve
→ finalize
→ Membership
~~~

Do not bypass Admission by directly creating Membership from a public invitation.

## Product flow

Target user-visible sequence:

~~~text
1. Invitation page
2. Register or Login
3. Email verification when required
4. Automatic return to the invitation
5. Create/open Admission
6. Admission progress page
7. Required agreements / current requirements
8. Submit
9. Review or clarification if configured
10. Approval
11. Membership finalization
12. Welcome / Group home
~~~

## Invitation page requirements

The page should make the invitation understandable before authentication.

Show only safe invitation-authorized information such as:

- Group name;
- short Group description;
- inviter identity where policy allows it;
- whether invitation is targeted;
- expiry/status where useful;
- what joining means;
- whether approval is required under the current flow;
- which next action is available;
- privacy-safe explanation of required steps.

Never expose:

- private Group Content not explicitly allowed for invitation preview;
- member directory;
- hidden Spaces;
- admission records of other candidates;
- raw email addresses that should be masked;
- secrets/tokens.

### States

Handle clearly:

- valid invite;
- expired;
- revoked;
- exhausted;
- already accepted/current Admission;
- already a current member;
- targeted to a different email;
- invalid/not found.

Do not turn these into ambiguous generic 404/422 pages when a safe user-facing explanation is possible.

Security-sensitive invalid tokens may still use non-disclosing behavior.

## Registration requirements

Keep registration minimal:

- username;
- email;
- password;
- password confirmation.

Do not add a large profile questionnaire in this phase.

On invitation registration:

- invitation context survives validation errors;
- targeted email rules are enforced;
- User and Actor creation remains transactional;
- login session is regenerated;
- intended return location is preserved;
- verification is required before invitation redemption where current policy requires it.

## Login requirements

A guest following an invitation should be able to choose Login.

After successful login:

- return to the invitation flow;
- do not dump the user at a generic dashboard;
- preserve invitation token safely in the intended URL/session flow.

If the authenticated User lacks an Actor due to an exceptional state, stop with a controlled recovery/error path rather than creating inconsistent domain state silently.

## Verification requirements

After registration/login when email verification is required:

- verification page explains why verification is needed;
- resend behavior is rate-limited;
- verified callback returns to the original invitation/admission path;
- expired or already-consumed invitation state is revalidated after verification;
- no authorization decision trusts the pre-verification page state.

## Redemption and Admission requirements

Redemption remains server-authoritative and transactional.

Required invariants:

- token hashed at rest;
- expiry checked;
- revocation checked;
- max-use/exhaustion checked;
- targeted email checked case-insensitively;
- duplicate acceptance handled idempotently;
- current active/suspended membership cannot create a duplicate admission;
- admission provenance remains immutable;
- re-admission behavior remains explicit;
- concurrency cannot over-consume a limited invitation.

After redemption, redirect to the candidate's Admission page.

## Admission progress UX

The candidate page should answer:

- What am I applying/joining for?
- What is my current status?
- What do I need to do next?
- Which agreements must I accept?
- Have I submitted?
- Is clarification requested?
- Was I approved/rejected/cancelled?
- Who acts next?

Prefer a clear progress/timeline presentation.

Do not expose management actions to candidates.

## Agreement UX

For required admission Agreements:

- show Agreement name;
- show the exact active version/terms;
- make acceptance explicit;
- record immutable evidence;
- display accepted state;
- prevent submit/finalize when required exact evidence is missing;
- explain reacceptance only when relevant.

This phase may improve presentation but must not weaken the current Agreement evidence contract.

## Candidate transitions

Current allowed candidate operations should remain explicit.

Candidate may:

- accept required agreements;
- submit when requirements are satisfied;
- cancel when lifecycle allows;
- resubmit after clarification where lifecycle allows.

Candidate must not:

- approve themselves;
- finalize membership;
- mutate Group configuration;
- access Group Content merely because an Admission exists.

## Reviewer/manager UX

Authorized Group managers should be able to:

- see pending Admissions;
- inspect candidate identity/basic details;
- inspect agreement completion;
- inspect timeline/events;
- request clarification;
- move to review states;
- approve/reject;
- finalize where current domain rules separate approval from finalization.

Actions must reauthorize at mutation time.

## Finalization

Finalization:

- requires approved Admission;
- requires current exact Agreement evidence;
- creates or restores Membership transactionally;
- grants baseline Member role;
- records Membership event/history;
- preserves provenance from Admission;
- redirects to a useful Group landing page.

No partial Membership should survive a failed transaction.

## Welcome / Group home

After successful membership:

- show clear confirmation;
- identify the Group;
- show available Spaces/capabilities the member can actually access;
- show required reacceptance if Group Agreements later demand it;
- avoid exposing management controls unless authorized.

## Owner invitation management

The existing invitation management page should be hardened for practical use.

At minimum:

- create targeted or reusable invitation;
- choose/use max uses according to current supported contract;
- show expiry;
- show usage count;
- copy/share resulting URL conveniently;
- revoke;
- show linked admissions safely;
- avoid exposing raw stored token after creation;
- clear empty states and statuses.

Do not add arbitrary admission Blueprint configuration yet; that belongs to Phase 8.

## Email delivery

Phase 1 should define production-ready invitation email behavior even if actual deployment credentials are environment-specific.

Requirements:

- queued mail where appropriate;
- safe invitation URL;
- Group/inviter context;
- expiration notice;
- no sensitive admission details;
- retry/failed-mail operational visibility;
- localization strategy;
- test coverage with mail fakes.

If direct invitation email sending is not yet enabled by product choice, document the manual-link flow and keep the backend ready for email delivery.

## Notification behavior

At minimum consider notifications for:

- invitation delivered/created;
- Admission submitted;
- clarification requested;
- approved/rejected;
- membership finalized.

Do not create a giant notification center in this phase if it is not needed. Use the simplest reusable notification path consistent with the target architecture.

## Mobile/accessibility requirements

Manual acceptance should cover:

- phone-width invitation page;
- phone-width registration/login/verification;
- admission page;
- agreement reading/acceptance;
- keyboard navigation;
- visible focus;
- labels/error associations;
- no horizontal overflow;
- clear button hierarchy;
- no modal-only critical workflow.

## Localization

Existing supported locales should not regress.

All new user-facing strings should use the existing localization approach.

Do not duplicate English literals throughout Livewire/Blade when the project already has localization files.

## Security requirements

Audit:

- rate limits for login/register/verification resend/invitation-sensitive endpoints;
- session fixation prevention;
- CSRF;
- open redirect protection in intended URL handling;
- token leakage in logs;
- authorization recheck;
- targeted invite email comparison;
- invitation enumeration risk;
- concurrent redemption;
- admission ownership;
- manager authorization;
- agreement exact-version acceptance.

## Tests

### Invitation

Cover:

- valid preview;
- expired;
- revoked;
- exhausted;
- targeted email;
- reusable max uses;
- concurrent/duplicate redemption behavior;
- already-member behavior;
- existing Admission behavior.

### Auth return flow

Cover:

- invite → registration → verification intent;
- invite → login → return;
- verified callback return;
- invalid/expired invitation after delayed verification.

### Admission

Cover:

- candidate view isolation;
- agreement requirement;
- submit;
- clarification;
- resubmit;
- approve;
- reject;
- finalize;
- membership/role creation;
- readmission behavior if supported;
- no duplicate Membership.

### Authorization

Cover candidate vs manager boundaries and cross-Group isolation.

### UX source/Livewire tests

Where browser automation is unavailable, add focused feature/source-contract tests for critical state presentation, but do not pretend these replace manual browser acceptance.

## Manual acceptance script

Use a fresh invitation and fresh email/user.

1. Owner creates invitation.
2. Open URL in logged-out/private browser.
3. Confirm invitation explains Group and next step.
4. Register.
5. Verify email through actual local mail/log flow.
6. Confirm redirect returns to invitation/admission rather than dashboard.
7. Accept required agreements.
8. Submit.
9. In owner session, review/approve/finalize.
10. In candidate session, reach Group home.
11. Confirm inaccessible management pages remain forbidden.
12. Repeat with:
    - expired invitation;
    - wrong targeted email;
    - reused valid invitation where max uses allow;
    - already-member case.
13. Repeat primary flow at mobile width.

## Included

- current invitation/auth/admission/agreement/membership UX and correctness;
- practical invitation management;
- return-path correctness;
- production-minded mail/notification integration;
- focused rate limiting/security corrections;
- tests;
- documentation/runbook for this path.

## Explicitly excluded

- Concept Kernel;
- full Profile system;
- AdmissionContext;
- configurable admission questionnaires;
- generic Submission engine;
- generic Workflow engine;
- Planner;
- Group Blueprints;
- Need/Offer;
- accounting;
- Financial Laboratory;
- broad homepage recommendation system;
- unrelated Content redesign.

## Migration rule

Do not rewrite the existing Admission/Agreement foundation merely because future phases will generalize parts of it.

Change only what Phase 1 needs for correctness/usability.

## Required implementation report

Write:

`Development-CodexReports/phase-01-invitation-onboarding-report.md`

Include:

- baseline commit;
- files inspected;
- issues found before change;
- accepted implementation plan;
- migrations;
- behavior changes;
- security decisions;
- tests added/changed;
- exact validation results;
- manual checks completed/not completed;
- unresolved risks;
- final Git state;
- recommended next action.

## Stop conditions

Stop and request human/architecture review if implementation requires:

- changing the fundamental Invitation → Admission → Membership contract;
- granting pre-membership Group access;
- weakening Agreement evidence;
- introducing a generic Profile/Submission/Workflow engine early;
- changing User/Actor identity semantics;
- adding a new external service/provider with nontrivial product implications;
- destructive migration;
- unresolved privacy/security behavior.

## Exit gate

Phase 1 is complete only when:

- automated tests are green;
- PHPStan/Pint/build are green;
- the manual fresh-user invitation journey succeeds;
- the owner can practically create/share/revoke invitations;
- candidate and manager permissions remain isolated;
- required Agreement evidence still blocks invalid finalization;
- no known critical onboarding security defect remains;
- the report is committed;
- human owner accepts the browser experience.
