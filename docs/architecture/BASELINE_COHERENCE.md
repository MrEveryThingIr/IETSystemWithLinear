# Baseline Coherence Before M3

IET exposes **surfaces**, not the whole platform.

A surface is a user-facing facility such as Contact Center, Business, Real Estate Office, Planner, Market or Finance.

Two different checks remain intentionally separate:

1. **Surface revelation** — should this facility exist in this user's visible application?
2. **Domain authorization** — may this user operate on this particular business, office, group, case, contract, etc.?

A surface grant never replaces existing owner/manager/member, real-estate portal, group or permission rules.

## Dependency closure

A surface may require other surfaces. Example:

```text
business.professions
  -> business.team
      -> business.core
```

When the super-admin selects a dependent feature, the server automatically grants the complete dependency closure. JavaScript merely mirrors that behavior in the UI.

## Enforcement mode

The earlier observe mode was a migration aid and is no longer part of the final
baseline. Publication is now **strict**: direct mapped routes that are not
explicitly revealed are blocked. Review the effective map with:

```bash
php artisan iet:surface-audit
```

The audit reports known surfaces, dependency relationships, duplicate method/URI
signatures and named routes that are not yet mapped. Unmapped routes are reported
rather than guessed into a security policy.

## Navigation baseline

Authenticated M1/M2/Real-Estate private pages receive a common top chrome with:

- Workspace home
- explicit Back
- contextual title
- Cancel on abandonable creation flows

The public Real Estate QR page deliberately remains isolated and does not reveal authenticated platform navigation.
