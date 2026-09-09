# Task Handoffs

This directory contains the durable cross-session record for meaningful Everything tasks. Read the [Project Compass](../PROJECT_COMPASS.md) for product and architecture authority and the [Development Circuit](../DEVELOPMENT_CIRCUIT.md) for the collaboration workflow.

## One living file per task

- Create one handoff when a meaningful task begins, or update the existing handoff when continuing it.
- Handoffs primarily connect Linear with the human owner and ChatGPT decision hub; GitHub links provide source and validation evidence.
- Do not create a new report for every review or correction loop.
- Before Linear identifiers exist, use stable neutral `BOOTSTRAP-NNN-<slug>.md` names.
- After Linear exists, prefer `<LINEAR-ID>-<short-task-name>.md`.
- Historical milestone reports under `Development-CodexReports/` remain evidence; do not copy them here or rewrite them as current state.

## Required sections

Every handoff records:

1. Task identifier and title.
2. Current workflow status.
3. Responsible arm or decision gate, and next step.
4. Objective.
5. Authoritative inputs.
6. Accepted decisions and invariants.
7. Included and excluded scope.
8. Files and systems examined.
9. Changes made.
10. Commands and tests actually run, with exact results.
11. Risks and unresolved questions.
12. Branch, commits, pull request, and Linear issue.
13. Review findings.
14. Final outcome.
15. Prompt for next step.

Use `Not created`, `Not run`, `Pending`, or `Not applicable` instead of omitting a field or implying completion.

## Source discipline

- Record durable conclusions rather than chat transcripts.
- Link to repository files, commits, pull requests, issues, and architecture decisions where possible.
- Never include credentials, environment values, tokens, signed URLs, recipient data, or copied email contents.
- Preserve exact failures and uncertainties without overwhelming the report with raw output.
- Linear is the live planning and workflow authority; GitHub is the source and validation authority; the human owner is product authority; ChatGPT synthesizes architecture and decisions.
- Ordinary accepted work may continue through routine steps without a human interruption. Escalate to the human owner and ChatGPT decision hub for unresolved product semantics, possible frozen-boundary changes, destructive or irreversible operations, incomplete validation, or materially different design choices.
- Codex is an optional specialist escalation arm, not a required handoff destination.
- If the handoff conflicts with the Project Compass, an accepted decision, or its Linear issue, stop and request human resolution.

The initial example and current bootstrap handoff is [BOOTSTRAP-001](BOOTSTRAP-001-project-foundation.md).
