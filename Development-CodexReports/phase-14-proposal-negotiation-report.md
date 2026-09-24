# Phase 14 Closure — Proposal + Negotiation

## Result

Phase 14 runtime is remotely complete.

- Branch: `feat/ideal-v1-14-proposal-negotiation`
- Baseline: `e3bf30dfd344939bd6d3ca939f5149133bff6f7a`
- Kernel: `3a30f21af06e478fc269d7db1e4085ce73500133` / CI `36047890895` — 474 tests / 2771 assertions
- Runtime/UI: `a27a2538161ff36d123eef1bd0f9d9c153298987` / CI `36048778108` — 477 tests / 2799 assertions
- Documentation closure: pending closure CI
- Local/browser/mobile/RTL/accessibility acceptance: deferred cumulatively

Proposal decisions are immutable and scoped to one exact ProposalVersion. A later version requires fresh decisions. The proposer accepts only the version they publish; every other required party must respond again.

Each ProposalVersion points to one sealed Content revision in a separate Negotiation Context. Active Relationship provenance may seed a Proposal, but Relationship and Proposal lifecycles remain independent.

Negotiation Conversation is discussion evidence only. Proposal Events project into the unified Timeline. Terminal Proposal states make the Negotiation Context read-only.

Accepted Proposal state creates no Contract, Commitment, Fulfillment, ownership, Financial Obligation, Accounting posting, Settlement or payment authority.

Next: Phase 15 — Contract, ContractVersion and explicit acceptance.
