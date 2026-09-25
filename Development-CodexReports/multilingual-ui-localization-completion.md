# Multilingual UI Localization Completion

## Scope

Publishable Ideal-v1 UI locales:

- English (`en`) — canonical UI source;
- Persian (`fa`) — complete and native-style hardened;
- Arabic (`ar`) — complete real localized UI coverage;
- Simplified Chinese (`zh_CN`) — complete real localized UI coverage.

This milestone concerns **application UI strings**, not the full System Manual or other seeded/versioned document content.

## Defects closed

Before this pass, Arabic and Simplified Chinese each had only 18 of the 29 English locale modules. Both also used English passthroughs for `access.php` and `intents.php`.

The missing workflow modules were:

- Accounting
- Collaboration
- Commitments
- Community
- Contracts
- Financial
- Journeys
- Library
- Planner
- Proposals
- Relationships

Both locales now contain all 29 UI locale modules and real localized Access/Intent copy.

The pass also removed audited user-facing leakage of recurring English implementation terms such as Actor, Context, Planner, Studio, Blueprint, Definition, Reader and General where a clear localized term is appropriate.

Arabic validation messages were upgraded to a complete Laravel-compatible localized set using the public Laravel-Lang Arabic catalog as the language baseline, while retaining IET-specific validation aliases.

## Automated contract

`Tests\Feature\LocalizationParityTest` now protects Persian, Arabic and Simplified Chinese:

1. every English locale module must exist in every supported locale;
2. every real English translation key must exist in every supported locale;
3. no supported locale may load an English locale file as a passthrough;
4. each translated string must preserve the same runtime placeholders as English.

This prevents incomplete releases and placeholder regressions from silently reaching the browser.

## Editorial-quality boundary

Persian has received a dedicated native-style product-language pass.

Arabic and Simplified Chinese now have complete real translations and terminology cleanup, but this milestone does **not** claim that every sentence has been reviewed by a native professional editor. A later native-speaker editorial pass can improve style without needing to repair missing coverage or English fallback architecture.

## Manual/document boundary

The System Manual and other seeded/versioned content remain English-canonical for the current release. UI translation does not imply that all document content has been translated or native-reviewed.
