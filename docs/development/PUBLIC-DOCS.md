# Public documentation and the invitation to collaborate (B0)

Served at `/docs`. It replaces the Help button's old target (the System Manual stored as Content, which was English-only and only seeded in local/testing, so it was missing in production).

## Decisions

| Question | Decision | Why |
|---|---|---|
| Where does the text live? | Markdown files in `resources/docs/{locale}/NN-slug.md` | Ships with the code, so it can never be "lost in production" for lack of seeding; reviewed in pull requests like code; no table to migrate. |
| Is it Content? | No. Own routes, own layout, own navigation | Docs must never mix with user-written public Content. They are not in the `/public` library and create no `SpaceContent`. |
| Who can read it? | `DOCS_ACCESS=public` (default) or `invite` | Invite mode admits signed-in people and holders of a link made with `php artisan docs:invite "Label" --days=30 --uses=20`. Only a hash of the token is stored; the link is shown once. Revoke with `--revoke=ID`. Invite mode is `noindex`. |
| Languages | `fa` and `en` complete; `ar` and `zh_CN` have translated page chrome and read the English chapters with a notice | A missing chapter falls back to English; a test keeps every English chapter present in Persian. |
| Expert proposals | Own table `doc_contributions` (field, expertise, education, experience, links, proposal, credit consent) | Readers of public pages may not be users, and what matters for one proposal is not a profile. Signed-in members are linked by `user_id`; nothing is copied from their profile. |
| Spam | Hidden trap field, `throttle:6,60`, server validation | No third-party captcha. |
| Reviewing proposals | `php artisan docs:contributions` (list), `--show=ID`, `--mark=ID --as=reviewed|accepted|declined` | No admin screen yet; add one only if volume justifies it. |

## Writing a chapter

Header (all values on one line):

```
---
title: ...
summary: one or two sentences
status: available | partial | research | planned
experts: economics, law   # keys from config/docs.php; drives the "do you work in this field?" invitation
---
```

Be honest about status: `research` means nothing is built or promised. Link between chapters with `/docs/<slug>`; the form is `/docs/collaborate`. A test checks every internal link, header and field label in every language.

## Contextual Help

`DocsHelpMap` chooses the chapter for the current page (for example `planner.*` opens the planner chapter).

## Not done here

An administrator screen for invitations and proposals; Arabic and Chinese chapter translations; screenshots.
