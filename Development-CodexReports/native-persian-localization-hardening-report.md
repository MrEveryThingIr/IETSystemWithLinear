# Native Persian Localization Hardening Report

## Objective

Make the publishable Ideal-v1 Persian interface feel coherent, natural and understandable to ordinary Persian users while preserving exact domain semantics.

This was not treated as a word-for-word translation task. The review focused on missing coverage, English fallbacks, literal phrasing, repeated terminology, implementation jargon and consistency across end-to-end user journeys.

## Defects found

The audit found three classes of localization defects:

1. **Missing Persian modules** — 11 complete English locale modules had no Persian counterpart:
   - Accounting
   - Collaboration
   - Commitments
   - Community
   - Contracts
   - Financial
   - Journeys
   - Library
   - Planner
   - Proposals
   - Relationships

2. **False Persian coverage** — `lang/fa/access.php` and `lang/fa/intents.php` simply loaded the English locale files.

3. **Mechanical/mixed-language wording** — existing Persian copy exposed or literally translated recurring implementation terms such as Actor, Context, Blueprint, Studio, Definition, General, Reader, block/revision/annotation terminology and finance/workflow phrases in ways that were technically understandable but unnatural for normal users.

## Changes

The hardening pass:

- added native Persian copy for all 11 missing modules;
- replaced the Access and Intent English passthroughs with complete Persian translations;
- rewrote the main release journeys in simple Persian:
  - access invitation / onboarding;
  - profile Needs & Offers;
  - discovery and matching;
  - Relationships;
  - Proposal negotiation;
  - Contracts;
  - Commitments and Fulfillment;
  - Planner;
  - Financial Obligations, Settlement and personal Accounting;
  - Group Community;
  - Content Library and placement;
  - Collaboration/Timeline;
  - structured submissions/evaluations;
  - Notifications;
- humanized shared UI vocabulary so repeated English implementation nouns no longer leak into Persian surfaces when a clear Persian term exists;
- simplified advanced Content/Reader/Studio/Media/Block/Structure wording;
- removed stale Persian-only Home keys that no longer had an English/source-code counterpart;
- preserved useful Persian-specific Laravel validation attribute aliases;
- added `docs/LOCALIZATION_FA_GUIDE.md` as the canonical UI-language style/glossary reference.

## Regression protection

Added `Tests\Feature\LocalizationParityTest`:

- Persian and English must have the same locale-file set;
- every real English translation leaf must exist in Persian;
- Persian locale files may not import/load English locale files;
- locale-specific Persian validation aliases/customizations remain allowed.

This converts missing Persian coverage from a browser surprise into a CI failure.

## Validation evidence

Localization feature head:

`1a6fc7831b11588ef494fa8b36b3c2e465223bd5`

Feature-branch CI:

`36160123238` — green.

PR #25 CI:

`36160463461` — green.

Integrated localization commit:

`edff668b6e8ad6c4a58a7d6c08bb54a821ce9662`

Post-merge integration CI:

`36160798652` — green.

Validated result:

- **545 tests / 3376 assertions**;
- MySQL 8.4 full migration chain green;
- SQLite rollback/reapply and backup/restore green;
- Pint green;
- PHPStan green;
- Vite production build green;
- npm audit green;
- Composer audit: no security advisories.

## Release-candidate progression

`release/ideal-v1-rc-4` is the immutable localization code checkpoint at the integrated localization SHA.

After canonical documentation synchronization, `release/ideal-v1-rc-5` is the candidate that should be used for the owner's cumulative local/browser acceptance.

Old RC branches remain immutable.

## Remaining human acceptance boundary

Automated tests can guarantee coverage and prevent English passthroughs, but they cannot prove that every phrase feels perfect in its rendered page.

During owner-local acceptance, Persian should be tested as a first-class path:

- switch to Persian before running the main journey;
- inspect RTL layout and alignment;
- read labels, empty states, validation errors, confirmations and notifications in context;
- flag any phrase that sounds formal, literal, ambiguous or disconnected from everyday Persian;
- convert each finding into a regression or targeted localization correction before a stable tag.

The System Manual/document-content translation remains a separate versioned-content milestone. This UI hardening does not claim the full manual is natively translated.
