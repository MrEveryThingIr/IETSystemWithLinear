# v1.12 mobile messaging shell acceptance

Date: 2026-10-31

Branch: `fix/v1.12-mobile-messaging-shell`
Base: exactly `fccba8cdef733ee509556f35909729cb23814e29` (deployed release). No integration branch was used.

## Root cause

The Messages frame sized itself with `100dvh - 7.5rem` (a different desktop subtraction) and forced a `26rem` minimum height. That frame was nested below the persistent time bar, navbar and main padding, inside a shell with another viewport minimum and large bottom padding. Its calculation did not reserve the real fixed phone navigation height/safe area. On short/phone viewports the conversation therefore extended behind/below the visible chrome; its composer sat at the bottom of that oversized frame. Clipping the frame did not correct the available-height calculation.

Flex intrinsic widths, the editor's fixed minimum width and the audio row could also resist shrinking. A long draft's existing 160px textarea growth cap was too tall for a keyboard-sized viewport. The browser regression caught Send falling below the composer's scroll box at 390x420; the final cap scales with the usable viewport.

Global-add triggers only checked whether the user had any launcher choices. Existing route context ordered choices but did not decide whether the workflow should show a global creation action.

## Correction

Messages uses a bounded `dvh` flex body and the existing Flux shell grid with `auto minmax(0, 1fr) auto` rows. Headers and the normal phone navigation participate in layout; navigation keeps its safe-area padding. The remaining main region and thread may shrink, the history scrolls internally, and the composer remains a separate nonshrinking region with bounded scrolling for expanded attachments/options. No guessed header/footer pixel offsets or global overflow-hiding CSS were added.

A small document-level viewport helper follows VisualViewport resize events for keyboards that resize only the visual viewport and supplies the textarea's viewport-relative maximum. It selects the current Messages body after Livewire navigation; it does not introduce another Alpine scope around the composer. The messaging layout adds viewport-fit/interactive-widget metadata and safe-area top/side padding. Ordinary pages retain their original layout. Legacy context conversation pages retain their existing document scrolling and bottom-chrome reservation; they use the shared thread width/control fixes.

Thread roots, editor and audio controls can shrink; long bubble content wraps. Existing labels, focus behavior, send shortcuts, RTL logic, theme colors and messaging actions remain in place.

`IntentionLauncher::visibleFor()` centrally combines route focus with existing usable choices. The phone FAB, sidebar trigger and modal all use it. Messages and legacy context conversations hide global add. Equivalent explicit create/compose/editor routes also hide it, including the existing Content editor route names (visibility only). Dashboard, lists, contacts and other ordinary eligible browsing pages keep it. No Content, Planner, Deals or Money domain code was changed.

## Changed files

- `app/Support/IntentionLauncher.php`: central focused-route visibility policy.
- `resources/views/components/app/{bottom-bar,launcher,shell}.blade.php`: shared policy and optional bounded Messages shell.
- `resources/views/components/iet/surface-sidebar.blade.php`: same visibility policy for the sidebar trigger.
- `resources/views/layouts/app.blade.php`: Messages-only viewport layout/metadata/safe areas.
- `resources/views/livewire/messages/{index,thread}.blade.php`: bounded frame, internal scrolling, constrained editor/media/composer.
- `resources/js/messages.js`: route-scoped viewport sizing helper; no send/upload/storage changes.
- `tests/Feature/MobileMessagingShellTest.php`: route-policy and EN/FA page regression coverage.
- `tools/mobile-messaging-browser-{setup.php,smoke.mjs}`: minimal isolated SQLite fixture and headless Chrome/CDP regression, following the existing release-browser-smoke approach.
- This report.

## Verification

