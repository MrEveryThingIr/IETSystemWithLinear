# Real Estate Intake integration boundary

Real Estate is a specialized **Business vertical**. It keeps its focused intake
and case-management workflow, but it is not a separate top-level product or
navigation destination.

## Product boundary

The canonical mental model is:

```
Businesses
    ↓
Business: مشاور املاک مهوری
    ↓
Real Estate intake channel
    ↓
public token intake → private case office
    ↓
qualified property offer
    ↓
Business Listing / catalog / market
```

The Business is the durable identity. Its members, contacts/clients, catalog,
work context, Deals, Planner links, and financial context stay on the shared
Business foundation. Real Estate contributes specialized fields, intake,
case review, private media, and property promotion.

## Publication and authorization

Private Real Estate operations use two layers:

1. **Business publication** — super-admin decides whether the account can use
   the Businesses facility.
2. **Domain authorization** — Business ownership/membership or the legacy
   portal grant determines which exact Real Estate office/cases are accessible.

A legacy portal grant cannot bypass Business publication.

The public presentation is intentionally separate from the internal platform:

- an active **public** Business has a standalone guest website at `/b/{slug}`;
- that website contains only the Business identity, its public contacts/locations,
  published public Listings, and any active specialized intake action;
- it does not expose IET navigation, Planner, Deals, Money, administration, or
  other platform concepts to the visitor;
- a linked Real Estate intake token is guest-reachable only while its owning
  Business is both active and public;
- a private or paused Business does not advertise the website/intake and those
  public routes return 404;
- intake submission keeps the existing throttling/privacy rules and exact
  contact/address information stays private to authorized office operators.

Publication Control itself remains super-admin-only.

## Compatibility

Historical Real Estate URLs and intake tokens remain valid.

- `/workspace/real-estate` redirects to Businesses filtered to
  `kind=real_estate`.
- Old `real-estate` feature grants are migrated to `business` grants.
- The `real-estate` surface key remains only as a hidden, non-grantable
  compatibility definition whose dependency is Business.
- Existing portals that are not yet linked to a Business can be adopted without
  changing their public token, case references, contacts, or media.

## Specialized experience preserved

The preferred focused Real Estate workflow remains:

- public property/request intake form;
- one-time preview;
- private office case list and filters;
- case details and private media;
- status progression;
- promotion of qualified property offers into a public-ready Business catalog draft;
- explicit operator preview and publication before that draft appears on the public Business website.

The difference is conceptual ownership: those screens now say and behave as a
specialized capability of the owning Business, rather than a parallel system.

## Acceptance checklist

- Super-admin publishes **Businesses**; there is no separately grantable Real
  Estate facility.
- Navigation has no standalone Real Estate primary destination.
- Under Businesses, a real-estate office appears as a normal business, e.g.
  **Business: مشاور املاک مهوری**.
- Opening the internal Business workspace exposes its specialized Real Estate
  office controls to authorized operators; publishing the Business exposes a
  separate visitor-facing Business website and intake action.
- A portal-granted user without Business publication gets 403 on private office
  routes.
- A Business-published user with exact office authorization can manage cases.
- After the Business is published, its standalone public website works without
  login and leads into the Real Estate intake.
- While the Business is private/inactive, both its public website and linked
  Real Estate intake are unavailable and are not advertised.
- Existing office URLs/cases survive legacy-office adoption into Business.
- Qualified property offers are promoted to public-ready Business Listing drafts; the
  operator reviews the customer preview and explicitly publishes the version before it
  appears on the public Business website.
- Editing an already published Listing creates a new working version without taking the
  previously published version offline.
