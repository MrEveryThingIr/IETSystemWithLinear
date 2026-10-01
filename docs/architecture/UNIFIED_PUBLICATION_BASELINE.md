# Unified Publication Baseline

`/dashboard` is the one authenticated home.

The existing header, Flux sidebar, account menu, locale control and Today page remain the shell.

Publication occurs at complete-workflow level:

- Profile includes contacts/addresses and professions.
- Businesses includes create/edit, contacts/locations, team and team professions.
- Deals includes relationships, proposals, contracts and commitments.
- Real Estate includes office case management and private case media.

Dependencies are transitive and server-enforced.

Examples:

```text
Real Estate -> Businesses -> Profile
Deals -> Market -> Profile
Deals -> Personal money -> Profile
```

An active, verified super-admin sees every available first-level facility, receives all platform capabilities, and never needs publication grants. Domain policies and record invariants remain authoritative; there is no global Laravel Gate/Policy bypass.

Normal users always retain Today. Other mapped facilities appear only when published.

Publication management is part of the same application shell at:

```text
/platform/publication
```

`/workspace` is compatibility-only and redirects to `/dashboard`.

The former release-profile-specific sidebar forks are removed from first-level navigation. Publication now has one source of truth:

```text
FeatureSurfaceRegistry
 -> UnifiedNavigation
 -> components/app/sidebar.blade.php
```
