# IET Concept Kernel

## Purpose

The Concept Kernel provides reusable semantic identity and classification without forcing the platform into one giant category tree.

It evolves useful ideas from the legacy Concept migrations while fixing their structural limitations.

## Core principle

A Concept describes what something means.

A predicate describes how a subject relates to that meaning.

A Scheme describes the classification perspective.

Example:

~~~text
Concept:
    Chess

Scheme hierarchy:
    Board Games → Chess
    Mind Sports → Chess

Assertions:
    Actor --has_skill--> Chess
    Actor --wants_to_learn--> Chess
    Content --teaches--> Chess
    Group --focuses_on--> Chess
~~~

"Skill", "need", "interest", "service", and "tag" are usually predicates/usages, not separate copies of the Concept.

## Why a single tree fails

A single `parent_id` requires one parent.

Real classification is frequently polyhierarchical:

~~~text
Board Games ─────┐
                 ├── Chess
Mind Sports ─────┘
~~~

The same Concept may also participate in multiple classification Schemes.

Therefore:

- do not store one authoritative `parent_id` on Concept;
- do not store one universal `depth`;
- do not store one universal `sort_order`.

Parent-specific ordering belongs on the hierarchy edge.

Depth is derived from a path and may differ by path.

## Target model

### ConceptVocabulary

Purpose:

- semantic namespace;
- ownership/curation scope;
- publication/governance boundary.

Possible ownership:

- platform;
- Actor;
- Group.

Do not use nullable User ownership as the only namespace model.

Suggested fields:

- id;
- uuid;
- owner_type / owner_id through an approved morph alias, or explicit nullable platform/actor/group ownership according to final implementation;
- name;
- slug;
- status;
- created_by_actor_id;
- timestamps.

### Concept

Purpose:

canonical semantic identity.

Suggested fields:

- id;
- uuid;
- vocabulary_id;
- slug;
- status: active / deprecated / merged;
- merged_into_concept_id nullable;
- summary nullable;
- metadata;
- timestamps.

Avoid contextual `type` values such as skill, need, tag, service, or template.

If a future intrinsic kind is introduced, it must represent genuine ontology rather than how a subject happens to use the Concept.

### ConceptLabel

Purpose:

- preferred labels;
- translations;
- synonyms;
- abbreviations.

Suggested fields:

- concept_id;
- locale;
- label;
- kind: preferred / synonym / abbreviation;
- normalized label/search key;
- timestamps.

Concept identity is not the English label.

Example:

~~~text
Concept UUID X
en: Chess
fa: شطرنج
~~~

### ConceptScheme

Purpose:

classification perspective.

Examples:

- subject;
- activity;
- industry;
- audience;
- education level;
- content format;
- purpose.

Suggested fields:

- id;
- uuid;
- vocabulary_id or governing scope;
- name;
- slug;
- description;
- status.

### ConceptSchemeMembership

A Concept may belong to many Schemes.

Suggested unique key:

- scheme_id + concept_id.

Optional metadata may express whether it is a root candidate, featured, hidden, etc.

### ConceptHierarchyEdge

Source of truth for broader/narrower hierarchy.

Suggested fields:

- scheme_id;
- parent_concept_id;
- child_concept_id;
- sort_order;
- created_by_actor_id;
- metadata;
- timestamps.

Invariants:

- parent != child;
- both Concepts are valid for the Scheme;
- no cycles;
- duplicate edge prohibited;
- changes update/rebuild affected closure transactionally.

### ConceptClosure

Derived query accelerator.

Suggested fields:

- scheme_id;
- ancestor_concept_id;
- descendant_concept_id;
- min_depth;
- optionally paths_count.

Self row has depth 0.

This table is not authored directly.

It exists to answer efficiently:

- all descendants;
- all ancestors;
- descendants under a selected Scheme;
- queries over broad parent Concepts.

### ConceptRelationType

Trusted semantic relation registry.

Examples:

- related_to;
- prerequisite_of / has_prerequisite;
- equivalent_to;
- supersedes;
- influenced_by.

Suggested properties:

- key;
- inverse_key;
- symmetric;
- transitive;
- status.

Initially relation types should be application-defined/trusted rather than unrestricted user strings.

### ConceptRelation

Non-hierarchical Concept→Concept semantics.

Suggested fields:

- from_concept_id;
- relation_type;
- to_concept_id;
- weight nullable;
- confidence nullable;
- source;
- created_by_actor_id;
- metadata;
- timestamps.

Hierarchy remains separate.

### ConceptAssertion

Generic Subject → Concept relation.

Suggested fields:

- id;
- uuid;
- subject_type;
- subject_id;
- concept_id;
- predicate;
- scheme_id nullable;
- weight nullable;
- confidence nullable;
- source;
- visibility;
- valid_from nullable;
- valid_until nullable;
- created_by_actor_id nullable;
- metadata;
- timestamps.

Examples:

~~~text
Actor --has_skill--> Programming
Actor --interested_in--> Programming
Actor --needs--> English
Content --about--> Chess
ContentRevision --teaches--> Chess
Group --focuses_on--> Chess
Plan --practices--> Chess
Need --requires_skill--> Laravel
Offer --offers_service--> Electrical Work
~~~