- `php artisan test --compact --filter='MobileMessagingShellTest|MessagingTest|LauncherAndNavigationTest'`: **17 passed, 1,638 assertions**, final run 18.411 seconds. An earlier concurrent run hit a Windows compiled-view rename lock; fixture compiled views were then isolated and subsequent full targeted runs passed.
- `php vendor/bin/pint --test app/Support/IntentionLauncher.php tests/Feature/MobileMessagingShellTest.php tools/mobile-messaging-browser-setup.php`: **passed**.
- `php vendor/bin/phpstan analyse app/Support/IntentionLauncher.php --no-progress --memory-limit=1G`: **passed, zero errors**.
- `npm run build`: **passed** (latest completed build 10.17 seconds).
- `git diff --check` for the scoped files: **passed**.
- Browser geometry matrix: **24/24 passed**. EN/LTR and FA/RTL × light/dark × 360×640, 390×844, 768×1024, 1440×900, 390×420 and 844×390. Long received text and multiline/unbroken drafts were included. Checks cover body/document and thread widths, internal history scrolling, composer bounds above navigation, attachment/voice/send hit testing and global-add absence.
- Real sending succeeds; dashboard global add is present, Messages global add is absent after SPA navigation, mounted viewport resizing and legacy-context composer reachability after document scrolling pass.
- The final strict extended browser runner exits **1** with **six runtime events (two occurrences of three unique existing Alpine errors)** on SPA re-entry to Messages: `busy`, `recording` and `locating` are undefined during Livewire cloning. A separate comparison server using the exact deployed SHA's layout/shell/thread Blade templates reproduced the same three errors on `spa-messages`, with **zero errors after sending**. The layout and send checks pass; this is not a claim of a completely clean browser runtime. The errors and baseline comparison are retained in local JSON artifacts. No framework/state rewrite was made in this UI-layout patch.

The fixture uses only `storage/release-acceptance/v1.12-mobile-messaging-shell/browser.sqlite`; normal development and production databases were not used. Browser assets/results/screenshots stay local and are not committed.

Full-repository PHPStan was also attempted. It failed with `Cannot redeclare method App\Livewire\Deals\MoneyPanel::askPercent()` at line 97. That duplicate is in unrelated concurrent uncommitted changes in the shared checkout, not in this branch's changes or its deployed base. Those Money and translation edits were left untouched and excluded from this commit. PHPStan for the affected application PHP file passes without suppressions.

Build has an existing optional `fontaine` optimized-font-fallback notice; build succeeds.

## Reproduce the browser check

1. Run `php tools/mobile-messaging-browser-setup.php` (it guards against using a nonfixture database).
2. Start an isolated local server on port 8012 with `DB_CONNECTION=sqlite`, `DB_DATABASE` pointing to the absolute fixture SQLite path, empty `DB_URL`, `CACHE_STORE=array`, `SESSION_DRIVER=file`, `SESSION_COOKIE=mobile_shell_acceptance`, `VIEW_COMPILED_PATH` pointing to the fixture's `views` directory, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, and `BROADCAST_CONNECTION=null`. Keep these process-local.
3. Start a separate headless Chrome profile with remote debugging on port 9238. Do not use a personal browser profile.
4. Run `node tools/mobile-messaging-browser-smoke.mjs`. Optional `MOBILE_SHELL_BASE_URL` / `MOBILE_SHELL_CDP_URL` change those endpoints. The fixture credentials are test-only, embedded in the test helper.

## Remaining human browser confirmation

- On physical iOS Safari and Android Chrome, open a real conversation at 360-390px, focus the composer, open/close the software keyboard, collapse/expand browser chrome, and rotate. Check the notched-device safe areas; desktop emulation does not prove real keyboard behavior.
- Repeat in EN/LTR and FA/RTL, light and dark. Confirm no sideways page movement, history scrolls while composer stays usable, long drafts/URLs wrap, and attachment/voice/send controls remain reachable.
- Select several long-named attachments, open reply/edit and attachment menus, then send a message. Check touch and keyboard navigation, Shift+Enter/newline and desktop Enter-to-send.
- Check a tablet and desktop: both inbox and conversation remain available at the existing desktop breakpoint. Check a legacy context conversation by scrolling down to its composer.
- Watch the console on SPA re-entry: the existing three Alpine clone-time errors noted above remain and should be handled as a separate messaging-state issue.
- Global + must be absent in Messages/conversations and explicit composer/editor routes, present on eligible dashboard/list pages, and restore correctly after Livewire navigation.

No migration/schema/model/version change, merge or deployment was made. Physical device confirmation remains an acceptance step, not a claimed automated result.