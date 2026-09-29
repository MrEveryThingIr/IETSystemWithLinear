# Browser-Gated Selective Assembly — Process Revision

## Status

**Accepted and merged.**

## Owner direction

The project is being recreated through selective reassembly of already-developed modules, not rebuilt from scratch.

Each module must be:

1. independently inspected;
2. compared with available later/candidate implementations;
3. improved where justified;
4. remotely validated;
5. inspected by the owner in the browser before final admission;
6. corrected with regression coverage when defects are found;
7. merged into the assembly only after browser acceptance;
8. recorded as a frozen checkpoint before the next dependent module begins.

## Why this revision matters

The previous continuous-remote rule allowed browser acceptance to be deferred until the end. That is no longer the desired assembly method.

The new method reduces cumulative ambiguity: when a later module is connected, every earlier accepted layer already has independent behavioral/browser evidence.

## Preserving planning and development reasoning

Every module report is a living record. It keeps both:

- the **pre-implementation plan** and candidate analysis;
- the **post-implementation reality**, including changed decisions, defects, fixes, evidence and next wiring.

Agents must append or clearly revise sections rather than replacing the original plan with a hindsight-only summary.

## Current position

- Selective assembly branch: `codex/ideal-v1-selective-assembly`.
- S0 remote baseline: certified.
- Certified S0 runtime/checkpoint: `2b89e301ef7f167083445cd305847f83dbf3e048`.
- Post-merge S0 CI: `36236888120` — success.
- S0 documentation closure later advanced the assembly documentation head without changing product runtime behavior.
- New rule: complete the S0 browser baseline smoke before implementing M1.

## Next module after the browser gate

M1 — Access, identity, registration provisioning, personal wallet/default monetary unit.

M1 will remain on its review branch through local/browser acceptance. It is not merged merely because CI is green.

## Durable authority

See `docs/SELECTIVE_ASSEMBLY_ROADMAP.md`.


## Closure evidence

- Governance review branch: `codex/selective-assembly-browser-gates`.
- Pull request: **#32 — Assembly: require browser acceptance per module**.
- Reviewed head: `774212e0733142d65e1369ae9cc780744cc6e95c`.
- PR-context CI: `36238090488` — success.
- Assembly merge SHA: `30dc939754c3fec7009250a61437db2633aabea7`.
- Post-merge assembly CI: `36238246665` — success.
- Runtime behavior changed by this process revision: **none**.
- Current required gate: M0/S0 owner browser baseline acceptance.
- M1 implementation status: **blocked until M0 browser acceptance is explicitly recorded**.