The uniqueness key must include predicate where semantic duplicates are prohibited.

Do not repeat the legacy error of unique(subject, concept) when one subject can validly have several predicates to the same Concept.

## Mutable catalog assertions vs immutable publication assertions

Attach Assertions at two levels when needed.

### Content identity assertion

Example:

~~~text
Content --topic--> Chess
~~~

This may be mutable discovery/catalog metadata.

### Exact revision assertion

Example:

~~~text
ContentRevision 7 --teaches--> King's Indian Defence
~~~

When semantic meaning is part of the published artifact, bind it to the exact revision and include appropriate evidence in the publication manifest.

Do not silently rewrite historical revision semantics when current catalog classification changes.

## Source and confidence

Assertions/relations may originate from:

- manual author selection;
- system rule;
- imported mapping;
- AI suggestion;
- verified external source.

Store source explicitly.

AI-generated classification should normally be a suggestion with confidence until accepted where correctness matters.

## Visibility

Actor/profile Assertions may be:

- private;
- selected Contexts;
- selected Groups;
- connections;
- public.

Do not assume every semantic statement about an Actor is public.

## Duplicate and merge lifecycle

Duplicates are inevitable.

Support:

~~~text
active
deprecated
merged
~~~

When Concept B is merged into Concept A:

- preserve B UUID and history;
- store `merged_into_concept_id = A`;
- new authoring resolves toward A;
- historical published evidence is not silently rewritten;
- queries may canonicalize current discovery to A.

## Concept-valued Content fields

Extend the trusted Content field registry later with:

- concept;
- concept_multi.

A field may constrain selection to a Scheme.

Example:

~~~text
Field: Subject
Type: concept
Allowed scheme: Academic Subjects
~~~

This is preferable to hard-coded select lists that must be revised whenever the taxonomy grows.

## Profile integration

Profile questionnaires may project selected answers into Actor Assertions.

Examples:

Question:
"What skills do you have?"

Result:

~~~text
Actor --has_skill--> Laravel
Actor --has_skill--> Chess
~~~

Question:
"What do you want to learn?"

Result:

~~~text
Actor --wants_to_learn--> English
~~~

Not every profile field becomes a Concept Assertion. Scalar facts such as birth date remain structured profile data.

## Need / Offer integration

Needs and Offers reference Concepts rather than arbitrary category strings where semantic matching matters.

Example:

~~~text
Need
    concept = Electrical Work

Offer
    concept = Electrical Work
~~~

Descendant-aware matching can use Scheme/closure queries where explicitly appropriate.

## Blueprint integration

Blueprints may carry semantic defaults:

~~~text
Chess Lesson Blueprint
    domain → Learning
    activity → Lesson
    subject → Chess
~~~

A Group Blueprint may similarly carry domain/purpose classifications.

## Query examples

The kernel should eventually support efficient queries such as:

- descendants of Learning within a selected Scheme;
- all Content about Chess or descendants of a broader Concept;
- Actors with has_skill Electrical Work;
- Actors who teach something another Actor wants_to_learn;
- Group Blueprints classified under Project/Construction;
- Needs whose required Concept is satisfied by an Offer's Concept or accepted narrower/broader relation.

Matching semantics must be explicit; do not assume all ancestor/descendant relationships imply substitutability.

## Security and integrity

- use stable morph aliases, not arbitrary PHP class names in stored polymorphic types;
- authorize creation/modification by Vocabulary/Scheme governance;
- validate predicate registry;
- validate relation registry;
- prevent hierarchy cycles transactionally;
- use indexes for subject/predicate/concept and concept/predicate discovery;
- do not cascade-delete canonical Concepts referenced by historical evidence;
- merge/deprecate instead.

## Legacy mapping

Legacy concepts table:
- preserve canonical identity idea;
- replace owner_user_id with scoped Vocabulary;
- replace parent_id/depth/sort_order with hierarchy edges + closure;
- remove contextual type semantics;
- replace is_template with relationship/classification to real Blueprint domain objects.

Legacy concept_closure:
- preserve as derived acceleration;
- add Scheme scope;
- use min depth in a polyhierarchy.

Legacy concept_relations:
- preserve typed graph;
- add controlled relation registry and richer provenance.

Legacy concept_attachments:
- evolve into ConceptAssertion;
- preserve role/predicate, weight, source and metadata ideas;
- add confidence, visibility, provenance and validity window.

Legacy user_concepts:
- eliminate as a special-case semantic table;
- Actor Concept Assertions replace it;
- aliases/labels become a separate concern.

## Acceptance proof for the Concept phase

The Concept Kernel is not considered complete merely because migrations exist.

It must prove at least:

1. one Chess Concept appears under two hierarchy parents without duplication;
2. the same Actor can simultaneously has_skill Chess and wants_to_learn Chess;
3. Content can be classified as about Chess;
4. a published revision can carry a revision-bound semantic assertion without later catalog edits changing historical evidence;
5. ancestor/descendant queries are correct after edge insertion/removal;
6. cycle insertion is rejected;
7. multilingual labels resolve correctly;
8. authorization prevents unauthorized Vocabulary/Scheme modification.
