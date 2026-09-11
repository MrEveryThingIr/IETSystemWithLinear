# Everything Development Circuit

## Purpose

Everything uses a Linear-first AI–human development circuit. Product meaning, architecture decisions, live work state, source history, and validation evidence have distinct authorities; none silently substitutes for another.

The default circuit is:

```text
Human owner + ChatGPT decision hub
→ Linear planning, execution, and review
→ GitHub branch, pull request, CI, commit, merge, or release
→ Human/ChatGPT decision gate
→ Linear correction or continuation
```

Linear may continue unblocked accepted work without human interruption between routine steps. Pause for the human owner and ChatGPT decision hub when a product semantic decision is unresolved, a frozen architecture boundary may change, a destructive or irreversible operation is required, validation cannot be completed, or a materially different design choice needs human judgment.

Codex is an optional escalation arm, not a mandatory stage. Use it for difficult Laravel architecture, security, concurrency, migrations or data integrity, deep repository audits, repeated failures, or an independent high-risk review.

## Responsibilities

### Human owner

- Is the product authority and approves unresolved product semantics.
- Approves material architecture decisions and changes to frozen boundaries.
- Approves sensitive merges and releases.
- Resolves contradictions between authoritative inputs.

### ChatGPT decision hub

- Synthesizes evidence, alternatives, and recommendations into a coherent direction.
- Identifies contradictions, missing evidence, and decisions requiring the owner.
- Does not duplicate Linear's live issue or workflow state.

### Linear

- Is the primary planning, execution, and review arm for accepted work.
- Stores projects, milestones, issues, dependencies, acceptance criteria, assignments, and live workflow state.
- Continues routine unblocked work, links issues to branches, pull requests, and handoffs, and records corrections.
- Never silently decides unresolved product semantics or changes frozen architecture boundaries.

### Codex escalation arm

- Inspects the repository and applicable instructions before acting.
- Is engaged only when specialist Laravel or independent high-risk review is needed.
- May investigate or implement the specifically escalated scope and reports exact Git state and validation evidence.

### GitHub

- Is authoritative for source, branches, pull requests, review discussion, CI evidence, commits, merges, and releases.
- Preserves committed history of handoffs and architecture decisions.

## Authority boundaries

- `docs/PROJECT_COMPASS.md` governs durable product vocabulary, destination, and frozen architecture.
- Human-approved architecture decisions govern explicit exceptions or refinements.
- The human owner is the product authority.
- ChatGPT is the architecture and decision-synthesis hub.
- Linear governs live issue, project, and workflow state after an issue exists.
- GitHub governs the exact source, pull request, CI, commit, merge, and release state.
- Task handoffs record verified execution history and cross-context information; they do not replace Linear or GitHub.
- Historical files in `Development-CodexReports/` remain evidence for their milestones but are not a live roadmap.

If these sources conflict in a way that changes behavior, scope, or acceptance, stop and request human resolution.

## Cycle states

1. **Proposed** — an idea or need exists but has not been analyzed.
2. **Analysis** — evidence, constraints, alternatives, and dependencies are being examined.
3. **Decision required** — one or more unresolved choices materially change behavior or architecture.
4. **Frozen** — the human owner has accepted the relevant semantics, invariants, and boundaries.
5. **Ready for implementation** — the implementation entry requirements are complete.
6. **In progress** — Linear or an explicitly escalated arm is changing the repository.
7. **Review** — implementation or documentation awaits review appropriate to its risk.
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

Every meaningful working session creates or updates exactly one task handoff in `docs/handoffs/`. Small correction loops update the same file rather than generating competing summaries.

Before Linear exists, use a neutral stable bootstrap identifier such as:

```text
BOOTSTRAP-001-project-foundation.md
```

After Linear is established, prefer:

```text
<LINEAR-ID>-<short-task-name>.md
```

Each handoff contains:

- Task identifier and title.
- Current status and responsible arm or decision gate.
- Objective and authoritative inputs.
- Accepted decisions and invariants.
- Included and excluded scope.
- Files or systems examined.
- Changes made.
- Tests and commands actually run, with exact results.
- Risks and unresolved questions.
- Git branch, commits, pull request, and Linear issue.
- Review findings and final outcome.
- `Prompt for next step`.

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

The protected production trunk is `main`. Completed milestones integrate through the long-lived `develop` branch, and releases move from `develop` to `main` through reviewed pull requests. Direct feature development on `main` or `develop` is prohibited.

Feature and correction work branches from `develop`. After Linear identifiers exist, prefer agent branches in this form:

```text
linear/<type>/<linear-id>-<slug>
```

Use a type that communicates intent, such as `feat`, `fix`, `refactor`, `docs`, or `chore`. Preserve a human-requested branch name when explicitly supplied. Every pull request should link its Linear issue and task handoff, keep unrelated changes out, report validation honestly, and target `develop`. Release pull requests target `main` and receive a semantic version tag after merge.

## Prompt for next step template

```text
TASK:
RESPONSIBLE ARM OR DECISION GATE:
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
