# Configurable Form and Field Extensions — Design Proposal

## Status and scope

**Proposal only; not an accepted ADR or implemented feature.** This document records a post-publishable-v1 design and a candidate milestone sequence. No schema, UI, validation, permissions, financial behavior, or Plan-start gate described below exists merely because it is documented here. The current release remains the bounded Phase 1–22 publishable-v1 candidate; do not add form-building to its release gate. See `docs/CURRENT_STATE.md`, `docs/PRODUCTION_ROADMAP.md`, and `docs/PUBLISHABLE_V1_RELEASE_GATE.md` for implementation and release truth.

Existing building blocks are versioned Content Definitions/Blueprints, InteractionDefinitions with Submissions/Responses, DomainBlueprintVersions with exact provenance, and the Phase 12 Plan/ScheduleRule/Occurrence lifecycle. They are **not** a general user-customizable form extension system for Plan or other domain objects. This proposal composes those patterns without claiming that a Content field, questionnaire, or Domain Blueprint already extends a Plan's authoritative fields.

## Problem and design boundary

People should be able to tailor the questions shown when creating or carrying out a Plan, and eventually other domain forms, without schema-per-user migrations or a generic JSON replacement for domain models. A field extension describes additional user input; the owning domain still decides what is valid and what each answer means.

- Keep Plan identity, Context, participants, recurrence/timezone, scheduling, lifecycle and Occurrence execution in Planner Actions and records. A form answer cannot start, complete, skip, or cancel an Occurrence by itself.
- Keep Content/Asset references and sealed revisions in their existing evidence domains; a form may reference them, not copy or mutate historical evidence.
- Use InteractionDefinition/Submission/Response for independently submitted/reviewed questionnaires; do not create a second survey engine in Planner. Reuse the version/evidence approach where suitable, not an unconditional dependency that makes every Plan a Submission.
- Keep DomainBlueprintVersion a guided-entry recipe. It may suggest an approved form revision, but is not the form runtime, a permission grant, or a Workflow engine.
- Keep ContractVersion, Commitment, Fulfillment, FinancialObligation, Settlement and JournalEntry authoritative in their own domains. Neither a form answer nor a comparison chart creates those facts.

## Proposed extension contract

Candidate model names below are **conceptual**, subject to the first implementation milestone and review; they are not statements about existing tables.

1. **Form identity and scope.** A stable FormDesign is owned by a Context or other explicitly authorized domain scope, declares a supported target kind (initially `plan`; other kinds only after a second proof), purpose (`create`, `before_start`, `execution_review` where supported), lifecycle, author and provenance. A binding names a specific target/surface and form version, not a global mutation of all Plans. Cross-Context reuse requires an explicit authorized copy/reference policy.
2. **Immutable versions.** FormDesignVersion stores a canonical normalized field manifest/hash, field identities (stable keys across compatible revisions), ordering, localized labels/help, defaults, validation rules, visibility rules and publication provenance. Draft edits may change; publishing freezes a version. Changing a label, required flag, option, unit, conditional rule or field type publishes a new version. No silent upgrades on existing bindings.
3. **Constrained field registry.** Start from approved primitives such as short/long text, bounded number/decimal, date/time, boolean, select, checklist item and authorized Asset/Content reference. Type-specific options, sizes, precision, date/timezone and choice limits are server-validated. Conditional display/required rules may reference only prior permitted fields through a bounded declarative grammar; no executable PHP, Blade, JavaScript, SQL or CSS in configuration. Reject cycles, unknown fields, unsupported types and expensive nesting.
4. **Answers and historical interpretation.** A saved answer binds target ID, form version/hash, logical field key/type, respondent Actor/acting User, timestamp and target lifecycle point. Validate on the server against the **pinned** version and the domain Action, not today's designer draft. Preserve submitted answers as immutable evidence; correction creates a new revision/event linked to the original, subject to the owning domain's policy. Draft autosave may be mutable but must be clearly distinct from submitted evidence. Archive/retire designs without deleting referenced versions or answers.
5. **Authorization and privacy.** Form authorship, publication, attachment to a domain target, response, review, and visibility are separate policy checks. Context access alone does not authorize Plan mutation or private answer disclosure. Render conditional fields server-side as well as in the UI; do not expose hidden answers by client-side filtering. Limit uploads to the existing authorized Asset pipeline, bound query/payload sizes, rate-limit public-facing submissions where applicable, and retain audit/provenance without secrets in logs.
6. **Presentation.** Mobile-first progressive forms with keyboard/screen-reader labels, error summary and per-field errors, clear optional/required states, RTL/localized layout, and desktop parity. A preview must show the published candidate as the intended respondent would see it under a specified authorization/locale, without generating authoritative answers.

