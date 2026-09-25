# Persian UI Localization Guide

## Purpose

Persian in IET should read like a coherent product written for Persian-speaking people, not an English interface translated word-by-word.

The goal is **semantic fidelity + natural everyday Persian**. Internal domain precision stays intact, while user-facing copy explains the action in language a normal Persian user can understand without knowing the implementation model.

## Core style rules

1. Translate the **meaning and user action**, not the English word order.
2. Prefer short, direct Persian sentences and familiar verbs.
3. Keep domain boundaries explicit when they protect correctness, but explain them in ordinary language.
4. Avoid exposing implementation vocabulary when a clear Persian expression exists.
5. Use one consistent Persian term for the same user-facing concept across navigation, forms, empty states, notifications and help text.
6. Keep placeholders such as `:name`, `:title`, `:time` and `:version` unchanged.
7. Preserve product/domain semantics. A friendlier translation must never turn a Proposal into a Contract, a Fulfillment into a Payment, or a Match into an agreement.
8. English acronyms or standards may remain when they are the actual standard name (for example SHA-256, UUID, PDF, IANA), but explain them in Persian when ordinary users see them.
9. Prefer Persian punctuation and readable RTL phrasing. Avoid unnecessary English punctuation inside Persian prose.
10. Do not translate seeded/manual content merely because the UI is Persian. Document translation has its own review lifecycle.

## Preferred user-facing terminology

| Domain concept | Preferred Persian UI wording | Notes |
| --- | --- | --- |
| Actor | هویت / فرد | Use «فرد» when talking about a person; «هویت» when the system identity distinction matters. Avoid exposing «کنشگر» to ordinary users unless a technical/admin surface truly needs it. |
| Context | فضا | Prefer «فضا» for a collaboration/content boundary. |
| GroupSpace | فضای گروه | |
| Admission | درخواست عضویت | Use wording around the user's action rather than the internal model name. |
| Intent / Need / Offer | نیاز / پیشنهاد | |
| Relationship | رابطه | |
| Proposal | پیشنهاد توافق | Makes clear that it is not yet a Contract. |
| Contract | قرارداد | |
| Commitment | تعهد | |
| Fulfillment | انجام تعهد | Prefer over literal/technical «اجرای تعهد» when referring to the submitted work/result. |
| Planner | برنامه‌ریزی / برنامه | |
| Occurrence | نوبت برنامه | Use «نوبت» where it reads naturally. |
| Financial Obligation | تعهد مالی | |
| Settlement | تسویه / پرداخت | Choose based on the sentence; keep confirmation semantics explicit. |
| Accounting | حسابداری شخصی | When the personal-ledger boundary matters. |
| Blueprint | الگوی محتوا | |
| Studio | ویرایش محتوا / ویرایشگر | Avoid untranslated “Studio” in Persian UI. |
| Reader | صفحه خواندن / نمای خواندن | |
| Definition | ساختار محتوا | In ordinary authoring surfaces. |
| Block | بخش | In ordinary document-layout UI. |
| Annotation | یادداشت روی محتوا | Prefer over formal «حاشیه‌نویسی» in normal user flows. |
| Revision | نسخه خصوصی / نسخه | Use the exact semantic distinction where historical immutability matters. |
| Evidence reference | ارجاع مدرک | |
| Community | جامعه گروه | |
| Directory | فهرست | |
| General space | فضای «عمومی» | Do not expose the English “General” label in Persian prose. |

## Tone examples

Prefer:

> «یک درخواست رابطه منتظر پاسخ شماست.»

over a literal implementation-oriented sentence.

Prefer:

> «این صفحه فقط اطلاعاتی را نشان می‌دهد که صاحب پروفایل مشخصاً با شما به اشتراک گذاشته است.»

over wording that exposes internal Actor terminology.

Prefer:

> «با انتشار، همان نسخه دقیق برای کاربران مجاز قابل مشاهده می‌شود و بعداً بدون ساخت نسخه تازه تغییر نمی‌کند.»

over a literal translation of “immutable revision” when the technical noun is not needed.

## Domain-boundary wording

Natural Persian must not blur authoritative boundaries:

- یک نیاز/پیشنهاد فقط بیان وضعیت یا قصد است؛ خودش قرارداد یا بدهی ایجاد نمی‌کند.
- پیدا شدن یک مورد سازگار، توافق محسوب نمی‌شود.
- رابطه فضای همکاری مستقیم است، نه قرارداد.
- پذیرش پیشنهاد توافق، پذیرش قرارداد نیست.
- انجام تعهد با تعهد مالی و پرداخت یکی نیست.
- ادعای پرداخت تا تأیید طرف مقابل، پرداخت قطعی محسوب نمی‌شود.
- ثبت در حسابداری شخصی با حقیقت اقتصادی مشترک یکی نیست.
- متن گفت‌وگو به‌تنهایی اقدام رسمی دامنه را جایگزین نمی‌کند.

## Engineering contract

Persian UI localization is protected by `Tests\Feature\LocalizationParityTest`:

- every Persian locale file must exist for every English locale file;
- every real English translation leaf must exist in Persian;
- Persian locale files must not load English locale files as passthroughs;
- Persian-specific validation aliases/custom messages are allowed in addition to the English baseline.

When adding a new English UI key, add the Persian translation in the **same change**. Do not rely on fallback English for a user-facing release.

## Review workflow

For future changes:

1. implement the English copy and domain behavior;
2. translate the same keys into natural Persian in the same branch;
3. compare the Persian sentence to the user action, not only to the English sentence;
4. reuse this glossary unless the product meaning truly changed;
5. run localization parity and the full regression suite;
6. during browser acceptance, inspect RTL layout, truncation, punctuation, number/date formatting and the wording in context;
7. treat awkward or misleading Persian found in browser testing as a product defect, not cosmetic feedback.

## Manual/document translation boundary

This guide governs **application UI copy**.

The IET System Manual and other seeded/versioned Content remain English-canonical for the current release. A Persian edition of those documents must be produced and reviewed as versioned Content, with its own translation/review provenance. Do not present machine-generated document translation as native-reviewed documentation.
