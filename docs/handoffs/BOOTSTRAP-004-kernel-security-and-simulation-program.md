# BOOTSTRAP-004 — Kernel security and simulation delivery program

## Task state

- **Status:** F2 validated; commit pending
- **Responsible arm:** Codex escalation arm; human owner remains product authority
- **Risk:** High across the full program; milestones are committed and validated independently

## Objective

Implement the accepted identity, authority, Membership, Group, workflow, delivery, and simulation architecture in ordered milestones F0–F4 and S1–S4 without crossing frozen boundaries or promoting simulated activity into live truth.

## Authoritative inputs

1. Human-approved evaluation and final settled decisions supplied on 2026-09-11.
2. `docs/ADR-001-identity-authority-and-simulation-boundaries.md`.
3. `docs/PROJECT_COMPASS.md` and `docs/DEVELOPMENT_CIRCUIT.md`.
4. Existing repository behavior, migrations, tests, and previous task handoffs.

## Accepted decisions and invariants

- User, Actor, Membership, and Group remain distinct.
- Platform authority belongs to User and is auditable.
- Actor identity is global and ordinary workflows cannot reassign `Actor.user_id`.
- Actors may hold multiple additive contextual roles in a Group.
- Membership status gates all contextual authority.
- Simulation reuses normal Group-domain records with strict consequence isolation.
- Simulation history is never promoted into live operational truth.
- MySQL 8+/InnoDB/single-primary and UTC storage define concurrency and time behavior.

## Included

- Foundation milestones F0–F4.
- Simulation milestones S1–S4 after the foundation exit gate.
- Focused commits, migration/fresh-upgrade checks, hostile authorization and isolation tests, static analysis, frontend build/audit, localization validation, and durable handoff evidence.

## Excluded

- Spaces and Content implementation, except future integration boundaries.
- Production Contracts, accounting, settlement, Evidence, and Reputation implementations beyond the isolation guards required by simulation.
- Any change that contradicts ADR-001 without a human-approved superseding ADR.

## Current verified state

- Branch `feat/kernel-security-foundation` starts exactly at `milestones` commit `0253c05`.
- Laravel 13.29.0 runs on PHP 8.4 with MySQL as the configured application database.
- Application timezone is UTC.
- Discarded security work exists at commit `a2da29d` and requires a dedicated recovery branch.
- Existing untracked Farsi factory/seeder work is preserved and excluded from unrelated milestone commits until reviewed under F4.

## F0 validation evidence

- Created `recovery/a2da29d-actor-security` at exact commit `a2da29d84d70ec3fceb6f33a08ed13800e8602a6`.
- Confirmed the working foundation branch starts exactly at `milestones` commit `0253c05`.
- Confirmed MySQL `8.4.3`, default `InnoDB`, writable single-primary development connection, and Laravel database connection `mysql`.
- Confirmed Laravel stores application timestamps under the configured `UTC` application timezone. The database host reports `Iran Standard Time`; application conversion must remain explicit at input/render boundaries.
- Normal PHPUnit compiled-view/cache paths are writable: full suite passed without the previous isolated-path workaround.
- Production Vite output is writable and the build succeeds when invoked through `npm.cmd` on Windows. PowerShell blocks `npm.ps1` under the host execution policy; this is a shell invocation constraint, not an application build failure.
- `php artisan test --compact --do-not-cache-result`: 115 tests passed, 658 assertions.
- `vendor/bin/phpstan analyse --no-progress`: passed with 0 errors.
- `npm.cmd run build`: passed under Vite 8.2.2.
- `git diff --check`: passed.

## Milestone ledger

| Milestone | State | Commit | Validation |
| --- | --- | --- | --- |
| F0 — Preserve/baseline | Complete | `663a64b` | PHPUnit 115/658; PHPStan 0; Vite build passed |
| F1 — Actor lockdown | Complete | `fee708d` | Focused 36/173; full PHPUnit 129/692; PHPStan 0; Vite build passed |
| F2 — Membership/RBAC | Validated; commit pending | Pending | Focused/regression 46/190; full PHPUnit 149/745; PHPStan 0; MySQL migrations and Vite build passed |
| F3 — Workflow integrity | Not started | — | — |
| F4 — Delivery reliability | Not started | — | — |
| S1 — Simulation boundary | Blocked by F0–F4 | — | — |
| S2 — Simulated Actors/sessions | Blocked by S1 | — | — |
| S3 — Collaborative simulation | Blocked by S2 | — | — |
| S4 — System/AI mandates | Blocked by S3 | — | — |

## Files and systems examined

- Git branch/ref topology and discarded commit `a2da29d`.
- Laravel/PHP/package versions through Laravel Boost.
- `config/app.php`, `phpunit.xml`, Composer scripts, npm scripts, filesystem ACLs, existing documentation, and task handoffs.

## Changes made

