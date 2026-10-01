# M2 / Unified Publication Audit — 2026-09-30

## Scope and conclusion

Audited and corrected the local checkout at `C:\laragon\www\EveryThing` against
`https://github.com/MrEveryThingIr/IETSystemWithLinear.git`.

The candidate is now **automated-gate clean but not yet publication-ready**.
Application defects found during this audit were corrected. The remaining gates
are release-process gates: preserve the local work in reviewed commits, run remote
CI on the exact pushed SHA, complete owner browser acceptance, reconcile the
selective-assembly admission record, and collect production operational evidence.

No commit or push was made.

## Exact Git state

- Local branch: `feat/m2-business-foundation`
- Local HEAD: `029cd97b5dfa794ad982ff9623065bd20818afe4`
- Remote `feat/m1-contact-address-foundation`: `029cd97b5dfa794ad982ff9623065bd20818afe4`
- Remote `main`: `e6d8c1981f2ddacc8de5cd54ce3338a22ba15f86`
- Remote `feat/m2-business-foundation`: absent in the locally fetched refs
- Working tree after the final audit: 45 tracked modified paths and 42 untracked
  paths, including this report

The implementation is local-only and does not yet have a remote recovery point.

## Defects corrected

1. **Inactive accounts could retain feature or root access.** Feature-surface and
   platform-root checks now require an active, verified User. M1/M2/workspace route
   groups consistently enforce `auth`, `account.active`, and `verified`.

2. **Publication accepted internal/arbitrary keys.** Grantable surfaces are now
   explicit and validated at both controller and service boundaries. Dependency
   closure remains server-enforced.

3. **The route/surface audit misclassified intentional routes.** Public,
   framework, core-shell, and domain-authorized routes are distinguished from
   unexplained mappings. Strict-mode audit now reports no duplicates or gaps.

4. **M1/M2 domain identity was attached directly to User.** Contact points,
   addresses, professions, Business ownership, and Business membership now bind
   to Actor. Authentication identity remains User. Controllers, services, access
   rules, models, views, and tests were updated together.

5. **Platform root authority had two registries.** The draft
   `platform_super_admins` model/table/provider path was removed.
   `platform_access_grants` is the sole auditable authority. Bootstrap, revoke,
   audit, publication bypass, and capability checks use it consistently.

6. **Root authority risked becoming domain-record bypass.** Super-admin bypass is
   limited to publication visibility and platform capabilities; Business and
   other record policies still require domain authority.

7. **Existing draft data needed a safe forward path.** Compatibility migrations
   consolidate old root rows into audited platform grants and translate legacy
   User-owned M1/M2 data to the User's Actor. They fail explicitly on missing Actor
   links, unsupported owner types, or identity collisions rather than guessing.

8. **The Business detail view contained malformed literal markup.** The stray
   fragments were removed and owner/member rendering now follows Actor-to-User
   presentation safely.

9. **Architecture notes contradicted strict enforcement.** Documentation now
   identifies strict publication as the final baseline and
   `platform_access_grants` as the only root registry.

10. **One-off generated root shell scripts were a commit hazard.** They remain on
    disk for recovery, but `/iet-*.sh` is ignored so stale code generators cannot
    be accidentally included in the release candidate.

11. **Static-analysis and formatting defects in the wider local candidate were
    corrected.** Eloquent relation typing, command iteration, public-intake model
    construction, and dirty-file formatting now pass their gates.

## Local database alignment

The configured environment is local Laravel 13.33.0 / PHP 8.4.12 using MySQL.
Read-only preflight found the expected draft schema:

- one legacy root row;
- one User-owned Business and one User-owned Business membership;
- two User-owned contact points and one User-owned address;
- no missing User-to-Actor links and no contact identity collisions.

The two pending compatibility migrations were then applied successfully. Final
aggregate verification showed:

- `platform_super_admins` removed;
- two effective active super-admin grants in the canonical grant table;
- Actor foreign keys present for Business ownership, membership, and professions;
- zero User-owned contact/address rows;
- two Actor-owned contact rows and one Actor-owned address row.

No record contents were printed into this report.

## Final verification evidence

- Focused authority/profile/business regression: 31 tests / 106 assertions, green.
- Full PHPUnit suite: 679 tests / 4,803 assertions, green (`310973 ms`).
- PHPStan level 5: 0 errors.
- Pint repository-wide formatting gate: passed.
- Explicit Blade compilation (`view:cache`): passed.
- `git diff --check`: passed; three existing Blade paths emit only line-ending
  normalization warnings.
- Vite 8.3.0 production build: passed.
- npm audit: 0 vulnerabilities.
- Composer audit: no security vulnerability advisories.
- Surface audit: strict mode, 20 available surfaces, no duplicate method/URI
  signatures, no unexplained unmapped named routes.
- Super-admin invariant audit: passed with two effective active grants.
- Disposable SQLite: full fresh migration, rollback of the final two migrations,
  and reapply all passed.
- Configured local MySQL: legacy-data preflight, both compatibility migrations,
  and post-migration aggregate verification passed.
- Post-format affected-area regression: 5 tests / 11 assertions, green; PHPStan
  remained at 0 errors.

Vite emitted one non-blocking advisory: optimized font fallbacks can use the
optional `fontaine` package. No dependency was added solely for that optimization.

## Remaining publication gates

### 1. Preserve and review the local candidate

The candidate has not been committed or pushed. Review the 45 modified and 42
untracked paths, split them into coherent commits, push the M2 branch, and run
remote CI on the exact candidate SHA. Until then, the work is not remotely
recoverable or reviewable.

### 2. Reconcile selective-assembly governance

`AGENTS.md`, `docs/CURRENT_STATE.md`, and the acceptance worksheet still record the
M00/S0 owner browser gate as blocking M1 admission. This audit does not silently
rewrite that historical authority or claim owner acceptance. The owner must decide
whether this M1/M2 candidate is admitted on the current line and update the
canonical state as part of that decision.

### 3. Complete browser acceptance

Owner browser acceptance has not occurred for this exact candidate. Exercise
normal, empty, invalid, and unauthorized journeys; reload persistence; desktop and
mobile layouts; keyboard/focus behavior; and English/Persian/Arabic/Chinese plus
RTL presentation for the affected surfaces.

### 4. Complete production operational evidence

Before publishing, run the repository's production-equivalent MySQL concurrency
and idempotency gate, queue/scheduler supervision smoke, backup/restore drill,
mail/realtime environment verification, secrets/config review, and deployment plus
rollback rehearsal. The local MySQL data migration passed, but it is not a
substitute for those operational gates.

## Readiness state

The application candidate is internally coherent across User/Actor identity,
platform authority, publication, and domain authorization, and all local automated
checks run in this audit are green. It should proceed to commit review and owner
browser/operations acceptance; it should **not** be published directly from the
current dirty, local-only checkout.
