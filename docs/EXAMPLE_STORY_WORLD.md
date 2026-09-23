# IET Example Story World

## Purpose

This file defines the canonical example cast used across developer docs, System Manual chapters, automated examples, later demo fixtures, and the final end-to-end browser acceptance worksheet.

The goal is continuity. A person introduced during registration should remain the same person when later examples need a Group owner, member, service provider, customer, worker, investor, author, reviewer, payer, or payee.

These examples explain behavior; they never bypass authorization or create production credentials.

## Cast

### Diego Santos — office operator / coordinator

- creates the first controlled system Access Invitations;
- owns/manages the **Maple Housing Office** Group in examples;
- receives property/service/capital requests from people who still use paper or phone;
- helps people record structured Needs/Offers;
- can introduce parties manually before automated Matching exists.

### Alice Morgan — property owner / requester

Alice receives an Access Invitation from Diego and registers without joining a Group.

Her first real case:

- owns the **Riverside Lot**;
- wants to build a small house;
- does not have enough cash or construction expertise;
- is open to a negotiated combination of cash, property/use contribution, capital participation, and services when exact value and terms are later agreed explicitly.

Alice therefore demonstrates:

- registration;
- Profile;
- Property Offer / collaboration contribution;
- Construction Service Need;
- Capital Need;
- later Relationship / Proposal / Contract / Planner / Fulfillment / Accounting.

### Bob Rahimi — construction service provider / worker

Bob is an already registered verified user.

He demonstrates:

- Service Offer: residential construction/electrical work;
- Group invitation as an existing user;
- direct client/provider Relationship with Alice;
- paid-work Contract;
- selected or recurring workdays;
- actual start/end;
- work evidence;
- earned amount and later payment.

### Carol Chen — capital provider / collaborator

Carol demonstrates:

- Capital Offer;
- project collaboration;
- multi-party negotiation;
- investment/capital contribution that must never be treated as ownership merely because it was mentioned in chat;
- later explicit Contract/version/acceptance semantics.

### Maple Housing Office — governed Group

The office demonstrates Group governance rather than global identity.

Members may include:

- Diego — Owner / office manager;
- Bob — optional service-provider member;
- later staff members with scoped permissions.

Alice and Carol do **not** need Group Membership merely to have an account or to participate in a direct Relationship/Context.

### Riverside Home Project — compositional proof case

The long-running example can eventually compose:

~~~text
Alice
  offers Riverside Lot / property contribution
  needs construction
  needs capital

Bob
  offers construction service / skill

Carol
  offers capital

→ manual discovery or later Match
→ Relationship / Project Context
→ Conversation
→ Proposal
→ versioned negotiated Contract
→ Commitments
→ Plans / Occurrences
→ Fulfillment + evidence
→ Financial Obligations
→ Accounting
→ Settlement / payment
→ unified Timeline
~~~

No early step implies the later one. A Need/Offer is not a Match. A Match is not a Proposal. Discussion is not acceptance. A Contract is not Fulfillment. Fulfillment is not payment.

## Smaller proof stories

### Simple product sale

Alice offers a used desk for sale. Bob wants a desk.

This path should remain lightweight:

~~~text
Offer / Need
→ discovery
→ optional direct Relationship / discussion
→ optional Proposal / terms
→ record external payment if desired
~~~

The user must not be forced through employment/project functionality.

### Simple personal expense

Bob records:

> Bought construction gloves for 80 CAD in cash.

The UI eventually creates the proper accounting event behind the friendly action **Add expense**.

### Paid work

Alice asks Bob to work 08:00–17:00 for 1,500,000 per accepted day on selected dates.

This is the canonical proof for:

~~~text
Relationship
→ ContractVersion
→ explicit acceptance
→ work Commitment
→ payment Commitment
→ Planner Occurrences
→ actual Fulfillment
→ review / acceptance
→ Financial Obligation
→ accounting
→ settlement
~~~

## Documentation rule

Whenever a manual page needs a person, Group, project, Content item, Need/Offer, Contract, activity, or financial example, prefer this story world before inventing an unrelated example.

New example actors may be added only when the existing cast cannot meaningfully demonstrate the behavior.

Every example must clearly distinguish:

- what exists in the current implementation;
- what is a later roadmap target;
- what the user clicks/types/selects;
- what durable object/action results;
- who can see it;
- what the action explicitly does **not** imply.
