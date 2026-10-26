# M3-C4 — Conversation-first Deal workspace

Branch `feat/m3-deals-terms`. Builds on C1–C3b; changes no immutable record, no acceptance rule.

## Architectural map (what is reused)

| Need | Reused as-is | New (thin) |
|---|---|---|
| Messages | `Conversation` (key `main`) on the Relationship `Context`; `PostContextMessage` (client-uuid idempotency, notifier, read state via `ManageConversation::markRead`) | message `kind = 'deal'` (a line recording a structured step; `meta.type`), never consent |
| Notifications / inbox | existing message notifier and `/messages` inbox work for the Relationship conversation unchanged | – |
| Near-real-time | page polls every 8 s (`wire:poll`); the global Echo/Reverb client keeps pushing message events where enabled. Persisting a message never depends on push | – |
| Origin | pinned `IntentVersion` snapshot through `IntentPresentation::build` (never the live listing) | `DealOrigin::card`, `x-deals.origin` |
| Who may talk | `ContextPolicy::interactContent` unchanged: nothing is posted into a Relationship conversation before the invitee consents (ConversationKernelTest rule kept) | the opening message is stored as `relationships.metadata.intro`, shown as the first bubble and in the invitation; replying joins |
| Who is a party | `PartyAuthority`, `DealParties`, `ProposalParty.principal_key` (C3b) | `DealPrincipals` (publisher of the pinned Need/Offer, or the Business/Group the responder chose; labels play no part) |
| Terms | `CreateProposal` / `ProposeTermsVersion` / `RespondToProposal(expectedVersion)` / single Agree | `ProposeDealTerms` (first call opens the Proposal, later calls add a version), `DealTermsForm`, `DealTermsDiff` |

## Journey

Offer → **Discuss** (`relationships.create?intent=`, now a single message box; roles inferred, descriptive only; optional "speaking as" Business/Group) → the other person replies (which accepts the invitation) or declines → ordinary conversation → **Propose terms** card → Agree / Ask for changes / Decline on the exact version shown (stale → refused) → one Agreement. Starting again from the same Offer reuses the open conversation.

## Compatibility

`relationships.show`, `proposals.show/create`, `contracts.show`, `deals.index` keep working. The old long Proposal form is one link under *Details*. The responder's chosen principal is stored as `relationships.metadata.responder_party`.

## Known limits

- Other managers of a Business/Group are not Relationship participants, so they cannot open the Deal page (they can open the Proposal/Contract pages through principal authority). Participant model unchanged here.
- Non-owners cannot open `intents.versions.show`; the card states the pinned version number instead of linking.
- Attachments/reply threading remain on the full conversation page (*Details*).
- The decision line in the conversation is best-effort; the Proposal records are the authority.
