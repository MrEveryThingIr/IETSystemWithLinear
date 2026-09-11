# BOOTSTRAP-003 — Construction-project demonstration data

## Task state

- **Status:** Ready to merge
- **Responsible arm:** Codex escalation arm; human owner and ChatGPT decision hub for final review
- **Next step:** Explore the populated workflows, review the construction vocabulary, then freeze the first Spaces and Content milestone
- **Risk:** Low; development-only factories, repeatable seed data, and tests

## Objective

Provide complete factories for every persisted application model and populate three realistic construction-project groups so current behavior and lifecycle edge cases can be inspected before Spaces and Content development.

## Authoritative inputs

1. Human request for three building-project groups, three owner-partners, varied membership, construction roles, responsibilities, and payment/settlement placeholders.
2. `docs/PROJECT_COMPASS.md` and `docs/DEVELOPMENT_CIRCUIT.md`.
3. Existing domain models, actions, authorization, migrations, and test conventions.

## Accepted decisions and invariants

- Each project has exactly three active users assigned the built-in Owner role; the first owner is the Group creator.
- Each project has 15–20 active members plus one removed historical member.
- Construction job titles are Group roles backed by the current coarse permission vocabulary.
- Role duties and reporting expectations are seeded as Stories in preparation for the future Spaces and Content domain.
- Payment, settlement, partnership shares, and obligations are explicitly marked planning placeholders. They are not executable transactions or Contracts.
- The default seeder is non-destructive and repeatable; it never removes existing records and skips already-created demonstration projects.
- All demonstration accounts use `password` and `example.test` addresses.

## Scope

### Included

- Factories for all 16 persisted application models, with useful admission, agreement, invitation, membership, and account states.
- `HasFactory` support on the seven models that lacked it.
- Three construction groups with three owner-partners and 15, 17, and 16 active members respectively.
- Twelve custom project roles plus built-in Owner and Member roles in each group.
- Duties for project manager, general contractor, civil engineer, architect, electrical contractor, plumbing contractor, site supervisor, safety officer, quantity surveyor, materials supplier, skilled worker, and general laborer.
- Five planning/progress Stories and three Story responsibilities per Story in each group.
- Every Admission status, active/targeted/expired/revoked/exhausted invitations, agreement lifecycle variants, acceptance evidence, and pending/approved/rejected role changes.
- Suspended, closed, unverified, and accountless Actor/account examples.
- Repeatability, topology, credentials, lifecycle, and complete-factory tests.

### Excluded

- New Contract, obligation, payment, invoice, ledger, or settlement tables.
- Production financial records or legally binding agreement content.
- Spaces, posts, comments, attachments, reactions, or moderation implementation.
- Destructive replacement of existing development data.

## Seeded projects

| Project | Active members | Former members | Roles | Invitations | Admissions | Agreements | Stories |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Al Noor Community Center | 15 | 1 | 14 | 8 | 3 | 3 | 5 |
| Greenline Mixed-Use Renovation | 17 | 1 | 14 | 7 | 2 | 3 | 5 |
| Riverside Residence Build | 16 | 1 | 14 | 8 | 3 | 3 | 5 |

The eight seeded Admissions contain one each of: `draft`, `submitted`, `under_review`, `clarification_required`, `approved`, `finalized`, `rejected`, and `cancelled`.

## Demo access

- Riverside owner: `partner1.riverside@example.test`
- Al Noor owner: `partner1.al-noor@example.test`
- Greenline owner: `partner1.greenline@example.test`
- Password for every seeded user: `password`
- Other members follow `member<number>.<project-slug>@example.test`.

## Changes made

- Added factories and named edge states across every persisted application model.
- Added `ConstructionProjectSeeder` using existing Group, role, and agreement actions where lifecycle invariants matter.
- Made `DatabaseSeeder` repeatable and connected it to the construction scenario.
- Added deterministic member-count generation and stable demonstration identifiers.
- Added role/responsibility, reporting, daily-record, governance, and payment-placeholder Stories.
- Added full invitation, admission, agreement, acceptance, membership, and role-change examples.
- Recorded the repeatable-seeder rule in `.ai/rules/seeders.md`.

## Commands and validation

- `php artisan db:seed --no-interaction` — passed and populated the development MySQL database without deleting existing data.
- A second `php artisan db:seed --no-interaction` — passed in 55 ms with seeded table counts unchanged.
- Factory and construction-seeder tests — passed: 4 tests, 87 assertions.
- Focused seeder/model regression tests — passed: 19 tests, 118 assertions.
- `php artisan test --compact --do-not-cache-result` with an isolated compiled-view path — passed: 104 tests, 559 assertions.
- `vendor/bin/phpstan analyse --no-progress` — passed with 0 errors.
- `vendor/bin/pint --dirty --format agent` — passed.
- `git diff --check` — passed in the final working-tree state.
- Read-only database inspection confirmed project counts and all eight Admission states.

Non-blocking environment note: validation used isolated compiled-view directories and disabled PHPUnit's result cache because the normal Windows cache locations are held by another process or ACL.

## Risks and unresolved questions

- Construction vocabulary and seeded English content should be reviewed by a domain expert before becoming product defaults.
- The current permission vocabulary is intentionally coarse; role duties are descriptive until Spaces/Content and later Contract/obligation authorization are designed.
- Stories are useful foundation records but have no complete end-user Space/Content interface yet.
- Seed credentials are development-only and must never be used as production credentials.

## Repository linkage

- **Branch:** `FIX_BY_VSCODE_AGENT`
- **Commit(s):** Pending for this dataset; current HEAD before commit is `822c1c9`
- **Pull request:** Not created
- **Linear issue:** Not created

## Final outcome

The development database is populated with three coherent construction-project scenarios and broad lifecycle coverage. Every persisted application model now has a working factory, and the repeatable seed is regression-tested and ready for review and commit.

## Prompt for next step

```text
TASK: Define the first Group Spaces and Content milestone using the construction-project scenarios.
RESPONSIBLE ARM OR DECISION GATE: Human owner + ChatGPT decision hub for product semantics; Linear for accepted implementation work.
AUTHORITATIVE INPUTS: docs/PROJECT_COMPASS.md; docs/DEVELOPMENT_CIRCUIT.md; docs/handoffs/BOOTSTRAP-002-invitation-admission-agreements.md; docs/handoffs/BOOTSTRAP-003-construction-project-demo-data.md; seeded project scenarios.
CURRENT VERIFIED STATE: Three construction groups contain 48 active members, 42 roles, 23 invitations, 8 Admissions covering every state, 9 agreements, and 15 planning/progress Stories.
ACCEPTED DECISIONS: Stories contain non-binding planning context; financial and settlement placeholders do not create Contracts or obligations.
OBJECTIVE: Turn the seeded reporting and daily-work examples into the smallest complete Space and authored Content flow.
INCLUDED: Space creation and visibility; post/article/diary types; authorship; revision; moderation; membership access; construction progress use cases.
EXCLUDED: Enforceable Contract, payment, ledger, and settlement behavior until those domains are separately designed.
ACCEPTANCE CRITERIA: Must be frozen in Linear before implementation.
REQUIRED TESTS: Authorization matrix, tenant isolation, lifecycle, escaping, query behavior, and complete author/reader journeys against seeded roles.
RISKS: Visibility, moderation, role authority, revision history, deletion, evidence, attachments, and content-type semantics remain unresolved.
REQUIRED OUTPUT: Accepted Linear issue, implementation, GitHub review evidence, and an updated task handoff.
STOP CONDITIONS: Stop for unresolved product semantics, frozen-boundary changes, destructive operations, or incomplete validation.
```
