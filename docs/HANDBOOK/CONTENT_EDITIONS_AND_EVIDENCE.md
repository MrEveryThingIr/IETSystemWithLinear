# Content Editions, Permalinks and Evidence References

These concepts are related but intentionally different.

## Content versus edition

A Content object is the stable identity of an artifact.

An author may publish several revisions/editions over time. The normal Content URL represents the Content and usually displays its current active published edition.

A sealed published revision is immutable historical evidence.

## Edition permalink

A **permalink** means permanent link.

In IET, **Edition permalink** means:

> Open this exact sealed revision, not whatever the Content's latest edition becomes later.

Current route shape:

~~~text
/contexts/{context}/contents/{content}/revisions/{revision}
~~~

The server verifies that the Content belongs to the Context, the revision belongs to the Content, the revision is sealed/verifiable, and the viewer is authorized.

Conceptually:

~~~text
normal Content link
→ show me this Content

edition permalink
→ show me exactly revision 4
~~~

If revision 5 is later published, the revision-4 permalink still resolves revision 4.

Use it when a human needs a stable historical URL.

## Content Evidence Reference

An Evidence Reference is stronger than a navigation link.

It is an immutable domain record meaning:

> this Actor cited this exact sealed evidence target.

It stores Context, Content, exact sealed revision, creator Actor and target identity. The target can be the whole revision or a more precise field, block, asset placement or relationship.

Evidence References are durable historical locators and are not mutable/deletable through the model.

## Why have both?

A permalink solves **navigation**.

An Evidence Reference solves **domain provenance**.

Example:

~~~text
Portfolio Content revision 3

edition permalink
→ a human can open revision 3 directly

Evidence Reference
→ an employment Submission can persist exactly which portfolio evidence was cited
~~~

## Creating an Evidence Reference today

On an eligible published Content Reader, an authorized user with Studio/edit authority sees **Create evidence reference**.

The current button creates a whole-revision Evidence Reference for the active sealed edition.

After creation the Reader displays its UUID.

The application route is:

~~~text
/content-evidence/{evidence-reference-uuid}
~~~

Access remains authorization-controlled. Possessing the UUID does not grant permission to the underlying Content.

The route resolves the Content Reader in historical-evidence mode and displays the exact referenced sealed revision.

## Demo portfolio evidence

The Phase 7 demo creates a Personal Content work sample for the employment candidate and creates a whole-revision Evidence Reference.

The seeder prints its UUID and URL. The candidate may paste either into the application's **Exact portfolio evidence** response.

When submitted, the Submission stores the Evidence Reference rather than a mutable latest-portfolio pointer.

## Why Interaction versions also bind Content revisions

An exam/application may be presented through Content.

Phase 7 binds the active InteractionDefinitionVersion to the exact published Content revision it belongs to.

Therefore an old Submission means:

~~~text
I answered
Interaction version 1
as presented with
Content revision 1
~~~

even after revision/version 2 exists.

That is the foundation for trustworthy historical review.
