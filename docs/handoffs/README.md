# Task Handoffs

This directory contains the durable cross-session record for meaningful Everything tasks. Read the [Project Compass](../PROJECT_COMPASS.md) for product and architecture authority and the [Development Circuit](../DEVELOPMENT_CIRCUIT.md) for the collaboration workflow.

## One living file per task

- Create one handoff when a meaningful task begins, or update the existing handoff when continuing it.
- Do not create a new report for every review or correction loop.
- Before Linear identifiers exist, use stable `BOOTSTRAP-NNN-<slug>.md` names.
- After Linear exists, prefer `<LINEAR-ID>-<short-task-name>.md`.
- Historical milestone reports under `Development-CodexReports/` remain evidence; do not copy them here or rewrite them as current state.

## Required sections

Every handoff records:

1. Task identifier and title.
2. Current workflow status.
3. Assigned and next arm.
4. Objective.
5. Authoritative inputs.
6. Accepted decisions and invariants.
7. Included and excluded scope.
8. Files and systems examined.
9. Changes made.
10. Commands and tests actually run, with exact results.
11. Risks and unresolved questions.
12. Branch, commits, pull request, and Linear issue.
13. Reviewer findings.
14. Final outcome.
15. Prompt for next arm.

Use `Not created`, `Not run`, `Pending`, or `Not applicable` instead of omitting a field or implying completion.

## Source discipline

- Record durable conclusions rather than chat transcripts.
- Link to repository files, commits, pull requests, issues, and architecture decisions where possible.
- Never include credentials, environment values, tokens, signed URLs, recipient data, or copied email contents.
- Preserve exact failures and uncertainties without overwhelming the report with raw output.
- Linear is the live workflow authority; GitHub is the code and validation authority; the handoff connects them.
- If the handoff conflicts with the Project Compass, an accepted decision, or its Linear issue, stop and request human resolution.

The initial example and current bootstrap handoff is [BOOTSTRAP-001](BOOTSTRAP-001-codex-to-claude.md).
