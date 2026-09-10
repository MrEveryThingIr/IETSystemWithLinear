# BOOTSTRAP-003 — English and Arabic localization infrastructure

## Task state

- **Status:** Ready to merge
- **Responsible arm:** Codex escalation arm; human owner and ChatGPT decision hub for final review
- **Next step:** Review and commit this verified localization foundation before beginning Group Spaces and Content
- **Risk:** Medium; request middleware, persisted account preferences, bidirectional UI, validation, and notifications

## Objective

Establish a production-oriented multilingual foundation initialized with English and Arabic, with safe runtime selection and a low-friction path for adding Chinese or another locale without changing routes or database structure.

## Authoritative inputs

1. Human request for a professional, extensible international multilingual system.
2. `docs/PROJECT_COMPASS.md` and `docs/DEVELOPMENT_CIRCUIT.md`.
3. Repository instructions, installed Laravel 13/Livewire 4/Flux 2 versions, existing application conventions, and current tests.

## Accepted decisions and invariants

- Locale identifiers remain outside URLs so invitations and signed links are language-neutral and stable.
- An authenticated user's persisted locale has priority, followed by the session, browser language negotiation, application locale, and fallback locale.
- Only explicitly configured locale codes may be selected.
- English is the fallback language; Arabic is the initial right-to-left language.
- User-authored names, descriptions, agreement text, and notes are preserved rather than translated and use automatic text direction where appropriate.
- Adding a language consists of registering metadata in `config/localization.php` and adding its catalogs; no route or schema change is required.

## Scope

### Included

- Locale configuration, selection middleware, public switch endpoint, reusable language menu, and response language header.
- Persisted per-user locale plus guest session and `Accept-Language` negotiation.
- English and Arabic framework validation/auth/password/pagination catalogs and application UI catalogs.
- LTR/RTL document direction and bidirectional-safe content fields.
- Localized authentication, dashboard, invitation, admission, agreement, group, actor, navigation, status, feedback, and starter-page interfaces.
- Recipient locale support for framework notifications and Arabic verification/password-reset email copy.
- Automated locale priority, allow-list, extensibility, catalog-parity, RTL, and notification tests.

### Excluded

- Automatic translation of user-authored content.
- Locale-prefixed routes or translated slugs.
- A translation-management service or machine-translation dependency.
- Localized application branding and a redesign of the starter landing page.
- Group Spaces and Content implementation.

## Files and systems examined

- Laravel middleware bootstrap, routes, authentication actions, notification locale contract, and user persistence.
- Application layouts, shared navigation, Livewire authentication and domain screens, feedback messages, and date rendering.
- Current migrations, user model invariants, CSS direction behavior, translation loading, and test conventions.

## Changes made

- Added `config/localization.php` and `App\Support\Localization` as the locale registry and direction authority.
- Added `SetLocale` to the web middleware stack with deterministic preference resolution and `Content-Language` responses.
- Added the CSRF-protected `locale.update` route/controller and a shared language switcher across public, authentication, invitation, and authenticated layouts.
- Added nullable `users.locale`, persisted locale during invited registration and later switching, and implemented Laravel's `HasLocalePreference` contract.
- Added English and Arabic catalogs, including validation and framework notification copy.
- Applied RTL at the document root, added Arabic-capable font fallbacks, and protected email/URL/telephone fields plus user-authored content direction.
- Replaced hard-coded interface and workflow feedback text across the current product surface with translation keys.
- Added translation-key parity and end-to-end localization coverage, and updated user-schema compatibility assertions.

## Commands and validation

- `php artisan migrate --no-interaction` — passed; `2026_09_10_204103_add_locale_to_users_table` applied in batch 3.
- `php artisan test --compact --do-not-cache-result` with an isolated compiled-view path — passed: 107 tests, 504 assertions.
- Focused localization and user-model suite — passed: 22 tests, 62 assertions.
- `vendor/bin/phpstan analyse --no-progress` — passed with 0 errors.
- `vendor/bin/pint --dirty --format agent` — passed.
- `php artisan view:cache` with an isolated compiled-view path — passed.
- `npm.cmd run build` — passed with Vite 8.2.2.
- `php artisan route:list --name=locale --except-vendor` — confirmed `POST locale` as `locale.update`.

Non-blocking environment note: the normal Windows compiled-view and PHPUnit result-cache locations are held by another process or ACL. Validation used fresh isolated compiled-view directories and `--do-not-cache-result`. Vite emitted the existing optional Fontaine optimization notice.

## Risks and unresolved questions

- Arabic copy should receive native-speaker product review before a public release.
- Adding Chinese still requires complete `zh_CN` catalogs; the automated test proves the configured locale works and safely falls back to English while catalogs are incomplete.
- Real-browser visual regression automation was not run; server rendering, Blade compilation, RTL markers, and production assets are verified.
- User-authored content is not translated by design.

## Repository linkage

- **Branch:** `FIX_BY_VSCODE_AGENT`
- **Commit(s):** Pending for this localization set; current HEAD before commit is `822c1c9`
- **Pull request:** Not created
- **Linear issue:** Not created

## Review findings

The project previously had no request locale negotiation, saved account language, safe locale allow-list, translation catalogs, RTL document behavior, localized validation/notifications, or permanent language switcher. Current domain screens also embedded English feedback and labels directly. These gaps are addressed without changing invitation URLs or domain identifiers.

## Final outcome

The application now has a verified English/Arabic localization foundation that is extensible through configuration and catalogs. It is ready for human/native-language review and merge, after which Group Spaces and Content can be developed on top of locale-aware layouts and workflows.

## Prompt for next step

```text
TASK: Define and implement the first locale-aware Group Spaces and Content milestone.
RESPONSIBLE ARM OR DECISION GATE: Human owner + ChatGPT decision hub for semantics; Linear for accepted implementation work.
AUTHORITATIVE INPUTS: docs/PROJECT_COMPASS.md; docs/DEVELOPMENT_CIRCUIT.md; docs/handoffs/BOOTSTRAP-002-invitation-admission-agreements.md; docs/handoffs/BOOTSTRAP-003-localization-infrastructure.md; merged GitHub source and CI evidence.
CURRENT VERIFIED STATE: Invitation/admission/agreement workflows and English/Arabic localization infrastructure pass 107 tests with 504 assertions.
ACCEPTED DECISIONS: Locale-neutral URLs; persisted user locale; English fallback; Arabic RTL; user-authored content retains its original language and automatic direction.
OBJECTIVE: Deliver the smallest complete Group Space and authored Content flow in both English and Arabic UI.
INCLUDED: Freeze Space visibility and creation authority; Content types and lifecycle; authorship; revision; moderation; membership access; translated system UI.
EXCLUDED: Automatic translation of user content, Contract, commerce, ledger, planner, reputation, and generic low-code builders.
ACCEPTANCE CRITERIA: Must be frozen in Linear before implementation.
REQUIRED TESTS: Authorization matrix, tenant isolation, lifecycle, rendering/escaping, locale parity, RTL rendering, query behavior, and complete author/reader journeys.
RISKS: Unresolved visibility, moderation, ownership, revision, deletion, media, and content-type semantics.
REQUIRED OUTPUT: Accepted Linear issue, implementation, GitHub review evidence, and an updated living handoff.
STOP CONDITIONS: Stop for unresolved product semantics, frozen-boundary changes, destructive operations, or incomplete validation.
```
