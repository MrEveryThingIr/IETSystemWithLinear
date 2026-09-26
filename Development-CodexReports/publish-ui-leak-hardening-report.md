# Publish UI Leak Hardening

## Trigger

Owner browser acceptance of the multilingual release candidate exposed two visible defects:

1. the Proposal create form rendered the raw translation key `ui.actions.cancel`;
2. the account dropdown rendered the literal Blade expression `{{ $accountUser->username }}`.

These were treated as release defects rather than cosmetic feedback because both bypass the intended localization/presentation layer.

## Root causes

- Proposal Create referenced a translation path that did not exist. The canonical localized key is `ui.common.cancel`.
- The account-menu template used Blade's escaped-JavaScript form `@{{ ... }}`, which intentionally prints braces instead of evaluating the server-side expression.

## Direct fixes

- Proposal cancel action now uses the existing localized `ui.common.cancel` key.
- Account menu now renders the profile display name first, shows the username only when it adds information, renders the handle normally, keeps email truncated with full value available through the title attribute, gives the menu a safer responsive width, and labels the appearance section.
- Content Studio navigation ARIA text is localized.
- Platform Access is fully localized across English, Persian, Arabic and Simplified Chinese, including title, help, status badges, request/review controls and feedback messages.
- Groups page group-creation-access controls are localized and reuse the same platform-access vocabulary.
- Commitment planning no longer exposes the hardcoded English `Daily` option.
- Structured-content select-option example text is localized.

## Regression protection

`Tests\Feature\LocalizationParityTest` now additionally checks:

- every statically referenced UI translation key resolves in every supported locale;
- Blade templates do not contain escaped server-side PHP interpolation such as `@{{ $value }}`;
- primary Livewire/app/layout templates do not ship literal English control text;
- Blade templates do not ship hardcoded English `aria-label` text.

`Tests\Feature\ReleaseExperienceTest` also renders the account menu and Persian publish surfaces to ensure the browser receives translated, evaluated output rather than translation keys or Blade source.

## Release discipline

`release/ideal-v1-rc-6` remains immutable. This correction must pass feature-branch CI, PR-context CI and independent integration CI before a newly numbered immutable RC is frozen. Browser acceptance continues only from that new RC.
