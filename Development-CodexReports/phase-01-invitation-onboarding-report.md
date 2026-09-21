# Phase 1 — Invitation, Registration and Admission Implementation Report

## Outcome

Phase 1 implementation and validation are complete. The closure commit should include this report together with the runtime/test changes and synchronized canonical documentation. No Phase 2 runtime work belongs in the same commit.

## Starting state

- Branch: `feat/group-spaces-communication`
- Phase 1 starting HEAD: `dee86677cdfd95b6b2887843cba699b21b07f6f0`
- The implementation was inherited mid-session with an intentionally dirty working tree.
- The inherited WIP covered 18 files and was reviewed/preserved rather than reset or reconstructed destructively.
- No migrations were introduced by Phase 1.

## Canonical material inspected

- `AGENTS.md`
- `.ai/rules/index.md` and applicable rules
- `docs/PROJECT_COMPASS.md`
- `docs/CURRENT_STATE.md`
- `docs/TARGET_ARCHITECTURE.md`
- `docs/PRODUCTION_ROADMAP.md`
- `docs/PHASE_01_INVITATION_ONBOARDING.md`
- `docs/ADR-001-identity-authority-and-simulation-boundaries.md`
- relevant project skills
- Phase 1 Actions, policies, routes, Livewire components, Blade views and tests

## Defects corrected

1. Invitation registration/login could create/redeem an Admission before required email verification.
2. Known expired/revoked/exhausted invitations could collapse into generic failure/404 behavior instead of safe explanatory states.
3. A stale invitation acceptance POST could race with expiry/revocation/exhaustion and fail generically instead of returning to the current safe invitation state.
4. Candidate submission did not independently enforce all currently required exact Agreement-version evidence.
5. Invitation management mutation authorization/presentation needed hardening, pagination and practical one-time private-link copy behavior.
6. Exhausted invitations could be misleadingly presented as active in manager UX.
7. New copy-link/status strings initially lacked complete locale parity.
8. Cross-Group manager isolation and the Admission-without-Membership boundary needed explicit Phase 1 regression coverage.
9. Invitation acceptance lacked a dedicated named mutation rate limiter.
10. An older Registration test still encoded the obsolete pre-verification Admission-creation contract and was aligned with the new invariant.

## Final implemented onboarding behavior

~~~text
Invitation preview
→ register/login
→ verify if required
→ return to same Invitation
→ explicit Continue to admission
→ redeem/create or resume Admission
→ accept exact required Group Agreement versions
→ submit
→ review / clarification / resubmit
→ approve
→ finalize transactionally
→ Membership
→ Group home
~~~

Registration creates User + Actor only. It does not consume the invitation or create Admission before verification and explicit continuation.

## Security and authorization decisions

- Preserve `Invitation → Admission → Membership` without exceptions.
- Invitation secrets remain SHA-256 hashed at rest and the private plaintext link is only presented at creation time.
- Unknown/random tokens remain non-disclosing; known unavailable invitations may show safe explanatory state.
- Target email comparison remains case-insensitive and public email presentation remains masked.
- Invitation page responses use private/no-store and no-referrer protections.
- Redemption remains transactional and revalidates expiry/revocation/exhaustion/targeting/current identity.
- Invitation acceptance uses a dedicated named per-identity rate limiter.
- Login/registration session regeneration and existing verification resend/auth rate limits are preserved.
- Candidate mutations remain candidate-only; reviewer/finalization mutations require current Group authority.
- Cross-Group manager authority is denied.
- Admission does not grant ordinary Group access before Membership.
- Exact required Agreement-version evidence is immutable and checked at submit/finalize boundaries.
- Membership creation/restoration remains transactional with provenance/history.

## Product/UX decisions

- New users always see where they came from, which Group they are joining, their current invitation/admission state and their next action.
- Registration/login/verification return to the invitation instead of a generic dashboard.
- Known unavailable invitations present useful states without exposing unknown-token information.
- Existing Admission can be resumed when appropriate even if an invitation later becomes exhausted.
- Owner invitation management supports targeted/reusable invitations, expiry/max uses, usage/status, revoke, linked Admissions, pagination and one-time link copy.
- Direct invitation email sending remains disabled by product choice in Phase 1; manual private-link delivery is the supported path. Production transactional-email operations belong to Phase 2.
- No generic Profile/Submission/Workflow/AdmissionContext/real-time/Contract system was introduced early.

## Tests changed/added

