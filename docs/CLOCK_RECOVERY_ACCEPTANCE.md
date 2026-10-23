# Homepage clock and permanent ambient bar

This change is part of draft PR #79; it does not deploy the application or change the production database.

## Historical investigation

PR [#36](https://github.com/MrEveryThingIr/IETSystemWithLinear/pull/36), head `a0353acb2ec5793621530e6dc0d74ebc522b2086`, recovered shared temporal presentation and the digital ambient status component. Its source library, `codex/release-first-publication-hardening` at `5830cb44e09c0238f1f1591f0134419ba67834c3`, contains that digital component but no analog homepage clock. Searches across available branch histories, clock/analog filenames, hand/face identifiers and drawing/rotation implementations did not locate an exact original analog implementation.

The owner was informed before reconstruction and requested a prominent analog homepage clock for guests and signed-in users, a large website name inside the face, and a digital date/time below with Gregorian detail on hover. The new face follows the current indigo/cyan theme. It is a reconstruction, not an assertion that the original visual design was recovered.

## Integration

- Extend the existing `ambient-status` component and custom element; do not introduce another time-preference or clock system.
- Reuse `TemporalPreferences`, `TemporalCalendar` and `Localization`: timezone, primary calendar, locale, date/time formats and hidden/hover/inline Gregorian equivalents.
- Preserve sign-in, dashboard access, branding and the newer homepage public-content preview below the clock.
- Include the existing ambient bar in every standalone application/public document, including authentication, invitations, public profiles/content, public businesses, intake and the workspace. It remains fixed above content and visible on phones; rotating text is no longer hidden on small screens.
- ResizeObserver measures the bar so it does not cover the sidebar or page. Mobile wrapping and translated messages are supported.
- SVG hands follow the same timezone as the digital clock. Sweep animation stops offscreen, in hidden documents or under reduced-motion preferences; the digital display still updates appropriately.
- Gregorian hover detail also works with keyboard focus and the existing touch popover mechanism. SVG decoration is hidden from assistive technology; an actual textual `<time>` is available without JavaScript. Avoid announcing every second or rotating message through a live region.
- Messages can be paused. Browser fullscreen remains optional and keeps the global bar visible.
- Detachment clears both intervals and the animation frame, aborts all instance listeners, disconnects observers and resets initialization so reconnection works. No page-global per-instance listeners accumulate during Livewire navigation.

## Verification

Focused automated coverage: `AmbientClockExperienceTest` and the existing temporal, public-content and publication suites. Browser regression harness: `tools/clock-browser-acceptance.mjs`, using only the isolated demo database/server on port 8001 and an isolated Chrome profile on debugging port 9225.

The harness checks guest/home and authenticated layouts on desktop/mobile, four locales, primary/equivalent presentation, exact zoned hand geometry, reduced-motion changes, fifteen detach/reconnect cycles without timer growth, and Livewire navigation into authentication. Screenshots and machine results are kept under ignored `storage/release-acceptance/clock`; the release report records exact final commit and gate results.

The real production-data upgrade/rollback rehearsal remains an independent unresolved release gate. Living Planner changes belong on a separate successor branch, not PR #79.
