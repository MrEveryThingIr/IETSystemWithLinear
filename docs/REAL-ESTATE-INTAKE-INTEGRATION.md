# Real Estate Intake integration boundary

This branch intentionally restores Public Real Estate Intake as a first-class,
independently publishable application surface while preserving the stronger
domain work added after `feat/public-real-estate-intake`.

## Product boundary

Real Estate Intake is **not** a submenu or compatibility alias of Business.

It is a focused intake/office system:

```
super-admin publication
        ↓
Real Estate Intake workspace
        ↓
authorized office portal(s)
        ↓
public token intake → private case office
        ↓
optional Business bridge
        ↓
Business Listing / catalog / market
```

The Business bridge is optional. A case remains an authoritative Real Estate
Intake case even when it is later promoted into a Business Listing.

## Publication and authorization

Two independent checks are intentional:

1. **Feature publication** — super-admin decides whether an authenticated user
   can discover/use the Real Estate Intake application surface.
2. **Portal/domain authorization** — after the surface is published, existing
   portal grants, linked Business permissions, or super-admin status decide
   which specific office portal and private cases the user may access.

A portal grant therefore cannot bypass feature publication.

The public intake form remains intentionally different: an active portal's
unguessable public token may be opened by a guest and submitted under the
existing throttling/privacy rules.

Publication Control itself remains a super-admin-only administrative surface.
Ordinary users neither receive it through publication grants nor see it in
navigation, and direct access is rejected.

## Experience choice

The public intake form and one-time preview from
`feat/public-real-estate-intake` were already preserved unchanged in the
latest branch.

This integration restores the preferred independent office/case experience:

- dedicated Real Estate workspace;
- spacious standalone office and case screens;
- clear route back to the main application;
- current locale-aware date presentation;
- current media handling;
- optional Business adoption/promotion controls without making Business the
  parent identity of the Real Estate system.

## Acceptance checklist

- Super-admin sees **Publication control**; an ordinary account does not.
- Super-admin can grant **Real Estate Intake** without granting **Business**.
- A user with only a portal grant gets 403 on Real Estate office routes.
- A user with both Real Estate publication and portal authorization can open
  the dedicated Real Estate workspace and that office.
- Real Estate appears as its own primary destination, not inside Business.
- Public token intake still works for an active portal without login.
- Suspended/unverified accounts are blocked from office administration.
- Existing optional Business adoption and case-to-listing promotion still work.