Coverage includes:

- register → verification without Admission redemption;
- invitation-scoped login/return;
- unavailable invitation states and unknown token behavior;
- stale acceptance after invitation becomes unavailable;
- existing Admission resume behavior;
- unverified redemption rejection;
- targeted/reusable/limited invitation behavior;
- exact Agreement evidence required for submission/finalization;
- candidate/manager/cross-Group isolation;
- pre-membership Group denial and post-finalization Group access;
- exhausted manager status;
- invitation acceptance throttling;
- localization parity;
- adversarial/forged Agreement evidence checks;
- legacy Registration test alignment with the new verified/explicit-redemption contract.

Intentional new focused regression file:

- `tests/Feature/PhaseOneInvitationContinuationTest.php`

## Validation results

Focused Phase 1 gate reported by the human owner:

- **41 tests passed / 216 assertions**.

Final repository validation reported by the human owner on 2026-09-21:

- `git diff --check`: **clean**;
- `php artisan test --compact`: **282 passed / 1437 assertions**;
- `vendor/bin/phpstan analyse`: **no errors**;
- `vendor/bin/pint --dirty --format agent`: **passed**;
- `npm run build`: **passed** with Vite 8.2.2.

The Vite build emitted an informational optional `fontaine` optimized-fallback notice; it did not fail the build and no dependency was added merely to remove the notice.

## Browser/manual acceptance

The human owner reports the implemented Phase 1 browser journey and subsequent hardening checks behave as intended, including the fresh invitation/registration/verification/return/Admission path and manager/candidate hardening checks exercised during implementation.

Browser-visible behavior verified during the work included:

- one-time invitation-link copy UX;
- translated invitation-management additions;
- safe unavailable/stale invitation behavior;
- verification before Admission creation;
- explicit Continue to admission;
- Agreement completion/submission gating;
- exhausted invitation manager state;
- pre-membership Group denial;
- post-finalization Group access;
- unrelated manager denial;
- normal invitation acceptance after throttling hardening.

A broad device/accessibility matrix remains a continuous production requirement; Phase 19 still owns the comprehensive audit. Phase 1 did not claim a full cross-browser accessibility certification.

## Architecture decision discovered during closure

Human review identified that mature Admission clarification should evolve from note-heavy state bouncing into an Admission Context with candidate/reviewer collaboration, structured evidence and explicit agreement/contract actions.

This was recorded rather than implemented early. See `docs/ADMISSION_COLLABORATION_ARCHITECTURE.md`.

Core rule:

> Conversation is where people negotiate; explicit authorized domain Actions are where the system makes something true.

The future design preserves the Phase 1 kernel, keeps Admission distinct from Membership, separates Group-wide Agreement versions from party-specific negotiated Contracts, and assigns dependencies to Phases 5/7/8/9/14.

## Temporary artifacts

Patch transport files used to move WIP between sessions are not project source and must not be committed:

- `phase-01-wip.patch`
- `phase-01-continuation.patch`
- `phase-01-continuation-v2.patch`
- `phase-01-hardening-v3.patch`
- `phase-01-hardening-v4.patch`
- `phase-01-registration-test-alignment.patch`
- `phase-01-registration-test-alignment-v2.patch`
- any later `phase-01-*.patch` transport artifact

## Unresolved risks / deferred work

No known critical Phase 1 onboarding defect remains from the audited scope.

Deliberately deferred:

- production CI/deploy/queue/mail/monitoring/backup/restore operations → Phase 2;
- Concept Kernel → Phase 3;
- progressive Profile → Phase 4;
- Admission Context/context-scoped collaboration foundation → Phase 5/8;
- structured Submission/Response/evidence → Phase 7;
- real-time outbox/broadcasting → Phase 9;
- generic negotiated Contract/Commitment/Fulfillment → Phase 14.

## Final Git gate

Before creating the Phase 1 closure commit:

1. apply/synchronize the canonical documentation closure;
2. remove temporary `phase-01-*.patch` transport files;
3. confirm `git diff --check`;
4. inspect `git status --short`, `git diff --stat`, and the staged diff;
5. stage only intended runtime/tests/docs/report files;
6. commit atomically as the Phase 1 closure;
7. push normally; never force-push;
8. stop for human review before Phase 2 runtime implementation.

## Recommended next phase

**Phase 2 — Delivery and Operations Baseline**, governed by `docs/PHASE_02_DELIVERY_OPERATIONS.md`.