The first implementation must decide how FormDesignVersions reuse existing Content Definition/InteractionDefinition storage and validation. Do not add a competing universal form table until the overlap and migration story are documented against actual code. Do not make arbitrary custom fields first-class searchable domain predicates by default; promote selected, typed projections only when a real query and authorization requirement justifies it.

## Plan-specific extension proof

Pilot one personal Plan and one Relationship/Commitment-linked Plan with the same extension mechanism but different authorized audiences. A Plan may pin one approved design version for each supported purpose. Materialized Occurrences must resolve their effective requirement version deterministically (for example by recording the version at occurrence creation or at an explicitly governed effective-time boundary); choose and test the rule before shipping. A later design revision affects future Plans/Occurrences only after explicit adoption, never already-started or completed execution evidence.

| Concern | Proposed Plan experience | Authority boundary |
| --- | --- | --- |
| Before-start requirements | Ordered user-defined prompts and acknowledgements; optional policy-governed blocking criteria evaluated by the explicit Start Occurrence Action, with clear failure reasons and a correction path. | A checklist tick or free-text declaration is not verified skill, safety compliance, Contract acceptance, or Fulfillment. Do not retroactively prevent an already-started occurrence. |
| Checklists | Reusable definition with stable item IDs, optional/required status and per-Occurrence completion evidence, actor/time and version provenance; distinguish preparation from execution/review. | Ticking a box alone does not Complete Occurrence or accept Fulfillment. |
| Required tools/resources | Typed expectations for tool/Asset/quantity/unit and availability/confirmation at the appropriate Plan or Occurrence point; references to existing authorized resources where supported. | A stated tool need does not reserve inventory, transfer ownership, authorize purchases, or prove actual use. Resource reservation would need its own domain policy/Action. |
| Expected financial values | Optional planned cost/revenue/budget fields with explicit MonetaryUnit, decimal precision, value direction and basis (per occurrence vs total), and documented aggregation semantics. | Estimates and user-entered actuals are planning observations, not Contract rates, recognized obligations, settlements, payments, or Ledger postings. No float arithmetic or cross-currency sum without explicit rate evidence. |
| Planned vs actual | Compare pinned expected values and schedule to actual occurrence times/checklist/resource observations; link to authoritative accepted Fulfillment and financial/ledger records only when access and provenance permit. Label source, unit, period, missing data and discrepancy; never present estimates as earned/paid/outstanding. | Do not duplicate authoritative actual time, accepted work, obligation or payment into a competing editable form field. Derived views must survive a reload and reconcile to source records. |

Keep personal use lightweight: form fields are optional unless a published design/authorized Plan binding requires them. Linked Commitment/Contract requirements cannot be weakened by a user-customized Plan design. If a before-start gate has legal, safety or financial meaning, obtain domain-owner approval and implement a named policy/Action rather than relying on a configurable required checkbox.

## Experimentation and promotion governance