- Added accepted ADR-001 as the durable architecture decision.
- Added this single program handoff for milestone-by-milestone evidence.
- Preserved discarded security work on `recovery/a2da29d-actor-security`.
- Added auditable User-scoped `platform_access_grants` with the frozen Superadmin capability map.
- Added the transactional, one-time, console-only `platform:bootstrap-superadmin {email}` workflow.
- Added `ActorPolicy` and matching route, Livewire-render, and Livewire-mutation authorization.
- Removed generic Actor identity editing and restricted generic creation to accountless Actors.
- Added model-level identity-link immutability and prohibition of physical Actor deletion.
- Added authorized Actor archival with timestamp, administrator, and required reason while protecting active User identities.
- Hid Actor administration navigation from unauthorized accounts and localized the revised interface in all four registered locales.
- Recorded durable application and Actor identity rules under `.ai/rules`.
- Added immutable built-in Group role identities, the frozen permission catalog, baseline Member plus additive contextual roles, and permission-union resolution isolated by Group.
- Replaced role-replacement semantics with explicit grant/revoke operations and prohibited custom roles from receiving ownership-transfer authority.
- Added database-enforced idempotence for pending grant/revoke requests, self-review protection, locked review, and single-role mutation semantics.
- Added the `active`, `suspended`, `left`, and `removed` Membership transition matrix with immutable events, authority suspension, role preservation on suspension, and baseline-only readmission behavior.
- Added dedicated atomic ownership transfer and extended last-active-Owner protection to role revocation, suspension, removal, and leave paths.
- Made Group creation require the User-scoped `create_groups` platform capability and aligned Group policies and rendered management surfaces with contextual capabilities.
- Localized all Group permission labels and new Membership, role-request, and ownership interface text across English, Arabic, Persian, and Simplified Chinese.

## Tests and commands actually run

- Laravel Boost application inspection confirmed Laravel 13.29.0, PHP 8.4, and MySQL.
- Git ancestry inspection confirmed the foundation branch currently equals `milestones`.
- Configuration inspection confirmed UTC application time.
- Filesystem ACL and full-suite execution confirmed writable project cache/view directories.
- `php artisan migrate --no-interaction` — F1 upgrade migrations passed on MySQL 8.4.3.
- Focused F1 suite — 36 tests passed with 173 assertions.
- Full `php artisan test --compact --do-not-cache-result` — 129 tests passed with 692 assertions.
- `vendor/bin/phpstan analyse --no-progress` — passed with 0 errors after F1.
- `npm.cmd run build` — production Vite build passed after F1.
- Actor route inspection confirms only authorized index, create, and show routes; the generic edit route is absent.
- Read-only schema inspection confirmed the platform grant foreign keys, audit fields, indexes, and correlation-ID uniqueness.
- `php artisan migrate --no-interaction` — F2 upgrade migrations passed on MySQL 8.4.3.
- Focused/regression F2 and localization suite — 46 tests passed with 190 assertions.
- Full `php artisan test --compact --do-not-cache-result` — 149 tests passed with 745 assertions.
- `vendor/bin/phpstan analyse --memory-limit=1G` — passed with 0 errors after F2.
- `npm.cmd run build` — production Vite build passed after the F2 Blade changes.
- `vendor/bin/pint --dirty --format agent` and `git diff --check` — passed.

## Risks and unresolved questions

- F3–F4 alter admission, invitation, evidence, concurrency, privacy, time, and delivery semantics and require narrow adversarial tests before the full suite.
- Existing Farsi seed work has not yet been attributed or validated and must not be silently overwritten.
- Simulation implementation remains prohibited until the complete foundation exit gate passes.

## Repository linkage

- **Branch:** `feat/kernel-security-foundation`
- **Commits:** Pending
- **Pull request:** Not created
- **Linear issue:** Not created

## Prompt for next step

```text
TASK: Complete the next incomplete milestone in ADR-001 delivery order.
RESPONSIBLE ARM OR DECISION GATE: Codex escalation arm unless a product semantic conflict requires the human owner.
AUTHORITATIVE INPUTS: ADR-001, Project Compass, Development Circuit, this handoff, repository tests and migrations.
CURRENT VERIFIED STATE: Read the milestone ledger and latest validation entries in this handoff.
ACCEPTED DECISIONS: All ADR-001 decisions.
OBJECTIVE: Satisfy the next milestone's rendered acceptance and commit it independently.
INCLUDED: Only that milestone and required supporting corrections.
EXCLUDED: Later milestones and any superseding architecture decision.
ACCEPTANCE CRITERIA: Milestone-specific behavior, adversarial tests, Pint, PHPStan, relevant build/database checks, focused commit.
REQUIRED TESTS: Narrow tests first, then the milestone-appropriate regression suite.
RISKS: Authorization, tenant isolation, concurrency, irreversible identity changes, cross-mode leakage.
REQUIRED OUTPUT: Focused commit and updated milestone ledger with exact evidence.
STOP CONDITIONS: Unresolved product semantics, frozen-boundary conflict, destructive data operation, or validation that cannot be completed.
```
