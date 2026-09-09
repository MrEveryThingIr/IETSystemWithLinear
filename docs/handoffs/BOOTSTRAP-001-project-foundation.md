# BOOTSTRAP-001 — Establish the project compass and development circuit

## Task state

- **Status:** Superseded as a bootstrap workflow record
- **Responsible arm:** Human owner + ChatGPT decision hub
- **Next step:** Use Linear as the primary planning, execution, and review arm for accepted work
- **Risk:** Low; documentation only

## Objective

Create the initial durable project compass and development circuit so future work can orient without access to the originating chats.

## Authoritative inputs

1. The human-supplied product and architecture north star, condensed into `docs/PROJECT_COMPASS.md`.
2. The verified Everything audit and correction pass dated 2026-09-09.
3. Repository instructions in `AGENTS.md` and `CLAUDE.md`.
4. Historical milestone evidence in `Development-CodexReports/`.
5. Current repository source and Git history where they establish verified present state.

## Accepted decisions and invariants

- Everything is a modular Laravel monolith.
- The Project Compass frozen boundaries remain unchanged.
- The human owner is product authority; ChatGPT is the architecture and decision-synthesis hub.
- Linear is the primary planning, execution, and review arm, and is authoritative for live issue, project, and workflow state.
- GitHub is authoritative for source, pull requests, CI, commits, merges, and releases.
- Codex is an optional specialist escalation arm for difficult Laravel architecture, security, concurrency, migrations or data integrity, deep audits, repeated failures, and independent high-risk review.
- Unresolved product semantics require human approval before dependent implementation.

## Scope

### Included

- Durable product destination and vocabulary.
- Verified present-state summary and corrected audit blockers.
- Frozen architecture boundaries and unresolved decisions.
- Development-circuit authority boundaries and handoff format.

### Excluded

- Application implementation or correction.
- PHP, Blade, JavaScript, CSS, migrations, configuration, tests, dependencies, and generated assets.
- Database reads or writes and migrations.

## Files and systems examined

- `AGENTS.md`, `CLAUDE.md`, and the Laravel skeleton `README.md`.
- Markdown reports under `Development-CodexReports/`.
- Documentation and architecture/workflow filename inventory.
- Current branches and recent Git history.

## Changes made

- Added the Project Compass, Development Circuit, handoff convention, and this bootstrap record.
- Renamed this bootstrap file from `BOOTSTRAP-001-codex-to-claude.md` to remove a stale arm-to-arm workflow convention.
- Updated the Development Circuit and handoff convention to establish the Linear-first operating model.

## Commands and validation

- Documentation inspection and internal-path review completed.
- No application tests, static analysis, formatter, build, migrations, database queries, or dependency commands were run because this task changes documentation only.

## Risks and unresolved questions

- Product and architecture decisions listed in the Project Compass remain unresolved until the human owner decides them.
- Linear project and issue identifiers do not yet exist.

## Repository linkage

- **Branch:** `docs/linear-first-development-circuit`
- **Commit(s):** Pending
- **Pull request:** Pending
- **Linear issue:** Not created

## Review findings

The prior expectation of mandatory Claude review is retired. Review depth is now risk-based, and Codex is used only as an explicit specialist escalation.

## Final outcome

This bootstrap record preserves the project-foundation history without establishing a permanent Codex-to-Claude workflow.

## Prompt for next step

```text
TASK: Continue accepted work through Linear.
RESPONSIBLE ARM OR DECISION GATE: Linear, with human owner + ChatGPT only at the defined decision gates.
AUTHORITATIVE INPUTS: docs/PROJECT_COMPASS.md; docs/DEVELOPMENT_CIRCUIT.md; the relevant Linear issue; GitHub source and CI evidence.
CURRENT VERIFIED STATE: The repository has a durable product compass and a Linear-first development circuit.
ACCEPTED DECISIONS: Preserve frozen architecture boundaries. Keep GitHub authoritative for source and validation, and Linear authoritative for live workflow state.
OBJECTIVE: Deliver the accepted issue scope without unrelated changes.
INCLUDED: Only the accepted issue scope.
EXCLUDED: Unapproved product semantics, frozen-boundary changes, and unrelated work.
ACCEPTANCE CRITERIA: The Linear issue acceptance criteria and required validation are satisfied.
REQUIRED TESTS: Those stated in the Linear issue; report exact results.
RISKS: Unresolved semantics, frozen-boundary changes, destructive operations, incomplete validation, or materially different design choices.
REQUIRED OUTPUT: Update the Linear issue and its living handoff with GitHub evidence and the final outcome.
STOP CONDITIONS: Escalate to the human owner + ChatGPT decision hub for any listed risk or decision gate.
```