1. **Private draft and preview.** Let the authorized owner design and test a form against synthetic/sample data, with no production mutation or public access. Validate registry compatibility and accessibility before publishing.
2. **Sandboxed pilot.** Opt-in for a narrow Context or selected future Plans only, with explicit version, audience, start/end, revocation/rollback and success criteria. Experiment results are not production authority; simulation/test submissions must be separated and cannot be promoted as live evidence.
3. **Review.** Compare completion, error, abandonment, privacy and support signals plus qualitative feedback. Review field semantics, locale/RTL/mobile behavior, accessibility, authorization, retention, cost/abuse limits, and effects on downstream Plan Actions. Inspect real examples without leaking private responses.
4. **Selective promotion.** An authorized maintainer copies an approved design into a new curated draft/version with source/version provenance and explicit review/approval. Publish only after validation and migration/compatibility checks; adopt for future targets via explicit bindings. Do **not** reinterpret or overwrite previous designs, submissions, responses, or Occurrence history. No automatic live promotion of experimental operational records.
5. **Rollback and audit.** Disable future use/unbind a defective version while retaining historical reads under the original schema and policy. Corrections/new versions have traceable reason, actor, timestamp and lineage. Preserve export/read access for entitled historical participants.

Do not call all user-generated designs trusted templates. Built-in/curated versus private/experimental status must be visibly distinct and must not confer permissions. A promoted design is still configuration; no automated Action execution or hidden transition rules.

## Candidate milestone and explicit backlog

**Release status:** design proposal only; no implementation milestone or release candidate for form extensions is declared complete. Phase 12 Planner and Phase 18 Domain Blueprints are implemented in their documented scope. This work is a post-v1 candidate, not part of Phase 23 Generic Workflow by default and not a reason to move the publishable-v1 gate. Prioritize against existing post-v1 phases after owner acceptance and current release/browser validation.

- [ ] **Design decision:** audit existing Content Definition, InteractionDefinition and DomainBlueprint validators/version bindings; decide reuse vs minimal new storage, field registry, historical answer model, permission matrix and effective-version rule. If a durable cross-domain decision is accepted, record an ADR separately.
- [ ] **Pilot implementation:** one Plan creation extension plus before-start and execution checklists, per-Occurrence pinned version/answers, server-side Action gate, immutable history/correction and scoped policies. No dynamic authority from form wording.
- [ ] **Resources and comparisons:** typed tool/resource expectations and explicit planned monetary values; derived planned-vs-actual view using authoritative Plan/Occurrence and optionally linked Fulfillment/financial sources; missing-value and currency semantics covered by tests.
- [ ] **Safe authoring:** private draft/preview, bounded field/condition registry, localized/mobile/RTL/accessible respondent UI, opt-in selected-Plan binding and explicit version adoption/retirement.
- [ ] **Governance pilot:** experiment lifecycle, permissioned review, curated-copy promotion with lineage, kill switch, retention/privacy review and proof that historical submissions are unchanged.
- [ ] **Second-domain proof:** validate at least one meaningfully unrelated form (for example an Admission requirement or a structured Content interaction) against the same contract before generalizing the abstraction; reuse existing Submission/Response rather than forking it.
- [ ] **Acceptance and rollout:** migration forward/rollback, auth/isolation and tamper tests, recurrence/version-boundary and timezone tests, precision/reconciliation tests, performance/pagination, accessibility/mobile/RTL/browser acceptance, updated System Manual and exact milestone report/CI evidence. Do not mark delivered until these gates pass.

## References

- `docs/PROJECT_COMPASS.md` — domain boundaries and generic-abstraction proof rule.
- `docs/CURRENT_STATE.md` — current Planner, Content and Submission implementation truth.
- `docs/PHASE_12_PERSONAL_ACTIVITY_PLANNER.md` — existing Plan/Occurrence scope.
- `docs/PHASE_18_DOMAIN_BLUEPRINTS.md` — curated journey recipe/version provenance.
- `docs/FINANCIAL_ARCHITECTURE.md` — accounting and experimental value boundaries.
- `docs/ADR-001-identity-authority-and-simulation-boundaries.md` — reviewed copying of forms/checklists as new drafts, never promotion of historical activity.
