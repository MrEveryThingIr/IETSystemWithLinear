# Everything Development Circuit

## Purpose

Everything uses a repository-based AI–human development network. Product meaning, implementation, independent criticism, issue state, and source history have different owners; none should silently substitute for another.

The normal circuit is:

```text
Human owner + ChatGPT decision hub
→ Codex preparation or implementation
→ Claude independent review
→ human decision
→ Linear issue creation or delegation
→ GitHub branch, pull request, and CI
→ validation
→ merge
→ handoff and state update
→ next cycle
```

The circuit may begin at analysis or issue preparation when implementation is not yet authorized. It must not skip unresolved product decisions merely to keep work moving.

## Responsibilities

### Human owner

- Approves product semantics and unresolved behavior.
- Approves material architecture decisions and changes to frozen boundaries.
- Approves sensitive merges and releases.
- Resolves contradictions between authoritative inputs.

### ChatGPT decision hub

- Examines reports from the development arms.
- Identifies contradictions, missing evidence, and decisions requiring the owner.
- Selects or recommends one coherent direction.
- Edits or augments the next-arm prompt only when needed.
- Does not duplicate Linear's issue or workflow state.

### Codex

- Inspects the repository and applicable instructions before acting.
- Prepares architecture-aware implementation plans and executes approved work.
- Owns Laravel/PHP/backend work, migrations, authorization, transactions, concurrency, integration, tests, and exact validation reporting when assigned.
- Preserves unrelated work and reports the exact Git state.

### Claude

- Performs independent domain, architecture, security, edge-case, and implementation review.
- Challenges unsupported conclusions and checks work against authoritative inputs.
- Performs UI/UX work when specifically assigned.
- Implements only when a Linear issue explicitly delegates implementation.

### Linear

- Stores projects, milestones, issues, dependencies, acceptance criteria, assignments, and live workflow state.
- Delegates routine coding sessions when an issue is sufficiently frozen.
- Links the issue, branch, pull request, and handoff.
- Never silently decides unresolved product semantics.

### GitHub

- Is the authoritative source for code, branches, pull requests, review discussion, CI evidence, commits, merges, and releases.
- Preserves the committed history of handoff reports and architecture decisions.

## Authority boundaries

- `docs/PROJECT_COMPASS.md` governs durable product vocabulary, destination, and frozen architecture.
- Human-approved architecture decisions govern explicit exceptions or refinements.
- Linear governs live task scope and workflow state after an issue exists.
- The task handoff records verified execution history and cross-arm context.
- GitHub and CI prove the exact code, review, and validation state.
- Historical files in `Development-CodexReports/` remain evidence for their milestones but are not a live roadmap.

If these sources conflict in a way that changes behavior, scope, or acceptance, stop and request human resolution.

## Cycle states

1. **Proposed** — an idea or need exists but has not been analyzed.
2. **Analysis** — evidence, constraints, alternatives, and dependencies are being examined.
3. **Decision required** — one or more unresolved choices materially change behavior or architecture.
4. **Frozen** — the human owner has accepted the relevant semantics, invariants, and boundaries.
5. **Ready for implementation** — the implementation entry requirements are complete.
6. **In progress** — an assigned arm is changing the repository.
7. **Review** — implementation or documentation awaits independent review.
8. **Changes requested** — review identified required corrections.
9. **Ready to merge** — acceptance criteria and required validation are satisfied.
10. **Done** — the accepted work is merged and durable state is updated.
11. **Blocked** — progress requires missing authority, information, access, or an external state change.

## Entry requirements for implementation

An issue may enter **Ready for implementation** only when it has:

- One objective.
- Accepted invariants and product semantics.
- Explicit included scope.
- Explicit excluded scope.
- Measurable acceptance criteria.
- Required tests and validation.
- Known dependencies.
- A stated risk level.
- No unresolved decision that would materially change behavior.

Discovery, audit, documentation, and decision work may proceed earlier, but must not be mislabeled as implementation.

## Handoff discipline

Every meaningful AI working session creates or updates exactly one task handoff in `docs/handoffs/`. Small correction loops update the same file rather than generating competing summaries.

Before Linear exists, use a stable bootstrap identifier such as:

```text
BOOTSTRAP-001-codex-to-claude.md
```

After Linear is established, prefer:

```text
<LINEAR-ID>-<short-task-name>.md
```

Each handoff contains:

- Task identifier and title.
- Current status and assigned arm.
- Objective and authoritative inputs.
- Accepted decisions and invariants.
- Included and excluded scope.
- Files or systems examined.
- Changes made.
- Tests and commands actually run, with exact results.
- Risks and unresolved questions.
- Git branch, commits, pull request, and Linear issue.
- Reviewer findings and final outcome.
- `Prompt for next arm`.

Report rules:

- Never claim a check that was not run.
- Record durable conclusions, not full conversations.
- Do not paste enormous output unless it is necessary evidence.
- Link to source, commits, and pull requests instead of duplicating diffs.
- Never include secrets, environment values, signed URLs, tokens, or personal recipient data.
- Update the existing task handoff rather than create a second source of truth.
- GitHub stores committed handoff history; Linear stores live workflow state.
- A handoff must not contradict its Linear issue or an accepted architecture decision.
- A blocked or incomplete result must be reported as such.

## Git discipline

The repository's trunk is `master`. Existing work uses short-lived feature branches; there is no permanent `develop` or release integration branch. Do not rename the trunk or introduce an integration branch without an explicit repository decision.

After Linear identifiers exist, prefer agent branches in this form:

```text
codex/<type>/<linear-id>-<slug>
```

Use a type that communicates intent, such as `feat`, `fix`, `refactor`, `docs`, or `chore`. Preserve a human-requested branch name when explicitly supplied. Every pull request should link its Linear issue and task handoff, keep unrelated changes out, report validation honestly, and target the repository's established trunk unless the issue says otherwise.

## Prompt for next arm template

```text
TASK:
ROLE OF THIS ARM:
AUTHORITATIVE INPUTS:
CURRENT VERIFIED STATE:
ACCEPTED DECISIONS:
OBJECTIVE:
INCLUDED:
EXCLUDED:
ACCEPTANCE CRITERIA:
REQUIRED TESTS:
RISKS:
REQUIRED OUTPUT:
STOP CONDITIONS:
```
